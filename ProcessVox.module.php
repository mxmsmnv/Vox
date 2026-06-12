<?php namespace ProcessWire;

require_once __DIR__ . '/VoxGamification.php';

/**
 * ProcessVox — Admin panel Process module for Vox.
 * All data access delegated to Vox module methods — no raw SQL here.
 */
class ProcessVox extends Process {

    public static function getModuleInfo(): array {
        return [
            'title'    => 'Vox Admin',
            'summary'  => 'Community discussions admin panel.',
            'version'  => 100,
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'icon'     => 'comments',
            'singular' => true,
            'autoload' => false,
            'requires' => ['Vox'],
            'page'     => [
                'name'   => 'vox',
                'parent' => 'admin',
                'title'  => 'Vox',
            ],
            'useNavJSON' => false,
        ];
    }

    private Vox $vox;

    public function init(): void {
        parent::init();
        $this->vox = $this->wire->modules->get('Vox');
        $url = $this->wire->config->urls('ProcessVox');
        $ver = self::getModuleInfo()['version'];
        $cssV = (string) (@filemtime(__DIR__ . '/css/vox.admin.css') ?: $ver);
        $jsV  = (string) (@filemtime(__DIR__ . '/js/vox.iconpicker.js') ?: $ver);
        $this->wire->config->styles->add($url . 'fontawesome/css/all.min.css?v=' . $ver);
        $this->wire->config->styles->add($url . 'css/vox.admin.css?v=' . $cssV);
        // Icon picker (FontAwesome) — icon list first, then the component.
        $this->wire->config->scripts->add($url . 'fontawesome/icons.js?v=' . $ver);
        $this->wire->config->scripts->add($url . 'js/vox.iconpicker.js?v=' . $jsV);
    }

    // ── Install / Uninstall ───────────────────────────────────────────────

    public function ___install(): void {
        parent::___install();
        // Create permissions
        $this->wire->modules->get('Vox')->ensurePermissions();
    }

    public function ___uninstall(): void {
        // Remove permissions
        foreach (array_keys(Vox::PERMISSIONS) as $name) {
            $p = $this->wire->permissions->get($name);
            if ($p->id) $this->wire->permissions->delete($p);
        }
        parent::___uninstall();
    }

    // ── Nav JSON (pending badge) ──────────────────────────────────────────

    public function executeNavJSON(): string {
        $pending = $this->vox->getPendingCount();
        $items   = [
            ['name' => 'dashboard',    'label' => 'Dashboard',     'url' => './'],
            ['name' => 'entries',      'label' => 'Entries',       'url' => './entries/'],
            ['name' => 'moderation',   'label' => 'Moderation',    'url' => './moderation/', 'badge' => $pending ?: ''],
            ['name' => 'schemas',      'label' => 'Field Schemas', 'url' => './schemas/'],
            ['name' => 'gamification', 'label' => 'Gamification',  'url' => './gamification/'],
            ['name' => 'stopwords',    'label' => 'Stop Words',    'url' => './stopwords/'],
            ['name' => 'settings',     'label' => 'Settings',      'url' => './settings/'],
        ];
        if ($this->wire->user->isSuperuser() || $this->wire->user->hasPermission('vox-api-docs')) {
            $items[] = ['name' => 'api', 'label' => 'API', 'url' => './api/'];
        }
        $items[] = ['name' => 'install', 'label' => 'Embed', 'url' => './install/'];
        header('Content-Type: application/json');
        return json_encode(['id' => 'vox', 'label' => 'Vox', 'items' => $items]);
    }

    // ── 1. Dashboard ──────────────────────────────────────────────────────

    public function execute(): string {
        $this->requirePermission('vox-view');
        $this->setTitle('Vox · Dashboard');

        $stats    = $this->vox->getAdminDashboardStats();
        $recent   = $this->vox->getRecentEntries(10);
        $queue    = $this->vox->getRecentEntries(5, Vox::STATUS_PENDING);
        $topPages = $this->vox->getTopPagesByActivity(6);

        // Flatten stat card fields from dashboard stats
        $avgRating = $stats['avg_rating'];
        $recRate   = $stats['rec_rate'];
        $activity  = $stats['activity'];
        $breakdown = $stats['breakdown'];

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.dashboard.php';
        return ob_get_clean();
    }

    // ── 2. Entries list ───────────────────────────────────────────────────

    public function executeEntries(): string {
        $this->requirePermission('vox-view');
        $this->setTitle('Vox · Entries');
        $input = $this->wire->input;
        $san   = $this->wire->sanitizer;

        if ($input->post('mass_action') && $this->wire->session->CSRF->hasValidToken()) {
            $this->requirePermission('vox-moderate');
            $action = $san->option($input->post('mass_action'), ['approve','spam','delete']);
            $ids    = array_values(array_filter(array_map('intval', (array)$input->post('ids'))));

            if ($action && $ids) {
                if ($action === 'delete') {
                    $count = $this->vox->deleteEntries($ids);
                    $this->message(sprintf($this->_('%d entries deleted.'), $count));
                } else {
                    $status = $action === 'approve' ? Vox::STATUS_PUBLISHED : Vox::STATUS_SPAM;
                    $this->vox->setEntriesStatus($ids, $status);
                    if ($status === Vox::STATUS_PUBLISHED) {
                        foreach ($ids as $id) { $this->awardOnApprove($id); }
                    }
                    $this->message($status === Vox::STATUS_PUBLISHED ? $this->_('Entries approved.') : $this->_('Entries marked as spam.'));
                }
            }
        }

        $perPage  = 25;
        $currPage = max(1, (int)($input->get('p') ?? 1));

        $filters = [
            'q'       => $san->text($input->get('q')      ?? ''),
            'type'    => $san->option($input->get('type')   ?? '', ['review','question','thread','comment','']),
            'status'  => $san->option($input->get('status') ?? '', ['published','pending','spam','']),
            'page_id' => (int)$input->get('page_id'),
            'period'  => $san->option($input->get('period') ?? '', ['today','week','month','']),
        ];

        $result  = $this->vox->getAdminEntries(array_merge($filters, [
            'per_page' => $perPage,
            'page'     => $currPage,
        ]));
        $entries = $result['entries'];
        $total   = $result['total'];
        $pages   = (int)ceil($total / $perPage);

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.entries.php';
        return ob_get_clean();
    }

    // ── 3. Edit single entry ──────────────────────────────────────────────

    public function executeEntry(): string {
        $this->requirePermission('vox-view');
        $input   = $this->wire->input;
        $entryId = (int)($input->get('id') ?? 0);

        if (!$entryId) $this->wire->session->redirect('./entries/');

        if ($input->post('delete_entry')) {
            $this->requirePermission('vox-moderate');
            if (!$this->wire->session->CSRF->hasValidToken()) {
                $this->error('CSRF validation failed.');
            } else {
                $this->vox->deleteEntry($entryId);
                $this->message('Entry deleted.');
                $this->wire->session->redirect('./entries/');
            }
        }

        if ($input->post('submit_entry')) {
            if (!$this->wire->session->CSRF->hasValidToken()) {
                $this->error('CSRF validation failed.');
            } else {
                $this->vox->saveEntryEdit($entryId, [
                    'status'    => $input->post('status'),
                    'body'      => $input->post('body'),
                    'recommend' => $input->post('recommend'),
                ]);
                $rating = (int)$input->post('rating');
                if ($rating) $this->vox->saveEntryRating($entryId, $rating);
                $this->vox->saveModNote($entryId, $input->post('mod_note') ?? '');
                $this->message('Entry saved.');
            }
        }

        $entry = $this->vox->getEntry($entryId);
        if (!$entry) $this->wire->session->redirect('./entries/');

        $schema    = $this->vox->getSchema((int)$entry['template_id'], $entry['type']);
        $fieldVals = $this->vox->getEntryFieldValues($entryId);
        // Change history is not tracked; the History tab falls back to creation info.
        $history   = [];
        $modNote   = $this->vox->getModNote($entryId);

        // Stats for Details tab
        $entryLikes = $this->vox->getEntryLikes($entryId);
        $likes      = $entryLikes['total'];
        $replies    = $this->vox->countReplies($entryId);
        $reports    = $this->vox->getEntryReportsCount($entryId);

        $this->setTitle("Vox · Edit Entry #{$entryId}");
        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.entry-edit.php';
        return ob_get_clean();
    }

    // ── 4. Moderation ─────────────────────────────────────────────────────

    public function executeModeration(): string {
        $this->setTitle('Vox · Moderation');
        $this->requirePermission('vox-moderate');

        $input  = $this->wire->input;
        $san    = $this->wire->sanitizer;

        // Mass action
        if ($input->post('mass_action') && $this->wire->session->CSRF->hasValidToken()) {
            $action = $san->option($input->post('mass_action'), ['approve','reject','spam']);
            $ids    = array_map('intval', (array)$input->post('ids'));
            if ($action && $ids) {
                $status = match($action) {
                    'approve' => Vox::STATUS_PUBLISHED,
                    'reject', 'spam' => Vox::STATUS_SPAM,
                    default   => Vox::STATUS_PENDING,
                };
                $this->vox->setEntriesStatus($ids, $status);
                // Award points on bulk approve
                if ($status === Vox::STATUS_PUBLISHED) {
                    foreach ($ids as $id) { $this->awardOnApprove($id); }
                }
            }
        }

        // Single quick action
        if ($input->post('quick_action') && $this->wire->session->CSRF->hasValidToken()) {
            $action  = $san->option($input->post('quick_action'), ['approve','reject','spam']);
            $entryId = (int)$input->post('entry_id');
            if ($action && $entryId) {
                $status = match($action) {
                    'approve' => Vox::STATUS_PUBLISHED,
                    'reject', 'spam' => Vox::STATUS_SPAM,
                    default   => Vox::STATUS_PENDING,
                };
                $this->vox->setEntryStatus($entryId, $status);
                if ($status === Vox::STATUS_PUBLISHED) {
                    $this->awardOnApprove($entryId);
                }
            }
        }

        // Report actions
        if ($input->post('report_action') && $this->wire->session->CSRF->hasValidToken()) {
            $action   = $san->option($input->post('report_action'), ['delete_entry','dismiss']);
            $reportId = (int)$input->post('report_id');
            if ($action && $reportId) {
                if ($action === 'delete_entry') {
                    $this->vox->deleteReportedEntry($reportId);
                    $this->message('Reported entry deleted.');
                } else {
                    $this->vox->dismissReport($reportId);
                    $this->message('Report dismissed.');
                }
            }
        }

        $perPage  = 20;
        $currPage = max(1, (int)($input->get('p') ?? 1));
        $filters  = [
            'q'       => $san->text($input->get('q')    ?? ''),
            'type'    => $san->option($input->get('type')   ?? '', ['review','question','thread','comment','']),
            'page_id' => (int)$input->get('page_id'),
        ];

        $pendingResult = $this->vox->getPendingEntries(array_merge($filters, [
            'per_page' => $perPage,
            'page'     => $currPage,
        ]));
        $pending      = $pendingResult['entries'];
        $pendingTotal = $pendingResult['total'];
        $pendingPages = (int)ceil($pendingTotal / $perPage);
        $reports      = $this->vox->getOpenReports();

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.moderation.php';
        return ob_get_clean();
    }

    // ── 5. Field Schemas ──────────────────────────────────────────────────

    public function executeSchemas(): string {
        $this->setTitle('Vox · Field Schemas');
        $this->requirePermission('vox-configure');

        $input = $this->wire->input;
        $san   = $this->wire->sanitizer;

        if ($input->post('submit_schema') && $this->wire->session->CSRF->hasValidToken()) {
            $fields = $this->collectSchemaFields();
            $this->vox->saveSchemaFields(
                (int)$input->post('template_id'),
                $san->option($input->post('entry_type') ?? 'review', ['review','question','thread','comment']),
                $fields
            );
            $this->message('Schema saved.');
        }

        if ($input->post('delete_schema') && $this->wire->session->CSRF->hasValidToken()) {
            $tid  = (int)$input->post('template_id');
            $etype= $san->option($input->post('entry_type') ?? '', ['review','question','thread','comment']);
            if ($tid && $etype) {
                $this->vox->deleteSchema($tid, $etype);
                $this->message('Schema deleted.');
            }
        }

        $schemaMap     = $this->vox->getSchemaTemplateMap();
        // Only content templates — hide ProcessWire system templates (admin, user, role, …)
        $allTemplates  = $this->wire->templates->find('sort=name');
        $sysTemplates  = [];
        foreach ($allTemplates as $t) {
            if ($t->flags & Template::flagSystem) $sysTemplates[] = $t;
        }
        foreach ($sysTemplates as $t) $allTemplates->remove($t);
        $firstTemplate = $allTemplates->count() ? (int)$allTemplates->first()->id : 0;
        $selTemplateId = (int)($input->get('template_id') ?: (array_key_first($schemaMap) ?? $firstTemplate));
        $selType       = $san->option($input->get('entry_type') ?? 'review', ['review','question','thread','comment']);
        $schema        = $selTemplateId ? $this->vox->getSchema($selTemplateId, $selType) : [];

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.schemas.php';
        return ob_get_clean();
    }

    // ── 6. Gamification ───────────────────────────────────────────────────

    public function executeGamification(): string {
        $this->setTitle('Vox · Gamification');
        $this->requirePermission('vox-configure');

        $input = $this->wire->input;
        $san   = $this->wire->sanitizer;

        if ($input->post('submit_ranks') && $this->wire->session->CSRF->hasValidToken()) {
            $ids    = (array)$input->post('rank_id');
            $labels = (array)$input->post('rank_label');
            $points = (array)$input->post('rank_min_points');
            $icons  = (array)$input->post('rank_icon');
            $ranks  = [];
            foreach ($ids as $i => $id) {
                $ranks[] = [
                    'id'         => $id,
                    'label'      => $labels[$i] ?? 'Rank',
                    'min_points' => $points[$i] ?? 0,
                    'icon'       => $icons[$i]  ?? 'cup',
                ];
            }
            $this->vox->saveRanks($ranks);
            $this->message('Ranks saved.');
        }

        if ($input->post('submit_badge') && $this->wire->session->CSRF->hasValidToken()) {
            $badgeId = (int)$input->post('badge_id');
            $image   = $this->resolveBadgeImage($badgeId);
            $res = $this->vox->saveBadgeDef([
                'id'          => $badgeId,
                'badge_key'   => $input->post('badge_key'),
                'label'       => $input->post('badge_label'),
                'icon'        => $input->post('badge_icon'),
                'image'       => $image,
                'metric'      => $input->post('badge_metric'),
                'threshold'   => (int)$input->post('badge_threshold'),
                'description' => $input->post('badge_description'),
                'enabled'     => $input->post('badge_enabled') ? 1 : 0,
            ]);
            $res['ok'] ? $this->message('Badge saved.') : $this->error($res['error']);
        }

        if ($input->post('delete_badge') && $this->wire->session->CSRF->hasValidToken()) {
            $this->vox->deleteBadgeDef((int)$input->post('badge_id'));
            $this->message('Badge deleted.');
        }

        if ($input->post('submit_points') && $this->wire->session->CSRF->hasValidToken()) {
            $cfg = $this->wire->modules->getConfig('Vox');
            foreach (['points_post','points_like_received','points_answer','points_best_answer'] as $k) {
                $val = (int)$input->post($k);
                if ($val >= 0) $cfg[$k] = $val;
            }
            $this->wire->modules->saveConfig('Vox', $cfg);
            $this->message('Points config saved.');
        }

        $ranks        = $this->vox->getRanks();
        $rankCounts   = $this->vox->getRankUserCounts();
        $badgeDefs    = $this->vox->getBadgeDefs();
        $badgeMetrics = $this->vox->getBadgeMetrics();
        $editBadge    = (int)$input->get('edit_badge');
        $period       = $san->option($input->get('period') ?? 'month', ['week','month','all']);
        $leaderboard = $this->vox->getLeaderboard($period, 20);
        // Read fresh from module config so values reflect a save made earlier in
        // this same request (the cached $this->vox instance is loaded once at init
        // and would otherwise show the pre-save values).
        $pCfg        = array_merge(Vox::getDefaults(), (array)$this->wire->modules->getConfig('Vox'));
        $pointsCfg   = [
            'points_post'          => (int)$pCfg['points_post'],
            'points_like_received' => (int)$pCfg['points_like_received'],
            'points_answer'        => (int)$pCfg['points_answer'],
            'points_best_answer'   => (int)$pCfg['points_best_answer'],
        ];

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.gamification.php';
        return ob_get_clean();
    }

    // ── 7. Stop Words ─────────────────────────────────────────────────────

    public function executeStopwords(): string {
        $this->setTitle('Vox · Stop Words');
        $this->requirePermission('vox-configure');

        $input = $this->wire->input;
        $san   = $this->wire->sanitizer;

        if ($input->post('add_word') && $this->wire->session->CSRF->hasValidToken()) {
            $word   = $san->text($input->post('word') ?? '');
            $action = $san->option($input->post('action') ?? 'reject', ['reject','flag']);
            $pageId = (int)$input->post('page_id');
            $pageRef = trim($san->text($input->post('page_ref') ?? ''));
            if (!$pageId && $pageRef) {
                $page = ctype_digit($pageRef) ? $this->wire->pages->get((int)$pageRef) : $this->wire->pages->get($pageRef);
                if ($page && $page->id) $pageId = (int)$page->id;
            }
            if ($word) {
                $this->vox->addStopword($word, $action, $pageId);
                $this->message("Word added: {$word}");
            }
        }

        if ($input->post('bulk_import') && $this->wire->session->CSRF->hasValidToken()) {
            $raw    = $san->textarea($input->post('bulk_words') ?? '');
            $action = $san->option($input->post('bulk_action') ?? 'reject', ['reject','flag']);
            $words  = array_filter(array_unique(
                array_map('trim', preg_split('/[\n,]+/', $raw))
            ));
            $count  = $this->vox->importStopwords(array_values($words), $action);
            $this->message("{$count} words imported.");
        }

        if ($input->post('delete_word') && $this->wire->session->CSRF->hasValidToken()) {
            $wordId = (int)$input->post('word_id');
            if ($wordId) $this->vox->deleteStopword($wordId);
        }

        $globalWords = $this->vox->getAllStopwords('global');
        $localWords  = $this->vox->getLocalStopwords();
        $hitLog      = $this->vox->getStopwordHitEntries(50);

        $stats = [
            'total'   => count($globalWords),
            'reject'  => count(array_filter($globalWords, fn($w) => $w['action'] === 'reject')),
            'flag'    => count(array_filter($globalWords, fn($w) => $w['action'] === 'flag')),
            'hits'    => $this->vox->getPendingCount(),
            'blocked' => $this->vox->getSpamCount(),
        ];

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.stopwords.php';
        return ob_get_clean();
    }

    // ── 8. Settings ───────────────────────────────────────────────────────

    public function executeSettings(): string {
        $this->setTitle('Vox · Settings');
        $this->requirePermission('vox-configure');

        $input = $this->wire->input;

        // Settings are edited on the standard module config screen (Modules → Vox).
        // This page only keeps the destructive "delete all data" action.
        if ($input->post('delete_all_data') && $this->wire->session->CSRF->hasValidToken()) {
            if ($this->wire->user->isSuperuser()) {
                $this->vox->deleteAllData();
                $this->message('All Vox data deleted.');
            }
        }

        $cfg      = array_merge(Vox::getDefaults(), (array)$this->wire->modules->getConfig('Vox'));
        $overview = $this->vox->getAdminOverview();

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.settings.php';
        return ob_get_clean();
    }

    // ── 9. REST API reference ────────────────────────────────────────────

    public function executeApi(): string {
        $this->setTitle('Vox · API');
        $this->requirePermission('vox-api-docs');

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.api.php';
        return ob_get_clean();
    }

    // ── 10. Embed code generator ─────────────────────────────────────────

    public function executeInstall(): string {
        $this->setTitle('Vox · Embed');
        $this->requirePermission('vox-view');

        $input = $this->wire->input;
        if ($input->post('install_demo') && $this->wire->session->CSRF->hasValidToken()) {
            $this->requirePermission('vox-configure');
            if ($input->post('confirm_demo')) {
                $status = $this->vox->installDemoData();
                $this->message('Demo installed: ' . ($status['url'] ?: '/vox-demo/'));
            } else {
                $this->warning('Tick "Install demo with sample data" before installing the demo.');
            }
        }

        if ($input->post('remove_demo') && $this->wire->session->CSRF->hasValidToken()) {
            $this->requirePermission('vox-configure');
            $this->vox->removeDemoData();
            $this->message('Demo removed.');
        }

        $apiBase = $this->wire->config->urls->root . 'vox-api/';
        $demoStatus = $this->vox->getDemoStatus();
        // Front-end widgets a user can drop into a template, in include order.
        $widgets = [
            'reviews'     => ['file' => 'vox.reviews.php',     'label' => 'Ratings & reviews',        'icon' => 'star',     'desc' => 'Star ratings, recommendations and review cards.'],
            'questions'   => ['file' => 'vox.questions.php',   'label' => 'Questions & answers',       'icon' => 'question', 'desc' => 'Q&A threads with best-answer marking.'],
            'discussions' => ['file' => 'vox.discussions.php', 'label' => 'Discussions & block comments', 'icon' => 'comments', 'desc' => 'Free threads and inline block comments.'],
        ];

        $vox = $this->vox;
        ob_start();
        include __DIR__ . '/templates/admin/vox.install.php';
        return ob_get_clean();
    }

    // ── AJAX: quick entry status change ──────────────────────────────────

    public function executeAjax(): string {
        if (!$this->wire->session->CSRF->hasValidToken()) {
            return $this->jsonResp(['error' => 'Invalid token'], 403);
        }
        $this->requirePermission('vox-moderate');

        $input  = $this->wire->input;
        $san    = $this->wire->sanitizer;
        $action = $san->option($input->post('action') ?? '', ['approve','reject','spam','delete','status']);
        $id     = (int)$input->post('id');

        if (!$action || !$id) return $this->jsonResp(['error' => 'Invalid request'], 400);

        if ($action === 'delete') {
            $this->vox->deleteEntry($id);
            return $this->jsonResp(['success' => true, 'id' => $id, 'status' => 'deleted']);
        }

        $status = match($action) {
            'approve' => Vox::STATUS_PUBLISHED,
            'reject'  => Vox::STATUS_SPAM,
            'spam'    => Vox::STATUS_SPAM,
            'status'  => $san->option($input->post('status') ?? '', [Vox::STATUS_PUBLISHED, Vox::STATUS_PENDING, Vox::STATUS_SPAM]),
            default   => '',
        };

        if (!$status) return $this->jsonResp(['error' => 'Invalid status'], 400);

        $this->vox->setEntryStatus($id, $status);

        if ($status === Vox::STATUS_PUBLISHED) {
            $this->awardOnApprove($id);
        }

        return $this->jsonResp(['success' => true, 'id' => $id, 'status' => $status]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Award gamification points when an entry is approved.
     */
    private function awardOnApprove(int $entryId): void {
        $entry = $this->vox->getEntry($entryId);
        if (!$entry || !$entry['user_id']) return;
        $gami = new VoxGamification($this->vox);
        $gami->onEntryPublished((int)$entry['user_id'], $entryId, $entry['type']);
    }

    /**
     * Collect and sanitize schema fields from POST.
     */
    private function collectSchemaFields(): array {
        $input   = $this->wire->input;
        $san     = $this->wire->sanitizer;
        $names   = (array)$input->post('field_name');
        $labels  = (array)$input->post('field_label');
        $types   = (array)$input->post('field_type');
        $opts    = (array)$input->post('field_options');
        $reqs    = (array)$input->post('field_required');

        $fields = [];
        foreach ($names as $i => $rawName) {
            $name = $san->fieldName($rawName ?? '');
            if (!$name) continue;
            // The required checkbox is keyed by the raw field name (field_required[<name>]),
            // so unchecked rows don't shift a positional index.
            $fields[] = [
                'field_name'    => $name,
                'field_label'   => $labels[$i] ?? $name,
                'field_type'    => $types[$i]  ?? 'text',
                'field_options' => $opts[$i]   ?? '',
                'required'      => !empty($reqs[(string)$rawName]) ? 1 : 0,
            ];
        }
        return $fields;
    }

    /**
     * Resolve the badge image filename from the request: keep the current one,
     * remove it, or store a freshly uploaded file. Returns the filename to save.
     */
    private function resolveBadgeImage(int $badgeId): string {
        $current = '';
        if ($badgeId) {
            foreach ($this->vox->getBadgeDefs() as $b) {
                if ((int)$b['id'] === $badgeId) { $current = (string)$b['image']; break; }
            }
        }
        $path = $this->vox->badgesPath();

        if ($this->wire->input->post('badge_image_remove')) {
            if ($current && is_file($path . $current)) @unlink($path . $current);
            return '';
        }

        if (!empty($_FILES['badge_image']['name'])) {
            $u = $this->wire(new WireUpload('badge_image'));
            $u->setMaxFiles(1);
            $u->setOverwrite(false);
            $u->setValidExtensions(['jpg','jpeg','png','gif','webp','svg']);
            $u->setDestinationPath($path);
            $files = $u->execute();
            if ($files) {
                if ($current && is_file($path . $current)) @unlink($path . $current);
                return $files[0];
            }
            foreach ($u->getErrors() as $e) $this->error($e);
        }
        return $current;
    }

    private function requirePermission(string $name): void {
        if (!$this->wire->user->isSuperuser() && !$this->wire->user->hasPermission($name)) {
            throw new WirePermissionException($this->_('You do not have permission to access this page.'));
        }
    }

    private function setTitle(string $title): void {
        // Browser <title> tab.
        $this->wire->page->title = $title;
        // Derive the section name from "Vox · Section" for a clean <h1> headline.
        $parts   = explode(' · ', $title, 2);
        $section = $parts[1] ?? $parts[0];
        $this->headline($section);
        // Breadcrumb trail: Admin / Vox (→ dashboard) / [section as headline].
        // Adding our own crumb also stops ProcessController auto-adding the
        // "Vox Admin" module-title crumb on URL-segment subpages.
        $this->breadcrumb($this->wire->config->urls->admin . 'vox/', $this->_('Vox'));
    }

    private function jsonResp(array $data, int $code = 200): string {
        ob_start();
        ob_end_clean();
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data);
    }
}

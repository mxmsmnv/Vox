<?php namespace ProcessWire;

require_once __DIR__ . '/VoxGamification.php';

/**
 * VoxApi — REST API endpoint for Vox module.
 * All data access goes through Vox module methods — no raw SQL here.
 */
class VoxApi extends WireData implements Module {

    public static function getModuleInfo(): array {
        return [
            'title'    => 'Vox API',
            'summary'  => 'REST API for Vox discussions module.',
            'version'  => 170,
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'icon'     => 'plug',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Vox'],
        ];
    }

    private Vox $vox;
    private VoxGamification $gami;
    private string $routeHookId = '';

    public function init(): void {
        $this->vox  = $this->wire->modules->get('Vox');
        $this->gami = new VoxGamification($this->vox);
    }

    public function ready(): void {
        if (!$this->routeHookId) {
            $this->routeHookId = $this->wire->addHook(
                '#^/vox-api(?:/(?P<path>.*))?/?$#',
                $this,
                'handleRequest'
            );
        }
    }

    // ── Router ────────────────────────────────────────────────────────────

    public function handleRequest(HookEvent $event): string {
        $path     = trim((string)($event->arguments('path') ?? ''), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $resource = $segments[0] ?? '';
        $action   = $segments[1] ?? '';
        $method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (count($segments) > 2) return $this->jsonError('Not found', 404);

        // Base URL — hide the discovery document unless explicitly permitted.
        if ($resource === '') return $this->apiIndex();

        if ($resource === 'blocks' && $action === '') {
            if ($method === 'GET') return $this->apiBlocks();
            return $this->methodNotAllowed(['GET']);
        }

        if ($resource === 'entries' && $action === '') {
            if ($method === 'GET') return $this->apiEntriesGet();
            return $this->methodNotAllowed(['GET']);
        }

        if ($resource === 'entries' && in_array($action, ['add', 'vote', 'report', 'best'], true)) {
            if ($method !== 'POST') return $this->methodNotAllowed(['POST']);
            if ($action === 'add')    return $this->apiEntryAdd();
            if ($action === 'vote')   return $this->apiEntryVote();
            if ($action === 'report') return $this->apiEntryReport();
            if ($action === 'best')   return $this->apiEntryBest();
        }

        if ($resource === 'leaderboard' && $action === '') {
            if ($method === 'GET') return $this->apiLeaderboard();
            return $this->methodNotAllowed(['GET']);
        }

        if ($resource === 'user-stats' && $action === '') {
            if ($method === 'GET') return $this->apiUserStats();
            return $this->methodNotAllowed(['GET']);
        }

        return $this->jsonError('Not found', 404);
    }

    /**
     * Hookable event fired after a new entry is created.
     * External modules can react with:
     *   $wire->addHookAfter('VoxApi::entryAdded', ...)
     *
     * @param array $data ['user_id' => int, 'entry_id' => int, 'type' => string, 'status' => string]
     */
    public function ___entryAdded(array $data): void {}

    public function ___uninstall(): void {
        $this->cleanupLegacyProcessPage();
    }

    public function ___install(): void {
        $this->cleanupLegacyProcessPage();
    }

    public function ___upgrade($fromVersion, $toVersion): void {
        $this->cleanupLegacyProcessPage();
    }

    // ── GET /vox-api/blocks/ ──────────────────────────────────────────────

    private function apiBlocks(): string {
        $input    = $this->wire->input;
        $pageId   = $this->resolvePageParam('get');
        $blockIds = $input->get('blocks');

        if (!$pageId)                                   return $this->jsonError('page_key required');
        if (!is_array($blockIds) || !count($blockIds))  return $this->json([]);

        $safe   = array_map(fn($b) => $this->wire->sanitizer->text($b), $blockIds);
        return $this->json($this->vox->getBlockCounts($pageId, $safe));
    }

    // ── GET /vox-api/entries/ ─────────────────────────────────────────────

    private function apiEntriesGet(): string {
        $input  = $this->wire->input;
        $san    = $this->wire->sanitizer;
        $pageId = $this->resolvePageParam('get');
        if (!$pageId) return $this->jsonError('page_key required');

        $perPage  = min(50, max(1, (int)($input->get('per_page') ?? 10)));
        $hasParent = $input->get('parent_key') !== null;
        $parentId = $hasParent ? $this->resolveEntryParam('get', 'parent') : null;

        $opts = [
            'page_id'  => $pageId,
            'block_id' => $san->text($input->get('block_id') ?? ''),
            'type'     => $san->option($input->get('type') ?? '', ['review','question','thread','comment','']),
            'status'   => Vox::STATUS_PUBLISHED,
            'page'     => max(1, (int)($input->get('page') ?? 1)),
            'per_page' => $perPage,
        ];

        // When fetching replies, omit depth filter (replies are at depth > 0)
        if ($parentId !== null) {
            $opts['parent_id'] = $parentId;
        } else {
            $opts['depth'] = 0;
        }

        $result = $this->vox->getEntries($opts);

        $result['entries'] = array_map(
            fn($e) => $this->vox->enrichEntryPublic($e),
            $result['entries']
        );
        $result['pages'] = (int)ceil($result['total'] / $perPage);

        return $this->json($result);
    }

    // ── POST /vox-api/entries/add ─────────────────────────────────────────

    private function apiEntryAdd(): string {
        if (!$this->wire->session->CSRF->hasValidToken()) {
            return $this->jsonError('Invalid CSRF token', 403);
        }

        $input     = $this->wire->input;
        $san       = $this->wire->sanitizer;
        $user      = $this->wire->user;
        $pageId    = $this->resolvePageParam('post');
        $blockId   = $san->text($input->post('block_id') ?? '');
        $type      = $san->option($input->post('type') ?? '', ['review','question','thread','comment']);
        $parentId  = $this->resolveEntryParam('post', 'parent');
        $body      = $san->textarea($input->post('body') ?? '');
        $rating    = max(0, min(5, (int)$input->post('rating')));
        $recommendRaw = $input->post('recommend');
        $recommend = ($recommendRaw !== null && $recommendRaw !== '') ? (int)(bool)$recommendRaw : null;

        if (!$pageId) return $this->jsonError('page_key required');
        if (!$type)   return $this->jsonError('type required');
        if (!$body)   return $this->jsonError('body required');

        $pwPage = $this->wire->pages->get($pageId);
        if (!$pwPage || !$pwPage->id) return $this->jsonError('Page not found', 404);
        if (!$pwPage->viewable())     return $this->jsonError('Page is not viewable', 403);

        $isGuest = !$user->isLoggedIn();
        if ($isGuest && !$this->vox->cfg('allow_guests')) {
            return $this->jsonError('Guest entries are not allowed', 403);
        }

        $swResult = $this->vox->checkStopwords($body, $pageId);
        if (!$swResult['pass'] && $swResult['action'] === 'reject') {
            return $this->jsonError('Your entry contains prohibited content', 422);
        }

        $guestName = $guestEmail = $guestFingerprint = '';
        if ($isGuest) {
            $guestName        = $san->text($input->post('guest_name') ?? '') ?: $this->vox->generateGuestName();
            $guestEmail       = $san->email($input->post('guest_email') ?? '');
            $guestFingerprint = $this->vox->getGuestFingerprint();
            if ($this->vox->cfg('guest_require_email') && !$guestEmail) {
                return $this->jsonError('Email is required for guest posts', 422);
            }
        }

        // Rate limit posting (superusers exempt)
        if (!$user->isSuperuser()) {
            $allowed = $this->vox->checkPostRateLimit(
                $isGuest ? null : (int)$user->id,
                $guestFingerprint,
                $this->vox->getClientIp()
            );
            if (!$allowed) return $this->jsonError('Too many posts — please wait a moment and try again', 429);
        }

        // Depth and root from parent
        $depth = 0; $rootId = null;
        if ($parentId) {
            $parent = $this->vox->getEntry($parentId);
            if (!$parent) return $this->jsonError('Parent entry not found', 404);
            if ((int)$parent['page_id'] !== $pageId) return $this->jsonError('Parent entry belongs to another page', 422);
            if ($parent['status'] !== Vox::STATUS_PUBLISHED) return $this->jsonError('Parent entry is not published', 403);
            $depth  = min(Vox::MAX_DEPTH, (int)$parent['depth'] + 1);
            $rootId = $parent['root_id'] ?: $parent['id'];
        }

        $status = $this->vox->cfg('moderation') === 'immediate'
            ? Vox::STATUS_PUBLISHED
            : Vox::STATUS_PENDING;
        if (isset($swResult['action']) && $swResult['action'] === 'flag') {
            $status = Vox::STATUS_PENDING;
        }

        $templateId = $pwPage->template->id;

        // Create entry
        $entryId = $this->vox->createEntry([
            'page_id'           => $pageId,
            'block_id'          => $blockId ?: null,
            'template_id'       => $templateId,
            'type'              => $type,
            'parent_id'         => $parentId ?: null,
            'root_id'           => $rootId,
            'depth'             => $depth,
            'user_id'           => $isGuest ? null : (int)$user->id,
            'guest_name'        => $guestName        ?: null,
            'guest_email'       => $guestEmail       ?: null,
            'guest_fingerprint' => $guestFingerprint ?: null,
            'body'              => $body,
            'status'            => $status,
            'recommend'         => $recommend,
            'ip'                => $this->vox->getClientIp(),
        ]);

        if (!$entryId) return $this->jsonError('Failed to save entry', 500);

        // Rating (built-in)
        if ($type === Vox::TYPE_REVIEW && $rating > 0) {
            $this->vox->saveEntryRating($entryId, $rating);
        }

        // Uploaded photos
        if (!empty($_FILES['photos'])) {
            $this->vox->saveEntryPhotos($entryId, $_FILES['photos']);
        }

        // Custom field values
        $schema = $this->vox->getSchema($templateId, $type);
        foreach ($schema as $field) {
            if ($field['builtin'] ?? false) continue;
            if (!(int)$field['id'])         continue;
            $val = $san->text($input->post($field['field_name']) ?? '');
            if ($val !== '') {
                $this->vox->saveEntryFieldValue($entryId, (int)$field['id'], $val);
            }
        }

        // Gamification
        if (!$isGuest && $status === Vox::STATUS_PUBLISHED) {
            $this->gami->onEntryPublished((int)$user->id, $entryId, $type);
            // Answer points only for actual answers — replies to questions.
            if ($type === Vox::TYPE_COMMENT && $parentId && ($parent['type'] ?? '') === Vox::TYPE_QUESTION) {
                $this->gami->onAnswerPosted((int)$user->id, $entryId);
            }
        }

        // Fire hookable event so external modules can react to new entries
        $this->entryAdded([
            'user_id'  => $isGuest ? 0 : (int)$user->id,
            'entry_id' => $entryId,
            'type'     => $type,
            'status'   => $status,
        ]);

        if ($status === Vox::STATUS_PENDING) {
            $this->sendPendingNotification($entryId);
        }

        $entry = $this->vox->getEntry($entryId);
        return $this->json(['success' => true, 'entry' => $this->vox->enrichEntryPublic($entry)], 201);
    }

    // ── POST /vox-api/entries/vote ────────────────────────────────────────

    private function apiEntryVote(): string {
        if (!$this->wire->session->CSRF->hasValidToken()) {
            return $this->jsonError('Invalid CSRF token', 403);
        }

        $input   = $this->wire->input;
        $user    = $this->wire->user;
        $isGuest = !$user->isLoggedIn();

        $entryId = $this->resolveEntryParam('post', 'entry');
        if (!$entryId) return $this->jsonError('entry_key required');

        $entry = $this->vox->getEntry($entryId);
        if (!$entry) return $this->jsonError('Entry not found', 404);
        if ($entry['status'] !== Vox::STATUS_PUBLISHED) return $this->jsonError('Entry is not published', 403);

        if ($isGuest && $this->vox->cfg('votes_guests') === 'disabled') {
            return $this->jsonError('Guest voting is disabled', 403);
        }

        $value       = (int)$input->post('value');
        $userId      = $isGuest ? null : (int)$user->id;
        $fingerprint = $isGuest ? $this->vox->getGuestFingerprint() : null;

        $result    = $this->vox->toggleVote($entryId, $value, $userId, $fingerprint);
        $newValue  = $result['user_vote'];
        $prevValue = $result['prev_vote'];

        // Award on a new positive vote, revoke on its withdrawal — like/unlike
        // cycles stay net-zero for the entry owner's points.
        $ownerId = (int)($entry['user_id'] ?? 0);
        if ($ownerId) {
            if ($prevValue <= 0 && $newValue > 0) {
                $this->gami->onLikeReceived($ownerId, $entryId);
            } elseif ($prevValue > 0 && $newValue <= 0) {
                $this->gami->onLikeRevoked($ownerId, $entryId);
            }
        }

        return $this->json(['success' => true, 'total' => $result['total'], 'user_vote' => $newValue]);
    }

    // ── POST /vox-api/entries/report ──────────────────────────────────────

    private function apiEntryReport(): string {
        if (!$this->wire->session->CSRF->hasValidToken()) {
            return $this->jsonError('Invalid CSRF token', 403);
        }

        $input   = $this->wire->input;
        $san     = $this->wire->sanitizer;
        $user    = $this->wire->user;
        $entryId = $this->resolveEntryParam('post', 'entry');
        $reason  = $san->text($input->post('reason') ?? '');

        if (!$entryId) return $this->jsonError('entry_key required');

        $entry = $this->vox->getEntry($entryId);
        if (!$entry) return $this->jsonError('Entry not found', 404);
        if ($entry['status'] !== Vox::STATUS_PUBLISHED) return $this->jsonError('Entry is not published', 403);

        // Reports carry no stored identity for guests — throttle per session.
        if (!$user->isSuperuser() && !$this->sessionThrottle('report', 5, 600)) {
            return $this->jsonError('Too many reports — please wait a moment and try again', 429);
        }

        $userId     = $user->isLoggedIn() ? (int)$user->id : null;
        $guestEmail = $userId ? '' : $san->email($input->post('guest_email') ?? '');
        $added      = $this->vox->addReport($entryId, $userId, $reason, $guestEmail);

        if (!$added) {
            return $this->json(['success' => true, 'message' => 'Already reported']);
        }

        return $this->json(['success' => true]);
    }

    // ── POST /vox-api/entries/best ────────────────────────────────────────

    private function apiEntryBest(): string {
        if (!$this->wire->session->CSRF->hasValidToken()) {
            return $this->jsonError('Invalid CSRF token', 403);
        }

        $user = $this->wire->user;
        if (!$user->isLoggedIn()) return $this->jsonError('Authentication required', 401);

        $entryId = $this->resolveEntryParam('post', 'entry');
        if (!$entryId) return $this->jsonError('entry_key required');

        $entry = $this->vox->getEntry($entryId);
        if (!$entry)              return $this->jsonError('Entry not found', 404);
        if (!$entry['parent_id']) return $this->jsonError('Entry has no parent question');
        if ($entry['status'] !== Vox::STATUS_PUBLISHED) return $this->jsonError('Entry is not published', 403);

        $parent = $this->vox->getEntry($entry['parent_id']);
        if (!$parent) return $this->jsonError('Parent not found', 404);
        if ($parent['status'] !== Vox::STATUS_PUBLISHED) return $this->jsonError('Parent entry is not published', 403);

        // Re-marking the current best answer is a no-op (no extra points).
        if (!empty($entry['is_best_answer'])) return $this->json(['success' => true]);

        $threadRoot = (int)($entry['root_id'] ?: $entry['parent_id']);
        $prevBestId = $this->vox->getBestAnswerId($threadRoot);

        $marked = $this->vox->markBestAnswer($entryId, (int)$user->id, $user->isSuperuser());
        if (!$marked) return $this->jsonError('Not allowed', 403);

        if ($prevBestId && $prevBestId !== $entryId) {
            $prevBest = $this->vox->getEntry($prevBestId);
            if ($prevBest && $prevBest['user_id']) {
                $this->gami->onBestAnswerRevoked((int)$prevBest['user_id'], $prevBestId);
            }
        }
        if ($entry['user_id']) {
            $this->gami->onBestAnswerSelected((int)$entry['user_id'], $entryId);
        }

        return $this->json(['success' => true]);
    }

    // ── GET /vox-api/leaderboard/ ─────────────────────────────────────────

    private function apiLeaderboard(): string {
        $input  = $this->wire->input;
        $san    = $this->wire->sanitizer;
        $period = $san->option($input->get('period') ?? 'month', ['week','month','all']);
        $limit  = min(50, max(1, (int)($input->get('limit') ?? 10)));
        return $this->json(array_map(fn($row) => $this->publicLeaderboardRow($row), $this->vox->getLeaderboard($period, $limit)));
    }

    // ── GET /vox-api/user-stats/ ──────────────────────────────────────────

    private function apiUserStats(): string {
        $user = $this->wire->user;
        if (!$user->isLoggedIn()) return $this->jsonError('Authentication required', 401);
        $userId = (int)$user->id;
        return $this->json([
            'user_key' => $this->vox->publicKey('user', $userId),
            'name'    => $user->name,
            'stats'   => $this->gami->getUserStats($userId),
            'rank'    => $this->publicRank($this->vox->getUserRank($userId)),
            'badges'  => $this->publicBadges($this->vox->getUserBadges($userId)),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Sliding-window throttle stored in the session.
     * Returns false when the caller exceeded $max actions per $window seconds.
     */
    private function sessionThrottle(string $key, int $max, int $window): bool {
        $session = $this->wire->session;
        $now     = time();
        $name    = "vox_throttle_{$key}";
        $times   = array_values(array_filter((array)$session->get($name), fn($t) => (int)$t > $now - $window));
        if (count($times) >= $max) {
            $session->set($name, $times);
            return false;
        }
        $times[] = $now;
        $session->set($name, $times);
        return true;
    }

    private function resolvePageParam(string $method): int {
        $input = $this->wire->input;
        $value = $method === 'post'
            ? ($input->post('page_key') ?? '')
            : ($input->get('page_key') ?? '');
        return $this->vox->resolvePublicKey('page', $value);
    }

    private function resolveEntryParam(string $method, string $name = 'entry'): int {
        $input = $this->wire->input;
        $keyName = $name . '_key';
        $value = $method === 'post'
            ? ($input->post($keyName) ?? '')
            : ($input->get($keyName) ?? '');
        return $this->vox->resolvePublicKey('entry', $value);
    }

    private function publicLeaderboardRow(array $row): array {
        if (!empty($row['user_id'])) {
            $row['user_key'] = $this->vox->publicKey('user', (int)$row['user_id']);
        }
        unset($row['user_id']);
        $row['rank'] = $this->publicRank($row['rank'] ?? []);
        return $row;
    }

    private function publicRank(?array $rank): array {
        if (!$rank) return [];
        if (!empty($rank['id'])) {
            $rank['rank_key'] = $this->vox->publicKey('rank', (int)$rank['id']);
        }
        unset($rank['id']);
        return $rank;
    }

    private function publicBadges(array $badges): array {
        return array_map(function($badge) {
            if (!is_array($badge)) return $badge;
            if (!empty($badge['id'])) {
                $badge['badge_key'] = $this->vox->publicKey('badge', (int)$badge['id']);
            }
            unset($badge['id'], $badge['user_id']);
            return $badge;
        }, $badges);
    }

    private function sendPendingNotification(int $entryId): void {
        $email = $this->vox->cfg('notify_email');
        if (!$email) return;
        $mailer = $this->wire->mail;
        if (!$mailer) return;
        try {
            $url = $this->wire->config->urls->admin . 'vox/moderation/';
            $mailer->new()
                ->to($email)
                ->subject('New entry pending review — Vox')
                ->body("A new entry (#$entryId) is awaiting moderation.\n\n{$url}")
                ->send();
        } catch (\Exception $e) {}
    }

    public function cleanupLegacyProcessPage(): void {
        $moduleId = (int)$this->wire->modules->getModuleID('VoxApi');
        if (!$moduleId) return;

        foreach ($this->wire->pages->find("process={$moduleId}, include=all") as $page) {
            try {
                $page->process = null;
                $this->wire->pages->trash($page);
            } catch (\Exception $e) {
                $this->wire->log->save('vox', 'Unable to trash legacy Vox API process page: ' . $e->getMessage());
            }
        }
    }

    /** Discovery document for the API base URL. */
    private function apiIndex(): string {
        if (!$this->wire->user->isSuperuser() && !$this->wire->user->hasPermission('vox-api-docs')) {
            return $this->blank();
        }

        $base = $this->wire->config->urls->root . 'vox-api/';
        return $this->json([
            'name'    => 'Vox API',
            'version' => Vox::VERSION,
            'base'    => $base,
            'ids'     => 'Public responses use opaque *_key values instead of internal ProcessWire/database ids.',
            'endpoints' => [
                ['method' => 'GET',  'path' => $base . 'blocks/',        'auth' => 'public', 'desc' => 'Comment counts for block ids on a page.'],
                ['method' => 'GET',  'path' => $base . 'entries/',       'auth' => 'public', 'desc' => 'Paginated list of published entries and replies.'],
                ['method' => 'POST', 'path' => $base . 'entries/add',    'auth' => 'csrf',   'desc' => 'Create a review, question, thread or comment.'],
                ['method' => 'POST', 'path' => $base . 'entries/vote',   'auth' => 'csrf',   'desc' => 'Toggle a like / helpful vote on an entry.'],
                ['method' => 'POST', 'path' => $base . 'entries/report', 'auth' => 'csrf',   'desc' => 'Report an entry for moderation.'],
                ['method' => 'POST', 'path' => $base . 'entries/best',   'auth' => 'login',  'desc' => 'Mark a comment as the best answer.'],
                ['method' => 'GET',  'path' => $base . 'leaderboard/',   'auth' => 'public', 'desc' => 'Top users by points for a period.'],
                ['method' => 'GET',  'path' => $base . 'user-stats/',    'auth' => 'login',  'desc' => 'Current user stats, rank and badges.'],
            ],
        ]);
    }

    private function json(mixed $data, int $code = 200): string {
        // Discard any output buffered by PW hooks/templates before sending JSON
        ob_start();
        ob_end_clean();
        $this->wire->config->ajax = true;
        http_response_code($code);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function blank(int $code = 200): string {
        ob_start();
        ob_end_clean();
        $this->wire->config->ajax = true;
        http_response_code($code);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: text/html; charset=utf-8');
        return '';
    }

    private function jsonError(string $message, int $code = 400): string {
        return $this->json(['error' => $message], $code);
    }

    private function methodNotAllowed(array $allowed): string {
        header('Allow: ' . implode(', ', $allowed));
        return $this->jsonError('Method not allowed', 405);
    }
}

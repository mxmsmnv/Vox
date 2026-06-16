<?php namespace ProcessWire;

require_once __DIR__ . '/VoxRepository.php';
require_once __DIR__ . '/VoxGamification.php';

/**
 * Vox - Community discussions module for ProcessWire
 *
 * @author  Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @link    https://smnv.org
 * @version 1.6.2
 * @license MIT
 */
class Vox extends WireData implements Module, ConfigurableModule {

    private ?VoxRepository $repository = null;

    // ── Request-level caches (avoid N+1 when rendering entry lists) ──────
    /** @var array<int,int> user_id => total points */
    private array $userPointsCache = [];
    /** @var ?array ranks ordered by min_points DESC */
    private ?array $ranksDescCache = null;
    /** @var array<int,array> entry_id => photos */
    private array $entryPhotosCache = [];
    /** @var array<int,array{total:int,user_liked:bool}> entry_id => likes */
    private array $entryLikesCache = [];
    /** @var array<int,array> entry_id => field values */
    private array $entryValuesCache = [];

    // ── Module info ───────────────────────────────────────────────────────

    public static function getModuleInfo(): array {
        return [
            'title'    => 'Vox',
            'summary'  => 'Community discussions: reviews, Q&A, threads and block comments for any page.',
            'version'  => 162,
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'icon'     => 'comments',
            'autoload' => true,
            'singular' => true,
            'requires' => ['PHP>=8.2', 'ProcessWire>=3.0.200'],
            'installs' => ['ProcessVox', 'VoxApi', 'TextformatterVox'],
        ];
    }

    // ── Version ───────────────────────────────────────────────────────────
    // Semantic version for display. The integer in getModuleInfo() (used by
    // ProcessWire for upgrade detection) does not round-trip through
    // formatVersion() to this string, so keep this in sync on each release.
    const VERSION = '1.6.2';

    // ── Table names ───────────────────────────────────────────────────────

    const TABLE_ENTRIES   = 'vox_entries';
    const TABLE_FIELDS    = 'vox_fields';
    const TABLE_VALUES    = 'vox_values';
    const TABLE_PHOTOS    = 'vox_photos';
    const TABLE_VOTES     = 'vox_votes';
    const TABLE_REPORTS   = 'vox_reports';
    const TABLE_STOPWORDS = 'vox_stopwords';
    const TABLE_POINTS    = 'vox_points';
    const TABLE_RANKS     = 'vox_ranks';
    const TABLE_BADGES    = 'vox_badges';
    const TABLE_BADGE_DEFS = 'vox_badge_defs';
    const TABLE_MOD_NOTES = 'vox_mod_notes';

    const PERMISSIONS = [
        'vox-view'      => 'View Vox discussions admin',
        'vox-moderate'  => 'Moderate Vox entries (approve/reject/spam)',
        'vox-configure' => 'Configure Vox module settings',
        'vox-api-docs'  => 'View Vox API documentation',
    ];

    // Entry types
    const TYPE_REVIEW   = 'review';
    const TYPE_QUESTION = 'question';
    const TYPE_THREAD   = 'thread';
    const TYPE_COMMENT  = 'comment';

    // Statuses
    const STATUS_PENDING   = 'pending';
    const STATUS_PUBLISHED = 'published';
    const STATUS_SPAM      = 'spam';

    // Max nesting depth (0,1,2 — L2 is flattened)
    const MAX_DEPTH = 2;

    // ── Default config ────────────────────────────────────────────────────

    public static function getDefaults(): array {
        return [
            'moderation'           => 'immediate',  // immediate|approval
            'allow_guests'         => 1,
            'guest_require_email'  => 0,
            'notify_email'         => '',
            'rate_post_interval'   => 30,            // seconds between posts, 0 = off
            'rate_post_hourly'     => 20,            // max posts per hour, 0 = off
            'panel_mode'           => 'inline',     // inline|sidebar
            'preview_count'        => 3,
            'votes_mode'           => 'likes',       // likes|helpful
            'votes_guests'         => 'disabled',    // disabled|fingerprint
            'photo_uploads'        => 1,
            'photo_max'            => 6,
            'photo_max_size'       => 5,             // MB
            'photo_path'           => '/site/assets/vox/uploads/',
            'points_post'          => 10,
            'points_like_received' => 2,
            'points_answer'        => 5,
            'points_best_answer'   => 15,
        ];
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────

    public function init(): void {
        // Make module accessible as $vox in templates
        $this->wire->set('vox', $this);
        if ($this->wire->modules->isInstalled('VoxApi')) {
            $this->wire->modules->get('VoxApi');
        }
    }

    public function ready(): void {
        $this->ensurePermissions();
        // Hook for automatic gamification checks after entry is added
        $this->addHookAfter('VoxApi::entryAdded', $this, 'hookCheckBadges');
    }

    private function repository(): VoxRepository {
        if (!$this->repository) {
            $this->repository = new VoxRepository($this->wire);
        }
        return $this->repository;
    }

    public function ensurePermissions(): void {
        $permissions = $this->wire->permissions;
        foreach (self::PERMISSIONS as $name => $title) {
            $permission = $permissions->get($name);
            if (!$permission || !$permission->id) {
                $permission = $permissions->add($name);
            }
            if ($permission->title !== $title) {
                $permission->title = $title;
                $permission->save();
            }
        }
    }

    // ── Public opaque identifiers ───────────────────────────────────────

    private function publicIdSecret(): string {
        $config = $this->wire->config;
        $salt = (string)($config->userAuthSalt ?? $config->sessionName ?? $config->httpHost ?? 'vox');
        return hash('sha256', 'vox-public-id|' . $salt, true);
    }

    private function base64UrlEncode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string {
        $value = strtr($value, '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad) $value .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode($value, true);
        return $decoded === false ? '' : $decoded;
    }

    /**
     * Create an opaque, signed public key for an internal id.
     * The raw ProcessWire/db id is encrypted before it leaves the server.
     */
    public function publicKey(string $scope, int $id): string {
        if ($id <= 0) return '';
        $scope = preg_replace('/[^a-z0-9_-]/i', '', $scope) ?: 'id';
        $key = $this->publicIdSecret();
        $iv = random_bytes(16);
        $payload = json_encode(['s' => $scope, 'i' => $id], JSON_UNESCAPED_SLASHES);
        $cipher = openssl_encrypt((string)$payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) return '';
        $mac = hash_hmac('sha256', $iv . $cipher, $key, true);
        return 'vox_' . $this->base64UrlEncode($iv . $mac . $cipher);
    }

    /**
     * Resolve an opaque public key back to an internal id.
     */
    public function resolvePublicKey(string $scope, mixed $publicKey): int {
        $publicKey = trim((string)$publicKey);
        if ($publicKey === '') return 0;
        if (!str_starts_with($publicKey, 'vox_')) return 0;

        $raw = $this->base64UrlDecode(substr($publicKey, 4));
        if (strlen($raw) < 49) return 0;

        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        $key = $this->publicIdSecret();
        $calc = hash_hmac('sha256', $iv . $cipher, $key, true);
        if (!hash_equals($mac, $calc)) return 0;

        $json = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $data = $json ? json_decode($json, true) : null;
        if (!is_array($data) || ($data['s'] ?? '') !== $scope) return 0;
        return max(0, (int)($data['i'] ?? 0));
    }

    // ── Install ───────────────────────────────────────────────────────────

    public function ___install(): void {
        $this->ensurePermissions();
        $db = $this->wire->database;

        // Entries
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_ENTRIES . "` (
                `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `page_id`           INT UNSIGNED NOT NULL,
                `block_id`          VARCHAR(100) NULL DEFAULT NULL,
                `template_id`       INT UNSIGNED NOT NULL DEFAULT 0,
                `type`              ENUM('review','question','thread','comment') NOT NULL,
                `parent_id`         INT UNSIGNED NULL DEFAULT NULL,
                `root_id`           INT UNSIGNED NULL DEFAULT NULL,
                `depth`             TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `user_id`           INT UNSIGNED NULL DEFAULT NULL,
                `guest_name`        VARCHAR(60) NULL DEFAULT NULL,
                `guest_email`       VARCHAR(255) NULL DEFAULT NULL,
                `guest_fingerprint` VARCHAR(64) NULL DEFAULT NULL,
                `body`              TEXT NOT NULL,
                `status`            ENUM('pending','published','spam') NOT NULL DEFAULT 'pending',
                `is_owner_reply`    TINYINT(1) NOT NULL DEFAULT 0,
                `is_best_answer`    TINYINT(1) NOT NULL DEFAULT 0,
                `recommend`         TINYINT(1) NULL DEFAULT NULL,
                `created`           DATETIME NOT NULL,
                `ip`                VARCHAR(45) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `page_id`       (`page_id`),
                KEY `block_id`      (`block_id`),
                KEY `parent_id`     (`parent_id`),
                KEY `root_id`       (`root_id`),
                KEY `user_id`       (`user_id`),
                KEY `status`        (`status`),
                KEY `type`          (`type`),
                KEY `created`       (`created`),
                KEY `page_status_type_depth` (`page_id`, `status`, `type`, `depth`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Field schemas
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_FIELDS . "` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `template_id`  INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = global',
                `entry_type`   ENUM('review','question','thread','comment') NOT NULL DEFAULT 'review',
                `field_name`   VARCHAR(60) NOT NULL,
                `field_label`  VARCHAR(120) NOT NULL DEFAULT '',
                `field_type`   ENUM('rating','text','textarea','select','bool','photo') NOT NULL DEFAULT 'text',
                `field_options` JSON NULL DEFAULT NULL,
                `required`     TINYINT(1) NOT NULL DEFAULT 0,
                `sort`         INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `template_entry` (`template_id`, `entry_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Custom field values
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_VALUES . "` (
                `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entry_id` INT UNSIGNED NOT NULL,
                `field_id` INT UNSIGNED NOT NULL,
                `value`    TEXT NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `entry_field` (`entry_id`, `field_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Entry photos
        $this->installPhotosTable();

        // Votes / likes
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_VOTES . "` (
                `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entry_id`          INT UNSIGNED NOT NULL,
                `user_id`           INT UNSIGNED NULL DEFAULT NULL,
                `guest_fingerprint` VARCHAR(64) NULL DEFAULT NULL,
                `value`             TINYINT NOT NULL DEFAULT 1 COMMENT '+1 or -1',
                `created`           DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `entry_id` (`entry_id`),
                KEY `user_id`  (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Reports / flags
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_REPORTS . "` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entry_id`   INT UNSIGNED NOT NULL,
                `user_id`    INT UNSIGNED NULL DEFAULT NULL,
                `guest_email` VARCHAR(255) NULL DEFAULT NULL,
                `reason`     VARCHAR(255) NOT NULL DEFAULT '',
                `status`     ENUM('open','dismissed') NOT NULL DEFAULT 'open',
                `created`    DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `entry_id` (`entry_id`),
                KEY `status`   (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Stop words
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_STOPWORDS . "` (
                `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `word`    VARCHAR(255) NOT NULL,
                `action`  ENUM('reject','flag') NOT NULL DEFAULT 'reject',
                `scope`   ENUM('global','local') NOT NULL DEFAULT 'global',
                `page_id` INT UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `scope_page` (`scope`, `page_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Points log
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_POINTS . "` (
                `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`  INT UNSIGNED NOT NULL,
                `entry_id` INT UNSIGNED NULL DEFAULT NULL,
                `action`   VARCHAR(60) NOT NULL,
                `points`   INT NOT NULL,
                `created`  DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Ranks
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_RANKS . "` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `label`      VARCHAR(60) NOT NULL,
                `min_points` INT NOT NULL DEFAULT 0,
                `icon`       VARCHAR(60) NOT NULL DEFAULT 'cup',
                `sort`       INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Badges awarded
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_BADGES . "` (
                `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`   INT UNSIGNED NOT NULL,
                `badge_key` VARCHAR(60) NOT NULL,
                `created`   DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_badge` (`user_id`, `badge_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Moderator notes
        $this->installModNotesTable();

        // Insert default ranks
        $this->installDefaultRanks();

        // Seed the default badge definitions
        $this->seedDefaultBadges();

        // Create upload directory
        $uploadPath = $this->wire->config->paths->root . ltrim(self::getDefaults()['photo_path'], '/');
        if (!is_dir($uploadPath)) {
            wireMkdir($uploadPath, true);
        }

        $this->installCompanionModules();

        $this->message('Vox installed successfully.');
    }

    private function installCompanionModules(): void {
        $modules = $this->wire->modules;
        foreach (['ProcessVox', 'VoxApi'] as $class) {
            $modules->refresh();
            if ($modules->isInstalled($class)) continue;
            try {
                $installed = $modules->install($class);
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if (!str_contains($message, "Duplicate entry '{$class}'") && !str_contains($message, "Duplicate entry '$class'")) {
                    throw $e;
                }

                // A failed prior/parallel install can leave the companion class
                // registered in `modules` while ProcessWire retries the insert.
                // Refresh the module cache and continue if PW now sees it as
                // installed; otherwise surface the original installer error.
                $modules->refresh();
                if (!$modules->isInstalled($class)) throw $e;
                continue;
            }

            $modules->refresh();
            if (!$installed && !$modules->isInstalled($class)) {
                throw new \RuntimeException("Unable to install required Vox companion module: {$class}");
            }
        }
    }

    private function installDefaultRanks(): void {
        $db = $this->wire->database;
        $defaults = [
            ['Newcomer',    0,     'seedling',  0],
            ['Enthusiast',  50,    'mug-hot',   1],
            ['Expert',      500,   'medal',     2],
            ['Connoisseur', 2000,  'star',      3],
            ['Master',      5000,  'gem',       4],
        ];
        $stmt = $db->prepare("
            INSERT INTO `" . self::TABLE_RANKS . "` (label, min_points, icon, sort)
            SELECT ?, ?, ?, ?
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM `" . self::TABLE_RANKS . "`
                WHERE label = ? AND min_points = ?
            )
        ");
        foreach ($defaults as $r) {
            $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[0], $r[1]]);
        }
    }

    // ── Uninstall ─────────────────────────────────────────────────────────

    public function ___uninstall(): void {
        $this->uninstallCompanionModules();

        $db = $this->wire->database;
        $tables = [
            self::TABLE_MOD_NOTES,
            self::TABLE_BADGE_DEFS, self::TABLE_BADGES, self::TABLE_RANKS, self::TABLE_POINTS,
            self::TABLE_STOPWORDS, self::TABLE_REPORTS, self::TABLE_VOTES,
            self::TABLE_PHOTOS,
            self::TABLE_VALUES, self::TABLE_FIELDS, self::TABLE_ENTRIES,
        ];
        foreach ($tables as $table) {
            $db->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $this->message('Vox uninstalled and all data removed.');
    }

    public function ___upgrade($fromVersion, $toVersion): void {
        $this->ensurePermissions();
        $this->installPhotosTable();
        $this->installModNotesTable();
        $this->ensureEntriesCompositeIndex();
        $this->seedDefaultBadges();
        $this->migrateDefaultIconsToFa();
        $this->installCompanionModules();

        if (!$this->wire->modules->isInstalled('VoxApi')) return;
        $api = $this->wire->modules->get('VoxApi');
        if ($api instanceof VoxApi) {
            $api->cleanupLegacyProcessPage();
        }
    }

    private function uninstallCompanionModules(): void {
        $modules = $this->wire->modules;
        foreach (['ProcessVox', 'VoxApi'] as $class) {
            if (!$modules->isInstalled($class)) continue;
            $modules->uninstall($class);
        }
    }

    private function installModNotesTable(): void {
        $this->wire->database->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_MOD_NOTES . "` (
                `entry_id` INT UNSIGNED NOT NULL,
                `note`     TEXT NOT NULL,
                `updated`  DATETIME NOT NULL,
                PRIMARY KEY (`entry_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /** Add the composite list-query index to installs created before it existed. */
    private function ensureEntriesCompositeIndex(): void {
        $db = $this->wire->database;
        $stmt = $db->prepare("SHOW INDEX FROM `" . self::TABLE_ENTRIES . "` WHERE Key_name = ?");
        $stmt->execute(['page_status_type_depth']);
        if ($stmt->fetch()) return;
        $db->exec("ALTER TABLE `" . self::TABLE_ENTRIES . "` ADD KEY `page_status_type_depth` (`page_id`, `status`, `type`, `depth`)");
    }

    private function installPhotosTable(): void {
        $this->wire->database->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_PHOTOS . "` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entry_id`      INT UNSIGNED NOT NULL,
                `filename`      VARCHAR(255) NOT NULL,
                `original_name` VARCHAR(255) NOT NULL DEFAULT '',
                `mime`          VARCHAR(120) NOT NULL DEFAULT '',
                `filesize`      INT UNSIGNED NOT NULL DEFAULT 0,
                `sort`          INT NOT NULL DEFAULT 0,
                `created`       DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `entry_id` (`entry_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Config UI ─────────────────────────────────────────────────────────

    public static function getModuleConfigInputfields(array $data) {
        $modules = wire('modules');
        $defaults = self::getDefaults();
        foreach ($defaults as $k => $v) {
            if (!isset($data[$k])) $data[$k] = $v;
        }

        $wrap = new InputfieldWrapper();

        // ── General ──
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('General');
        $fs->icon  = 'gear';

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'moderation');
        $f->label = __('Moderation mode');
        $f->description = __('How new entries are handled before appearing publicly.');
        $f->addOption('immediate', __('Immediate publish'));
        $f->addOption('approval',  __('Require approval'));
        $f->attr('value', $data['moderation']);
        $f->columnWidth = 50;
        $fs->add($f);

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'allow_guests');
        $f->label = __('Allow guest entries');
        $f->description = __('Non-registered users can post reviews, questions and comments.');
        $f->addOption(1, __('Yes'));
        $f->addOption(0, __('No'));
        $f->attr('value', (int) $data['allow_guests']);
        $f->columnWidth = 50;
        $fs->add($f);

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'guest_require_email');
        $f->label = __('Require email from guests');
        $f->description = __('Guests must provide an email address to post.');
        $f->addOption(1, __('Yes'));
        $f->addOption(0, __('No'));
        $f->attr('value', (int) $data['guest_require_email']);
        $f->columnWidth = 50;
        $fs->add($f);

        $f = $modules->get('InputfieldEmail');
        $f->attr('name', 'notify_email');
        $f->label = __('Notification email');
        $f->description = __('Receive alerts for new pending entries and reports. Leave blank to disable.');
        $f->attr('value', $data['notify_email']);
        $f->columnWidth = 50;
        $fs->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'rate_post_interval');
        $f->label = __('Min seconds between posts');
        $f->description = __('Per user / guest. 0 disables the interval limit.');
        $f->attr('value', (int) $data['rate_post_interval']);
        $f->min = 0; $f->max = 3600;
        $f->columnWidth = 50;
        $fs->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'rate_post_hourly');
        $f->label = __('Max posts per hour');
        $f->description = __('Per user / guest. 0 disables the hourly limit.');
        $f->attr('value', (int) $data['rate_post_hourly']);
        $f->min = 0; $f->max = 1000;
        $f->columnWidth = 50;
        $fs->add($f);

        $wrap->add($fs);

        // ── Display ──
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('Display');
        $fs->icon  = 'eye';

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'panel_mode');
        $f->label = __('Block comment panel mode');
        $f->description = __('How inline block discussions appear on the page.');
        $f->addOption('inline',  __('Inline — expands below content block'));
        $f->addOption('sidebar', __('Sidebar — floats beside active block'));
        $f->attr('value', $data['panel_mode']);
        $f->columnWidth = 50;
        $fs->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'preview_count');
        $f->label = __('Initial comments shown');
        $f->description = __('Number of comments pre-loaded before "Show more".');
        $f->attr('value', (int) $data['preview_count']);
        $f->min = 1; $f->max = 20;
        $f->columnWidth = 50;
        $fs->add($f);

        $wrap->add($fs);

        // ── Voting ──
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('Voting');
        $fs->icon  = 'thumbs-up';

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'votes_mode');
        $f->label = __('Vote mode');
        $f->addOption('likes',   __('Likes only (+1)'));
        $f->addOption('helpful', __('Helpful / Not helpful (+1 / -1)'));
        $f->attr('value', $data['votes_mode']);
        $f->columnWidth = 50;
        $fs->add($f);

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'votes_guests');
        $f->label = __('Guest voting');
        $f->addOption('disabled',     __('Disabled'));
        $f->addOption('fingerprint',  __('Allow via browser fingerprint'));
        $f->attr('value', $data['votes_guests']);
        $f->columnWidth = 50;
        $fs->add($f);

        $wrap->add($fs);

        // ── Photos ──
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('Photo uploads');
        $fs->icon  = 'image';

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'photo_uploads');
        $f->label = __('Enable photo uploads');
        $f->description = __('Users can attach images to reviews and questions.');
        $f->addOption(1, __('Yes'));
        $f->addOption(0, __('No'));
        $f->attr('value', (int) $data['photo_uploads']);
        $f->columnWidth = 33;
        $fs->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'photo_max');
        $f->label = __('Max photos per entry');
        $f->attr('value', (int) $data['photo_max']);
        $f->min = 1; $f->max = 20;
        $f->columnWidth = 33;
        $fs->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'photo_max_size');
        $f->label = __('Max file size (MB)');
        $f->attr('value', (int) $data['photo_max_size']);
        $f->min = 1; $f->max = 50;
        $f->columnWidth = 34;
        $fs->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'photo_path');
        $f->label = __('Upload path');
        $f->description = __('Relative to site root. Directory will be created if it doesn\'t exist.');
        $f->attr('value', $data['photo_path']);
        $fs->add($f);

        $wrap->add($fs);

        // ── Gamification ──
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('Gamification — points');
        $fs->icon  = 'trophy';

        foreach ([
            'points_post'          => __('Points for posting a review / thread / question'),
            'points_like_received' => __('Points per like received'),
            'points_answer'        => __('Points for answering a question'),
            'points_best_answer'   => __('Points when answer is marked best'),
        ] as $name => $label) {
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', $name);
            $f->label = $label;
            $f->attr('value', (int) $data[$name]);
            $f->min = 0;
            $f->columnWidth = 25;
            $fs->add($f);
        }

        $wrap->add($fs);

        return $wrap;
    }

    // ── Public API helpers (used by VoxApi and templates) ─────────────────

    /**
     * Get config value with fallback to default.
     */
    public function cfg(string $key): mixed {
        $defaults = self::getDefaults();
        $val = $this->get($key);
        return ($val !== null && $val !== '') ? $val : ($defaults[$key] ?? null);
    }

    /**
     * Get all active stop words (global + page-specific).
     * Returns array of ['word' => string, 'action' => 'reject'|'flag']
     */
    public function getStopwords(int $pageId = 0): array {
        $db = $this->wire->database;
        if ($pageId) {
            $stmt = $db->prepare("
                SELECT word, action FROM `" . self::TABLE_STOPWORDS . "`
                WHERE scope = 'global' OR (scope = 'local' AND page_id = ?)
            ");
            $stmt->execute([$pageId]);
        } else {
            $stmt = $db->query("SELECT word, action FROM `" . self::TABLE_STOPWORDS . "` WHERE scope = 'global'");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check body text against stop words.
     * Returns ['pass' => true] or ['pass' => false, 'action' => 'reject'|'flag', 'word' => '...']
     */
    public function checkStopwords(string $body, int $pageId = 0): array {
        $words = $this->getStopwords($pageId);
        $bodyLow = mb_strtolower($body);
        foreach ($words as $row) {
            if (mb_strpos($bodyLow, mb_strtolower($row['word'])) !== false) {
                return ['pass' => false, 'action' => $row['action'], 'word' => $row['word']];
            }
        }
        return ['pass' => true];
    }

    /**
     * Get the total points for a user (cached per request).
     */
    public function getUserPoints(int $userId): int {
        if (!$userId) return 0;
        if (isset($this->userPointsCache[$userId])) return $this->userPointsCache[$userId];
        $stmt = $this->wire->database->prepare("
            SELECT COALESCE(SUM(points), 0) FROM `" . self::TABLE_POINTS . "`
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $this->userPointsCache[$userId] = (int) $stmt->fetchColumn();
    }

    /**
     * Get total points for many users in one query (primes the cache).
     * Returns [user_id => points] including zero rows.
     */
    public function getUserPointsBatch(array $userIds): array {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $missing = array_values(array_diff($userIds, array_keys($this->userPointsCache)));
        if ($missing) {
            foreach ($missing as $id) $this->userPointsCache[$id] = 0;
            $in = implode(',', array_fill(0, count($missing), '?'));
            $stmt = $this->wire->database->prepare("
                SELECT user_id, COALESCE(SUM(points), 0) AS total
                FROM `" . self::TABLE_POINTS . "`
                WHERE user_id IN ({$in})
                GROUP BY user_id
            ");
            $stmt->execute($missing);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->userPointsCache[(int)$row['user_id']] = (int)$row['total'];
            }
        }
        $result = [];
        foreach ($userIds as $id) $result[$id] = $this->userPointsCache[$id];
        return $result;
    }

    /** Ranks ordered by min_points DESC (cached per request). */
    private function ranksByMinPointsDesc(): array {
        if ($this->ranksDescCache === null) {
            $this->ranksDescCache = $this->wire->database->query("
                SELECT id, label, icon, min_points FROM `" . self::TABLE_RANKS . "`
                ORDER BY min_points DESC
            ")->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $this->ranksDescCache;
    }

    /** Highest rank whose min_points <= $points, or null. */
    private function rankForPoints(int $points): ?array {
        foreach ($this->ranksByMinPointsDesc() as $rank) {
            if ($points >= (int)$rank['min_points']) return $rank;
        }
        return null;
    }

    /**
     * Get the current rank for a user based on total points.
     * Returns array with id, label, icon or null.
     */
    public function getUserRank(int $userId): ?array {
        $points = $this->getUserPoints($userId);
        $rank = $this->rankForPoints($points);
        if ($rank !== null) $rank['points'] = $points;
        return $rank;
    }

    /**
     * Get all badge keys awarded to a user.
     */
    public function getUserBadges(int $userId): array {
        if (!$userId) return [];
        $db = $this->wire->database;
        $stmt = $db->prepare("
            SELECT badge_key FROM `" . self::TABLE_BADGES . "`
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Award points to a user and log the action.
     */
    public function awardPoints(int $userId, string $action, int $points, ?int $entryId = null): void {
        if (!$userId || !$points) return;
        $db = $this->wire->database;
        $stmt = $db->prepare("
            INSERT INTO `" . self::TABLE_POINTS . "` (user_id, entry_id, action, points, created)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $entryId, $action, $points]);
        unset($this->userPointsCache[$userId]);
    }

    /**
     * Net points a user has received for one entry across the given actions.
     * Used by gamification to keep award/revoke pairs idempotent, so repeated
     * moderation toggles or like/unlike cycles cannot farm points.
     */
    public function getPointsNet(int $userId, int $entryId, array $actions): int {
        if (!$userId || !$entryId || !$actions) return 0;
        $in = implode(',', array_fill(0, count($actions), '?'));
        $stmt = $this->wire->database->prepare("
            SELECT COALESCE(SUM(points), 0) FROM `" . self::TABLE_POINTS . "`
            WHERE user_id = ? AND entry_id = ? AND action IN ({$in})
        ");
        $stmt->execute(array_merge([$userId, $entryId], $actions));
        return (int)$stmt->fetchColumn();
    }

    /**
     * Award a badge to a user (idempotent — INSERT IGNORE).
     */
    public function awardBadge(int $userId, string $badgeKey): void {
        if (!$userId) return;
        $db = $this->wire->database;
        $stmt = $db->prepare("
            INSERT IGNORE INTO `" . self::TABLE_BADGES . "` (user_id, badge_key, created)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $badgeKey]);
    }

    // ── Badge definitions (admin-editable) ────────────────────────────────

    /**
     * Metrics a badge can be awarded for. key => human label.
     * Each maps to a stat returned by VoxGamification::getUserStats().
     */
    public function getBadgeMetrics(): array {
        return [
            'reviews'          => 'Reviews posted',
            'answers'          => 'Answers posted',
            'threads'          => 'Threads started',
            'best_answers'     => 'Best answers received',
            'unique_pages'     => 'Pages contributed to',
            'likes_received'   => 'Likes received',
            'long_reviews'     => 'Long reviews (500+ chars)',
            'photos'           => 'Photo entries',
            'points'           => 'Total points',
            'leaderboard_top3' => 'Reached monthly top 3',
        ];
    }

    /** Create the badge-definitions table if missing (lazy migration). */
    private function installBadgeDefsTable(): void {
        $db = $this->wire->database;
        $db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE_BADGE_DEFS . "` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `badge_key`   VARCHAR(60)  NOT NULL,
                `label`       VARCHAR(120) NOT NULL,
                `icon`        VARCHAR(60)  NOT NULL DEFAULT 'star',
                `image`       VARCHAR(255) NOT NULL DEFAULT '',
                `metric`      VARCHAR(32)  NOT NULL DEFAULT 'reviews',
                `threshold`   INT NOT NULL DEFAULT 1,
                `description` VARCHAR(255) NOT NULL DEFAULT '',
                `enabled`     TINYINT(1) NOT NULL DEFAULT 1,
                `sort`        INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `badge_key` (`badge_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // Add the image column to installs created before badge images existed.
        if (!$db->query("SHOW COLUMNS FROM `" . self::TABLE_BADGE_DEFS . "` LIKE 'image'")->fetch()) {
            $db->exec("ALTER TABLE `" . self::TABLE_BADGE_DEFS . "` ADD COLUMN `image` VARCHAR(255) NOT NULL DEFAULT '' AFTER `icon`");
        }
    }

    /** Seed the default badge set (only when the table is empty). FontAwesome icon names. */
    public function seedDefaultBadges(): void {
        $db = $this->wire->database;
        $this->installBadgeDefsTable();
        if ((int)$db->query("SELECT COUNT(*) FROM `" . self::TABLE_BADGE_DEFS . "`")->fetchColumn() > 0) return;

        // key, label, FA icon, metric, threshold
        $defaults = [
            ['first_review',   'First Pour',         'pencil',         'reviews',          1],
            ['answers_5',      'Helpful',            'comment-dots',   'answers',          5],
            ['helpful_expert', 'Helpful Expert',     'user-check',     'answers',          20],
            ['thread_created', 'Conversationalist',  'comments',       'threads',          1],
            ['best_answer_1',  'Best Answer',        'star',           'best_answers',     1],
            ['best_answer_3',  '3× Best Answer',     'bullseye',       'best_answers',     3],
            ['likes_10',       'Crowd Pleaser',      'heart',          'likes_received',   10],
            ['likes_50',       'On Fire',            'fire',           'likes_received',   50],
            ['century',        'Century',            'trophy',         'likes_received',   100],
            ['reviews_10',     'Critic',             'list',           'reviews',          10],
            ['prolific',       'Prolific',           'book',           'reviews',          50],
            ['deep_dive',      'Deep Dive',          'pen-to-square',  'long_reviews',     1],
            ['pages_5',        'Explorer',           'location-dot',   'unique_pages',     5],
            ['world_traveler', 'World Traveler',     'globe',          'unique_pages',     10],
            ['photographer',   'Photographer',       'camera',         'photos',           10],
            ['master',         'Master',             'gem',            'points',           5000],
            ['community_star', 'Community Star',     'chart-line',     'leaderboard_top3', 1],
        ];
        $stmt = $db->prepare("
            INSERT INTO `" . self::TABLE_BADGE_DEFS . "` (badge_key, label, icon, metric, threshold, sort)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($defaults as $i => $d) {
            $stmt->execute([$d[0], $d[1], $d[2], $d[3], $d[4], $i]);
        }
    }

    /**
     * One-time migration: convert the original Bootstrap-Icons names of the
     * default badges and ranks to FontAwesome names. Only rows still holding
     * the old default value are touched, so customised icons are preserved.
     */
    public function migrateDefaultIconsToFa(): void {
        $db = $this->wire->database;
        $this->installBadgeDefsTable();

        // badge_key => [oldBootstrapIcon, faIcon]
        $badges = [
            'first_review'   => ['pencil-fill', 'pencil'],
            'answers_5'      => ['chat-quote-fill', 'comment-dots'],
            'helpful_expert' => ['person-fill-check', 'user-check'],
            'thread_created' => ['chat-dots-fill', 'comments'],
            'best_answer_1'  => ['star-fill', 'star'],
            'likes_10'       => ['heart-fill', 'heart'],
            'century'        => ['trophy-fill', 'trophy'],
            'reviews_10'     => ['card-list', 'list'],
            'prolific'       => ['journals', 'book'],
            'deep_dive'      => ['pencil-square', 'pen-to-square'],
            'pages_5'        => ['geo-alt-fill', 'location-dot'],
            'photographer'   => ['camera-fill', 'camera'],
            'community_star' => ['bar-chart-line-fill', 'chart-line'],
        ];
        $stmt = $db->prepare("UPDATE `" . self::TABLE_BADGE_DEFS . "` SET icon = ? WHERE badge_key = ? AND icon = ?");
        foreach ($badges as $key => [$old, $new]) {
            $stmt->execute([$new, $key, $old]);
        }

        // ranks: label => [oldBootstrapIcon, faIcon]
        $ranks = [
            'Newcomer'    => ['cup', 'seedling'],
            'Enthusiast'  => ['cup-hot', 'mug-hot'],
            'Expert'      => ['trophy-fill', 'medal'],
            'Connoisseur' => ['star', 'star'],
            'Master'      => ['gem', 'gem'],
        ];
        $rstmt = $db->prepare("UPDATE `" . self::TABLE_RANKS . "` SET icon = ? WHERE label = ? AND icon = ?");
        foreach ($ranks as $label => [$old, $new]) {
            if ($old !== $new) $rstmt->execute([$new, $label, $old]);
        }
    }

    /** Run the Bootstrap→FA icon migration exactly once (flagged in module config). */
    private function ensureIconMigration(): void {
        $cfg = (array) $this->wire->modules->getConfig('Vox');
        if (!empty($cfg['icons_fa_migrated'])) return;
        $this->migrateDefaultIconsToFa();
        $cfg['icons_fa_migrated'] = 1;
        $this->wire->modules->saveConfig('Vox', $cfg);
    }

    /** Filesystem path where badge images are stored (created on demand). */
    public function badgesPath(): string {
        $path = $this->wire->config->paths->files . 'vox-badges/';
        if (!is_dir($path)) wireMkdir($path, true);
        return $path;
    }

    /** Public URL for a stored badge image filename (empty if none). */
    public function badgeImageUrl(string $filename): string {
        if ($filename === '') return '';
        return $this->wire->config->urls->files . 'vox-badges/' . rawurlencode($filename);
    }

    /**
     * All badge definitions, ordered. Auto-creates + seeds on first use.
     * @param bool $enabledOnly Only return enabled badges (for awarding).
     */
    public function getBadgeDefs(bool $enabledOnly = false): array {
        $this->seedDefaultBadges();
        $this->ensureIconMigration();
        $where = $enabledOnly ? 'WHERE enabled = 1' : '';
        return $this->wire->database
            ->query("SELECT * FROM `" . self::TABLE_BADGE_DEFS . "` {$where} ORDER BY sort ASC, id ASC")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create or update a badge definition.
     * Returns [ok => bool, error => string].
     */
    public function saveBadgeDef(array $data): array {
        $db  = $this->wire->database;
        $san = $this->wire->sanitizer;
        $this->installBadgeDefsTable();

        $id        = (int)($data['id'] ?? 0);
        $label     = trim($san->text($data['label'] ?? ''));
        $icon      = $san->text($data['icon'] ?? '') ?: 'star';
        $image     = $san->text($data['image'] ?? '');
        $metric    = $san->option($data['metric'] ?? '', array_keys($this->getBadgeMetrics())) ?: 'reviews';
        $threshold = max(0, (int)($data['threshold'] ?? 1));
        $desc      = $san->text($data['description'] ?? '');
        $enabled   = !empty($data['enabled']) ? 1 : 0;

        if ($label === '') return ['ok' => false, 'error' => 'Label is required.'];

        if ($id) {
            // key is immutable on edit (it identifies awarded badges)
            $stmt = $db->prepare("
                UPDATE `" . self::TABLE_BADGE_DEFS . "`
                SET label = ?, icon = ?, image = ?, metric = ?, threshold = ?, description = ?, enabled = ?
                WHERE id = ?
            ");
            $stmt->execute([$label, $icon, $image, $metric, $threshold, $desc, $enabled, $id]);
            return ['ok' => true, 'error' => ''];
        }

        // New: derive a unique key from the provided key or the label
        $key = $san->fieldName($data['badge_key'] ?? '') ?: $san->fieldName(strtolower($label));
        if ($key === '') return ['ok' => false, 'error' => 'Could not derive a badge key.'];
        $exists = $db->prepare("SELECT COUNT(*) FROM `" . self::TABLE_BADGE_DEFS . "` WHERE badge_key = ?");
        $exists->execute([$key]);
        if ((int)$exists->fetchColumn() > 0) return ['ok' => false, 'error' => "Badge key '{$key}' already exists."];

        $sort = (int)$db->query("SELECT COALESCE(MAX(sort), 0) + 1 FROM `" . self::TABLE_BADGE_DEFS . "`")->fetchColumn();
        $stmt = $db->prepare("
            INSERT INTO `" . self::TABLE_BADGE_DEFS . "` (badge_key, label, icon, image, metric, threshold, description, enabled, sort)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$key, $label, $icon, $image, $metric, $threshold, $desc, $enabled, $sort]);
        return ['ok' => true, 'error' => ''];
    }

    /** Delete a badge definition (awarded badges of this key are left intact). */
    public function deleteBadgeDef(int $id): void {
        if (!$id) return;
        $db = $this->wire->database;
        $img = $db->prepare("SELECT image FROM `" . self::TABLE_BADGE_DEFS . "` WHERE id = ?");
        $img->execute([$id]);
        $file = (string) $img->fetchColumn();
        if ($file && is_file($this->badgesPath() . $file)) @unlink($this->badgesPath() . $file);
        $stmt = $db->prepare("DELETE FROM `" . self::TABLE_BADGE_DEFS . "` WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Get field schema for a template + entry type.
     * Returns built-in fields first, then custom fields sorted by `sort`.
     */
    public function getSchema(int $templateId, string $entryType): array {
        $builtIn = $this->getBuiltinFields($entryType);

        $db = $this->wire->database;
        $stmt = $db->prepare("
            SELECT * FROM `" . self::TABLE_FIELDS . "`
            WHERE entry_type = ? AND (template_id = ? OR template_id IS NULL)
            ORDER BY template_id DESC, sort ASC
        ");
        $stmt->execute([$entryType, $templateId]);
        $custom = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Deduplicate: template-specific overrides global
        $seen = [];
        $result = [];
        foreach ($custom as $f) {
            if (!isset($seen[$f['field_name']])) {
                $seen[$f['field_name']] = true;
                if (isset($f['field_options']) && is_string($f['field_options']) && $f['field_options'] !== '') {
                    $decoded = json_decode($f['field_options'], true);
                    $f['field_options'] = is_array($decoded) ? $decoded : [];
                }
                $result[] = $f;
            }
        }

        return array_merge($builtIn, $result);
    }

    /**
     * Built-in fields that are always present and cannot be removed.
     */
    private function getBuiltinFields(string $entryType): array {
        $fields = [];

        if (in_array($entryType, [self::TYPE_REVIEW])) {
            $fields[] = [
                'id'           => 0,
                'field_name'   => 'rating',
                'field_label'  => 'Overall rating',
                'field_type'   => 'rating',
                'required'     => 1,
                'builtin'      => true,
            ];
        }

        $fields[] = [
            'id'           => 0,
            'field_name'   => 'body',
            'field_label'  => 'Text',
            'field_type'   => 'textarea',
            'required'     => 1,
            'builtin'      => true,
        ];

        if (in_array($entryType, [self::TYPE_REVIEW])) {
            $fields[] = [
                'id'           => 0,
                'field_name'   => 'recommend',
                'field_label'  => 'Recommendation',
                'field_type'   => 'bool',
                'required'     => 0,
                'builtin'      => true,
            ];
        }

        return $fields;
    }

    /**
     * Get guest fingerprint from browser headers + IP.
     * Not a real fingerprint but sufficient for basic rate limiting.
     */
    public function getGuestFingerprint(): string {
        $data  = ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                 ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
                 ($_SERVER['REMOTE_ADDR'] ?? '');
        return hash('sha256', $data);
    }

    /**
     * Get sanitized client IP.
     * Uses ProcessWire's session IP (which does not trust client-supplied
     * proxy headers by default) and falls back to REMOTE_ADDR. Headers like
     * X-Forwarded-For are intentionally ignored — they are spoofable unless
     * the server config explicitly handles a trusted proxy.
     */
    public function getClientIp(): string {
        $session = $this->wire->session;
        if ($session) {
            $ip = trim((string)$session->getIP());
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Check whether this identity may create another entry right now.
     * Limits are configurable (rate_post_interval / rate_post_hourly, 0 = off).
     * Logged-in users are matched by user_id; guests by fingerprint or IP.
     */
    public function checkPostRateLimit(?int $userId, string $fingerprint, string $ip): bool {
        $interval = max(0, (int)$this->cfg('rate_post_interval'));
        $hourly   = max(0, (int)$this->cfg('rate_post_hourly'));
        if (!$interval && !$hourly) return true;

        $conds = []; $params = [];
        if ($userId) {
            $conds[] = 'user_id = ?'; $params[] = $userId;
        } else {
            if ($fingerprint) { $conds[] = 'guest_fingerprint = ?'; $params[] = $fingerprint; }
            if ($ip)          { $conds[] = 'ip = ?';                $params[] = $ip; }
        }
        if (!$conds) return true;
        $who = '(' . implode(' OR ', $conds) . ')';

        $db = $this->wire->database;
        if ($interval) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "`
                WHERE {$who} AND created > DATE_SUB(NOW(), INTERVAL {$interval} SECOND)
            ");
            $stmt->execute($params);
            if ((int)$stmt->fetchColumn() > 0) return false;
        }
        if ($hourly) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "`
                WHERE {$who} AND created > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute($params);
            if ((int)$stmt->fetchColumn() >= $hourly) return false;
        }
        return true;
    }

    /**
     * Generate anonymous guest nickname.
     */
    public function generateGuestName(): string {
        return 'Anonymous-' . rand(100, 999);
    }

    /**
     * Hook: check and award badges after saving an entry.
     */
    protected function hookCheckBadges(HookEvent $event): void {
        $userId = (int) ($event->arguments(0)['user_id'] ?? 0);
        if (!$userId) return;

        $gami = new VoxGamification($this);
        $gami->checkAndAward($userId);
    }

    /**
     * Get leaderboard: top N users by total points.
     */
    public function getLeaderboard(string $period = 'month', int $limit = 10): array {
        $db = $this->wire->database;
        $dateFilter = match($period) {
            'week'  => "AND created >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "",
        };
        $sql = "
            SELECT user_id, SUM(points) AS total
            FROM `" . self::TABLE_POINTS . "`
            WHERE 1=1 {$dateFilter}
            GROUP BY user_id
            ORDER BY total DESC
            LIMIT " . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Ranks are based on all-time points — fetch them for all rows at once
        $allTimePoints = $this->getUserPointsBatch(array_column($rows, 'user_id'));

        // Enrich with PW user data
        $users = $this->wire->users;
        $result = [];
        foreach ($rows as $row) {
            $userId = (int)$row['user_id'];
            $user = $users->get($userId);
            if (!$user || !$user->id) continue;
            $points = $allTimePoints[$userId] ?? 0;
            $rank = $this->rankForPoints($points);
            if ($rank !== null) $rank['points'] = $points;
            $result[] = [
                'user_id'  => $userId,
                'name'     => $this->displayUserName($user),
                'points'   => (int) $row['total'],
                'rank'     => $rank,
            ];
        }
        return $result;
    }

    private function displayUserName(User $user): string {
        $display = trim((string)($user->title ?: ''));
        if ($display !== '') return $display;
        return ucwords(str_replace(['-', '_'], ' ', (string)$user->name));
    }

    public function displayText(string $text): string {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ── Frontend data methods (used by templates — NO SQL in templates) ─

    /**
     * Get paginated entries for a page/type/block.
     * Returns ['entries' => [...], 'total' => int]
     * Each entry is enriched with author_name and author_rank.
     */
    public function getEntries(array $opts = []): array {
        $db       = $this->wire->database;
        $et       = self::TABLE_ENTRIES;
        $pageId   = (int)($opts['page_id']   ?? 0);
        $parentId = isset($opts['parent_id']) ? (int)$opts['parent_id'] : null;
        $type     = $opts['type']    ?? '';
        $blockId  = $opts['block_id'] ?? null;
        $status   = $opts['status']  ?? self::STATUS_PUBLISHED;
        // depth: default 0 unless parent_id is set (then fetch any depth), or explicitly null
        $depth    = array_key_exists('depth', $opts) ? (isset($opts['depth']) ? (int)$opts['depth'] : null) : ($parentId !== null ? null : 0);
        $perPage  = min(50, max(1, (int)($opts['per_page'] ?? 10)));
        $page     = max(1, (int)($opts['page'] ?? 1));
        $offset   = ($page - 1) * $perPage;

        $where  = ['e.status = ?'];
        $params = [$status];

        if ($pageId)              { $where[] = 'e.page_id = ?';   $params[] = $pageId; }
        if ($parentId !== null)   { $where[] = 'e.parent_id = ?'; $params[] = $parentId; }
        if ($type)                { $where[] = 'e.type = ?';      $params[] = $type; }
        if ($depth !== null)      { $where[] = 'e.depth = ?';     $params[] = $depth; }
        if ($blockId !== null) {
            if ($blockId === '') {
                $where[] = 'e.block_id IS NULL';
            } else {
                $where[] = 'e.block_id = ?';
                $params[] = $blockId;
            }
        }

        $ws = implode(' AND ', $where);

        $stmtC = $db->prepare("SELECT COUNT(*) FROM `{$et}` e WHERE {$ws}");
        $stmtC->execute($params);
        $total = (int)$stmtC->fetchColumn();

        $stmtE = $db->prepare("
            SELECT e.*, u.name AS user_name
            FROM `{$et}` e
            LEFT JOIN pages u ON u.id = e.user_id
            WHERE {$ws}
            ORDER BY e.created DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtE->execute($params);
        $entries = $stmtE->fetchAll(\PDO::FETCH_ASSOC);

        $this->preloadEntryData(array_column($entries, 'id'));
        $this->getUserPointsBatch(array_column($entries, 'user_id'));
        foreach ($entries as &$entry) {
            $entry = $this->enrichEntry($entry);
        }

        return ['entries' => $entries, 'total' => $total];
    }

    /**
     * Resolve a ProcessWire user for public profile views.
     * Accepts a user id, user name, user_key or User object. Defaults to the
     * current logged-in user when no explicit profile target is provided.
     */
    public function resolveProfileUser(mixed $target = null): ?User {
        if ($target instanceof User && $target->id) return $target;
        if ($target === null || $target === '') {
            $user = $this->wire->user;
            return ($user && $user->isLoggedIn()) ? $user : null;
        }

        if (is_numeric($target)) {
            $user = $this->wire->users->get((int)$target);
            return ($user && $user->id) ? $user : null;
        }

        $value = trim((string)$target);
        $userId = $this->resolvePublicKey('user', $value);
        if ($userId) {
            $user = $this->wire->users->get($userId);
            return ($user && $user->id) ? $user : null;
        }

        $name = $this->wire->sanitizer->pageName($value);
        if ($name !== '') {
            $user = $this->wire->users->get($name);
            return ($user && $user->id) ? $user : null;
        }

        return null;
    }

    /**
     * Complete data set for modular public profile sections.
     */
    public function getUserProfileData(mixed $target = null, int $activityLimit = 10): array {
        $user = $this->resolveProfileUser($target);
        if (!$user) return [];

        $userId = (int)$user->id;
        $gami = new VoxGamification($this);
        $stats = $gami->getUserStats($userId);
        $stmtTotal = $this->wire->database->prepare("
            SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "`
            WHERE user_id = ? AND status = 'published'
        ");
        $stmtTotal->execute([$userId]);
        $stats['total'] = (int)$stmtTotal->fetchColumn();

        $rank = $this->getUserRank($userId);
        $ranks = $this->getRanks();
        $rankProgress = $this->getUserRankProgress($userId);
        $badges = $this->getUserBadgeProgress($userId, $stats);

        return [
            'user' => [
                'id' => $userId,
                'user_key' => $this->publicKey('user', $userId),
                'name' => (string)$user->name,
                'display_name' => $this->displayUserName($user),
                'created' => $user->created ? date('Y-m-d H:i:s', (int)$user->created) : '',
            ],
            'stats' => $stats,
            'rank' => $rank,
            'ranks' => $ranks,
            'rank_progress' => $rankProgress,
            'badges' => $badges,
            'activity' => $this->getUserActivity($userId, $activityLimit),
            'points' => [
                'total' => $this->getUserPoints($userId),
                'breakdown' => $this->getUserPointBreakdown($userId),
            ],
        ];
    }

    /**
     * Rank progression data for profile views.
     */
    public function getUserRankProgress(int $userId): array {
        $points = $this->getUserPoints($userId);
        $ranks = $this->getRanks();
        usort($ranks, fn($a, $b) => (int)$a['min_points'] <=> (int)$b['min_points']);

        $currentIndex = -1;
        foreach ($ranks as $i => $rank) {
            if ($points >= (int)$rank['min_points']) $currentIndex = $i;
        }
        $current = $currentIndex >= 0 ? $ranks[$currentIndex] : null;
        $next = $ranks[$currentIndex + 1] ?? null;
        $base = $current ? (int)$current['min_points'] : 0;
        $target = $next ? (int)$next['min_points'] : max($points, $base);
        $range = max(1, $target - $base);

        return [
            'points' => $points,
            'current' => $current,
            'current_index' => $currentIndex,
            'next' => $next,
            'to_next' => $next ? max(0, $target - $points) : 0,
            'percent' => $next ? min(100, max(0, (int)round((($points - $base) / $range) * 100))) : 100,
        ];
    }

    /**
     * Badge definitions split into earned and locked with simple progress.
     */
    public function getUserBadgeProgress(int $userId, ?array $stats = null): array {
        $stats = $stats ?? (new VoxGamification($this))->getUserStats($userId);
        $earnedKeys = $this->getUserBadges($userId);
        $defs = $this->getBadgeDefs(false);
        $earned = [];
        $locked = [];

        foreach ($defs as $def) {
            $metric = (string)($def['metric'] ?? '');
            $threshold = max(0, (int)($def['threshold'] ?? 0));
            $value = $metric === 'leaderboard_top3'
                ? (!empty($stats['leaderboard_top3']) ? 1 : 0)
                : (int)($stats[$metric] ?? 0);
            $row = $def;
            $row['earned'] = in_array((string)$def['badge_key'], $earnedKeys, true);
            $row['progress_value'] = $value;
            $row['progress_percent'] = $threshold > 0 ? min(100, (int)round(($value / $threshold) * 100)) : 100;
            if ($row['earned']) $earned[] = $row;
            else $locked[] = $row;
        }

        return ['earned' => $earned, 'locked' => $locked, 'all' => array_merge($earned, $locked)];
    }

    /**
     * Recent published entries for one user, enriched for profile activity.
     */
    public function getUserActivity(int $userId, int $limit = 10): array {
        if (!$userId) return [];
        $limit = min(50, max(1, $limit));
        $stmt = $this->wire->database->prepare("
            SELECT e.*, u.name AS user_name, ft.data AS page_title
            FROM `" . self::TABLE_ENTRIES . "` e
            LEFT JOIN pages u ON u.id = e.user_id
            LEFT JOIN field_title ft ON ft.pages_id = e.page_id
            WHERE e.user_id = ? AND e.status = 'published'
            ORDER BY e.created DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->preloadEntryData(array_column($rows, 'id'));
        foreach ($rows as &$row) {
            $row = $this->enrichEntry($row);
            $row['page_title'] = $this->displayText((string)($row['page_title'] ?? ''));
        }
        return $rows;
    }

    /**
     * Points grouped by action for a profile sidebar/breakdown section.
     */
    public function getUserPointBreakdown(int $userId): array {
        if (!$userId) return [];
        $stmt = $this->wire->database->prepare("
            SELECT action, COALESCE(SUM(points), 0) AS points, COUNT(*) AS events
            FROM `" . self::TABLE_POINTS . "`
            WHERE user_id = ?
            GROUP BY action
            ORDER BY points DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get Q&A-style question rows for Answers mode.
     * Filters: newest, active, unanswered, solved, voted.
     */
    public function getAnswerQuestions(int $pageId = 0, string $filter = 'active', int $perPage = 15, int $page = 1): array {
        $db = $this->wire->database;
        $filter = $this->wire->sanitizer->option($filter, ['newest', 'active', 'unanswered', 'solved', 'voted']) ?: 'active';
        $perPage = min(50, max(1, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $where = ["q.status = ?", "q.type = ?", "q.depth = 0"];
        $params = [self::STATUS_PUBLISHED, self::TYPE_QUESTION];
        if ($pageId) {
            $where[] = "q.page_id = ?";
            $params[] = $pageId;
        }

        $replyCountSql = "(SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "` a WHERE a.status = 'published' AND a.type = 'comment' AND (a.parent_id = q.id OR a.root_id = q.id))";
        $bestSql = "(SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "` b WHERE b.status = 'published' AND b.is_best_answer = 1 AND (b.parent_id = q.id OR b.root_id = q.id))";
        $lastActivitySql = "(SELECT MAX(a2.created) FROM `" . self::TABLE_ENTRIES . "` a2 WHERE a2.status = 'published' AND (a2.id = q.id OR a2.parent_id = q.id OR a2.root_id = q.id))";
        $votesSql = "(SELECT " . $this->voteTotalSql() . " FROM `" . self::TABLE_VOTES . "` v WHERE v.entry_id = q.id)";

        if ($filter === 'unanswered') $where[] = "{$replyCountSql} = 0";
        if ($filter === 'solved') $where[] = "{$bestSql} > 0";

        $order = match($filter) {
            'newest' => 'q.created DESC',
            'voted' => 'votes DESC, last_activity DESC, q.created DESC',
            default => 'last_activity DESC, q.created DESC',
        };

        $ws = implode(' AND ', $where);
        $stmtC = $db->prepare("SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "` q WHERE {$ws}");
        $stmtC->execute($params);
        $total = (int)$stmtC->fetchColumn();

        $stmt = $db->prepare("
            SELECT q.*, u.name AS user_name,
                   {$replyCountSql} AS answer_count,
                   {$bestSql} AS best_count,
                   COALESCE({$lastActivitySql}, q.created) AS last_activity,
                   COALESCE({$votesSql}, 0) AS votes
            FROM `" . self::TABLE_ENTRIES . "` q
            LEFT JOIN pages u ON u.id = q.user_id
            WHERE {$ws}
            ORDER BY {$order}
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->preloadEntryData(array_column($rows, 'id'));
        $this->getUserPointsBatch(array_column($rows, 'user_id'));
        foreach ($rows as &$row) {
            $row = $this->enrichEntry($row);
            $row['answer_count'] = (int)($row['answer_count'] ?? 0);
            $row['best_count'] = (int)($row['best_count'] ?? 0);
            $row['votes'] = (int)($row['votes'] ?? 0);
            $row['last_activity'] = (string)($row['last_activity'] ?? $row['created']);
        }

        return ['entries' => $rows, 'total' => $total, 'filter' => $filter];
    }

    /**
     * Summary counts for Answers mode filters.
     */
    public function getAnswerStats(int $pageId = 0): array {
        $where = ["q.status = ?", "q.type = ?", "q.depth = 0"];
        $params = [self::STATUS_PUBLISHED, self::TYPE_QUESTION];
        if ($pageId) {
            $where[] = "q.page_id = ?";
            $params[] = $pageId;
        }
        $ws = implode(' AND ', $where);
        $replyCountSql = "(SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "` a WHERE a.status = 'published' AND a.type = 'comment' AND (a.parent_id = q.id OR a.root_id = q.id))";
        $bestSql = "(SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "` b WHERE b.status = 'published' AND b.is_best_answer = 1 AND (b.parent_id = q.id OR b.root_id = q.id))";
        $stmt = $this->wire->database->prepare("
            SELECT COUNT(*) AS total,
                   SUM({$replyCountSql} = 0) AS unanswered,
                   SUM({$bestSql} > 0) AS solved
            FROM `" . self::TABLE_ENTRIES . "` q
            WHERE {$ws}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int)($row['total'] ?? 0),
            'unanswered' => (int)($row['unanswered'] ?? 0),
            'solved' => (int)($row['solved'] ?? 0),
        ];
    }

    /**
     * Get a single entry by ID, enriched with author data.
     */
    public function getEntry(int $id): ?array {
        $row = $this->repository()->getEntryRow($id);
        return $row ? $this->enrichEntry($row) : null;
    }

    /**
     * Get all custom field values for an entry as [field_name => value].
     * Built-in rating (field_id = 0) is returned under key 'rating'.
     */
    public function getEntryFieldValues(int $entryId): array {
        if (isset($this->entryValuesCache[$entryId])) return $this->entryValuesCache[$entryId];
        $db = $this->wire->database;

        $stmt = $db->prepare("
            SELECT f.field_name, fv.value
            FROM `" . self::TABLE_VALUES . "` fv
            JOIN `" . self::TABLE_FIELDS . "` f ON f.id = fv.field_id
            WHERE fv.entry_id = ?
        ");
        $stmt->execute([$entryId]);
        $vals = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        if (!isset($vals['rating'])) {
            $stmt = $db->prepare("
                SELECT value FROM `" . self::TABLE_VALUES . "`
                WHERE entry_id = ? AND field_id = 0 LIMIT 1
            ");
            $stmt->execute([$entryId]);
            $rating = $stmt->fetchColumn();
            if ($rating !== false) $vals['rating'] = $rating;
        }

        return $this->entryValuesCache[$entryId] = $vals;
    }

    /** SQL aggregate for an entry's vote total, matching the votes_mode config. */
    private function voteTotalSql(): string {
        // likes mode counts positive votes; helpful mode shows the net score
        return $this->cfg('votes_mode') === 'likes'
            ? "COALESCE(SUM(CASE WHEN value > 0 THEN value ELSE 0 END), 0)"
            : "COALESCE(SUM(value), 0)";
    }

    /**
     * Get vote total and whether the current user has upvoted an entry.
     * Total follows votes_mode: positive count for likes, net for helpful.
     * Returns ['total' => int, 'user_liked' => bool]
     */
    public function getEntryLikes(int $entryId): array {
        if (isset($this->entryLikesCache[$entryId])) return $this->entryLikesCache[$entryId];
        $db   = $this->wire->database;
        $user = $this->wire->user;

        $stmt = $db->prepare("
            SELECT " . $this->voteTotalSql() . "
            FROM `" . self::TABLE_VOTES . "`
            WHERE entry_id = ?
        ");
        $stmt->execute([$entryId]);
        $total = (int)$stmt->fetchColumn();

        $userLiked = false;
        if ($user->isLoggedIn()) {
            $stmt = $db->prepare("
                SELECT id FROM `" . self::TABLE_VOTES . "`
                WHERE entry_id = ? AND user_id = ? AND value > 0 LIMIT 1
            ");
            $stmt->execute([$entryId, $user->id]);
            $userLiked = (bool)$stmt->fetchColumn();
        }

        return $this->entryLikesCache[$entryId] = ['total' => $total, 'user_liked' => $userLiked];
    }

    /**
     * Batch-load photos, vote totals and field values for a list of entries
     * into the request-level caches. Turns the per-entry lookups done while
     * rendering a list (photos / likes / custom fields) into 4 queries total.
     */
    public function preloadEntryData(array $entryIds): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $entryIds))));
        if (!$ids) return;
        $db   = $this->wire->database;
        $user = $this->wire->user;

        // Photos
        $toLoad = array_values(array_diff($ids, array_keys($this->entryPhotosCache)));
        if ($toLoad) {
            foreach ($toLoad as $id) $this->entryPhotosCache[$id] = [];
            $inP = implode(',', array_fill(0, count($toLoad), '?'));
            $stmt = $db->prepare("
                SELECT id, entry_id, filename, original_name, mime, filesize, sort, created
                FROM `" . self::TABLE_PHOTOS . "`
                WHERE entry_id IN ({$inP})
                ORDER BY entry_id, sort ASC, id ASC
            ");
            $stmt->execute($toLoad);
            $baseUrl = $this->photoBaseUrl();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $entryId = (int)$row['entry_id'];
                unset($row['entry_id']);
                $this->entryPhotosCache[$entryId][] = $this->photoRowToArray($row, $baseUrl);
            }
        }

        // Vote totals + current user's upvotes
        $toLoad = array_values(array_diff($ids, array_keys($this->entryLikesCache)));
        if ($toLoad) {
            foreach ($toLoad as $id) $this->entryLikesCache[$id] = ['total' => 0, 'user_liked' => false];
            $inL = implode(',', array_fill(0, count($toLoad), '?'));
            $stmt = $db->prepare("
                SELECT entry_id, " . $this->voteTotalSql() . " AS total
                FROM `" . self::TABLE_VOTES . "`
                WHERE entry_id IN ({$inL})
                GROUP BY entry_id
            ");
            $stmt->execute($toLoad);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->entryLikesCache[(int)$row['entry_id']]['total'] = (int)$row['total'];
            }
            if ($user->isLoggedIn()) {
                $stmt = $db->prepare("
                    SELECT DISTINCT entry_id FROM `" . self::TABLE_VOTES . "`
                    WHERE entry_id IN ({$inL}) AND user_id = ? AND value > 0
                ");
                $stmt->execute(array_merge($toLoad, [(int)$user->id]));
                foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $entryId) {
                    $this->entryLikesCache[(int)$entryId]['user_liked'] = true;
                }
            }
        }

        // Field values (custom fields + built-in rating at field_id = 0)
        $toLoad = array_values(array_diff($ids, array_keys($this->entryValuesCache)));
        if ($toLoad) {
            foreach ($toLoad as $id) $this->entryValuesCache[$id] = [];
            $inV = implode(',', array_fill(0, count($toLoad), '?'));
            $stmt = $db->prepare("
                SELECT fv.entry_id, fv.field_id, fv.value, f.field_name
                FROM `" . self::TABLE_VALUES . "` fv
                LEFT JOIN `" . self::TABLE_FIELDS . "` f ON f.id = fv.field_id
                WHERE fv.entry_id IN ({$inV})
            ");
            $stmt->execute($toLoad);
            $builtinRatings = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $entryId = (int)$row['entry_id'];
                if ((int)$row['field_id'] === 0) {
                    $builtinRatings[$entryId] = $row['value'];
                    continue;
                }
                $name = (string)$row['field_name'];
                if ($name === '') continue; // orphaned value of a deleted field
                $this->entryValuesCache[$entryId][$name] = $row['value'];
            }
            // Built-in rating only when no custom field named 'rating' exists
            foreach ($builtinRatings as $entryId => $value) {
                if (!isset($this->entryValuesCache[$entryId]['rating'])) {
                    $this->entryValuesCache[$entryId]['rating'] = $value;
                }
            }
        }
    }

    /**
     * Get child entries (direct replies) for an entry.
     * Returns up to $limit + 1 rows so caller can detect hasMore.
     */
    public function getChildEntries(int $parentId, int $limit): array {
        $db   = $this->wire->database;
        $stmt = $db->prepare("
            SELECT e.*, u.name AS user_name
            FROM `" . self::TABLE_ENTRIES . "` e
            LEFT JOIN pages u ON u.id = e.user_id
            WHERE e.parent_id = ? AND e.status = 'published'
            ORDER BY e.created ASC
            LIMIT " . ($limit + 1)
        );
        $stmt->execute([$parentId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->preloadEntryData(array_column($rows, 'id'));
        $this->getUserPointsBatch(array_column($rows, 'user_id'));
        foreach ($rows as &$row) {
            $row = $this->enrichEntry($row);
        }

        return $rows;
    }

    /**
     * Count published direct and nested replies under one root entry.
     */
    public function getEntryReplyCount(int $entryId): int {
        if (!$entryId) return 0;
        $stmt = $this->wire->database->prepare("
            SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "`
            WHERE status = 'published' AND (parent_id = ? OR root_id = ?)
        ");
        $stmt->execute([$entryId, $entryId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Latest published activity timestamp for a thread root, including replies.
     */
    public function getEntryLastActivity(int $entryId): string {
        if (!$entryId) return '';
        $stmt = $this->wire->database->prepare("
            SELECT MAX(created) FROM `" . self::TABLE_ENTRIES . "`
            WHERE status = 'published' AND (id = ? OR parent_id = ? OR root_id = ?)
        ");
        $stmt->execute([$entryId, $entryId, $entryId]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    /**
     * Get the user_id of an entry's author.
     */
    public function getEntryOwnerId(int $entryId): int {
        $stmt = $this->wire->database->prepare("
            SELECT user_id FROM `" . self::TABLE_ENTRIES . "` WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$entryId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get rating distribution for the reviews histogram.
     * Returns [1 => count, 2 => count, ..., 5 => count]
     */
    public function getRatingDistribution(int $pageId): array {
        $stmt = $this->wire->database->prepare("
            SELECT fv.value AS rating, COUNT(*) AS cnt
            FROM `" . self::TABLE_ENTRIES . "` e
            JOIN `" . self::TABLE_VALUES . "` fv
                ON fv.entry_id = e.id AND fv.field_id = 0
            WHERE e.page_id = ? AND e.type = 'review' AND e.status = 'published'
            GROUP BY fv.value
        ");
        $stmt->execute([$pageId]);

        $dist = array_fill(1, 5, 0);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $r = (int)$row['rating'];
            if ($r >= 1 && $r <= 5) $dist[$r] = (int)$row['cnt'];
        }
        return $dist;
    }

    /**
     * Get average star rating for a page (0.0 if no reviews).
     */
    public function getAverageRating(int $pageId = 0): float {
        if ($pageId) {
            $dist = $this->getRatingDistribution($pageId);
        } else {
            // Global average across all pages
            $stmt = $this->wire->database->query("
                SELECT COALESCE(AVG(CAST(fv.value AS DECIMAL(4,2))), 0)
                FROM `" . self::TABLE_VALUES . "` fv
                JOIN `" . self::TABLE_ENTRIES . "` e ON e.id = fv.entry_id
                WHERE fv.field_id = 0 AND e.status = 'published'
            ");
            return round((float)$stmt->fetchColumn(), 2);
        }
        $total = array_sum($dist);
        if (!$total) return 0.0;
        $sum = array_sum(array_map(fn($r, $c) => $r * $c, array_keys($dist), array_values($dist)));
        return round($sum / $total, 2);
    }

    /**
     * Get recommendation rate for a page.
     * Returns ['rate' => int 0-100, 'total' => int]
     */
    public function getRecommendRate(int $pageId = 0): array {
        $db = $this->wire->database;
        if ($pageId) {
            $stmt = $db->prepare("
                SELECT SUM(recommend = 1) AS yes, COUNT(recommend) AS total
                FROM `" . self::TABLE_ENTRIES . "`
                WHERE page_id = ? AND type = 'review'
                  AND status = 'published' AND recommend IS NOT NULL
            ");
            $stmt->execute([$pageId]);
        } else {
            $stmt = $db->query("
                SELECT SUM(recommend = 1) AS yes, COUNT(recommend) AS total
                FROM `" . self::TABLE_ENTRIES . "`
                WHERE type = 'review' AND status = 'published' AND recommend IS NOT NULL
            ");
        }
        $row   = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($row['total'] ?? 0);
        $rate  = $total ? (int)round(100 * $row['yes'] / $total) : 0;
        return ['rate' => $rate, 'total' => $total];
    }

    /**
     * Get block comment counts for multiple block IDs in one query.
     * Returns [block_id => count, ...]
     */
    public function getBlockCounts(int $pageId, array $blockIds): array {
        if (!$blockIds) return [];
        // Sanitize: keep only non-empty strings
        $san      = $this->wire->sanitizer;
        $blockIds = array_values(array_filter(
            array_map(fn($id) => $san->text((string)$id), $blockIds)
        ));
        if (!$blockIds) return [];

        $db   = $this->wire->database;
        $in   = implode(',', array_fill(0, count($blockIds), '?'));
        $stmt = $db->prepare("
            SELECT block_id, COUNT(*) AS cnt
            FROM `" . self::TABLE_ENTRIES . "`
            WHERE page_id = ? AND status = 'published' AND block_id IN ({$in})
            GROUP BY block_id
        ");
        $stmt->execute(array_merge([$pageId], $blockIds));

        $result = array_fill_keys($blockIds, 0);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[$row['block_id']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * Enrich a raw DB row with author_name and author_rank.
     */
    public function enrichEntry(array $entry, bool $forPublic = false): array {
        $userId = (int)($entry['user_id'] ?? 0);
        if ($userId) {
            $pwUser = $this->wire->users->get($userId);
            $pwUserName = ($pwUser && $pwUser->id) ? $this->displayUserName($pwUser) : ($entry['user_name'] ?? '');
            $entry['author_name'] = $pwUserName ?: "User #{$userId}";
            $rank = $this->getUserRank($userId);
            $entry['author_rank'] = $rank['label'] ?? '';
        } else {
            $entry['author_name'] = $entry['guest_name'] ?: 'Anonymous';
            $entry['author_rank'] = 'Guest';
        }
        // Cast integer fields
        foreach (['id','page_id','parent_id','root_id','depth','template_id',
                  'is_best_answer','is_owner_reply'] as $k) {
            if (array_key_exists($k, $entry)) $entry[$k] = (int)$entry[$k];
        }
        unset($entry['user_name']);
        $entry['photos'] = $this->getEntryPhotos((int)($entry['id'] ?? 0));
        // Strip sensitive fields when serving public API responses
        if ($forPublic) {
            unset($entry['guest_email'], $entry['guest_fingerprint'], $entry['ip']);
        }
        return $entry;
    }

    /**
     * Enrich entry for public API output (strips sensitive guest fields).
     */
    public function enrichEntryPublic(array $entry): array {
        $entry = $this->enrichEntry($entry, true);

        $entryId = (int)($entry['id'] ?? 0);
        $pageId = (int)($entry['page_id'] ?? 0);
        $parentId = (int)($entry['parent_id'] ?? 0);
        $rootId = (int)($entry['root_id'] ?? 0);

        $entryKey = $this->publicKey('entry', $entryId);
        $entry['id'] = $entryKey;
        $entry['entry_key'] = $entryKey;
        $entry['page_key'] = $this->publicKey('page', $pageId);
        $entry['parent_key'] = $parentId ? $this->publicKey('entry', $parentId) : '';
        $entry['root_key'] = $rootId ? $this->publicKey('entry', $rootId) : '';

        if (!empty($entry['user_id'])) {
            $entry['user_key'] = $this->publicKey('user', (int)$entry['user_id']);
        }

        unset(
            $entry['page_id'],
            $entry['parent_id'],
            $entry['root_id'],
            $entry['template_id'],
            $entry['user_id']
        );

        return $entry;
    }

    /**
     * Get dashboard stats for Admin panel.
     * Returns counts, activity, breakdown, avg rating, rec rate.
     */
    public function getAdminDashboardStats(): array {
        $db = $this->wire->database;
        $et = self::TABLE_ENTRIES;
        $rt = self::TABLE_REPORTS;

        $total = (int)$db->query("SELECT COUNT(*) FROM `{$et}`")->fetchColumn();

        $stmt = $db->query("
            SELECT
                SUM(status = 'pending')                    AS pending,
                SUM(status = 'pending' AND type = 'review') AS pending_reviews,
                SUM(status = 'pending' AND type = 'comment') AS pending_comments
            FROM `{$et}`
        ");
        $p = $stmt->fetch(\PDO::FETCH_ASSOC);

        $reports = (int)$db->query("SELECT COUNT(*) FROM `{$rt}` WHERE status = 'open'")->fetchColumn();
        $users   = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM `{$et}` WHERE user_id IS NOT NULL")->fetchColumn();

        // Activity last 30 days
        $stmt = $db->query("
            SELECT DATE(created) AS day, COUNT(*) AS cnt
            FROM `{$et}`
            WHERE created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created) ORDER BY day ASC
        ");
        $activity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Breakdown by type (published only)
        $stmt = $db->query("SELECT type, COUNT(*) AS cnt FROM `{$et}` WHERE status = 'published' GROUP BY type");
        $breakdown = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'total'            => $total,
            'pending'          => (int)$p['pending'],
            'pending_reviews'  => (int)$p['pending_reviews'],
            'pending_comments' => (int)$p['pending_comments'],
            'reports'          => $reports,
            'users'            => $users,
            'activity'         => $activity,
            'breakdown'        => $breakdown,
            'avg_rating'       => $this->getAverageRating(0),
            'rec_rate'         => $this->getRecommendRate(0)['rate'],
        ];
    }

    /**
     * Get recent entries for Admin dashboard (enriched, with page_name).
     */
    public function getRecentEntries(int $limit = 10, string $status = ''): array {
        $db     = $this->wire->database;
        $et     = self::TABLE_ENTRIES;
        $order  = $status === self::STATUS_PENDING ? 'ASC' : 'DESC';
        $validStatuses = [self::STATUS_PUBLISHED, self::STATUS_PENDING, self::STATUS_SPAM];
        if ($status && in_array($status, $validStatuses)) {
            $stmt = $db->prepare("
                SELECT e.*, u.name AS user_name, ft.data AS page_name
                FROM `{$et}` e
                LEFT JOIN pages u ON u.id = e.user_id
                LEFT JOIN field_title ft ON ft.pages_id = e.page_id
                WHERE e.status = ?
                ORDER BY e.created {$order}
                LIMIT " . (int)$limit
            );
            $stmt->execute([$status]);
        } else {
            $stmt = $db->query("
                SELECT e.*, u.name AS user_name, ft.data AS page_name
                FROM `{$et}` e
                LEFT JOIN pages u ON u.id = e.user_id
                LEFT JOIN field_title ft ON ft.pages_id = e.page_id
                ORDER BY e.created {$order}
                LIMIT " . (int)$limit
            );
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->preloadEntryData(array_column($rows, 'id'));
        $this->getUserPointsBatch(array_column($rows, 'user_id'));
        foreach ($rows as &$r) {
            $r = $this->enrichEntry($r);
            // Add stopword flag for admin display
            $r['has_stopword'] = $this->entryHasStopword($r);
        }
        return $rows;
    }

    /**
     * Get top pages by entry activity for Admin dashboard.
     */
    public function getTopPagesByActivity(int $limit = 6): array {
        $stmt = $this->wire->database->query("
            SELECT page_id, COUNT(*) AS cnt
            FROM `" . self::TABLE_ENTRIES . "`
            WHERE status = 'published'
            GROUP BY page_id ORDER BY cnt DESC
            LIMIT " . (int)$limit
        );
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $page = $this->wire->pages->get((int)$row['page_id']);
            $result[] = [
                'page_id' => (int)$row['page_id'],
                'title'   => ($page && $page->id) ? $page->title : "Page #{$row['page_id']}",
                'url'     => ($page && $page->id) ? $page->url   : '',
                'cnt'     => (int)$row['cnt'],
            ];
        }
        return $result;
    }

    /**
     * Get filtered entries for Admin entries list.
     */
    public function getAdminEntries(array $opts = []): array {
        $db      = $this->wire->database;
        $san     = $this->wire->sanitizer;
        $et      = self::TABLE_ENTRIES;
        $perPage = max(1, min(100, (int)($opts['per_page'] ?? 25)));
        $page    = max(1, (int)($opts['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if ($q = $san->text($opts['q'] ?? '')) {
            $where[] = "(e.body LIKE ? OR e.guest_name LIKE ?)";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($type = $san->option($opts['type'] ?? '', ['review','question','thread','comment',''])) {
            $where[] = "e.type = ?"; $params[] = $type;
        }
        if ($status = $san->option($opts['status'] ?? '', ['published','pending','spam',''])) {
            $where[] = "e.status = ?"; $params[] = $status;
        }
        if ($pageId = (int)($opts['page_id'] ?? 0)) {
            $where[] = "e.page_id = ?"; $params[] = $pageId;
        }
        if ($period = $san->option($opts['period'] ?? '', ['today','week','month',''])) {
            $intervals = ['today'=>'1 DAY','week'=>'7 DAY','month'=>'30 DAY'];
            $where[] = "e.created >= DATE_SUB(NOW(), INTERVAL " . $intervals[$period] . ")";
        }

        $ws = implode(' AND ', $where);

        $stmtC = $db->prepare("SELECT COUNT(*) FROM `{$et}` e WHERE {$ws}");
        $stmtC->execute($params);
        $total = (int)$stmtC->fetchColumn();

        $stmtE = $db->prepare("
            SELECT e.*, u.name AS user_name, ft.data AS page_name
            FROM `{$et}` e
            LEFT JOIN pages u ON u.id = e.user_id
            LEFT JOIN field_title ft ON ft.pages_id = e.page_id
            WHERE {$ws}
            ORDER BY e.created DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtE->execute($params);
        $entries = $stmtE->fetchAll(\PDO::FETCH_ASSOC);

        $this->preloadEntryData(array_column($entries, 'id'));
        $this->getUserPointsBatch(array_column($entries, 'user_id'));
        foreach ($entries as &$entry) {
            $entry = $this->enrichEntry($entry);
            $entry['has_stopword'] = $this->entryHasStopword($entry);
        }

        return ['entries' => $entries, 'total' => $total];
    }

    /**
     * Get pending count (for nav badge).
     */
    public function getPendingCount(): int {
        return $this->repository()->getPendingCount();
    }

    /**
     * Get open reports count.
     */
    public function getOpenReportsCount(): int {
        return $this->repository()->getOpenReportsCount();
    }

    /**
     * Get open reports count for one entry.
     */
    public function getEntryReportsCount(int $entryId): int {
        return $this->repository()->getEntryReportsCount($entryId);
    }

    /**
     * Get pending entries with optional filters (for moderation queue).
     */
    public function getPendingEntries(array $opts = []): array {
        $opts['status'] = self::STATUS_PENDING;
        return $this->getAdminEntries($opts);
    }

    /**
     * Get open reports enriched with entry + reporter data.
     */
    public function getOpenReports(): array {
        return $this->repository()->getOpenReports();
    }

    /**
     * Get one report row.
     */
    public function getReport(int $reportId): ?array {
        return $this->repository()->getReport($reportId);
    }

    /**
     * Mark a report as handled without removing the entry.
     */
    public function dismissReport(int $reportId): bool {
        return $this->repository()->dismissReport($reportId);
    }

    /**
     * Delete the reported entry. The report itself is removed by deleteEntry().
     */
    public function deleteReportedEntry(int $reportId): bool {
        $report = $this->getReport($reportId);
        if (!$report) return false;
        return $this->deleteEntry((int)$report['entry_id']);
    }

    /**
     * Update entry status (approve/reject/spam).
     */
    public function setEntryStatus(int $entryId, string $status): bool {
        return $this->repository()->setEntryStatus($entryId, $status);
    }

    /**
     * Bulk-update status for multiple entries.
     */
    public function setEntriesStatus(array $ids, string $status): bool {
        return $this->repository()->setEntriesStatus($ids, $status);
    }

    /**
     * Delete one entry with its nested replies and dependent rows.
     */
    public function deleteEntry(int $entryId): bool {
        return $this->repository()->deleteEntry($entryId);
    }

    /**
     * Delete multiple entries with their descendants.
     */
    public function deleteEntries(array $ids): int {
        return $this->repository()->deleteEntries($ids);
    }

    /**
     * Get all ranks ordered by sort.
     */
    public function getRanks(): array {
        return $this->wire->database
            ->query("SELECT * FROM `" . self::TABLE_RANKS . "` ORDER BY sort ASC")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count users in each rank bracket, keyed by rank id.
     * A user belongs to the highest rank whose min_points <= their total
     * (all-time) points. Returns [rank_id => count].
     */
    public function getRankUserCounts(): array {
        $ranks = $this->getRanks();
        if (!$ranks) return [];

        // Rank thresholds, highest first, so the first match wins.
        $thresholds = [];
        foreach ($ranks as $r) {
            $thresholds[] = ['id' => (int)$r['id'], 'min' => (int)$r['min_points']];
        }
        usort($thresholds, fn($a, $b) => $b['min'] <=> $a['min']);

        $rows = $this->wire->database->query("
            SELECT user_id, COALESCE(SUM(points), 0) AS total
            FROM `" . self::TABLE_POINTS . "`
            WHERE user_id IS NOT NULL
            GROUP BY user_id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($thresholds as $t) $counts[$t['id']] = 0;
        foreach ($rows as $row) {
            $total = (int) $row['total'];
            foreach ($thresholds as $t) {
                if ($total >= $t['min']) { $counts[$t['id']]++; break; }
            }
        }
        return $counts;
    }

    /**
     * Save ranks from admin form (full replace).
     */
    public function saveRanks(array $ranks): void {
        $db  = $this->wire->database;
        $san = $this->wire->sanitizer;
        $rt  = self::TABLE_RANKS;
        $this->ranksDescCache = null;
        $db->exec("DELETE FROM `{$rt}` WHERE id > 0");
        $stmt = $db->prepare("INSERT INTO `{$rt}` (id, label, min_points, icon, sort) VALUES (?, ?, ?, ?, ?)");
        foreach ($ranks as $i => $r) {
            $stmt->execute([
                (int)($r['id'] ?? 0) ?: null,
                $san->text($r['label'] ?? 'Rank'),
                max(0, (int)($r['min_points'] ?? 0)),
                $san->text($r['icon'] ?? 'cup'),
                $i,
            ]);
        }
    }

    /**
     * Get all stop words with optional scope filter.
     */
    public function getAllStopwords(string $scope = ''): array {
        $db = $this->wire->database;
        if ($scope) {
            $stmt = $db->prepare("SELECT * FROM `" . self::TABLE_STOPWORDS . "` WHERE scope = ? ORDER BY word ASC");
            $stmt->execute([$scope]);
        } else {
            $stmt = $db->query("SELECT * FROM `" . self::TABLE_STOPWORDS . "` ORDER BY scope, word ASC");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get stop words grouped by page_id for per-page display.
     * Returns [page_id => ['title' => string, 'words' => [...]]]
     */
    public function getLocalStopwords(): array {
        $stmt = $this->wire->database->query("
            SELECT sw.*, ft.data AS page_title
            FROM `" . self::TABLE_STOPWORDS . "` sw
            LEFT JOIN field_title ft ON ft.pages_id = sw.page_id
            WHERE sw.scope = 'local'
            ORDER BY sw.page_id, sw.word
        ");
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $pid = (int)$row['page_id'];
            $result[$pid]['title']  = $row['page_title'] ?? "Page #{$pid}";
            $result[$pid]['words'][] = $row;
        }
        return $result;
    }

    /**
     * Add a stop word.
     */
    public function addStopword(string $word, string $action = 'reject', int $pageId = 0): bool {
        $san    = $this->wire->sanitizer;
        $word   = mb_strtolower($san->text($word));
        $action = in_array($action, ['reject','flag']) ? $action : 'reject';
        $scope  = $pageId ? 'local' : 'global';
        if (!$word) return false;
        $this->wire->database
             ->prepare("INSERT IGNORE INTO `" . self::TABLE_STOPWORDS . "` (word, action, scope, page_id) VALUES (?, ?, ?, ?)")
             ->execute([$word, $action, $scope, $pageId ?: null]);
        return true;
    }

    /**
     * Delete a stop word by ID.
     */
    public function deleteStopword(int $id): void {
        $this->wire->database
             ->prepare("DELETE FROM `" . self::TABLE_STOPWORDS . "` WHERE id = ?")
             ->execute([$id]);
    }

    /**
     * Bulk-import stop words.
     */
    public function importStopwords(array $words, string $action = 'reject'): int {
        $san    = $this->wire->sanitizer;
        $action = in_array($action, ['reject','flag']) ? $action : 'reject';
        $stmt   = $this->wire->database->prepare(
            "INSERT IGNORE INTO `" . self::TABLE_STOPWORDS . "` (word, action, scope, page_id) VALUES (?, ?, 'global', NULL)"
        );
        $count = 0;
        foreach ($words as $w) {
            $w = mb_strtolower($san->text(trim($w)));
            if ($w) { $stmt->execute([$w, $action]); $count++; }
        }
        return $count;
    }

    /**
     * Delete all content data (truncate entry/vote/value/report/points/badge
     * tables) and remove uploaded entry photos from disk.
     */
    public function deleteAllData(): void {
        // Remove photo files before their DB rows disappear
        $stmt = $this->wire->database->query("SELECT filename FROM `" . self::TABLE_PHOTOS . "`");
        $dir = $this->getPhotoUploadPath();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $filename) {
            $path = $dir . basename((string)$filename);
            if (is_file($path)) @unlink($path);
        }

        $tables = [
            self::TABLE_MOD_NOTES,
            self::TABLE_BADGES, self::TABLE_POINTS, self::TABLE_REPORTS,
            self::TABLE_VOTES, self::TABLE_PHOTOS, self::TABLE_VALUES, self::TABLE_ENTRIES,
        ];
        foreach ($tables as $t) {
            $this->wire->database->exec("TRUNCATE TABLE `{$t}`");
        }
        $this->entryPhotosCache = $this->entryLikesCache = $this->entryValuesCache = [];
        $this->userPointsCache = [];
    }

    /**
     * Status summary for the optional local demo site.
     */
    public function getDemoStatus(): array {
        $root = $this->wire->pages->get('path=/vox-demo/, include=all');
        $template = $this->wire->templates->get('vox-demo');
        $entries = 0;

        if ($root && $root->id) {
            $ids = $this->demoPageIds($root);
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->wire->database->prepare(
                    "SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "` WHERE page_id IN ($placeholders)"
                );
                $stmt->execute($ids);
                $entries = (int)$stmt->fetchColumn();
            }
        }

        return [
            'installed' => (bool)($root && $root->id && $template && $template->id),
            'url'       => ($root && $root->id) ? $root->url : '',
            'page_id'   => ($root && $root->id) ? (int)$root->id : 0,
            'template'  => ($template && $template->id) ? $template->name : '',
            'entries'   => $entries,
        ];
    }

    /**
     * Install a self-contained demo site that does not rely on _main.php.
     */
    public function installDemoData(): array {
        $this->removeDemoData(false);
        $this->ensureDemoAssets();
        $this->writeDemoTemplateFile();

        $template = $this->ensureDemoTemplate();
        $pages = $this->ensureDemoPages($template);
        $this->seedDemoSchema($template);
        $demoUsers = $this->ensureDemoUsers();
        $this->seedDemoEntries($pages);
        $this->seedDemoUserEntries($pages, $demoUsers);
        $this->seedDemoStopwords((int)$pages['restaurant']->id);

        return $this->getDemoStatus();
    }

    /**
     * Remove demo pages, demo entries and demo schema.
     */
    public function removeDemoData(bool $removeTemplateFile = true): void {
        $root = $this->wire->pages->get('path=/vox-demo/, include=all');
        if ($root && $root->id) {
            $demoIds = $this->demoPageIds($root);
            foreach ($demoIds as $pageId) {
                $this->deleteEntriesForPage($pageId);
            }
            // Local stop words attached to demo pages would be orphaned
            if ($demoIds) {
                $in = implode(',', array_fill(0, count($demoIds), '?'));
                $this->wire->database
                    ->prepare("DELETE FROM `" . self::TABLE_STOPWORDS . "` WHERE scope = 'local' AND page_id IN ({$in})")
                    ->execute($demoIds);
            }
            $this->wire->pages->delete($root, true);
            $this->wire->pages->uncacheAll();
        }

        $template = $this->wire->templates->get('vox-demo');
        if ($template && $template->id) {
            $this->deleteSchema((int)$template->id, self::TYPE_REVIEW);
            $this->deleteSchema((int)$template->id, self::TYPE_QUESTION);
            $this->deleteSchema((int)$template->id, self::TYPE_THREAD);
            $this->deleteSchema((int)$template->id, self::TYPE_COMMENT);

            try {
                $fg = $template->fieldgroup;
                $this->wire->templates->delete($template);
                if ($fg && $fg->id && $fg->name === 'vox-demo') {
                    $this->wire->fieldgroups->delete($fg);
                }
            } catch (\Throwable $e) {}
        }

        // Remove the seeded global demo words — exact word + action + scope
        // match, so identical user-added local words are left alone.
        $stmt = $this->wire->database->prepare(
            "DELETE FROM `" . self::TABLE_STOPWORDS . "` WHERE word = ? AND action = ? AND scope = 'global'"
        );
        foreach ([['scam', 'reject'], ['counterfeit', 'flag']] as $w) {
            $stmt->execute($w);
        }

        $this->removeDemoUsers();

        if ($removeTemplateFile) {
            $file = $this->wire->config->paths->templates . 'vox-demo.php';
            if (is_file($file)) @unlink($file);
            $assetDir = $this->wire->config->paths->assets . 'vox-demo/';
            if (is_dir($assetDir)) {
                foreach (glob($assetDir . '*') ?: [] as $assetFile) {
                    if (is_file($assetFile)) @unlink($assetFile);
                }
                @rmdir($assetDir);
            }
        }
    }

    private function demoAssetSources(): array {
        return [
            'restaurant.jpg' => 'https://jrobuchon.com/wp-content/uploads/2023/12/The_Woodward_view_1324-min-1024x576.jpg',
            'hotel.webp'     => 'https://www.villacastagnola.com/static/e1f67679fa10bf586b71930f41af6af4/placeholder-video-home-desktop.webp',
            'product.webp'   => 'https://www.lindt-home-of-chocolate.com/wp-content/uploads/2020/04/lind-home-of-chocolate-facebook.jpg',
        ];
    }

    private function ensureDemoAssets(): void {
        $assetDir = $this->wire->config->paths->assets . 'vox-demo/';
        if (!is_dir($assetDir)) {
            @mkdir($assetDir, 0775, true);
        }
        if (!is_dir($assetDir) || !is_writable($assetDir)) {
            return;
        }

        foreach ($this->demoAssetSources() as $fileName => $url) {
            $target = $assetDir . $fileName;
            if (is_file($target) && filesize($target) > 2048) continue;

            $context = stream_context_create([
                'http' => [
                    'timeout' => 12,
                    'header'  => "User-Agent: Mozilla/5.0 Vox Demo Installer\r\nAccept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8\r\n",
                ],
            ]);
            $data = @file_get_contents($url, false, $context);
            if (is_string($data) && strlen($data) > 2048) {
                file_put_contents($target, $data);
            }
        }
    }

    private function demoPageIds(Page $root): array {
        $ids = [(int)$root->id];
        foreach ($root->children('include=all') as $child) {
            $ids[] = (int)$child->id;
        }
        return array_values(array_filter($ids));
    }

    private function deleteEntriesForPage(int $pageId): void {
        $stmt = $this->wire->database->prepare(
            "SELECT id FROM `" . self::TABLE_ENTRIES . "` WHERE page_id = ? AND depth = 0 ORDER BY id DESC"
        );
        $stmt->execute([$pageId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $entryId) {
            $this->deleteEntry((int)$entryId);
        }
    }

    private function ensureDemoTemplate(): Template {
        $templates = $this->wire->templates;
        $fields    = $this->wire->fields;

        $template = $templates->get('vox-demo');
        if ($template && $template->id) {
            $template->noPrependTemplateFile = 1;
            $template->noAppendTemplateFile = 1;
            $template->save();
            return $template;
        }

        $fg = new Fieldgroup();
        $fg->name = 'vox-demo';
        $fg->add($fields->get('title'));
        $fg->save();

        $template = new Template();
        $template->name = 'vox-demo';
        $template->fieldgroup = $fg;
        $template->noPrependTemplateFile = 1;
        $template->noAppendTemplateFile = 1;
        $template->slashUrls = 1;
        $template->save();
        return $template;
    }

    private function ensureDemoPages(Template $template): array {
        $pages = $this->wire->pages;
        $pages->uncacheAll();
        $root = $pages->get('path=/vox-demo/, include=all');
        if (!$root || !$root->id) {
            $root = new Page();
            $root->template = $template;
            $root->parent = $pages->get('/');
            $root->name = 'vox-demo';
            $root->title = 'Vox Demo';
            $root->save();
        }

        $children = [
            'restaurant' => ['name' => 'latelier-robuchon-geneva', 'title' => "L'Atelier Robuchon Geneva"],
            'hotel'      => ['name' => 'villa-castagnola-lugano', 'title' => 'Grand Hotel Villa Castagnola Lugano'],
            'product'    => ['name' => 'lindt-home-of-chocolate-zurich', 'title' => 'Lindt Home of Chocolate Zurich'],
        ];

        $result = ['root' => $root];
        foreach ($children as $key => $data) {
            $page = $pages->get('path=' . $root->path . $data['name'] . '/, include=all');
            if (!$page || !$page->id) {
                $page = new Page();
                $page->template = $template;
                $page->parent = $root;
                $page->name = $data['name'];
                $page->title = $data['title'];
                $page->save();
            } elseif ((string)$page->title !== (string)$data['title']) {
                $page->title = $data['title'];
                $page->save();
            }
            $result[$key] = $page;
        }

        return $result;
    }

    private function ensureDemoUsers(): array {
        $users = $this->wire->users;
        $result = [];
        $defs = [
            'sofia' => ['name' => 'sofia-keller', 'title' => 'Sofia Keller'],
            'marc'  => ['name' => 'marc-bianchi', 'title' => 'Marc Bianchi'],
            'elena' => ['name' => 'elena-meier', 'title' => 'Elena Meier'],
        ];
        foreach ($defs as $key => $def) {
            $user = $users->get($def['name']);
            if (!$user || !$user->id) {
                $user = $users->add($def['name']);
                $user->pass = bin2hex(random_bytes(12));
                $user->email = $def['name'] . '@example.test';
            }
            $user->title = $def['title'];
            $user->save();
            $result[$key] = $user;
        }
        return $result;
    }

    private function removeDemoUsers(): void {
        foreach (['sofia-keller', 'marc-bianchi', 'elena-meier', 'vox_demo_sofia', 'vox_demo_marc', 'vox_demo_elena'] as $name) {
            $user = $this->wire->users->get($name);
            if ($user && $user->id) {
                $this->wire->database
                    ->prepare("DELETE FROM `" . self::TABLE_BADGES . "` WHERE user_id = ?")
                    ->execute([(int)$user->id]);
                $this->wire->database
                    ->prepare("DELETE FROM `" . self::TABLE_POINTS . "` WHERE user_id = ?")
                    ->execute([(int)$user->id]);
                try { $this->wire->users->delete($user); } catch (\Throwable $e) {}
            }
        }
    }

    private function seedDemoSchema(Template $template): void {
        $this->saveSchemaFields((int)$template->id, self::TYPE_REVIEW, [
            ['field_name' => 'service', 'field_label' => 'Service', 'field_type' => 'rating', 'field_options' => '', 'required' => 0],
            ['field_name' => 'atmosphere', 'field_label' => 'Atmosphere', 'field_type' => 'rating', 'field_options' => '', 'required' => 0],
            ['field_name' => 'taste_profile', 'field_label' => 'Taste profile', 'field_type' => 'rating', 'field_options' => 'style=dot', 'required' => 0],
            ['field_name' => 'visit_type', 'field_label' => 'Visit type', 'field_type' => 'select', 'field_options' => 'Dinner,Weekend stay,Family visit,Gift shopping', 'required' => 0],
        ]);
    }

    private function seedDemoEntries(array $pages): void {
        $restaurant = $pages['restaurant'];
        $hotel      = $pages['hotel'];
        $product    = $pages['product'];
        $templateId = (int)$restaurant->template->id;

        $r1 = $this->demoEntry($restaurant, self::TYPE_REVIEW, 'Sofia Demo', 'Dinner service felt polished and calm. The counter format makes the kitchen part of the evening, so it is a strong example for review cards and follow-up replies.', 1, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(28, 18, 12));
        $this->saveEntryRating($r1, 5);
        $this->demoSchemaValues($templateId, $r1, ['service' => '5', 'atmosphere' => '5', 'taste_profile' => '4', 'visit_type' => 'Dinner']);
        $this->toggleVote($r1, 1, null, 'demo-like-a');
        $this->toggleVote($r1, 1, null, 'demo-like-b');

        $r2 = $this->demoEntry($restaurant, self::TYPE_REVIEW, 'Marc Tester', 'Great room for a special dinner. I would add clearer notes about dietary preferences before booking, which makes this useful for moderation and Q&A tests.', 1, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(21, 12, 34));
        $this->saveEntryRating($r2, 4);
        $this->demoSchemaValues($templateId, $r2, ['service' => '4', 'atmosphere' => '5', 'taste_profile' => '5', 'visit_type' => 'Dinner']);

        $q1 = $this->demoEntry($restaurant, self::TYPE_QUESTION, 'Elena QA', 'Can the restaurant handle a vegetarian tasting menu?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(16, 9, 8));
        $a1 = $this->demoReply($restaurant, $q1, 'Demo Manager', 'Yes. Add the preference while booking and confirm it again before the visit. This answer is marked as best in the demo.', $this->demoCreatedAt(15, 10, 27));
        $this->markBestAnswer($a1, 1, true);

        $thread = $this->demoEntry($restaurant, self::TYPE_THREAD, 'Nina Moderator', 'What should be collected before publishing restaurant reviews?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(11, 17, 5));
        $this->demoReply($restaurant, $thread, 'Demo Editor', 'Rating, recommendation, visit type and one or two category scores are usually enough for a clean first launch.', $this->demoCreatedAt(10, 8, 41));

        $h1 = $this->demoEntry($hotel, self::TYPE_REVIEW, 'Luca Demo', 'The lakeside setting is the main memory. This seeded review is useful for hotel pages because it mixes service, location and atmosphere in one card.', 1, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(8, 14, 22));
        $this->saveEntryRating($h1, 5);
        $this->demoSchemaValues($templateId, $h1, ['service' => '5', 'atmosphere' => '5', 'taste_profile' => '3', 'visit_type' => 'Weekend stay']);

        $h2 = $this->demoEntry($hotel, self::TYPE_QUESTION, 'Anna QA', 'Is the hotel a good fit for a quiet weekend stay?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(6, 11, 19));
        $this->demoReply($hotel, $h2, 'Demo Concierge', 'Yes. For a quieter example flow, ask for a lake-facing room and keep restaurant requests attached to the booking.', $this->demoCreatedAt(5, 16, 2));

        $p1 = $this->demoEntry($product, self::TYPE_REVIEW, 'Jon Demo', 'The museum and shop make a good product-experience demo: visitors can review the tour, ask practical questions and discuss gifts in one place.', 1, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(4, 13, 48));
        $this->saveEntryRating($p1, 4);
        $this->demoSchemaValues($templateId, $p1, ['service' => '4', 'atmosphere' => '5', 'taste_profile' => '5', 'visit_type' => 'Family visit']);

        $p2 = $this->demoEntry($product, self::TYPE_THREAD, 'Gift Tester', 'Which part of the chocolate experience is best for first-time visitors?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(3, 15, 9));
        $this->demoReply($product, $p2, 'Demo Visitor', 'The exhibition gives context, and the shop is an easy place to test product-style comments and recommendations.', $this->demoCreatedAt(2, 9, 52));

        $block = $this->demoEntry($product, self::TYPE_COMMENT, 'Demo Reader', 'This inline block discussion is tied to the implementation snippet section.', null, 'install-snippet', self::STATUS_PUBLISHED, $this->demoCreatedAt(2, 18, 16));
        $this->toggleVote($block, 1, null, 'demo-like-c');

        $pending = $this->demoEntry($hotel, self::TYPE_QUESTION, 'Pending Guest', 'Could this be moderated before publishing?', null, '', self::STATUS_PENDING, $this->demoCreatedAt(1, 10, 31));
        $this->saveModNote($pending, 'Demo pending entry for the moderation queue.');
        $this->addReport($r2, null, 'Demo report for moderation preview.', 'reporter@example.test');
    }

    private function seedDemoUserEntries(array $pages, array $users): void {
        $root = $pages['root'];
        $restaurant = $pages['restaurant'];
        $hotel = $pages['hotel'];
        $product = $pages['product'];
        $templateId = (int)$restaurant->template->id;
        $sofia = (int)$users['sofia']->id;
        $marc = (int)$users['marc']->id;
        $elena = (int)$users['elena']->id;

        $qa1 = $this->demoEntry($root, self::TYPE_QUESTION, '', 'How should I add Vox Answers mode to a ProcessWire site?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(9, 11, 12), $elena);
        $ans1 = $this->demoReply($root, $qa1, '', 'Install the module, include vox.init.php once, then include vox.answers.php on a dedicated page. The demo also shows smaller sections if you want a custom layout.', $this->demoCreatedAt(8, 13, 20), $sofia);
        $this->markBestAnswer($ans1, 1, true);
        $this->awardPoints($elena, 'post', (int)$this->cfg('points_post'), $qa1);
        $this->awardPoints($sofia, 'answer', (int)$this->cfg('points_answer'), $ans1);
        $this->awardPoints($sofia, 'best_answer', (int)$this->cfg('points_best_answer'), $ans1);
        $this->toggleVote($qa1, 1, $marc, '');
        $this->toggleVote($ans1, 1, $marc, '');

        $qa2 = $this->demoEntry($root, self::TYPE_QUESTION, '', 'Can Vox profile sections be placed separately in a custom account page?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(7, 15, 45), $marc);
        $this->demoReply($root, $qa2, '', 'Yes. Fetch $voxProfile once and include only the sections you need: header, rank, badges, activity, points or leaderboard.', $this->demoCreatedAt(6, 10, 10), $sofia);
        $this->awardPoints($marc, 'post', (int)$this->cfg('points_post'), $qa2);

        $qa3 = $this->demoEntry($root, self::TYPE_QUESTION, '', 'Which filters are useful for a small community Q&A page?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(5, 9, 30), $sofia);
        $this->awardPoints($sofia, 'post', (int)$this->cfg('points_post'), $qa3);

        $r = $this->demoEntry($restaurant, self::TYPE_REVIEW, '', 'As a demo editor, I like how reviews, questions and profile reputation are connected. This entry exists so the profile activity section has real review data.', 1, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(4, 12, 15), $sofia);
        $this->saveEntryRating($r, 5);
        $this->demoSchemaValues($templateId, $r, ['service' => '5', 'atmosphere' => '4', 'taste_profile' => '5', 'visit_type' => 'Dinner']);
        $this->awardPoints($sofia, 'post', (int)$this->cfg('points_post'), $r);
        $this->toggleVote($r, 1, $marc, '');

        $thread = $this->demoEntry($hotel, self::TYPE_THREAD, '', 'What should a hotel demo page show first: reviews, questions or open discussions?', null, '', self::STATUS_PUBLISHED, $this->demoCreatedAt(3, 17, 5), $marc);
        $this->demoReply($hotel, $thread, '', 'For a client demo, start with the real page context and then show profile/activity so they understand the complete loop.', $this->demoCreatedAt(2, 14, 44), $elena);
        $this->awardPoints($marc, 'post', (int)$this->cfg('points_post'), $thread);

        (new VoxGamification($this))->checkAndAward($sofia);
        (new VoxGamification($this))->checkAndAward($marc);
        (new VoxGamification($this))->checkAndAward($elena);
    }

    private function demoCreatedAt(int $daysAgo, int $hour, int $minute): string {
        $daysAgo = max(0, min(29, $daysAgo));
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));
        $day = date('Y-m-d', strtotime("-{$daysAgo} days"));
        return sprintf('%s %02d:%02d:00', $day, $hour, $minute);
    }

    private function demoEntry(Page $page, string $type, string $guestName, string $body, ?int $recommend = null, string $blockId = '', string $status = self::STATUS_PUBLISHED, string $created = '', int $userId = 0): int {
        $entryId = $this->createEntry([
            'page_id'      => (int)$page->id,
            'block_id'     => $blockId ?: null,
            'template_id'  => (int)$page->template->id,
            'type'         => $type,
            'user_id'      => $userId ?: null,
            'guest_name'   => $userId ? null : $guestName,
            'body'         => $body,
            'status'       => $status,
            'recommend'    => $recommend,
            'ip'           => '127.0.0.1',
        ]);
        $this->setDemoEntryCreated($entryId, $created);
        return $entryId;
    }

    private function demoReply(Page $page, int $parentId, string $guestName, string $body, string $created = '', int $userId = 0): int {
        $parent = $this->getEntry($parentId);
        $entryId = $this->createEntry([
            'page_id'      => (int)$page->id,
            'template_id'  => (int)$page->template->id,
            'type'         => self::TYPE_COMMENT,
            'parent_id'    => $parentId,
            'root_id'      => $parent['root_id'] ?: $parentId,
            'depth'        => min(self::MAX_DEPTH, (int)$parent['depth'] + 1),
            'user_id'      => $userId ?: null,
            'guest_name'   => $userId ? null : $guestName,
            'body'         => $body,
            'status'       => self::STATUS_PUBLISHED,
            'ip'           => '127.0.0.1',
        ]);
        $this->setDemoEntryCreated($entryId, $created);
        return $entryId;
    }

    private function setDemoEntryCreated(int $entryId, string $created): void {
        if (!$entryId || $created === '') return;
        $this->wire->database->prepare("
            UPDATE `" . self::TABLE_ENTRIES . "` SET created = ? WHERE id = ?
        ")->execute([$created, $entryId]);
    }

    private function demoSchemaValues(int $templateId, int $entryId, array $values): void {
        $schema = $this->getSchema($templateId, self::TYPE_REVIEW);
        foreach ($schema as $field) {
            if (!empty($field['builtin'])) continue;
            $name = (string)$field['field_name'];
            if (array_key_exists($name, $values)) {
                $this->saveEntryFieldValue($entryId, (int)$field['id'], (string)$values[$name]);
            }
        }
    }

    private function seedDemoStopwords(int $pageId): void {
        $this->addStopword('scam', 'reject');
        $this->addStopword('counterfeit', 'flag');
        $this->addStopword('spoiler', 'flag', $pageId);
    }

    private function writeDemoTemplateFile(): void {
        $file = $this->wire->config->paths->templates . 'vox-demo.php';
        file_put_contents($file, $this->demoTemplateSource());
    }

    private function demoTemplateSource(): string {
        return <<<'PHP'
<?php namespace ProcessWire;

$voxPath = $config->paths->Vox . 'templates/views/';
$demoAssetUrl = $config->urls->assets . 'vox-demo/';
$isRoot = $page->name === 'vox-demo';
$demoCases = [
    'latelier-robuchon-geneva' => [
        'type' => 'Restaurant demo',
        'lead' => 'A real Geneva restaurant scenario for ratings, recommendations, booking questions, replies and moderation.',
        'facts' => ['Fine dining', 'Geneva', 'Booking questions'],
        'url' => 'https://auberge.com/the-woodward/dine/latelier-robuchon/',
        'image' => $demoAssetUrl . 'restaurant.jpg',
        'imageAlt' => 'The Woodward in Geneva, home of L Atelier Robuchon',
    ],
    'villa-castagnola-lugano' => [
        'type' => 'Hotel demo',
        'lead' => 'A Lugano hospitality scenario for service reviews, guest questions, internal moderation notes and reports.',
        'facts' => ['Lake Lugano', 'Hotel stay', 'Guest service'],
        'url' => 'https://www.villacastagnola.com/en',
        'image' => $demoAssetUrl . 'hotel.webp',
        'imageAlt' => 'Grand Hotel Villa Castagnola on Lake Lugano',
    ],
    'lindt-home-of-chocolate-zurich' => [
        'type' => 'Product experience demo',
        'lead' => 'A Zurich chocolate experience scenario for product-style reviews, taste profile dots, Q&A and inline discussions.',
        'facts' => ['Chocolate museum', 'Zurich/Kilchberg', 'Gift shopping'],
        'url' => 'https://www.lindt-home-of-chocolate.com/en/',
        'image' => $demoAssetUrl . 'product.webp',
        'imageAlt' => 'Lindt Home of Chocolate in Zurich',
    ],
];
$demo = $demoCases[$page->name] ?? $demoCases['latelier-robuchon-geneva'];
$demoTitle = html_entity_decode((string)$page->title, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($demoTitle, ENT_NOQUOTES, 'UTF-8') ?> · Vox Demo</title>
    <?php include $voxPath . 'vox.init.php'; ?>
    <style>
        body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f7f2fa;color:#1d1b20;font-size:16px}
        .demo-shell{max-width:1120px;margin:0 auto;padding:32px 20px 64px}
        .demo-nav{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:28px}
        .demo-brand{font-weight:800;color:#111;text-decoration:none}.demo-links{display:flex;gap:10px;flex-wrap:wrap}
        .demo-links a{color:#49454f;text-decoration:none;padding:9px 12px;border-radius:4px;background:#fffbfe;border:1px solid #cac4d0}
        .demo-hero{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:28px;align-items:center;background:#fffbfe;border:1px solid #cac4d0;border-radius:4px;padding:32px;margin-bottom:24px}
        .demo-kicker{text-transform:uppercase;font-size:14px;letter-spacing:.08em;color:#6750a4;font-weight:700}.demo-hero h1{font-size:42px;line-height:1.05;margin:.2em 0}.demo-lead{font-size:19px;line-height:1.6;color:#49454f}
        .demo-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}.demo-button{display:inline-flex;align-items:center;text-decoration:none;border-radius:4px;padding:11px 16px;border:1px solid #cac4d0;background:#fffbfe;color:#1d1b20;font-weight:700}.demo-button--primary{background:#6750a4;color:#fff;border-color:#6750a4}
        .demo-visual{min-height:240px;border-radius:4px;background:#f3edf7;position:relative;overflow:hidden}.demo-visual img{width:100%;height:100%;min-height:240px;display:block;object-fit:cover}
        .demo-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:24px}.demo-card{background:#fffbfe;border:1px solid #cac4d0;border-radius:4px;padding:20px}.demo-card h2,.demo-card h3{margin-top:0}
        .demo-card__image{height:128px;margin:-20px -20px 16px;border-radius:4px 4px 0 0;overflow:hidden;background:#f3edf7}.demo-card__image img{width:100%;height:100%;display:block;object-fit:cover}
        .demo-panel{background:#fffbfe;border:1px solid #cac4d0;border-radius:4px;padding:28px;margin-bottom:24px}.demo-panel>.vox-wrap{max-width:none}.demo-code{overflow:auto;background:#1d1b20;color:#f7f2fa;border-radius:4px;padding:18px;font-size:15px;line-height:1.5}
        .demo-widget-grid{display:grid;gap:24px}.demo-widget-wide{grid-column:1/-1}
        .demo-section-title{margin:0 0 14px;font-size:26px}.demo-muted{color:#625b71;line-height:1.6}
        .demo-feature-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:24px}.demo-feature{background:#fffbfe;border:1px solid #cac4d0;border-radius:4px;padding:16px}.demo-feature strong{display:block;margin-bottom:6px}.demo-feature span{font-size:14px;color:#625b71;line-height:1.45}
        .demo-two-col{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,.36fr);gap:28px;align-items:start}
        .demo-inline-content{font-size:17px;line-height:1.75;color:#334155}.demo-inline-content p{margin:0 0 1em}.demo-inline-content .vox-inline-form{margin:1.15rem 0}.demo-two-col .demo-code{margin-top:2.1rem}
        @media(min-width:920px){.demo-widget-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:760px){.demo-hero,.demo-card-grid,.demo-feature-grid,.demo-two-col{grid-template-columns:1fr}.demo-hero h1{font-size:32px}}
    </style>
</head>
<body>
<div class="demo-shell">
    <nav class="demo-nav">
        <a class="demo-brand" href="<?= $pages->get('/vox-demo/')->url ?>">Vox Demo</a>
        <div class="demo-links">
            <a href="<?= $pages->get('/vox-demo/latelier-robuchon-geneva/')->url ?>">Restaurant</a>
            <a href="<?= $pages->get('/vox-demo/villa-castagnola-lugano/')->url ?>">Hotel</a>
            <a href="<?= $pages->get('/vox-demo/lindt-home-of-chocolate-zurich/')->url ?>">Product</a>
            <a href="<?= $config->urls->root ?>vox-api/">API</a>
        </div>
    </nav>

<?php if ($isRoot): ?>
    <section class="demo-hero">
        <div>
            <p class="demo-kicker">Complete demo</p>
            <h1>Vox in every mode</h1>
            <p class="demo-lead">A single standalone demo for reviews, Q&A, Answers mode, forum overview, inline forms, block comments, profiles, reputation, moderation and API documentation.</p>
            <div class="demo-actions">
                <a class="demo-button demo-button--primary" href="#demo-answers">Answers</a>
                <a class="demo-button" href="#demo-forum">Forum</a>
                <a class="demo-button" href="#demo-profile">Profile</a>
                <a class="demo-button" href="#demo-inline">Inline form</a>
            </div>
        </div>
        <div class="demo-visual"><img src="<?= htmlspecialchars($demoAssetUrl . 'product.webp') ?>" alt="Vox complete demo" loading="lazy"></div>
    </section>

    <section class="demo-feature-grid">
        <article class="demo-feature"><strong>Reviews</strong><span>Ratings, dot ratings, recommendations, photos and custom fields.</span></article>
        <article class="demo-feature"><strong>Questions</strong><span>Classic Q&A widgets with replies and best answers.</span></article>
        <article class="demo-feature"><strong>Answers mode</strong><span>Q&A platform layout with filters, question detail and contributor sidebar.</span></article>
        <article class="demo-feature"><strong>Profiles</strong><span>Stats, rank progression, badges, points, activity and leaderboard.</span></article>
    </section>

    <section class="demo-panel" id="demo-answers">
        <h2 class="demo-section-title">Answers mode</h2>
        <p class="demo-muted">A StackOverflow-style Q&A surface built from Vox questions, replies, votes, best answers and reputation.</p>
        <?php include $voxPath . 'vox.answers.php'; ?>
    </section>

    <section class="demo-panel" id="demo-profile">
        <h2 class="demo-section-title">Flexible profile sections</h2>
        <p class="demo-muted">This uses a seeded ProcessWire user so profile stats, activity, points and badges are real data.</p>
        <?php $voxProfile = $modules->get('Vox')->getUserProfileData('sofia-keller'); include $voxPath . 'vox.profile.php'; unset($voxProfile); ?>
    </section>

    <section class="demo-panel" id="demo-inline">
        <h2 class="demo-section-title">Inline editorial form</h2>
        <div class="demo-two-col">
            <div class="demo-inline-content">
                <p>Vox can place a compact participation block inside long-form content, after the reader has enough context to respond.</p>
                <?php $voxInlineType = 'question'; $voxInlineTitle = 'Ask about this demo'; $voxInlineIntro = 'This is the same inline form that a Textformatter token can place between article paragraphs.'; $voxInlineButton = 'Send question'; include $voxPath . 'vox.inline-form.php'; unset($voxInlineType, $voxInlineTitle, $voxInlineIntro, $voxInlineButton); ?>
                <p>The surrounding page keeps flowing normally, so this pattern works well for editorial pages, product explainers and documentation.</p>
            </div>
            <pre class="demo-code"><code>[[vox:form type="question"
  title="Ask about this demo"
  button="Send question"]]</code></pre>
        </div>
    </section>

    <section class="demo-panel" id="demo-forum">
        <h2 class="demo-section-title">Forum overview</h2>
    <?php
    $voxForumTitle = 'Forum';
    $voxForumIntro = 'Demo discussions grouped by real-world scenarios: restaurant, hotel and product experience.';
    $voxForumCategories = [
        ['page' => '/vox-demo/latelier-robuchon-geneva/', 'description' => 'Geneva dining reviews, booking questions and best-answer flow.'],
        ['page' => '/vox-demo/villa-castagnola-lugano/', 'description' => 'Lugano hospitality reviews, guest questions and moderation examples.'],
        ['page' => '/vox-demo/lindt-home-of-chocolate-zurich/', 'description' => 'Zurich chocolate experience with product-style discussions and taste-profile ratings.'],
    ];
    include $voxPath . 'vox.forum.php';
    ?>
    </section>
<?php else: ?>
    <section class="demo-hero">
        <div>
            <p class="demo-kicker"><?= htmlspecialchars($demo['type']) ?></p>
            <h1><?= htmlspecialchars($demoTitle, ENT_NOQUOTES, 'UTF-8') ?></h1>
            <p class="demo-lead"><?= htmlspecialchars($demo['lead']) ?></p>
            <div class="demo-actions">
                <a class="demo-button demo-button--primary" href="#vox-reviews">Reviews</a>
                <a class="demo-button" href="#vox-questions">Questions</a>
                <a class="demo-button" href="#vox-discussions">Discussions</a>
                <a class="demo-button" href="<?= htmlspecialchars($demo['url']) ?>" target="_blank" rel="noopener">Official site</a>
            </div>
        </div>
        <div class="demo-visual"><img src="<?= htmlspecialchars($demo['image']) ?>" alt="<?= htmlspecialchars($demo['imageAlt']) ?>" loading="lazy"></div>
    </section>

    <section class="demo-panel" data-discuss-block="install-snippet">
        <h2>Implementation pattern</h2>
        <p>This demo template disables the site's automatic prepend and append files, so Markup Regions and <code>_main.php</code> cannot reshape the output.</p>
        <pre class="demo-code"><code>$voxPath = $config-&gt;paths-&gt;Vox . 'templates/views/';
include $voxPath . 'vox.init.php';
include $voxPath . 'vox.reviews.php';
include $voxPath . 'vox.questions.php';
include $voxPath . 'vox.discussions.php';</code></pre>
    </section>

    <section class="demo-card-grid">
        <?php foreach ($demo['facts'] as $fact): ?>
        <article class="demo-card"><h3><?= htmlspecialchars($fact) ?></h3><p>Sample demo content for fast Vox integration checks.</p></article>
        <?php endforeach ?>
    </section>

    <section class="demo-panel" id="vox-reviews">
        <h2>Reviews</h2>
        <?php include $voxPath . 'vox.reviews.php'; ?>
    </section>
    <section class="demo-panel" id="vox-questions">
        <h2>Questions & Answers</h2>
        <?php include $voxPath . 'vox.questions.php'; ?>
    </section>
    <section class="demo-panel" id="vox-discussions">
        <h2>Discussions</h2>
        <?php include $voxPath . 'vox.discussions.php'; ?>
    </section>
<?php endif; ?>
</div>
</body>
</html>
PHP;
    }

    /**
     * Check if an entry body contains any stop word (used for admin display).
     */
    public function entryHasStopword(array $entry): bool {
        $words   = $this->getStopwords((int)($entry['page_id'] ?? 0));
        $bodyLow = mb_strtolower($entry['body'] ?? '');
        foreach ($words as $w) {
            if (mb_strpos($bodyLow, mb_strtolower($w['word'])) !== false) return true;
        }
        return false;
    }

    /**
     * Get moderator note for an entry (returns empty string if none).
     */
    public function getModNote(int $entryId): string {
        $stmt = $this->wire->database->prepare(
            "SELECT note FROM `" . self::TABLE_MOD_NOTES . "` WHERE entry_id = ? LIMIT 1"
        );
        $stmt->execute([$entryId]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    /**
     * Save moderator note for an entry.
     */
    public function saveModNote(int $entryId, string $note): void {
        $this->wire->database->prepare("
            INSERT INTO `" . self::TABLE_MOD_NOTES . "` (entry_id, note, updated) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE note = VALUES(note), updated = NOW()
        ")->execute([$entryId, $note]);
    }

    /**
     * Save a single entry edit from admin (status, body, recommend).
     */
    public function saveEntryEdit(int $entryId, array $data): bool {
        $san    = $this->wire->sanitizer;
        $status = $san->option($data['status'] ?? '', [self::STATUS_PUBLISHED, self::STATUS_PENDING, self::STATUS_SPAM]);
        $body   = $san->textarea($data['body'] ?? '');
        if (!$status || !$body) return false;

        $recommend = isset($data['recommend']) && $data['recommend'] !== ''
            ? (int)(bool)$data['recommend']
            : null;

        $this->wire->database
             ->prepare("UPDATE `" . self::TABLE_ENTRIES . "` SET status = ?, body = ?, recommend = ? WHERE id = ?")
             ->execute([$status, $body, $recommend, $entryId]);
        return true;
    }

    /**
     * Save schema fields from admin (full replace for template+type).
     */
    public function saveSchemaFields(int $templateId, string $entryType, array $fields): void {
        $db         = $this->wire->database;
        $san        = $this->wire->sanitizer;
        $ft         = self::TABLE_FIELDS;
        $validTypes = ['rating','text','textarea','select','bool','photo'];
        $validTypes_enum = ['review','question','thread','comment'];

        if (!$templateId || !in_array($entryType, $validTypes_enum)) return;

        $db->prepare("DELETE FROM `{$ft}` WHERE template_id = ? AND entry_type = ?")
           ->execute([$templateId, $entryType]);

        $stmt = $db->prepare("
            INSERT INTO `{$ft}` (template_id, entry_type, field_name, field_label, field_type, field_options, required, sort)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($fields as $i => $f) {
            $name = $san->fieldName($f['field_name'] ?? '');
            if (!$name) continue;
            $type = $san->option($f['field_type'] ?? 'text', $validTypes);
            $optRaw = $f['field_options'] ?? '';
            if (is_array($optRaw)) {
                $opt = array_values(array_filter(array_map(fn($v) => $san->text((string)$v), $optRaw), fn($v) => $v !== ''));
            } else {
                $opt = array_values(array_filter(array_map(
                    fn($v) => $san->text(trim($v)),
                    explode(',', (string)$optRaw)
                ), fn($v) => $v !== ''));
            }
            $stmt->execute([
                $templateId, $entryType, $name,
                $san->text($f['field_label'] ?? $name),
                $type, $opt ? json_encode($opt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                !empty($f['required']) ? 1 : 0,
                $i,
            ]);
        }
    }

    /**
     * Get schema template map for admin: [template_id => [entry_types...]]
     */
    public function getSchemaTemplateMap(): array {
        $stmt = $this->wire->database->query(
            "SELECT DISTINCT template_id, entry_type FROM `" . self::TABLE_FIELDS . "` WHERE template_id IS NOT NULL"
        );
        $map = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $map[$row['template_id']][] = $row['entry_type'];
        }
        return $map;
    }

    /**
     * Get stopword hit entries (pending/spam, for admin hit log).
     */
    public function getStopwordHitEntries(int $limit = 50): array {
        $stmt = $this->wire->database->query("
            SELECT e.id, e.body, e.type, e.page_id, e.guest_name, e.user_id, e.created, e.status,
                   ft.data AS page_name
            FROM `" . self::TABLE_ENTRIES . "` e
            LEFT JOIN field_title ft ON ft.pages_id = e.page_id
            WHERE e.status IN ('pending','spam')
            ORDER BY e.created DESC
            LIMIT " . (int)$limit
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get overview stats for admin settings panel.
     */
    public function getAdminOverview(): array {
        $tables = [
            self::TABLE_ENTRIES, self::TABLE_FIELDS, self::TABLE_VALUES,
            self::TABLE_PHOTOS, self::TABLE_VOTES, self::TABLE_REPORTS,
            self::TABLE_STOPWORDS, self::TABLE_POINTS, self::TABLE_RANKS,
            self::TABLE_BADGES, self::TABLE_BADGE_DEFS, self::TABLE_MOD_NOTES,
        ];
        return [
            'version' => self::VERSION,
            'total'   => (int)$this->wire->database
                             ->query("SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "`")
                             ->fetchColumn(),
            'tables'  => count($tables),
            'pw'      => $this->wire->config->version,
            'php'     => PHP_VERSION,
        ];
    }

    /**
     * Get distinct block IDs that have published entries on a page.
     * Returns array of non-empty block ID strings.
     */
    public function getPageBlockIds(int $pageId): array {
        $stmt = $this->wire->database->prepare("
            SELECT DISTINCT block_id
            FROM `" . self::TABLE_ENTRIES . "`
            WHERE page_id = ? AND status = 'published' AND block_id IS NOT NULL AND block_id != ''
        ");
        $stmt->execute([$pageId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Count all published replies (nested) for an entry by root_id.
     */
    public function countReplies(int $rootId): int {
        $stmt = $this->wire->database->prepare("
            SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "`
            WHERE root_id = ? AND status = 'published'
        ");
        $stmt->execute([$rootId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Create a new entry and save field values.
     * Returns the new entry ID, or 0 on failure.
     */
    public function createEntry(array $data): int {
        $san = $this->wire->sanitizer;
        $db  = $this->wire->database;

        $stmt = $db->prepare("
            INSERT INTO `" . self::TABLE_ENTRIES . "`
                (page_id, block_id, template_id, type, parent_id, root_id, depth,
                 user_id, guest_name, guest_email, guest_fingerprint,
                 body, status, recommend, created, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            (int)($data['page_id']           ?? 0),
            ($data['block_id']               ?? null) ?: null,
            (int)($data['template_id']       ?? 0),
            $data['type']                    ?? 'comment',
            ($data['parent_id']              ?? null) ?: null,
            ($data['root_id']                ?? null) ?: null,
            (int)($data['depth']             ?? 0),
            ($data['user_id']                ?? null) ?: null,
            ($data['guest_name']             ?? null) ?: null,
            ($data['guest_email']            ?? null) ?: null,
            ($data['guest_fingerprint']      ?? null) ?: null,
            $data['body']                    ?? '',
            $data['status']                  ?? self::STATUS_PENDING,
            array_key_exists('recommend', $data) ? $data['recommend'] : null,
            $data['ip']                      ?? '',
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * Save built-in rating value (field_id = 0) for an entry.
     */
    public function saveEntryRating(int $entryId, int $rating): void {
        if ($rating < 1 || $rating > 5) return;
        unset($this->entryValuesCache[$entryId]);
        $this->wire->database->prepare("
            INSERT INTO `" . self::TABLE_VALUES . "` (entry_id, field_id, value)
            VALUES (?, 0, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ")->execute([$entryId, $rating]);
    }

    /**
     * Save a single custom field value for an entry.
     */
    public function saveEntryFieldValue(int $entryId, int $fieldId, string $value): void {
        if (!$entryId || !$fieldId) return;
        unset($this->entryValuesCache[$entryId]);
        $this->wire->database->prepare("
            INSERT INTO `" . self::TABLE_VALUES . "` (entry_id, field_id, value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ")->execute([$entryId, $fieldId, $value]);
    }

    /** Public base URL for uploaded entry photos. */
    private function photoBaseUrl(): string {
        $baseUrl = rtrim($this->wire->config->urls->root, '/') . '/' . ltrim($this->cfg('photo_path'), '/');
        return rtrim($baseUrl, '/') . '/';
    }

    /** Cast a photo DB row and attach its public URL. */
    private function photoRowToArray(array $row, string $baseUrl): array {
        $row['id']       = (int)$row['id'];
        $row['filesize'] = (int)$row['filesize'];
        $row['sort']     = (int)$row['sort'];
        $row['url']      = $baseUrl . rawurlencode($row['filename']);
        return $row;
    }

    /**
     * Return persisted photos for one entry with public URLs (cached per request).
     */
    public function getEntryPhotos(int $entryId): array {
        if (!$entryId) return [];
        if (isset($this->entryPhotosCache[$entryId])) return $this->entryPhotosCache[$entryId];

        $stmt = $this->wire->database->prepare("
            SELECT id, filename, original_name, mime, filesize, sort, created
            FROM `" . self::TABLE_PHOTOS . "`
            WHERE entry_id = ?
            ORDER BY sort ASC, id ASC
        ");
        $stmt->execute([$entryId]);

        $baseUrl = $this->photoBaseUrl();
        $photos = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $photos[] = $this->photoRowToArray($row, $baseUrl);
        }
        return $this->entryPhotosCache[$entryId] = $photos;
    }

    /**
     * Persist uploaded entry photos and return saved rows.
     */
    public function saveEntryPhotos(int $entryId, array $files): array {
        if (!$entryId || !$this->cfg('photo_uploads')) return [];
        unset($this->entryPhotosCache[$entryId]);

        $normalized = $this->normalizeUploadedPhotos($files);
        if (!$normalized) return [];

        $maxFiles = max(1, (int)$this->cfg('photo_max'));
        $maxBytes = max(1, (int)$this->cfg('photo_max_size')) * 1024 * 1024;
        $targetDir = $this->getPhotoUploadPath();
        if (!is_dir($targetDir)) {
            wireMkdir($targetDir, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) return [];

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        $stmt = $this->wire->database->prepare("
            INSERT INTO `" . self::TABLE_PHOTOS . "`
                (entry_id, filename, original_name, mime, filesize, sort, created)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $saved = [];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        foreach (array_slice($normalized, 0, $maxFiles) as $sort => $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $tmp = (string)($file['tmp_name'] ?? '');
            if (!$tmp || !is_uploaded_file($tmp)) continue;
            $size = (int)($file['size'] ?? 0);
            if ($size < 1 || $size > $maxBytes) continue;

            $mime = $finfo->file($tmp) ?: '';
            if (!isset($allowed[$mime])) continue;

            $original = $this->wire->sanitizer->text((string)($file['name'] ?? 'photo'));
            $filename = sprintf(
                'entry-%d-%s.%s',
                $entryId,
                bin2hex(random_bytes(8)),
                $allowed[$mime]
            );

            if (!move_uploaded_file($tmp, $targetDir . $filename)) continue;

            $stmt->execute([$entryId, $filename, $original, $mime, $size, $sort]);
            $saved[] = [
                'id'            => (int)$this->wire->database->lastInsertId(),
                'filename'      => $filename,
                'original_name' => $original,
                'mime'          => $mime,
                'filesize'      => $size,
                'sort'          => $sort,
                'url'           => rtrim($this->wire->config->urls->root, '/') . '/' . trim($this->cfg('photo_path'), '/') . '/' . $filename,
            ];
        }

        return $saved;
    }

    private function normalizeUploadedPhotos(array $files): array {
        if (!isset($files['name'])) return [];
        if (!is_array($files['name'])) return [$files];

        $normalized = [];
        foreach ($files['name'] as $i => $name) {
            $normalized[] = [
                'name'     => $name,
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }
        return $normalized;
    }

    private function getPhotoUploadPath(): string {
        return rtrim($this->wire->config->paths->root, '/') . '/' . trim($this->cfg('photo_path'), '/') . '/';
    }

    /**
     * Toggle a vote for an entry. Handles insert / update / delete.
     * Returns ['total' => int, 'user_vote' => int]
     */
    public function toggleVote(int $entryId, int $value, ?int $userId, ?string $fingerprint): array {
        $db        = $this->wire->database;
        unset($this->entryLikesCache[$entryId]);
        $likesOnly = $this->cfg('votes_mode') === 'likes';
        $value     = $likesOnly ? 1 : ($value >= 0 ? 1 : -1);

        $stmt = $db->prepare("
            SELECT id, value FROM `" . self::TABLE_VOTES . "`
            WHERE entry_id = ? AND (user_id = ? OR (user_id IS NULL AND guest_fingerprint = ?))
            LIMIT 1
        ");
        $stmt->execute([$entryId, $userId, $fingerprint]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        $prevValue = $existing ? (int)$existing['value'] : 0;

        if ($existing) {
            if ((int)$existing['value'] === $value) {
                $db->prepare("DELETE FROM `" . self::TABLE_VOTES . "` WHERE id = ?")
                   ->execute([$existing['id']]);
                $newValue = 0;
            } else {
                $db->prepare("UPDATE `" . self::TABLE_VOTES . "` SET value = ? WHERE id = ?")
                   ->execute([$value, $existing['id']]);
                $newValue = $value;
            }
        } else {
            $db->prepare("
                INSERT INTO `" . self::TABLE_VOTES . "` (entry_id, user_id, guest_fingerprint, value, created)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$entryId, $userId, $fingerprint, $value]);
            $newValue = $value;
        }

        $totalSql = $likesOnly
            ? "SELECT COALESCE(SUM(value), 0) FROM `" . self::TABLE_VOTES . "` WHERE entry_id = ? AND value > 0"
            : "SELECT COALESCE(SUM(value), 0) FROM `" . self::TABLE_VOTES . "` WHERE entry_id = ?";
        $stmt = $db->prepare($totalSql);
        $stmt->execute([$entryId]);

        return ['total' => (int)$stmt->fetchColumn(), 'user_vote' => $newValue, 'prev_vote' => $prevValue];
    }

    /**
     * Add a report for an entry. Returns false if already reported by this user.
     */
    public function addReport(int $entryId, ?int $userId, string $reason, string $guestEmail = ''): bool {
        $db = $this->wire->database;

        if ($userId) {
            $stmt = $db->prepare("
                SELECT id FROM `" . self::TABLE_REPORTS . "`
                WHERE entry_id = ? AND user_id = ? AND status = 'open'
            ");
            $stmt->execute([$entryId, $userId]);
            if ($stmt->fetchColumn()) return false; // already reported
        }

        $db->prepare("
            INSERT INTO `" . self::TABLE_REPORTS . "` (entry_id, user_id, guest_email, reason, status, created)
            VALUES (?, ?, ?, ?, 'open', NOW())
        ")->execute([$entryId, $userId, $guestEmail ?: null, $reason]);

        return true;
    }

    /**
     * Mark an entry as the best answer in its thread.
     * Unmarks any previous best answer. Returns false if not allowed.
     */
    /**
     * Get the current best-answer entry id within a thread (0 if none).
     */
    public function getBestAnswerId(int $threadRootId): int {
        if (!$threadRootId) return 0;
        $stmt = $this->wire->database->prepare("
            SELECT id FROM `" . self::TABLE_ENTRIES . "`
            WHERE (root_id = ? OR id = ?) AND is_best_answer = 1
            LIMIT 1
        ");
        $stmt->execute([$threadRootId, $threadRootId]);
        return (int)$stmt->fetchColumn();
    }

    public function markBestAnswer(int $entryId, int $actingUserId, bool $isSuperuser): bool {
        $entry = $this->getEntry($entryId);
        if (!$entry || !$entry['parent_id']) return false;
        if (!empty($entry['is_best_answer'])) return false; // already best — nothing to do

        $parent = $this->getEntry($entry['parent_id']);
        if (!$parent) return false;

        $canMark = $isSuperuser || ((int)$parent['user_id'] === $actingUserId);
        if (!$canMark) return false;

        $db         = $this->wire->database;
        $threadRoot = $entry['root_id'] ?: $entry['parent_id'];

        $db->prepare("
            UPDATE `" . self::TABLE_ENTRIES . "` SET is_best_answer = 0
            WHERE (root_id = ? OR id = ?) AND is_best_answer = 1
        ")->execute([$threadRoot, $threadRoot]);

        $db->prepare("UPDATE `" . self::TABLE_ENTRIES . "` SET is_best_answer = 1 WHERE id = ?")
           ->execute([$entryId]);

        return true;
    }

    /**
     * Delete a schema for a template + entry type.
     */
    public function deleteSchema(int $templateId, string $entryType): void {
        $valid = ['review','question','thread','comment'];
        if (!$templateId || !in_array($entryType, $valid)) return;
        $this->wire->database
             ->prepare("DELETE FROM `" . self::TABLE_FIELDS . "` WHERE template_id = ? AND entry_type = ?")
             ->execute([$templateId, $entryType]);
    }

    /**
     * Count entries with spam status (for stop words stats panel).
     */
    public function getSpamCount(): int {
        return (int)$this->wire->database
            ->query("SELECT COUNT(*) FROM `" . self::TABLE_ENTRIES . "` WHERE status = 'spam'")
            ->fetchColumn();
    }

}

<?php

declare(strict_types=1);

namespace ProcessWire;

// Isolated source test: never touches a ProcessWire site or production data.
class Wire {
    public function __construct(public \PDO $database, public object $config, public object $modules) {}
}
class Vox {
    public const TABLE_ENTRIES = 'vox_entries';
    public const TABLE_VALUES = 'vox_values';
    public const TABLE_PHOTOS = 'vox_photos';
    public const TABLE_VOTES = 'vox_votes';
    public const TABLE_REPORTS = 'vox_reports';
    public const TABLE_POINTS = 'vox_points';
    public const TABLE_MOD_NOTES = 'vox_mod_notes';
}
class Process {
    public object $wire;
    public function __construct() {
        $this->wire = (object)['user' => new class {
            public function isSuperuser(): bool { return false; }
            public function hasPermission(string $name): bool { return $name === 'vox-view'; }
        }];
    }
    protected function _(string $message): string { return $message; }
}
class WirePermissionException extends \RuntimeException {}

require dirname(__DIR__) . '/VoxRepository.php';
require dirname(__DIR__) . '/ProcessVox.module.php';

$db = new \PDO('sqlite::memory:');
$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec('CREATE TABLE vox_entries (id INTEGER PRIMARY KEY, parent_id INTEGER, user_id INTEGER)');
foreach (['vox_values', 'vox_photos', 'vox_votes', 'vox_reports', 'vox_points', 'vox_mod_notes'] as $table) {
    $db->exec("CREATE TABLE {$table} (entry_id INTEGER, filename TEXT)");
}
$db->exec("INSERT INTO pages VALUES (1, 'owner')");
$db->exec('INSERT INTO vox_entries VALUES (10, NULL, 1), (11, 10, 1), (12, NULL, 2)');
foreach (['vox_values', 'vox_photos', 'vox_votes', 'vox_reports', 'vox_points', 'vox_mod_notes'] as $table) {
    $db->exec("INSERT INTO {$table} (entry_id) VALUES (10), (11), (12)");
}
$config = (object)['paths' => (object)['root' => '/no-vox-photos/']];
$modules = new class {
    public function get(string $name): object {
        if ($name !== 'Vox') throw new \RuntimeException('Unexpected module');
        return new class { public function cfg(string $key): string { return $key === 'photo_path' ? 'photos' : ''; } };
    }
};
$repo = new VoxRepository(new Wire($db, $config, $modules));
if (!$repo->deleteEntry(10) || $repo->deleteEntry(10)) throw new \RuntimeException('Deletion result mismatch');
foreach (['vox_entries', 'vox_values', 'vox_photos', 'vox_votes', 'vox_reports', 'vox_points', 'vox_mod_notes'] as $table) {
    $ids = $db->query("SELECT " . ($table === 'vox_entries' ? 'id' : 'entry_id') . " FROM {$table}")->fetchAll(\PDO::FETCH_COLUMN);
    if (array_map('intval', $ids) !== [12]) throw new \RuntimeException("Dependent {$table} rows not scoped to deleted thread");
}

$denied = false;
try { (new ProcessVox())->executeEntry(); }
catch (WirePermissionException $error) { $denied = true; }
if (!$denied) throw new \RuntimeException('vox-view alone reached the single-entry editor');

echo "Vox deletion and moderator permission guards OK.\n";

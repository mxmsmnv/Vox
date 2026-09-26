<?php

$root = dirname(__DIR__);
$sources = [
    'Vox.module.php' => file_get_contents($root . '/Vox.module.php'),
    'VoxGamification.php' => file_get_contents($root . '/VoxGamification.php'),
];

foreach ($sources as $file => $source) {
    if ($source === false) throw new RuntimeException("Could not read {$file}.");
    if (preg_match('/SUM\s*\(\s*(?!CASE\b)[^)]*(?:\s(?:IN|IS|AND|OR)\s|[=<>])/i', $source)) {
        throw new RuntimeException("{$file} still contains a non-portable boolean SUM aggregate.");
    }
}

if (substr_count($sources['Vox.module.php'], 'SUM(CASE WHEN') < 5) {
    throw new RuntimeException('Expected portable Vox conditional aggregates were not found.');
}
if (substr_count($sources['VoxGamification.php'], 'SUM(CASE WHEN') < 5) {
    throw new RuntimeException('Expected portable gamification aggregates were not found.');
}
if (stripos($sources['Vox.module.php'], 'FROM DUAL') !== false) {
    throw new RuntimeException('Default rank seeding still depends on MySQL FROM DUAL.');
}

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->exec('CREATE TABLE vox_ranks (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT, min_points INTEGER, icon TEXT, sort INTEGER)');
$seed = $sqlite->prepare(
    'INSERT INTO vox_ranks (label, min_points, icon, sort)
     SELECT ?, ?, ?, ?
     WHERE NOT EXISTS (SELECT 1 FROM vox_ranks WHERE label = ? AND min_points = ?)'
);
$rank = ['Newcomer', 0, 'seedling', 0];
$seed->execute([$rank[0], $rank[1], $rank[2], $rank[3], $rank[0], $rank[1]]);
$seed->execute([$rank[0], $rank[1], $rank[2], $rank[3], $rank[0], $rank[1]]);
if ((int) $sqlite->query('SELECT COUNT(*) FROM vox_ranks')->fetchColumn() !== 1) {
    throw new RuntimeException('Portable default rank seed is not idempotent on SQLite.');
}

echo "Vox database portability checks passed.\n";

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

echo "Vox database portability checks passed.\n";

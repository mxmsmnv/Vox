<?php namespace ProcessWire;

require dirname(__DIR__) . '/templates/views/vox.helpers.php';

$checks = [
    'stars expose a named image' => str_starts_with(
        vox_stars(4),
        '<span class="vox-stars" role="img" aria-label="4 out of 5">'
    ),
    'dots expose a named image' => str_starts_with(
        vox_dots(3),
        '<span class="vox-dots" role="img" aria-label="3 out of 5">'
    ),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, 'Failed: ' . implode(', ', $failed) . "\n");
    exit(1);
}

echo 'Vox view-helper accessibility checks passed (' . count($checks) . ").\n";

<?php namespace ProcessWire;

/**
 * Read-only smoke coverage for readable and legacy Vox profile identifiers.
 *
 * Usage:
 *   VOX_PW_ROOT=/path/to/processwire php tests/profile-url.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = rtrim((string)getenv('VOX_PW_ROOT'), '/');
if ($root === '' || !is_file($root . '/index.php')) {
    fwrite(STDERR, "Set VOX_PW_ROOT to a ProcessWire installation.\n");
    exit(2);
}

chdir($root);
require $root . '/index.php';

$vox = $wire->modules->get('Vox');
$profileUser = $wire->users->get('roles=superuser');
if (!$vox instanceof Vox || !$profileUser->id) {
    fwrite(STDERR, "Vox or a superuser fixture is unavailable.\n");
    exit(3);
}

$legacyKey = $vox->publicKey('user', (int)$profileUser->id);
$slug = $wire->sanitizer->pageName((string)$profileUser->name);
$checks = [
    'legacy key resolves' => (int)($vox->resolveProfileUser($legacyKey)->id ?? 0) === (int)$profileUser->id,
    'readable slug resolves' => (int)($vox->resolveProfileUser($slug)->id ?? 0) === (int)$profileUser->id,
    'slug is stable' => $vox->profileSlug($legacyKey) === $slug,
    'default readable URL' => $vox->profileUrl($legacyKey) === '/community/profile/' . rawurlencode($slug) . '/',
    'custom local base URL' => $vox->profileUrl($legacyKey, '/people/') === '/people/' . rawurlencode($slug) . '/',
    'unsafe base fails closed' => $vox->profileUrl($legacyKey, 'https://example.com/profile/') === '/community/profile/' . rawurlencode($slug) . '/',
    'unknown user has no URL' => $vox->profileUrl('vox_unknown') === '',
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, 'Failed: ' . implode(', ', $failed) . "\n");
    exit(1);
}

echo 'Vox profile URL checks passed (' . count($checks) . ").\n";

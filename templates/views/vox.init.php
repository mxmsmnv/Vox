<?php namespace ProcessWire;
/**
 * Vox frontend init.
 * Outputs CSS, window.VoxConfig, and JS (bundle or individual files).
 *
 * Include once per page, ideally before </body> or in <head>:
 *   include $config->paths->Vox . 'templates/views/vox.init.php';
 *
 * To use individual JS files instead of the bundle (useful during development):
 *   $voxJsBundle = false;
 *   include $config->paths->Vox . 'templates/views/vox.init.php';
 *
 * To load only specific JS modules (omit profile and blocks if not needed):
 *   $voxJsModules = ['core','stars','vote','reply','entry','filters','photos','init'];
 *   include $config->paths->Vox . 'templates/views/vox.init.php';
 */

$vox     = wire('modules')->get('Vox');
$cfg     = wire('config');
$user    = wire('user');
$csrf    = wire('session')->CSRF;
$modUrl  = $cfg->urls->Vox;
$cssPath = __DIR__ . '/../../css/vox.css';
$cssVer  = is_file($cssPath) ? filemtime($cssPath) : time();
$jsDir   = __DIR__ . '/../../js/';
$jsVer   = is_file($jsDir . 'vox.bundle.js') ? filemtime($jsDir . 'vox.bundle.js') : time();

// Whether to use bundle.js (true) or individual files (false)
// Default: use bundle in production, individual files in development
$useBundle = $voxJsBundle ?? !$cfg->debug;

// Which JS modules to load when not using bundle.
// Full list in dependency order:
$voxAllModules  = ['vox.core','vox.stars','vox.vote','vox.reply','vox.entry','vox.blocks','vox.filters','vox.photos','vox.profile','vox.init'];
$voxJsFileList  = $voxJsModules ?? $voxAllModules;

// NOTE: the moderation stop-word list is intentionally NOT exposed to the
// client. Publishing it would let anyone read the blocklist from page source
// and trivially work around it. Stop words are enforced server-side in the
// entries/add API (Vox::checkStopwords) on every submission.

// Only a boolean login flag is exposed — no user id, login name, rank or
// badges. The client never consumed that data; per-user stats are available
// on demand from the authenticated /vox-api/user-stats/ endpoint.
$isLoggedIn = $user->isLoggedIn();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<link rel="stylesheet" href="<?= $modUrl ?>css/vox.css?v=<?= $cssVer ?>">

<script>
window.VoxConfig = {
    apiUrl:      "<?= wire('config')->urls->root ?>vox-api/",
    csrfName:    "<?= $csrf->getTokenName() ?>",
    csrfValue:   "<?= $csrf->getTokenValue() ?>",
    panelMode:   "<?= $vox->cfg('panel_mode') ?>",
    photoMax:    <?= (int)$vox->cfg('photo_max') ?>,
    allowGuests: <?= $vox->cfg('allow_guests') ? 'true' : 'false' ?>,
    loggedIn:    <?= $isLoggedIn ? 'true' : 'false' ?>
};
</script>

<?php if ($useBundle): ?>
<script src="<?= $modUrl ?>js/vox.bundle.js?v=<?= $jsVer ?>" defer></script>
<?php else: ?>
<?php foreach ($voxJsFileList as $mod): ?>
<script src="<?= $modUrl ?>js/<?= $mod ?>.js?v=<?= $jsVer ?>" defer></script>
<?php endforeach ?>
<?php endif ?>

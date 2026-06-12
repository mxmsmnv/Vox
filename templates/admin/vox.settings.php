<?php namespace ProcessWire;
/**
 * Vox Admin — Settings overview.
 *
 * Editable settings live on the standard ProcessWire module config screen
 * (Modules → Vox). This page shows the current state, links to that screen
 * and the API tab, and keeps the destructive "delete all data" action.
 */
include __DIR__ . '/_vox.design-system.php';

$csrf = wire()->session->CSRF;
$moduleCfgUrl = wire()->config->urls->admin . 'module/edit?name=Vox';
$voxUrl = wire()->config->urls->admin . 'vox/';

$tiles = [
 ['Moderation', ucfirst($cfg['moderation']), 'Entry publishing mode', 'cog'],
 ['Guests', $cfg['allow_guests'] ? 'Allowed' : 'Blocked', $cfg['guest_require_email'] ? 'Email required' : 'No email required', 'users'],
 ['Photos', $cfg['photo_uploads'] ? 'On' : 'Off', (int)$cfg['photo_max'] . ' photos · ' . (int)$cfg['photo_max_size'] . ' MB', 'image'],
 ['Tables', $overview['tables'], 'Database footprint', 'database'],
];

$configGroups = [
 ['General', 'Moderation mode · guest entries · notification email'],
 ['Display', 'Block comment panel mode · initial comments shown'],
 ['Voting', 'Vote mode · guest voting'],
 ['Photos', 'Uploads · max count / size · upload path'],
];

?>

<p class="uk-text-meta uk-margin-small-bottom">Vox v<?= $overview['version'] ?> · Current configuration at a glance.</p>

<!-- Summary tiles -->
<div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-4@m uk-margin Vox-stat-grid" uk-grid>
<?php foreach ($tiles as [$label, $value, $sub, $icon]): ?>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-flex uk-flex-between uk-flex-middle">
   <span class="uk-text-meta uk-text-uppercase"><?= $label ?></span>
   <span uk-icon="icon:<?= $icon ?>;ratio:0.85" class="uk-text-muted"></span>
  </div>
  <div class="uk-text-large uk-text-bold uk-margin-small-top"><?= htmlspecialchars((string)$value) ?></div>
  <div class="uk-text-meta"><?= htmlspecialchars($sub) ?></div>
 </div></div>
<?php endforeach ?>
</div>

<div class="uk-grid-small uk-flex-top" uk-grid>
<div class="uk-width-expand@m">

 <!-- Configure -->
 <div class="uk-card uk-card-default uk-card-body uk-margin Vox-form-panel">
  <h3 class="uk-card-title"><span uk-icon="icon:cog;ratio:0.9" class="uk-text-primary"></span> Configuration</h3>
  <p class="uk-margin-small-top">Moderation, guests, display, voting and photo-upload settings are managed on the standard ProcessWire module configuration screen.</p>
  <div class="uk-grid-small uk-child-width-1-2@s uk-margin-small" uk-grid>
<?php foreach ($configGroups as [$g, $desc]): ?>
   <div>
    <div class="uk-text-bold"><?= $g ?></div>
    <div class="uk-text-meta"><?= $desc ?></div>
   </div>
<?php endforeach ?>
  </div>
  <div class="uk-margin-small-top">
   <a href="<?= $moduleCfgUrl ?>" class="uk-button uk-button-primary"><span uk-icon="icon:cog;ratio:0.85"></span> Open module settings</a>
   <a href="<?= $voxUrl ?>api/" class="uk-button uk-button-default"><span uk-icon="icon:bolt;ratio:0.85"></span> REST API reference</a>
  </div>
 </div>

 <!-- Danger zone -->
 <div class="uk-card uk-card-default uk-card-body uk-margin Vox-danger">
  <h3 class="uk-card-title"><span uk-icon="icon:warning;ratio:0.9"></span> Danger zone</h3>
  <div class="uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-grid-small" uk-grid>
   <div>
    <div class="uk-text-bold">Delete all Vox data</div>
    <div class="uk-text-meta">Permanently removes all entries, votes, points, badges and reports. This cannot be undone.</div>
   </div>
   <div>
    <form method="post" action="">
     <?= $csrf->renderInput() ?>
     <input type="hidden" name="delete_all_data" value="1">
     <button type="submit" class="uk-button uk-button-danger"
      onclick="return confirm('Delete ALL Vox data? This cannot be undone!')">
      <span uk-icon="icon:trash;ratio:0.85"></span> Delete all data
     </button>
    </form>
   </div>
  </div>
 </div>

</div>

<!-- Sidebar -->
<div class="uk-width-1-3@m">
 <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
  <h3 class="uk-card-title">Quick overview</h3>
  <dl class="uk-description-list uk-description-list-divider">
   <dt>Module version</dt><dd><?= $overview['version'] ?></dd>
   <dt>Total entries</dt><dd><?= number_format($overview['total']) ?></dd>
   <dt>DB tables</dt><dd><?= $overview['tables'] ?></dd>
   <dt>ProcessWire</dt><dd><?= htmlspecialchars((string)$overview['pw']) ?></dd>
   <dt>PHP</dt><dd><?= htmlspecialchars((string)$overview['php']) ?></dd>
  </dl>
 </div>
</div>
</div>

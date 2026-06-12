<?php namespace ProcessWire;
/**
 * Vox Admin — Gamification (native AdminThemeUikit markup).
 */
include __DIR__ . '/_vox.design-system.php';

$voxUrl = wire()->config->urls->admin . 'vox/';
$csrf = wire()->session->CSRF;
$activeTab = wire()->input->get('tab');
if (!in_array($activeTab, ['ranks','badges','points','leaderboard'], true)) $activeTab = 'ranks';
$activePeriod = in_array(wire()->input->get('period'), ['week', 'month', 'all'], true) ? wire()->input->get('period') : 'month';
$period = $activePeriod;
function gTabUrl(array $params): string {
  $params = array_filter($params + ['tab' => '', 'period' => ''], fn($v) => $v !== null && $v !== '');
  return '?' . http_build_query($params);
}

// FontAwesome class for an icon name (brand-aware).
$faBrands = [];
foreach (@file(__DIR__ . '/../../fontawesome/brands.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $b) {
  $faBrands[preg_replace('/^fa-/', '', trim($b))] = true;
}
$faClass = fn(string $n): string => (isset($faBrands[$n]) ? 'fa-brands' : 'fa-solid') . ' fa-' . htmlspecialchars($n ?: 'star');
// Render a badge's mark: uploaded image if present, else its FA icon.
$badgeMark = function(array $bd) use ($faClass, $vox): string {
  if (!empty($bd['image'])) {
    return '<img src="' . htmlspecialchars($vox->badgeImageUrl($bd['image'])) . '" alt="" class="vox-badge-img">';
  }
  return '<i class="' . $faClass($bd['icon'] ?? 'star') . '"></i>';
};
?>

<p class="uk-text-meta uk-margin-small-bottom">Manage ranks, badges, point rules, and leaderboard views.</p>

<div class="uk-grid-small uk-child-width-1-3@m uk-margin Vox-stat-grid" uk-grid>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Ranks</div><div class="uk-text-large uk-text-bold"><?= count($ranks) ?></div><div class="uk-text-meta">Progression levels</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Badges</div><div class="uk-text-large uk-text-bold"><?= count($badgeDefs) ?></div><div class="uk-text-meta">Available awards</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Leaderboard</div><div class="uk-text-large uk-text-bold"><?= count($leaderboard) ?></div><div class="uk-text-meta"><?= ucfirst($period) ?> period</div>
 </div></div>
</div>

<ul class="uk-subnav uk-subnav-pill uk-margin Vox-inline-tabs" aria-label="Gamification view">
 <li class="<?= $activeTab === 'ranks' ? 'uk-active' : '' ?>"><a href="<?= gTabUrl(['tab' => 'ranks']) ?>">Ranks</a></li>
 <li class="<?= $activeTab === 'badges' ? 'uk-active' : '' ?>"><a href="<?= gTabUrl(['tab' => 'badges']) ?>">Badges</a></li>
 <li class="<?= $activeTab === 'points' ? 'uk-active' : '' ?>"><a href="<?= gTabUrl(['tab' => 'points']) ?>">Points config</a></li>
 <li class="<?= $activeTab === 'leaderboard' ? 'uk-active' : '' ?>"><a href="<?= gTabUrl(['tab' => 'leaderboard']) ?>">Leaderboard</a></li>
</ul>

<?php if ($activeTab === 'ranks'): ?>
 <form method="post" action="" id="ranks-form">
  <?= $csrf->renderInput() ?>
  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel">
   <h3 class="uk-card-title">Ranks</h3>
   <div class="uk-overflow-auto">
   <table class="uk-table uk-table-divider uk-table-middle uk-table-small" id="ranks-table">
    <thead><tr><th>Icon</th><th>Label</th><th>Min points</th><th>Users</th><th></th></tr></thead>
    <tbody>
<?php foreach ($ranks as $rk): ?>
     <tr>
      <td>
       <input type="hidden" name="rank_id[]" value="<?= $rk['id'] ?>">
       <input type="text" name="rank_icon[]" value="<?= htmlspecialchars($rk['icon']) ?>" class="uk-input uk-form-width-small uk-text-monospace" data-vox-icon>
      </td>
      <td><input type="text" name="rank_label[]" value="<?= htmlspecialchars($rk['label']) ?>" class="uk-input"></td>
      <td><input type="number" name="rank_min_points[]" value="<?= $rk['min_points'] ?>" min="0" class="uk-input uk-form-width-small"></td>
      <?php $rkUsers = (int)($rankCounts[$rk['id']] ?? 0); ?>
      <td class="<?= $rkUsers ? 'uk-text-bold' : 'uk-text-muted' ?>"><?= $rkUsers ?: '—' ?></td>
      <td class="uk-text-right"></td>
     </tr>
<?php endforeach ?>
<?php if (!$ranks): ?>
     <tr><td colspan="5"><div class="Vox-empty-state"><div uk-icon="icon:star;ratio:1.6"></div><p class="uk-margin-small-top"><strong>No ranks configured.</strong> Add the first rank to start rewarding active authors.</p></div></td></tr>
<?php endif ?>
    </tbody>
   </table>
   </div>
   <hr>
   <button type="submit" name="submit_ranks" value="1" class="uk-button uk-button-primary"><span uk-icon="icon:check;ratio:0.8"></span> Save all ranks</button>
   <button type="button" onclick="document.getElementById('add-rank').toggleAttribute('hidden')" class="uk-button uk-button-default"><span uk-icon="icon:plus;ratio:0.8"></span> Add rank</button>
   <div id="add-rank" class="uk-grid-small uk-flex-middle uk-margin-small-top" uk-grid hidden>
    <div><input id="nr-label" class="uk-input" placeholder="Label"></div>
    <div><input id="nr-pts" class="uk-input uk-form-width-small" type="number" placeholder="Min pts"></div>
    <div><input id="nr-icon" class="uk-input uk-text-monospace" placeholder="FA icon, e.g. trophy" value="star"></div>
    <div><button type="button" onclick="addRank()" class="uk-button uk-button-primary"><span uk-icon="icon:plus;ratio:0.8"></span> Add</button>
     <button type="button" onclick="document.getElementById('add-rank').setAttribute('hidden','')" class="uk-button uk-button-default">Cancel</button></div>
   </div>
  </div>
 </form>
<?php endif ?>

<?php if ($activeTab === 'badges'):
 // Editing target (from ?edit_badge=ID) or a blank new badge.
 $edit = null;
 foreach ($badgeDefs as $bd) { if ((int)$bd['id'] === $editBadge) { $edit = $bd; break; } }
 $form = $edit ?? ['id'=>0,'badge_key'=>'','label'=>'','icon'=>'star','image'=>'','metric'=>'reviews','threshold'=>1,'description'=>'','enabled'=>1];
 // Human-readable "awarded for" text.
 $criterion = function(array $b) use ($badgeMetrics): string {
   $m = $badgeMetrics[$b['metric']] ?? $b['metric'];
   return $b['metric'] === 'leaderboard_top3' ? $m : $m . ' ≥ ' . (int)$b['threshold'];
 };
?>
 <div uk-grid>
  <!-- Badge list -->
  <div class="uk-width-expand@m">
   <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
    <h3 class="uk-card-title">Badges <span class="uk-text-meta">— awarded automatically when a user meets the rule</span></h3>
    <div class="uk-overflow-auto">
    <table class="uk-table uk-table-divider uk-table-middle uk-table-small uk-margin-remove">
     <thead><tr><th></th><th>Badge</th><th>Awarded for</th><th>Status</th><th class="uk-text-right">Actions</th></tr></thead>
     <tbody>
<?php foreach ($badgeDefs as $bd): ?>
      <tr class="<?= (int)$bd['id'] === $editBadge ? 'uk-background-muted' : '' ?>">
       <td class="vox-badge-mark <?= $bd['enabled'] ? '' : 'uk-text-muted' ?>"><?= $badgeMark($bd) ?></td>
       <td><div class="uk-text-bold"><?= htmlspecialchars($bd['label']) ?></div><code class="uk-text-meta"><?= htmlspecialchars($bd['badge_key']) ?></code><?php if ($bd['description']): ?><div class="uk-text-meta"><?= htmlspecialchars($bd['description']) ?></div><?php endif ?></td>
       <td><span class="uk-label uk-label-success"><?= htmlspecialchars($criterion($bd)) ?></span><div class="uk-text-meta uk-margin-small-top"><?= !empty($bd['image']) ? 'custom image' : 'fa-' . htmlspecialchars($bd['icon']) ?></div></td>
       <td><?= $bd['enabled'] ? '<span class="uk-text-success" uk-icon="icon:check"></span> on' : '<span class="uk-text-muted">off</span>' ?></td>
       <td class="uk-text-right">
        <a href="<?= gTabUrl(['tab'=>'badges']) ?>&edit_badge=<?= $bd['id'] ?>" class="uk-icon-button" uk-icon="pencil" uk-tooltip="Edit"></a>
        <form method="post" action="" class="uk-display-inline">
         <?= $csrf->renderInput() ?>
         <input type="hidden" name="badge_id" value="<?= $bd['id'] ?>">
         <button name="delete_badge" value="1" class="uk-icon-button" uk-icon="trash" uk-tooltip="Delete" onclick="return confirm('Delete badge \'<?= htmlspecialchars($bd['label']) ?>\'? Users who already earned it keep it.')"></button>
        </form>
       </td>
      </tr>
<?php endforeach ?>
<?php if (!$badgeDefs): ?>
      <tr><td colspan="5"><div class="Vox-empty-state"><div uk-icon="icon:star;ratio:1.6"></div><p class="uk-margin-small-top">No badges yet. Create one on the right.</p></div></td></tr>
<?php endif ?>
     </tbody>
    </table>
    </div>
   </div>
  </div>

  <!-- Create / edit form -->
  <div class="uk-width-1-3@m">
   <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-form-panel">
    <h3 class="uk-card-title"><span uk-icon="icon:<?= $edit ? 'pencil' : 'plus-circle' ?>;ratio:0.9" class="uk-text-primary"></span> <?= $edit ? 'Edit badge' : 'New badge' ?></h3>
    <form method="post" action="" enctype="multipart/form-data">
     <?= $csrf->renderInput() ?>
     <input type="hidden" name="badge_id" value="<?= (int)$form['id'] ?>">

     <label class="uk-form-label">Name</label>
     <input name="badge_label" class="uk-input uk-margin-small-bottom" value="<?= htmlspecialchars($form['label']) ?>" placeholder="e.g. Crowd Pleaser" required>

<?php if (!$edit): ?>
     <label class="uk-form-label">Key <span class="uk-text-meta">(optional, auto from name)</span></label>
     <input name="badge_key" class="uk-input uk-text-monospace uk-margin-small-bottom" value="<?= htmlspecialchars($form['badge_key']) ?>" placeholder="crowd_pleaser">
<?php else: ?>
     <label class="uk-form-label">Key</label>
     <input class="uk-input uk-text-monospace uk-margin-small-bottom" value="<?= htmlspecialchars($form['badge_key']) ?>" disabled>
<?php endif ?>

     <label class="uk-form-label">Icon</label>
     <input name="badge_icon" class="uk-input uk-text-monospace uk-margin-small-bottom" value="<?= htmlspecialchars($form['icon']) ?>" placeholder="trophy" data-vox-icon>

     <label class="uk-form-label">Image <span class="uk-text-meta">(optional — shown instead of the icon)</span></label>
<?php if (!empty($form['image'])): ?>
     <div class="uk-flex uk-flex-middle uk-margin-small-bottom" style="gap:10px">
      <img src="<?= htmlspecialchars($vox->badgeImageUrl($form['image'])) ?>" alt="" class="vox-badge-img-lg">
      <label class="uk-text-meta"><input type="checkbox" name="badge_image_remove" value="1" class="uk-checkbox"> Remove image</label>
     </div>
<?php endif ?>
     <input type="file" name="badge_image" accept="image/*" class="uk-margin-small-bottom" style="display:block">

     <label class="uk-form-label">Awarded for</label>
     <select name="badge_metric" id="badge-metric" class="uk-select uk-margin-small-bottom" onchange="voxBadgeMetric()">
<?php foreach ($badgeMetrics as $mk => $ml): ?>
      <option value="<?= $mk ?>" <?= $form['metric'] === $mk ? 'selected' : '' ?>><?= htmlspecialchars($ml) ?></option>
<?php endforeach ?>
     </select>

     <div id="badge-threshold-wrap" class="uk-margin-small-bottom">
      <label class="uk-form-label">Threshold <span class="uk-text-meta">(reach at least)</span></label>
      <input type="number" name="badge_threshold" id="badge-threshold" class="uk-input" min="0" value="<?= (int)$form['threshold'] ?>">
     </div>

     <label class="uk-form-label">Description <span class="uk-text-meta">(optional)</span></label>
     <input name="badge_description" class="uk-input uk-margin-small-bottom" value="<?= htmlspecialchars($form['description']) ?>" placeholder="Shown to users">

     <label class="uk-margin-small-bottom uk-display-block"><input type="checkbox" name="badge_enabled" value="1" class="uk-checkbox" <?= $form['enabled'] ? 'checked' : '' ?>> Enabled (awarded to users)</label>

     <button type="submit" name="submit_badge" value="1" class="uk-button uk-button-primary uk-width-1-1"><span uk-icon="icon:check;ratio:0.8"></span> <?= $edit ? 'Save changes' : 'Create badge' ?></button>
<?php if ($edit): ?>
     <a href="<?= gTabUrl(['tab'=>'badges']) ?>" class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top">Cancel</a>
<?php endif ?>
    </form>
   </div>
  </div>
 </div>

 <script>
 function voxBadgeMetric(){
  var m=document.getElementById('badge-metric').value;
  document.getElementById('badge-threshold-wrap').style.display = (m==='leaderboard_top3') ? 'none' : '';
 }
 voxBadgeMetric();
 </script>
<?php endif ?>

<?php if ($activeTab === 'points'): ?>
 <form method="post" action="">
  <?= $csrf->renderInput() ?>
  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel">
   <h3 class="uk-card-title">Points config</h3>
<?php
 $rows = [
  ['points_post', 'Post review / thread / question', 'Any new top-level entry'],
  ['points_like_received', 'Receive a like', 'On any owned entry'],
  ['points_answer', 'Answer a question', 'Post a reply to a Q&A entry'],
  ['points_best_answer', 'Answer marked as best', 'By question author or moderator'],
 ];
 foreach ($rows as [$k,$label,$desc]): ?>
   <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-top">
    <div><div class="uk-text-bold"><?= $label ?></div><div class="uk-text-meta"><?= $desc ?></div></div>
    <div class="uk-flex uk-flex-middle">
     <span class="uk-text-muted uk-margin-small-right">+</span>
     <input type="number" name="<?= $k ?>" value="<?= $pointsCfg[$k] ?>" min="0" class="uk-input uk-form-width-small uk-text-center">
     <span class="uk-text-muted uk-margin-small-left">pts</span>
    </div>
   </div>
<?php endforeach ?>
  </div>
  <button type="submit" name="submit_points" value="1" class="uk-button uk-button-primary"><span uk-icon="icon:check;ratio:0.8"></span> Save points config</button>
 </form>
<?php endif ?>

<?php if ($activeTab === 'leaderboard'): ?>
 <ul class="uk-subnav uk-subnav-pill uk-margin Vox-inline-tabs" aria-label="Leaderboard period">
<?php foreach (['month'=>'This month','week'=>'This week','all'=>'All time'] as $p=>$pl): ?>
  <li class="<?= $period === $p ? 'uk-active' : '' ?>"><a href="<?= gTabUrl(['tab' => 'leaderboard', 'period' => $p]) ?>"><?= $pl ?></a></li>
<?php endforeach ?>
 </ul>
 <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
  <div class="uk-overflow-auto">
  <table class="uk-table uk-table-divider uk-table-middle uk-table-small">
   <thead><tr><th class="uk-table-shrink">#</th><th>User</th><th>Rank</th><th>Points</th></tr></thead>
   <tbody>
<?php foreach ($leaderboard as $i => $lb): ?>
    <tr class="<?= $lb['user_id']===wire()->user->id ? 'uk-text-emphasis' : '' ?>">
     <td class="uk-text-bold uk-text-muted"><?= $i+1 ?></td>
     <td><?= htmlspecialchars($lb['name']) ?><?php if ($lb['user_id']===wire()->user->id): ?> <span class="uk-text-meta">← you</span><?php endif ?></td>
     <td><?php if (!empty($lb['rank'])): ?><span class="uk-label"><?= htmlspecialchars($lb['rank']['label']) ?></span><?php endif ?></td>
     <td class="uk-text-bold uk-text-primary"><?= number_format($lb['points']) ?></td>
    </tr>
<?php endforeach ?>
<?php if (!$leaderboard): ?>
    <tr><td colspan="4"><div class="Vox-empty-state"><div uk-icon="icon:bolt;ratio:1.6"></div><p class="uk-margin-small-top"><strong>No leaderboard yet.</strong> Users appear here after earning points.</p></div></td></tr>
<?php endif ?>
   </tbody>
  </table>
  </div>
 </div>
<?php endif ?>

<script>
function addRank(){
 var l=document.getElementById('nr-label').value.trim();
 var p=document.getElementById('nr-pts').value;
 var ic=document.getElementById('nr-icon').value.trim();
 if(!l)return;
 var tb=document.querySelector('#ranks-table tbody');
 var tr=document.createElement('tr');
 tr.innerHTML='<td><input type="hidden" name="rank_id[]" value=""><input type="text" name="rank_icon[]" value="'+ic.replace(/"/g,'')+'" class="uk-input uk-form-width-small uk-text-monospace" data-vox-icon></td>'+
 '<td><input type="text" name="rank_label[]" value="'+l.replace(/"/g,'&quot;')+'" class="uk-input"></td>'+
 '<td><input type="number" name="rank_min_points[]" value="'+p+'" min="0" class="uk-input uk-form-width-small"></td>'+
 '<td class="uk-text-muted">&mdash;</td><td></td>';
 tb.appendChild(tr);
 if(window.voxIconPickerScan) window.voxIconPickerScan(tr);
 document.getElementById('add-rank').setAttribute('hidden','');
}
</script>

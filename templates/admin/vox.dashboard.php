<?php namespace ProcessWire;
/**
 * Vox Admin — Dashboard (native AdminThemeUikit markup).
 */
include __DIR__ . '/_vox.design-system.php';

$voxUrl = wire()->config->urls->admin . 'vox/';
$statusLabel = ['published'=>'uk-label-success','pending'=>'uk-label-warning','spam'=>'uk-label-danger'];
$typeLabelCls = ['review'=>'uk-label-success','question'=>'uk-label-warning'];

function fmtTime(string $dt): string {
 $d = time() - strtotime($dt);
 if ($d < 60) return $d . 's ago';
 if ($d < 3600) return round($d/60) . 'm ago';
 if ($d < 86400) return round($d/3600) . 'h ago';
 return date('M j', strtotime($dt));
}

$chart = [];
for ($i = 29; $i >= 0; $i--) { $chart[date('Y-m-d', strtotime("-{$i} days"))] = 0; }
foreach ($activity as $a) { if (isset($chart[$a['day']])) $chart[$a['day']] = (int)$a['cnt']; }
$maxAct = max(max(array_values($chart)), 1);
$hasRecent = count($recent) > 0;
$hasQueue = count($queue) > 0;
$hasTopPages = count($topPages) > 0;
$apiUrl = wire()->config->urls->root . 'vox-api/';
$moderationMode = $vox->cfg('moderation') === 'immediate' ? 'Immediate publish' : 'Approval queue';
$entryTypes = ['review'=>['Reviews','star'],'comment'=>['Comments','comment'],'question'=>['Questions','question'],'thread'=>['Threads','list']];
$csrf = wire()->session->CSRF;
?>

<!-- Header -->
<div class="uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-margin Vox-admin-head">
 <p class="uk-text-meta uk-margin-remove">Moderation, activity, and author engagement at a glance.</p>
 <div>
  <a href="<?= $voxUrl ?>entries/" class="uk-button uk-button-primary"><span uk-icon="icon:list;ratio:0.85"></span> Entries</a>
  <a href="<?= $voxUrl ?>moderation/" class="uk-button uk-button-default"><span uk-icon="icon:warning;ratio:0.85"></span> Queue</a>
  <a href="<?= $voxUrl ?>settings/" class="uk-button uk-button-default"><span uk-icon="icon:cog;ratio:0.85"></span> Settings</a>
 </div>
</div>

<!-- Stat cards -->
<div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-4@m uk-margin Vox-stat-grid" uk-grid>
 <div><a class="uk-card uk-card-default uk-card-small uk-card-body uk-display-block uk-link-toggle" href="<?= $voxUrl ?>entries/">
  <div class="uk-flex uk-flex-between"><span class="uk-text-meta uk-text-uppercase">Total entries</span><span uk-icon="icon:comment;ratio:0.8"></span></div>
  <div class="uk-text-large uk-text-bold"><?= number_format($stats['total']) ?></div>
  <div class="uk-text-meta"><?= $stats['pending'] ? '+' . number_format($stats['pending']) . ' pending' : 'Nothing pending' ?></div>
 </a></div>
 <div><a class="uk-card uk-card-default uk-card-small uk-card-body uk-display-block uk-link-toggle" href="<?= $voxUrl ?>moderation/">
  <div class="uk-flex uk-flex-between"><span class="uk-text-meta uk-text-uppercase">Pending review</span><span uk-icon="icon:clock;ratio:0.8"></span></div>
  <div class="uk-text-large uk-text-bold"><?= $stats['pending'] ?></div>
  <div class="uk-text-meta"><?= $stats['pending_reviews'] ?> reviews · <?= $stats['pending_comments'] ?> comments</div>
 </a></div>
 <div><a class="uk-card uk-card-default uk-card-small uk-card-body uk-display-block uk-link-toggle" href="<?= $voxUrl ?>moderation/">
  <div class="uk-flex uk-flex-between"><span class="uk-text-meta uk-text-uppercase">Open reports</span><span uk-icon="icon:flag;ratio:0.8"></span></div>
  <div class="uk-text-large uk-text-bold"><?= $stats['reports'] ?></div>
  <div class="uk-text-meta"><?= $stats['reports'] ? 'Awaiting review' : 'No reports' ?></div>
 </a></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-flex uk-flex-between"><span class="uk-text-meta uk-text-uppercase">Active users</span><span uk-icon="icon:users;ratio:0.8"></span></div>
  <div class="uk-text-large uk-text-bold"><?= $stats['users'] ?></div>
  <div class="uk-text-meta">Unique authors</div>
 </div></div>
</div>

<div uk-grid>
 <!-- Left column -->
 <div class="uk-width-expand@m">

  <!-- Activity chart -->
  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel Vox-dashboard-breakdown">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Activity <span class="uk-text-meta">· last 30 days</span></h3>
    <strong><?= number_format(array_sum($chart)) ?> entries</strong>
   </div>
   <div class="Vox-chart">
<?php foreach ($chart as $day => $cnt): $h = $cnt ? max(8, round(100*$cnt/$maxAct)) : 3; ?>
    <span class="Vox-chart__bar<?= $cnt ? ' Vox-chart__bar--active' : '' ?>" style="height:<?= $h ?>%" title="<?= $day ?>: <?= $cnt ?>"></span>
<?php endforeach ?>
   </div>
   <div class="uk-flex uk-flex-between uk-text-meta uk-margin-small-top">
    <span><?= date('M j', strtotime('-29 days')) ?></span>
    <span><?= date('M j', strtotime('-14 days')) ?></span>
    <span>Today</span>
   </div>
  </div>

  <!-- Recent entries -->
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Recent entries</h3>
    <a href="<?= $voxUrl ?>entries/" class="uk-text-meta">View all <span uk-icon="icon:arrow-right;ratio:0.75"></span></a>
   </div>
<?php if ($hasRecent): ?>
   <div class="uk-overflow-auto">
   <table class="uk-table uk-table-divider uk-table-middle uk-table-small">
    <thead><tr><th>Author</th><th>Type</th><th>Page</th><th>Status</th><th>Time</th><th class="uk-text-right"></th></tr></thead>
    <tbody>
<?php foreach ($recent as $e): ?>
    <tr>
     <td><strong><?= htmlspecialchars($e['author_name']) ?></strong><?php if ($e['author_rank']): ?> <span class="uk-text-meta">· <?= htmlspecialchars($e['author_rank']) ?></span><?php endif ?></td>
     <td><span class="uk-label <?= $typeLabelCls[$e['type']] ?? '' ?>"><?= ucfirst($e['type']) ?></span></td>
     <td><span class="uk-text-meta"><?= htmlspecialchars($e['page_name'] ?? "Page #{$e['page_id']}") ?></span></td>
     <td><span class="uk-label <?= $statusLabel[$e['status']] ?? '' ?>"><?= ucfirst($e['status']) ?></span></td>
     <td><span class="uk-text-meta"><?= fmtTime($e['created']) ?></span></td>
     <td class="uk-text-right uk-text-nowrap">
<?php if ($e['status'] === 'pending'): ?>
      <form method="post" action="<?= $voxUrl ?>ajax/" class="uk-display-inline">
       <?= $csrf->renderInput() ?>
       <input type="hidden" name="id" value="<?= $e['id'] ?>">
       <button name="action" value="approve" class="uk-icon-button" uk-icon="check" uk-tooltip="Approve"></button>
       <button name="action" value="spam" class="uk-icon-button" uk-icon="close" uk-tooltip="Spam"></button>
      </form>
<?php else: ?>
      <a href="<?= $voxUrl ?>entry/?id=<?= $e['id'] ?>" class="uk-icon-button" uk-icon="pencil" uk-tooltip="Edit"></a>
<?php endif ?>
     </td>
    </tr>
<?php endforeach ?>
    </tbody>
   </table>
   </div>
<?php else: ?>
   <div class="Vox-empty-state">
    <div uk-icon="icon:comment;ratio:1.1"></div>
    <p class="uk-margin-small-top"><strong>No entries yet.</strong> Embed the public widgets to start filling this dashboard.</p>
   </div>
<?php endif ?>
  </div>

 </div>

 <!-- Right column -->
 <div class="uk-width-1-3@m">

  <!-- Entries by type -->
  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel Vox-dashboard-breakdown">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Entries by type</h3>
    <span class="uk-text-meta"><?= number_format($typeTotal = array_sum($breakdown)) ?> total</span>
   </div>
<?php foreach ($entryTypes as $t => [$label, $icon]):
   $cnt = (int)($breakdown[$t] ?? 0);
   $pct = $typeTotal ? round(100 * $cnt / $typeTotal) : 0; ?>
   <div class="uk-flex uk-flex-between uk-text-small Vox-dashboard-meter-row">
    <span><i uk-icon="icon:<?= $icon ?>;ratio:0.72"></i> <?= $label ?></span><span class="uk-text-bold"><?= number_format($cnt) ?></span>
   </div>
   <progress class="uk-progress uk-margin-remove" value="<?= $pct ?>" max="100"></progress>
<?php endforeach ?>
   <div class="uk-flex uk-flex-between uk-margin-small-top">
    <span class="uk-text-meta">Avg rating <strong class="uk-text-emphasis"><?= number_format($avgRating, 1) ?> ★</strong></span>
    <span class="uk-text-meta">Recommend <strong class="uk-text-emphasis"><?= $recRate ?>%</strong></span>
   </div>
  </div>

  <!-- Pending queue -->
  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Pending</h3>
    <a href="<?= $voxUrl ?>moderation/" class="uk-text-meta">All <span uk-icon="icon:arrow-right;ratio:0.75"></span></a>
   </div>
<?php if ($hasQueue): ?>
   <ul class="uk-list uk-list-divider">
<?php foreach ($queue as $e): ?>
    <li class="uk-flex uk-flex-between uk-flex-middle">
     <div class="uk-width-expand uk-margin-small-right uk-text-truncate">
      <div class="uk-text-truncate"><?= htmlspecialchars(mb_substr($e['body'],0,70)) ?></div>
      <div class="uk-text-meta"><?= ucfirst($e['type']) ?> · <?= htmlspecialchars($e['page_name'] ?? "Page #{$e['page_id']}") ?></div>
     </div>
     <form method="post" action="<?= $voxUrl ?>ajax/" class="uk-text-nowrap">
      <?= $csrf->renderInput() ?>
      <input type="hidden" name="id" value="<?= $e['id'] ?>">
      <button name="action" value="approve" class="uk-icon-button" uk-icon="check" uk-tooltip="Approve"></button>
      <button name="action" value="spam" class="uk-icon-button" uk-icon="close" uk-tooltip="Spam"></button>
     </form>
    </li>
<?php endforeach ?>
   </ul>
<?php else: ?>
   <div class="Vox-empty-state"><div uk-icon="icon:check;ratio:1"></div> <span>Queue is clear</span></div>
<?php endif ?>
  </div>

  <!-- Top pages -->
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel Vox-dashboard-top-pages">
   <h3 class="uk-card-title uk-margin-small-bottom">Top pages</h3>
<?php if ($hasTopPages): $maxCnt = max(array_column($topPages, 'cnt') ?: [1]); ?>
<?php foreach ($topPages as $i => $pg): ?>
   <div class="uk-flex uk-flex-between uk-text-small Vox-dashboard-meter-row">
    <span class="uk-text-truncate"><?= $i+1 ?>. <?php if ($pg['url']): ?><a href="<?= $pg['url'] ?>" target="_blank" class="uk-link-muted"><?= htmlspecialchars($pg['title']) ?></a><?php else: ?><?= htmlspecialchars($pg['title']) ?><?php endif ?></span>
    <em class="uk-text-meta"><?= $pg['cnt'] ?></em>
   </div>
   <progress class="uk-progress uk-margin-remove" value="<?= round(100*$pg['cnt']/$maxCnt) ?>" max="100"></progress>
<?php endforeach ?>
<?php else: ?>
   <div class="Vox-empty-state"><div uk-icon="icon:world;ratio:1"></div> <span>No active pages yet</span></div>
<?php endif ?>
  </div>

 </div>
</div>

<script>
(function(){
 document.querySelectorAll('form[action*="/ajax/"]').forEach(function(form){
  form.addEventListener('submit', function(e){
   e.preventDefault();
   var fd = new FormData(form);
   fetch(form.action, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success) location.reload();});
  });
 });
})();
</script>

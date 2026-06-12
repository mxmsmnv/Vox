<?php namespace ProcessWire;
/**
 * Vox Admin — Moderation (native AdminThemeUikit markup).
 */
include __DIR__ . '/_vox.design-system.php';

$voxUrl = wire()->config->urls->admin . 'vox/';
$csrf = wire()->session->CSRF;

function mTime(string $dt): string {
 $d = time() - strtotime($dt);
 if ($d < 3600) return round($d/60).'m ago';
 return date('M j H:i', strtotime($dt));
}
function mPageUrl(array $filters, int $page): string {
 return '?' . http_build_query(array_filter($filters + ['p' => $page]));
}
$activeTab = wire()->input->get('tab') === 'reports' ? 'reports' : 'pending';
function mListUrl(array $filters, string $tab): string {
    $next = array_filter(array_merge($filters, ['tab' => $tab, 'p' => 1]));
    return '?' . http_build_query($next);
}
function mTypeUrl(array $filters, ?string $type): string {
    $next = array_filter(array_merge($filters, ['tab' => 'pending', 'type' => $type, 'p' => 1]), fn($v, $k) => $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
    return '?' . http_build_query($next);
}
$typeLabel = ['review'=>'uk-label-success','question'=>'uk-label-warning'];
?>

<p class="uk-text-meta uk-margin-small-bottom">Review pending entries and handle reports from one queue.</p>

<!-- Metric tiles -->
<div class="uk-grid-small uk-child-width-1-3@m uk-margin Vox-stat-grid" uk-grid>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Pending queue</div>
  <div class="uk-text-large uk-text-bold"><?= (int)$pendingTotal ?></div>
  <div class="uk-text-meta"><?= count($pending) ?> loaded now</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Reports</div>
  <div class="uk-text-large uk-text-bold"><?= count($reports) ?></div>
  <div class="uk-text-meta">Open reports</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Filters</div>
  <div class="uk-text-large uk-text-bold"><?= count(array_filter($filters)) ?></div>
  <div class="uk-text-meta">Active constraints</div>
 </div></div>
</div>

<!-- Pending / Reports switch -->
<ul class="uk-subnav uk-subnav-pill uk-margin Vox-inline-tabs" aria-label="Moderation queue">
 <li class="<?= $activeTab === 'pending' ? 'uk-active' : '' ?>"><a href="<?= mListUrl($filters, 'pending') ?>">Pending<?= $pendingTotal ? ' <span class="uk-badge">'.(int)$pendingTotal.'</span>' : '' ?></a></li>
 <li class="<?= $activeTab === 'reports' ? 'uk-active' : '' ?>"><a href="<?= mListUrl($filters, 'reports') ?>">Reports<?= $reports ? ' <span class="uk-badge">'.count($reports).'</span>' : '' ?></a></li>
</ul>

<?php if ($activeTab === 'pending'): ?>
 <!-- Filters -->
 <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-filter-panel">
  <form method="get" action="">
   <ul class="uk-subnav uk-subnav-pill uk-margin-small-bottom Vox-inline-tabs" aria-label="Entry type filter">
    <li class="<?= ($filters['type'] ?? '') === '' ? 'uk-active' : '' ?>"><a href="<?= mTypeUrl($filters, null) ?>">All types</a></li>
<?php foreach (['review','comment','question','thread'] as $t): ?>
    <li class="<?= ($filters['type'] ?? '') === $t ? 'uk-active' : '' ?>"><a href="<?= mTypeUrl($filters, $t) ?>"><?= ucfirst($t) ?></a></li>
<?php endforeach ?>
   </ul>
   <div class="uk-grid-small uk-flex-middle" uk-grid>
    <div class="uk-width-expand@s"><input name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" class="uk-input" placeholder="Search&hellip;"></div>
    <div class="uk-width-auto Vox-action-row">
     <button type="submit" class="uk-button uk-button-primary">Filter</button>
     <a href="<?= $voxUrl ?>moderation/" class="uk-button uk-button-default"><span uk-icon="icon:refresh;ratio:0.85"></span> Clear</a>
    </div>
   </div>
  </form>
 </div>

 <!-- Mass action form -->
 <form method="post" action="" id="mass-form">
  <?= $csrf->renderInput() ?>
  <input type="hidden" name="mass_action" id="mass-action-val">
  <div id="mass-ids"></div>
 </form>

 <div id="mass-bar" class="uk-card uk-card-default uk-card-small uk-card-body uk-margin uk-flex uk-flex-middle uk-flex-wrap uk-grid-small uk-hidden Vox-bulk-bar" uk-grid>
  <div class="uk-width-expand"><span class="uk-label" id="mass-count">0 selected</span></div>
  <div class="uk-width-auto">
   <button type="button" class="uk-button uk-button-primary" onclick="massAct('approve')">Approve all</button>
   <button type="button" class="uk-button uk-button-danger" onclick="massAct('reject')">Reject all</button>
   <button type="button" class="uk-button uk-button-default" onclick="massAct('spam')">Spam all</button>
  </div>
 </div>

 <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
  <div class="uk-overflow-auto">
  <table class="uk-table uk-table-divider uk-table-middle uk-table-small">
   <thead><tr>
    <th class="uk-table-shrink"><input type="checkbox" id="ca" class="uk-checkbox" onchange="toggleAll(this)"></th>
    <th>Author</th><th>Type</th><th>Content</th><th>Page</th><th>Time</th><th class="uk-text-right">Actions</th>
   </tr></thead>
   <tbody>
<?php foreach ($pending as $e): ?>
   <tr id="pr-<?= $e['id'] ?>">
    <td><input type="checkbox" class="uk-checkbox rc" value="<?= $e['id'] ?>" onchange="upd()"></td>
    <td>
     <strong><?= htmlspecialchars($e['author_name']) ?></strong>
     <?php if ($e['author_rank']): ?><div class="uk-text-meta"><?= htmlspecialchars($e['author_rank']) ?></div><?php endif ?>
    </td>
    <td><span class="uk-label <?= $typeLabel[$e['type']] ?? '' ?>"><?= ucfirst($e['type']) ?></span></td>
    <td>
     <a href="<?= $voxUrl ?>entry/?id=<?= $e['id'] ?>"><?= htmlspecialchars(mb_substr($e['body'],0,80)) ?></a>
     <?php if (!empty($e['has_stopword'])): ?> <span class="uk-label uk-label-danger"><span uk-icon="icon:warning;ratio:0.7"></span> Stop word</span><?php endif ?>
    </td>
    <td><span class="uk-text-meta"><?= htmlspecialchars($e['page_name'] ?? '') ?></span></td>
    <td><span class="uk-text-meta"><?= mTime($e['created']) ?></span></td>
    <td class="uk-text-right uk-text-nowrap">
      <button type="button" class="uk-icon-button" uk-icon="check" uk-tooltip="Approve" onclick="qa('approve',<?= $e['id'] ?>)"></button>
      <a href="<?= $voxUrl ?>entry/?id=<?= $e['id'] ?>" class="uk-icon-button" uk-icon="pencil" uk-tooltip="Edit"></a>
      <button type="button" class="uk-icon-button" uk-icon="ban" uk-tooltip="Reject" onclick="qa('reject',<?= $e['id'] ?>)"></button>
    </td>
   </tr>
<?php endforeach ?>
<?php if (!$pending): ?>
   <tr><td colspan="7">
    <div class="Vox-empty-state">
     <div uk-icon="icon:check;ratio:1.6"></div>
     <p class="uk-margin-small-top"><strong>Queue is clear.</strong> No entries are waiting for moderation.</p>
    </div>
   </td></tr>
<?php endif ?>
   </tbody>
  </table>
  </div>
 </div>
 <div class="uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-margin-small-top">
  <span class="uk-text-meta">Showing <span data-vox-shown><?= count($pending) ?></span> of <span data-vox-total><?= number_format($pendingTotal) ?></span></span>
<?php if ($pendingPages > 1): ?>
  <ul class="uk-pagination uk-margin-remove">
   <?php if ($currPage > 1): ?><li><a href="<?= mPageUrl($filters, $currPage-1) ?>"><span uk-pagination-previous></span></a></li><?php endif ?>
   <?php for ($i = max(1,$currPage-2); $i <= min($pendingPages,$currPage+2); $i++): ?>
   <li class="<?= $i===$currPage?'uk-active':'' ?>"><a href="<?= mPageUrl($filters,$i) ?>"><?= $i ?></a></li>
   <?php endfor ?>
   <?php if ($currPage < $pendingPages): ?><li><a href="<?= mPageUrl($filters,$currPage+1) ?>"><span uk-pagination-next></span></a></li><?php endif ?>
  </ul>
<?php endif ?>
 </div>
<?php else: ?>

 <!-- Reports tab -->
 <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
  <div class="uk-overflow-auto">
  <table class="uk-table uk-table-divider uk-table-middle uk-table-small">
   <thead><tr><th>Entry preview</th><th>Reporter</th><th>Reason</th><th>Date</th><th class="uk-text-right">Actions</th></tr></thead>
   <tbody>
<?php foreach ($reports as $r): ?>
   <tr id="rr-<?= $r['id'] ?>">
    <td>
     <div><?= htmlspecialchars(mb_substr($r['entry_body'],0,80)) ?></div>
     <div class="uk-text-meta"><?= ucfirst($r['entry_type']) ?> · <?= htmlspecialchars($r['page_name'] ?? '') ?></div>
    </td>
    <td><span class="uk-text-meta"><?= htmlspecialchars($r['reporter_name'] ?? 'Guest') ?></span></td>
    <td><span class="uk-label uk-label-warning"><?= htmlspecialchars(mb_substr($r['reason'],0,30)) ?></span></td>
    <td><span class="uk-text-meta"><?= date('M j', strtotime($r['created'])) ?></span></td>
    <td class="uk-text-right uk-text-nowrap">
     <form method="post" action="" class="uk-display-inline">
      <?= $csrf->renderInput() ?>
      <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
      <button name="report_action" value="delete_entry" class="uk-icon-button" uk-icon="trash" uk-tooltip="Delete reported entry" onclick="return confirm('Delete the reported entry?')"></button>
      <button name="report_action" value="dismiss" class="uk-icon-button" uk-icon="close" uk-tooltip="Dismiss"></button>
     </form>
    </td>
   </tr>
<?php endforeach ?>
<?php if (!$reports): ?>
   <tr><td colspan="5">
    <div class="Vox-empty-state">
     <div uk-icon="icon:flag;ratio:1.6"></div>
     <p class="uk-margin-small-top"><strong>No open reports.</strong> Reported entries will appear here for review.</p>
    </div>
   </td></tr>
<?php endif ?>
   </tbody>
  </table>
  </div>
 </div>
<?php endif ?>

<script>
var ajaxUrl='<?= $voxUrl ?>ajax/';
var csrfName='<?= $csrf->getTokenName() ?>',csrfValue='<?= $csrf->getTokenValue() ?>';
function toggleAll(cb){document.querySelectorAll('.rc').forEach(c=>{c.checked=cb.checked;});upd();}
function upd(){var n=document.querySelectorAll('.rc:checked').length;document.getElementById('mass-bar').classList.toggle('uk-hidden',!n);document.getElementById('mass-count').textContent=n+' selected';}
function massAct(action){document.getElementById('mass-action-val').value=action;var ids='';document.querySelectorAll('.rc:checked').forEach(c=>ids+='<input type="hidden" name="ids[]" value="'+c.value+'">');document.getElementById('mass-ids').innerHTML=ids;document.getElementById('mass-form').submit();}
function qa(action,id){var fd=new FormData();fd.append('action',action);fd.append('id',id);fd.append(csrfName,csrfValue);fetch(ajaxUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json()).then(d=>{if(d.success){var row=document.getElementById('pr-'+id);if(row){row.remove();voxDecCount();}}});}
function voxDecCount(){['[data-vox-shown]','[data-vox-total]'].forEach(function(sel){var el=document.querySelector(sel);if(el){var n=parseInt(el.textContent.replace(/[^0-9]/g,''),10)||0;el.textContent=Math.max(0,n-1);}});}
</script>

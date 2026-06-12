<?php namespace ProcessWire;
/**
 * Vox Admin — Entries list (native AdminThemeUikit markup).
 */
include __DIR__ . '/_vox.design-system.php';

$voxUrl = wire()->config->urls->admin . 'vox/';
$csrf = wire()->session->CSRF;

function eTime(string $dt): string {
 $d = time() - strtotime($dt);
 if ($d < 3600) return round($d/60) . 'm ago';
 if ($d < 86400) return round($d/3600) . 'h ago';
 return date('M j, Y', strtotime($dt));
}
function pageUrl(array $filters, int $page): string {
 return '?' . http_build_query(array_filter($filters + ['p' => $page]));
}
function listUrl(array $filters, array $updates): string {
 $next = array_filter(array_merge($filters, $updates), fn($v, $k) => $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
 return '?' . http_build_query($next);
}
$statusOptions = ['published'=>'Published','pending'=>'Pending','spam'=>'Spam'];
$typeOptions   = ['review'=>'Reviews','comment'=>'Comments','question'=>'Questions','thread'=>'Threads'];
$statusLabel   = ['published'=>'uk-label-success','pending'=>'uk-label-warning','spam'=>'uk-label-danger'];
$typeLabelCls  = ['review'=>'uk-label-success','question'=>'uk-label-warning'];
?>

<div class="uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-margin-small-bottom Vox-admin-head">
 <p class="uk-text-meta uk-margin-remove">Filter, review, and moderate community entries from one place.</p>
 <a href="?<?= http_build_query(array_filter($filters)) ?>&export=csv" class="uk-button uk-button-default">Export CSV</a>
</div>

<!-- Metric tiles -->
<div class="uk-grid-small uk-child-width-1-3@m uk-margin Vox-stat-grid" uk-grid>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Total entries</div>
  <div class="uk-text-large uk-text-bold"><?= number_format($total) ?></div>
  <div class="uk-text-meta">All matching records</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Visible now</div>
  <div class="uk-text-large uk-text-bold"><?= count($entries) ?></div>
  <div class="uk-text-meta">This page</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Filters</div>
  <div class="uk-text-large uk-text-bold"><?= count(array_filter($filters)) ?></div>
  <div class="uk-text-meta">Active constraints</div>
 </div></div>
</div>

<!-- Filters -->
<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-filter-panel">
 <form method="get" action="" id="filter-form">
  <ul class="uk-subnav uk-subnav-pill uk-margin-small-bottom Vox-inline-tabs" aria-label="Status filter">
   <li class="<?= ($filters['status'] ?? '') === '' ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['status' => null, 'p' => 1]) ?>">All status</a></li>
<?php foreach ($statusOptions as $status => $label): ?>
   <li class="<?= ($filters['status'] ?? '') === $status ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['status' => $status, 'p' => 1]) ?>"><?= $label ?></a></li>
<?php endforeach ?>
  </ul>
  <ul class="uk-subnav uk-subnav-pill uk-margin-small-bottom Vox-inline-tabs" aria-label="Type filter">
   <li class="<?= ($filters['type'] ?? '') === '' ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['type' => null, 'p' => 1]) ?>">All types</a></li>
<?php foreach ($typeOptions as $type => $label): ?>
   <li class="<?= ($filters['type'] ?? '') === $type ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['type' => $type, 'p' => 1]) ?>"><?= $label ?></a></li>
<?php endforeach ?>
  </ul>
  <ul class="uk-subnav uk-subnav-pill uk-margin-small-bottom Vox-inline-tabs" aria-label="Period filter">
   <li class="<?= ($filters['period'] ?? '') === '' ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['period' => null, 'p' => 1]) ?>">All time</a></li>
   <li class="<?= ($filters['period'] ?? '') === 'today' ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['period' => 'today', 'p' => 1]) ?>">Today</a></li>
   <li class="<?= ($filters['period'] ?? '') === 'week' ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['period' => 'week', 'p' => 1]) ?>">Week</a></li>
   <li class="<?= ($filters['period'] ?? '') === 'month' ? 'uk-active' : '' ?>"><a href="<?= listUrl($filters, ['period' => 'month', 'p' => 1]) ?>">Month</a></li>
  </ul>
  <div class="uk-grid-small uk-flex-middle" uk-grid>
   <div class="uk-width-expand@s"><input id="vox-entry-q" type="search" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" class="uk-input" placeholder="Search text&hellip;"></div>
   <div class="uk-width-auto Vox-action-row">
    <button type="submit" class="uk-button uk-button-primary">Filter</button>
    <a href="<?= $voxUrl ?>entries/" class="uk-button uk-button-default"><span uk-icon="icon:refresh;ratio:0.85"></span> Clear</a>
   </div>
  </div>
 </form>
</div>

<form method="post" action="" id="mass-form">
 <?= $csrf->renderInput() ?>
 <input type="hidden" name="mass_action" id="mass-action-val">
 <div id="mass-ids"></div>

 <div id="mass-bar" class="uk-card uk-card-default uk-card-small uk-card-body uk-margin uk-flex uk-flex-middle uk-flex-wrap uk-grid-small uk-hidden Vox-bulk-bar" uk-grid>
  <div class="uk-width-expand"><span class="uk-label" id="mass-count">0 selected</span></div>
  <div class="uk-width-auto">
   <button type="button" class="uk-button uk-button-primary" onclick="massSubmit('approve')">Approve</button>
   <button type="button" class="uk-button uk-button-default" onclick="massSubmit('spam')">Spam</button>
   <button type="button" class="uk-button uk-button-danger" onclick="massSubmit('delete')">Delete</button>
  </div>
 </div>

 <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
  <div class="uk-overflow-auto">
  <table class="uk-table uk-table-divider uk-table-middle uk-table-small">
   <thead><tr>
    <th class="uk-table-shrink"><input type="checkbox" id="ca" class="uk-checkbox" onchange="toggleAll(this)"></th>
    <th>Author</th><th>Type</th><th>Content</th><th>Page</th><th>Status</th><th>Date</th><th class="uk-text-right">Actions</th>
   </tr></thead>
   <tbody>
<?php foreach ($entries as $e): ?>
   <tr id="erow-<?= $e['id'] ?>">
    <td><input type="checkbox" class="uk-checkbox rc" value="<?= $e['id'] ?>" onchange="upd()"></td>
    <td>
     <strong><?= htmlspecialchars($e['author_name']) ?></strong>
     <?php if ($e['author_rank']): ?><div class="uk-text-meta"><?= htmlspecialchars($e['author_rank']) ?></div><?php endif ?>
    </td>
    <td><span class="uk-label <?= $typeLabelCls[$e['type']] ?? '' ?>"><?= ucfirst($e['type']) ?></span></td>
    <td>
     <a href="<?= $voxUrl ?>entry/?id=<?= $e['id'] ?>"><?= htmlspecialchars(mb_substr($e['body'],0,100)) ?></a>
     <?php if (!empty($e['has_stopword'])): ?> <span class="uk-label uk-label-danger">Stop word</span><?php endif ?>
    </td>
    <td>
     <?php
      $ePage  = (int)$e['page_id'] ? wire()->pages->get((int)$e['page_id']) : null;
      $ePgUrl = ($ePage && $ePage->id && $ePage->viewable()) ? $ePage->url : '';
      $ePgLbl = $e['page_name'] ?? ($ePage && $ePage->id ? $ePage->title : "Page #{$e['page_id']}");
     ?>
     <?php if ($ePgUrl): ?>
      <a href="<?= $ePgUrl ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($ePgLbl) ?>"><?= htmlspecialchars($ePgLbl) ?> <span uk-icon="icon:link;ratio:0.7"></span></a>
     <?php else: ?>
      <span class="uk-text-meta"><?= htmlspecialchars($ePgLbl) ?></span>
     <?php endif ?>
    </td>
    <td><span class="uk-label <?= $statusLabel[$e['status']] ?? '' ?>"><?= ucfirst($e['status']) ?></span></td>
    <td><span class="uk-text-meta"><?= eTime($e['created']) ?></span></td>
    <td class="uk-text-right uk-text-nowrap">
<?php if ($e['status'] === 'pending'): ?>
      <button type="button" class="uk-icon-button" uk-icon="check" uk-tooltip="Approve" onclick="quickAct('approve',<?= $e['id'] ?>)"></button>
<?php endif ?>
      <a href="<?= $voxUrl ?>entry/?id=<?= $e['id'] ?>" class="uk-icon-button" uk-icon="pencil" uk-tooltip="Edit"></a>
      <button type="button" class="uk-icon-button" uk-icon="trash" uk-tooltip="Delete" onclick="if(confirm('Delete this entry?'))quickAct('delete',<?= $e['id'] ?>)"></button>
    </td>
   </tr>
<?php endforeach ?>
<?php if (!$entries): ?>
   <tr><td colspan="8">
    <div class="Vox-empty-state">
     <div uk-icon="icon:comments;ratio:1.6"></div>
     <p class="uk-margin-small-top"><strong>No entries found.</strong> Adjust filters or wait for the first Vox submissions.</p>
    </div>
   </td></tr>
<?php endif ?>
   </tbody>
  </table>
  </div>
 </div>
</form>

<div class="uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-margin-small-top">
 <span class="uk-text-meta">Showing <span data-vox-shown><?= count($entries) ?></span> of <span data-vox-total><?= number_format($total) ?></span></span>
<?php if ($pages > 1): ?>
 <ul class="uk-pagination uk-margin-remove">
  <?php if ($currPage > 1): ?><li><a href="<?= pageUrl($filters, $currPage-1) ?>"><span uk-pagination-previous></span></a></li><?php endif ?>
  <?php for ($i = max(1,$currPage-2); $i <= min($pages,$currPage+2); $i++): ?>
  <li class="<?= $i===$currPage?'uk-active':'' ?>"><a href="<?= pageUrl($filters,$i) ?>"><?= $i ?></a></li>
  <?php endfor ?>
  <?php if ($currPage < $pages): ?><li><a href="<?= pageUrl($filters,$currPage+1) ?>"><span uk-pagination-next></span></a></li><?php endif ?>
 </ul>
<?php endif ?>
</div>

<script>
var ajaxUrl = '<?= $voxUrl ?>ajax/';
var csrfName = '<?= $csrf->getTokenName() ?>';
var csrfValue = '<?= $csrf->getTokenValue() ?>';
function toggleAll(cb){ document.querySelectorAll('.rc').forEach(c=>{c.checked=cb.checked;}); upd(); }
function upd(){ var n=document.querySelectorAll('.rc:checked').length; var bar=document.getElementById('mass-bar'); if(n){bar.classList.remove('uk-hidden');}else{bar.classList.add('uk-hidden');} document.getElementById('mass-count').textContent=n+' selected'; }
function massSubmit(action){ document.getElementById('mass-action-val').value=action; var ids=''; document.querySelectorAll('.rc:checked').forEach(c=>ids+='<input type="hidden" name="ids[]" value="'+c.value+'">'); document.getElementById('mass-ids').innerHTML=ids; document.getElementById('mass-form').submit(); }
function quickAct(action,id){
 var fd=new FormData(); fd.append('action',action); fd.append('id',id); fd.append(csrfName,csrfValue);
 fetch(ajaxUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json()).then(d=>{
 if(d.success){var row=document.getElementById('erow-'+id);if(row){row.remove();['[data-vox-shown]','[data-vox-total]'].forEach(function(sel){var el=document.querySelector(sel);if(el){var n=parseInt(el.textContent.replace(/[^0-9]/g,''),10)||0;el.textContent=Math.max(0,n-1);}});}}
 });
}
</script>

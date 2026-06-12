<?php namespace ProcessWire;
/**
 * Vox Admin — Edit Entry (native AdminThemeUikit markup).
 */
include __DIR__ . '/_vox.design-system.php';

$voxUrl = wire()->config->urls->admin . 'vox/';
$csrf = wire()->session->CSRF;
$type = $entry['type'];
$statusLabel = ['published'=>'uk-label-success','pending'=>'uk-label-warning','spam'=>'uk-label-danger'];

$userId = (int)$entry['user_id'];
$uName = $entry['author_name'];
$rank = $userId ? $vox->getUserRank($userId) : null;
$pwPage = wire()->pages->get((int)$entry['page_id']);
$pageName = ($pwPage && $pwPage->id) ? $pwPage->title : "Page #{$entry['page_id']}";
$activeTab = wire()->input->get('tab');
if (!in_array($activeTab, ['edit', 'details', 'history'], true)) $activeTab = 'edit';
function entryTabUrl(int $id, string $tab): string { return '?' . http_build_query(['id' => $id, 'tab' => $tab]); }
$authorInitial = mb_strtoupper(mb_substr(trim($uName ?: 'G'), 0, 1));
$typeIcon = ['review'=>'star','question'=>'question','thread'=>'list','comment'=>'comment'][$type] ?? 'comment';
$statusTone = ['published'=>'success','pending'=>'warning','spam'=>'danger'][$entry['status']] ?? 'neutral';
$pageUrl = ($pwPage && $pwPage->id && $pwPage->viewable()) ? $pwPage->url : '';
?>

<ul class="uk-breadcrumb">
 <li><a href="<?= $voxUrl ?>entries/">Entries</a></li>
 <li><span>Edit #<?= $entry['id'] ?></span></li>
</ul>

<div class="Vox-entry-hero uk-margin">
 <div class="Vox-entry-hero__main">
  <div class="Vox-entry-avatar"><?= htmlspecialchars($authorInitial) ?></div>
  <div class="Vox-entry-title">
   <div class="Vox-entry-kicker">
    <span uk-icon="icon:<?= $typeIcon ?>;ratio:0.82"></span>
    <span><?= ucfirst($type) ?></span>
    <span>Entry #<?= (int)$entry['id'] ?></span>
   </div>
   <h2 class="uk-h3 uk-margin-remove"><?= htmlspecialchars($pageName) ?></h2>
   <div class="Vox-entry-meta">
    <span><?= htmlspecialchars($uName) ?></span>
    <span><?= date('M j, Y H:i', strtotime($entry['created'])) ?></span>
    <code><?= htmlspecialchars($entry['ip'] ?? '') ?></code>
   </div>
  </div>
 </div>
 <div class="Vox-entry-hero__side">
  <span class="Vox-status-pill Vox-status-pill--<?= $statusTone ?>" id="hdr-status"><?= ucfirst($entry['status']) ?></span>
  <div class="Vox-entry-actions">
   <?php if ($pageUrl): ?><a href="<?= $pageUrl ?>" target="_blank" rel="noopener" class="uk-button uk-button-default"><span uk-icon="icon:link;ratio:0.8"></span> View page</a><?php endif ?>
   <a href="<?= $voxUrl ?>entries/" class="uk-button uk-button-default"><span uk-icon="icon:arrow-left;ratio:0.8"></span> Back</a>
  </div>
 </div>
</div>

<ul class="uk-subnav uk-subnav-pill uk-margin Vox-inline-tabs" aria-label="Entry view">
 <li class="<?= $activeTab === 'edit' ? 'uk-active' : '' ?>"><a href="<?= entryTabUrl((int)$entry['id'], 'edit') ?>"><span uk-icon="icon:pencil;ratio:0.85"></span> Edit</a></li>
 <li class="<?= $activeTab === 'details' ? 'uk-active' : '' ?>"><a href="<?= entryTabUrl((int)$entry['id'], 'details') ?>"><span uk-icon="icon:info;ratio:0.85"></span> Details</a></li>
 <li class="<?= $activeTab === 'history' ? 'uk-active' : '' ?>"><a href="<?= entryTabUrl((int)$entry['id'], 'history') ?>"><span uk-icon="icon:clock;ratio:0.85"></span> History</a></li>
</ul>

<?php if ($activeTab === 'edit'): ?>
<form method="post" action="" class="Vox-entry-edit-form">
 <?= $csrf->renderInput() ?>
 <input type="hidden" name="submit_entry" value="1">

 <div uk-grid>
 <div class="uk-width-expand@m">

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel Vox-entry-content-card">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Content</h3>
    <span class="uk-text-meta"><?= ucfirst($type) ?> body</span>
   </div>
<?php if ($type === 'review'): ?>
   <div class="uk-margin">
    <label class="uk-form-label">Overall rating</label>
    <div class="Vox-admin-stars" data-vox-stars-wrap data-val="<?= (int)($fieldVals['rating'] ?? 0) ?>">
<?php for ($i=1;$i<=5;$i++): ?>
     <button type="button" data-vox-star class="Vox-admin-star<?= $i <= (int)($fieldVals['rating'] ?? 0) ? ' is-active' : '' ?>" aria-label="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">★</button>
<?php endfor ?>
     <input type="hidden" name="rating" data-vox-rating value="<?= (int)($fieldVals['rating'] ?? 0) ?>">
     <span class="Vox-admin-stars__value" data-vox-rating-label><?= (int)($fieldVals['rating'] ?? 0) ?: 'Not rated' ?></span>
    </div>
   </div>
<?php endif ?>
   <div class="uk-margin">
    <label class="uk-form-label">Text</label>
    <textarea name="body" class="uk-textarea Vox-entry-body-input" rows="6"><?= htmlspecialchars($entry['body']) ?></textarea>
   </div>
<?php if ($type === 'review'): ?>
   <div class="uk-margin">
    <label class="uk-form-label">Recommendation</label>
    <div class="Vox-recommend-choices" role="radiogroup" aria-label="Recommendation">
     <label class="Vox-recommend-choice Vox-recommend-choice--yes">
      <input type="radio" name="recommend" value="1" <?= (string)$entry['recommend']==='1'?'checked':'' ?>>
      <span uk-icon="icon:thumbs-up;ratio:0.8"></span>
      <span>Recommends</span>
     </label>
     <label class="Vox-recommend-choice Vox-recommend-choice--no">
      <input type="radio" name="recommend" value="0" <?= (string)$entry['recommend']==='0'?'checked':'' ?>>
      <span uk-icon="icon:thumbs-down;ratio:0.8"></span>
      <span>Does not</span>
     </label>
    </div>
   </div>
<?php endif ?>
<?php if ($type === 'question'): ?>
   <div class="uk-margin"><label><input type="checkbox" name="is_best_answer" value="1" <?= $entry['is_best_answer']?'checked':'' ?> class="uk-checkbox"> Mark as best answer</label></div>
<?php endif ?>
<?php
 $paramFields = array_filter($schema, fn($f) => !($f['builtin']??false) && $f['field_type']==='rating');
 if ($paramFields && $type === 'review'): ?>
   <div class="uk-margin">
    <label class="uk-form-label">Category ratings</label>
    <div class="uk-grid-small uk-child-width-1-2@s" uk-grid>
<?php foreach ($paramFields as $f): $val = (float)($fieldVals[$f['field_name']] ?? 0); $pct = $val ? round(20*$val) : 0; ?>
     <div>
      <div class="uk-text-meta"><?= htmlspecialchars($f['field_label']) ?></div>
      <progress class="uk-progress uk-margin-remove" value="<?= $pct ?>" max="100"></progress>
      <input type="number" name="<?= $f['field_name'] ?>" value="<?= $val ?>" min="0" max="5" step="0.1" class="uk-input uk-margin-small-top">
     </div>
<?php endforeach ?>
    </div>
   </div>
<?php endif ?>
  </div>

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel">
   <h3 class="uk-card-title"><span uk-icon="icon:lock;ratio:0.85"></span> Moderator note <span class="uk-text-meta">— internal only</span></h3>
   <textarea name="mod_note" class="uk-textarea Vox-entry-note-input" rows="2" placeholder="Add a private note&hellip;"><?= htmlspecialchars($modNote) ?></textarea>
  </div>

  <div class="uk-grid-small uk-child-width-1-2@m uk-margin" uk-grid>
  <div>
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel Vox-entry-side-card">
   <h3 class="uk-card-title">Author</h3>
   <dl class="uk-description-list uk-description-list-divider">
    <dt>Name</dt><dd><?= htmlspecialchars($uName) ?></dd>
    <dt>Role</dt><dd><?= $userId ? 'Registered user' : 'Guest' ?></dd>
<?php if ($rank): ?><dt>Rank</dt><dd><span class="uk-label"><?= htmlspecialchars($rank['label']) ?></span></dd><?php endif ?>
    <dt>IP</dt><dd><code><?= htmlspecialchars($entry['ip'] ?? '') ?></code></dd>
   </dl>
  </div>
  </div>

  <div>
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel Vox-entry-side-card">
   <h3 class="uk-card-title">Entry info</h3>
   <dl class="uk-description-list uk-description-list-divider">
    <dt>ID</dt><dd>#<?= $entry['id'] ?></dd>
    <dt>Type</dt><dd><?= ucfirst($type) ?></dd>
    <dt>Page</dt><dd><?= htmlspecialchars($pageName) ?></dd>
    <dt>Depth</dt><dd><?= $entry['depth'] ?></dd>
    <dt>Created</dt><dd><?= date('M j, Y H:i', strtotime($entry['created'])) ?></dd>
   </dl>
  </div>
  </div>
  </div>

 </div>

 <div class="uk-width-1-3@m">

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel Vox-entry-status-card">
   <h3 class="uk-card-title">Status</h3>
   <div id="status-segmented" class="Vox-status-choices">
    <label class="Vox-status-choice Vox-status-choice--success"><input class="uk-radio" type="radio" name="status" value="published" id="status-published" <?= $entry['status']==='published'?'checked':'' ?>> <span><strong>Published</strong><em>Visible on site</em></span></label>
    <label class="Vox-status-choice Vox-status-choice--warning"><input class="uk-radio" type="radio" name="status" value="pending" id="status-pending" <?= $entry['status']==='pending'?'checked':'' ?>> <span><strong>Pending review</strong><em>Needs moderation</em></span></label>
    <label class="Vox-status-choice Vox-status-choice--danger"><input class="uk-radio" type="radio" name="status" value="spam" id="status-spam" <?= $entry['status']==='spam'?'checked':'' ?>> <span><strong>Spam</strong><em>Hidden from public</em></span></label>
   </div>
<?php if ($entry['status'] === 'pending'): ?>
   <div class="uk-margin-small-top Vox-action-row">
    <button type="button" class="uk-button uk-button-primary" onclick="document.getElementById('status-published').checked=true;updateStatus('published')"><span uk-icon="icon:check;ratio:0.8"></span> Approve</button>
    <button type="button" class="uk-button uk-button-danger" onclick="document.getElementById('status-spam').checked=true;updateStatus('spam')"><span uk-icon="icon:close;ratio:0.8"></span> Reject</button>
   </div>
<?php endif ?>
  </div>

  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-danger Vox-entry-danger">
   <h3 class="uk-card-title uk-text-danger"><span uk-icon="icon:warning;ratio:0.85"></span> Danger zone</h3>
   <button type="submit" name="delete_entry" value="1" class="uk-button uk-button-danger uk-width-1-1" onclick="return confirm('Delete this entry permanently?')"><span uk-icon="icon:trash;ratio:0.8"></span> Delete entry</button>
  </div>

 </div>
 </div>

 <div class="Vox-entry-savebar">
  <button type="submit" class="uk-button uk-button-primary"><span uk-icon="icon:check;ratio:0.85"></span> Save changes</button>
  <a href="<?= $voxUrl ?>entries/" class="uk-button uk-button-default">Cancel</a>
<?php if (count(wire()->session->messages())): ?>
  <span class="uk-text-success uk-margin-small-left"><span uk-icon="icon:check;ratio:0.8"></span> Saved</span>
<?php endif ?>
 </div>
</form>
<?php endif ?>

<?php if ($activeTab === 'details'): ?>
<div uk-grid>
 <div class="uk-width-expand@m">
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
   <h3 class="uk-card-title">Raw record</h3>
   <pre><?= htmlspecialchars(json_encode(array_diff_key($entry, ['body'=>1,'ip'=>1,'guest_email'=>1,'guest_fingerprint'=>1]), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
  </div>
 </div>
 <div class="uk-width-1-3@m">
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
   <h3 class="uk-card-title">Statistics</h3>
   <dl class="uk-description-list uk-description-list-divider">
    <dt>Total likes</dt><dd><?= $likes ?? '—' ?></dd>
    <dt>Replies</dt><dd><?= $replies ?? '—' ?></dd>
    <dt>Reports</dt><dd><?= $reports ?? '—' ?></dd>
   </dl>
  </div>
 </div>
</div>
<?php endif ?>

<?php if ($activeTab === 'history'): ?>
<div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
 <h3 class="uk-card-title">Change history</h3>
 <ul class="uk-list uk-list-divider">
<?php if ($history): foreach ($history as $h): ?>
  <li class="uk-flex uk-flex-middle"><span uk-icon="icon:history;ratio:0.8" class="uk-margin-small-right"></span><div><div class="uk-text-bold"><?= htmlspecialchars($h['action'] ?? '') ?></div><div class="uk-text-meta"><?= htmlspecialchars($h['created'] ?? '') ?></div></div></li>
<?php endforeach; else: ?>
  <li class="uk-flex uk-flex-middle"><span uk-icon="icon:history;ratio:0.8" class="uk-margin-small-right"></span><div><div>Entry created</div><div class="uk-text-meta">by <?= htmlspecialchars($uName) ?> · <?= date('M j, Y H:i', strtotime($entry['created'])) ?></div></div></li>
<?php endif ?>
 </ul>
</div>
<?php endif ?>

<script>
var statusLabels = {published:'Published',pending:'Pending',spam:'Spam'};
var statusCls = {published:'Vox-status-pill--success',pending:'Vox-status-pill--warning',spam:'Vox-status-pill--danger'};
function updateStatus(v){
 var el = document.getElementById('hdr-status');
 if (!el) return;
 var hidden = document.querySelector('input[name="status"][value="' + v + '"]');
 if (hidden) hidden.checked = true;
 el.className = 'Vox-status-pill ' + (statusCls[v] || 'Vox-status-pill--neutral');
 el.textContent = statusLabels[v] || v;
}
document.addEventListener('change', function(e){
 if (e.target && e.target.matches && e.target.matches('input[name="status"]')) updateStatus(e.target.value);
});
(function(){
 var wrap = document.querySelector('[data-vox-stars-wrap]');
 if (!wrap) return;
 var stars = wrap.querySelectorAll('[data-vox-star]');
 var inp = wrap.querySelector('[data-vox-rating]');
 var cur = parseInt(wrap.dataset.val || '0', 10);
 var label = wrap.querySelector('[data-vox-rating-label]');
 function paint(n){ stars.forEach(function(s,i){ s.classList.toggle('is-active', i < n); }); if(label) label.textContent = n ? (n + '/5') : 'Not rated'; }
 stars.forEach(function(s,idx){
  s.addEventListener('mouseenter', function(){ paint(idx+1); });
  s.addEventListener('mouseleave', function(){ paint(cur); });
  s.addEventListener('click', function(e){ e.preventDefault(); cur=idx+1; wrap.dataset.val=cur; if(inp)inp.value=cur; paint(cur); });
 });
 paint(cur);
})();
</script>

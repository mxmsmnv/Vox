<?php namespace ProcessWire;
/**
 * Vox Admin — Stop Words (native AdminThemeUikit markup).
 */
include __DIR__ . '/_vox.design-system.php';

$voxUrl = wire()->config->urls->admin . 'vox/';
$csrf = wire()->session->CSRF;
$activeTab = wire()->input->get('tab');
if (!in_array($activeTab, ['global', 'local', 'hits'], true)) $activeTab = 'global';
function stopTabUrl(string $tab): string { return '?' . http_build_query(['tab' => $tab]); }
function stopWordTagClass(string $action): string { return $action === 'reject' ? 'Vox-stopword-tag--reject' : 'Vox-stopword-tag--flag'; }
function stopWordActionLabel(string $action): string { return $action === 'reject' ? 'Reject' : 'Flag'; }
?>

<p class="uk-text-meta uk-margin-small-bottom">Maintain moderation words, page-specific lists, and hit history.</p>

<div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-4@m uk-margin Vox-stat-grid" uk-grid>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Total rules</div><div class="uk-text-large uk-text-bold"><?= $stats['total'] ?></div><div class="uk-text-meta">Global stop words</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Reject</div><div class="uk-text-large uk-text-bold"><?= $stats['reject'] ?></div><div class="uk-text-meta">Hard blocks</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Flag</div><div class="uk-text-large uk-text-bold"><?= $stats['flag'] ?></div><div class="uk-text-meta">Needs moderation</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-text-meta uk-text-uppercase">Hits</div><div class="uk-text-large uk-text-bold"><?= $stats['hits'] ?></div><div class="uk-text-meta">This week</div>
 </div></div>
</div>

<ul class="uk-subnav uk-subnav-pill uk-margin Vox-inline-tabs" aria-label="Stop words view">
 <li class="<?= $activeTab === 'global' ? 'uk-active' : '' ?>"><a href="<?= stopTabUrl('global') ?>">Global list <span class="uk-badge"><?= $stats['total'] ?></span></a></li>
 <li class="<?= $activeTab === 'local' ? 'uk-active' : '' ?>"><a href="<?= stopTabUrl('local') ?>">Per-page <span class="uk-badge"><?= count($localWords) ?></span></a></li>
 <li class="<?= $activeTab === 'hits' ? 'uk-active' : '' ?>"><a href="<?= stopTabUrl('hits') ?>">Hit log</a></li>
</ul>

<?php if ($activeTab === 'global'): ?>
<div uk-grid>
 <div class="uk-width-expand@m">

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Word list</h3>
    <input oninput="filterWords(this.value)" class="uk-input uk-form-width-small" placeholder="Filter&hellip;">
   </div>
<?php if ($globalWords): ?>
   <div class="Vox-stopword-list">
<?php foreach ($globalWords as $w): ?>
    <span class="Vox-stopword-tag <?= stopWordTagClass($w['action']) ?>" data-word="<?= htmlspecialchars($w['word']) ?>">
     <span class="Vox-stopword-tag__word"><?= htmlspecialchars($w['word']) ?></span>
     <span class="Vox-stopword-tag__action"><?= stopWordActionLabel($w['action']) ?></span>
     <form method="post" action="" class="uk-display-inline">
      <?= $csrf->renderInput() ?>
      <input type="hidden" name="word_id" value="<?= $w['id'] ?>">
      <button name="delete_word" value="1" class="Vox-stopword-tag__remove" title="Remove <?= htmlspecialchars($w['word']) ?>" aria-label="Remove <?= htmlspecialchars($w['word']) ?>">&times;</button>
     </form>
    </span>
<?php endforeach ?>
   </div>
<?php else: ?>
   <div class="Vox-empty-state">
    <div uk-icon="icon:ban;ratio:1.6"></div>
    <p class="uk-margin-small-top"><strong>No global stop words.</strong> Add words or import a list to start filtering submissions.</p>
   </div>
<?php endif ?>
   <hr>
   <form method="post" action="" class="uk-grid-small uk-flex-middle" uk-grid>
    <?= $csrf->renderInput() ?>
    <div class="uk-width-expand@s"><input name="word" class="uk-input" placeholder="New word or phrase&hellip;"></div>
    <div class="uk-width-auto">
     <label><input class="uk-radio" type="radio" name="action" value="reject" checked> Reject</label>
     <label class="uk-margin-small-left"><input class="uk-radio" type="radio" name="action" value="flag"> Flag</label>
    </div>
    <div class="uk-width-auto"><button name="add_word" value="1" class="uk-button uk-button-primary"><span uk-icon="icon:plus;ratio:0.8"></span> Add</button></div>
   </form>
  </div>

  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-form-panel">
   <h3 class="uk-card-title">Bulk import</h3>
   <form method="post" action="">
    <?= $csrf->renderInput() ?>
    <p class="uk-text-meta">One word per line, or comma-separated.</p>
    <textarea name="bulk_words" class="uk-textarea" rows="5" placeholder="word1&#10;word2&#10;bad phrase"></textarea>
    <div class="uk-margin-small-top">
     <label><input class="uk-radio" type="radio" name="bulk_action" value="reject" checked> Reject</label>
     <label class="uk-margin-small-left uk-margin-small-right"><input class="uk-radio" type="radio" name="bulk_action" value="flag"> Flag</label>
     <button name="bulk_import" value="1" class="uk-button uk-button-primary"><span uk-icon="icon:upload;ratio:0.8"></span> Import</button>
    </div>
   </form>
  </div>

 </div>
 <div class="uk-width-1-3@m">
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
   <h3 class="uk-card-title">Statistics</h3>
   <dl class="uk-description-list uk-description-list-divider">
    <dt>Total words</dt><dd><?= $stats['total'] ?></dd>
    <dt>Reject rules</dt><dd><?= $stats['reject'] ?></dd>
    <dt>Flag rules</dt><dd><?= $stats['flag'] ?></dd>
    <dt>Hits this week</dt><dd><?= $stats['hits'] ?></dd>
    <dt>Blocked</dt><dd><?= $stats['blocked'] ?></dd>
   </dl>
  </div>
 </div>
</div>
<?php endif ?>

<?php if ($activeTab === 'local'): ?>
<div uk-grid>
<div class="uk-width-expand@m">
<div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
 <h3 class="uk-card-title">Per-page stop word lists</h3>
<?php if ($localWords): ?>
<?php foreach ($localWords as $pid => $pg): ?>
 <div class="Vox-local-stopword-group">
 <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-top">
  <span><strong><?= htmlspecialchars($pg['title']) ?></strong> <span class="uk-text-meta">ID: <?= $pid ?></span></span>
  <span class="uk-label"><?= count($pg['words']) ?></span>
 </div>
 <div class="Vox-stopword-list uk-margin-small-top">
<?php foreach ($pg['words'] as $w): ?>
  <span class="Vox-stopword-tag <?= stopWordTagClass($w['action']) ?>" data-word="<?= htmlspecialchars($w['word']) ?>">
   <span class="Vox-stopword-tag__word"><?= htmlspecialchars($w['word']) ?></span>
   <span class="Vox-stopword-tag__action"><?= stopWordActionLabel($w['action']) ?></span>
   <form method="post" action="" class="uk-display-inline">
    <?= $csrf->renderInput() ?>
    <input type="hidden" name="word_id" value="<?= $w['id'] ?>">
    <button name="delete_word" value="1" class="Vox-stopword-tag__remove" title="Remove <?= htmlspecialchars($w['word']) ?>" aria-label="Remove <?= htmlspecialchars($w['word']) ?>">&times;</button>
   </form>
  </span>
<?php endforeach ?>
 </div>
 </div>
<?php endforeach ?>
<?php else: ?>
 <div class="Vox-empty-state">
  <div uk-icon="icon:file-text;ratio:1.6"></div>
  <p class="uk-margin-small-top"><strong>No page-specific lists.</strong> Local moderation rules appear here after they are configured.</p>
 </div>
<?php endif ?>
</div>
</div>
<div class="uk-width-1-3@m">
 <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-form-panel">
  <h3 class="uk-card-title">Add page rule</h3>
  <form method="post" action="">
   <?= $csrf->renderInput() ?>
   <input type="hidden" name="page_id" value="">
   <label class="uk-form-label">Page ID or path</label>
   <input name="page_ref" class="uk-input uk-margin-small-bottom" placeholder="1234 or /products/demo/">
   <label class="uk-form-label">Word or phrase</label>
   <input name="word" class="uk-input uk-margin-small-bottom" placeholder="Only for this page&hellip;">
   <div class="uk-margin-small-bottom">
    <label><input class="uk-radio" type="radio" name="action" value="reject" checked> Reject</label>
    <label class="uk-margin-small-left"><input class="uk-radio" type="radio" name="action" value="flag"> Flag</label>
   </div>
   <button name="add_word" value="1" class="uk-button uk-button-primary uk-width-1-1"><span uk-icon="icon:plus;ratio:0.8"></span> Add page rule</button>
  </form>
  <p class="uk-text-meta uk-margin-small-top">Local rules are checked together with global rules, but only for entries submitted on the selected page.</p>
 </div>
</div>
</div>
<?php endif ?>

<?php if ($activeTab === 'hits'): ?>
<div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
 <div class="uk-overflow-auto">
 <table class="uk-table uk-table-divider uk-table-middle uk-table-small">
  <thead><tr><th>Entry preview</th><th>Author</th><th>Status</th><th>Time</th></tr></thead>
  <tbody>
<?php foreach ($hitLog as $e): ?>
  <tr>
   <td><div class="uk-text-truncate"><?= htmlspecialchars(mb_substr($e['body'],0,100)) ?></div></td>
   <td><span class="uk-text-meta"><?= htmlspecialchars($e['guest_name'] ?? ($e['user_id'] ? "User #{$e['user_id']}" : 'Guest')) ?></span></td>
   <td><span class="uk-label <?= $e['status']==='spam'?'uk-label-danger':'uk-label-warning' ?>"><?= ucfirst($e['status']) ?></span></td>
   <td><span class="uk-text-meta"><?= date('M j H:i', strtotime($e['created'])) ?></span></td>
  </tr>
<?php endforeach ?>
<?php if (!$hitLog): ?>
  <tr><td colspan="4">
   <div class="Vox-empty-state">
    <div uk-icon="icon:history;ratio:1.6"></div>
    <p class="uk-margin-small-top"><strong>No stop word hits.</strong> Matched entries appear in this log.</p>
   </div>
  </td></tr>
<?php endif ?>
  </tbody>
 </table>
 </div>
</div>
<?php endif ?>

<script>
function filterWords(q){ document.querySelectorAll('[data-word]').forEach(p=>{ p.classList.toggle('uk-hidden', !!q && !p.dataset.word.toLowerCase().includes(q.toLowerCase())); }); }
</script>

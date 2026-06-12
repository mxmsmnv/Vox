<?php namespace ProcessWire;
/**
 * Vox Admin — Field Schemas (native AdminThemeUikit markup).
 *
 * Schemas are optional, per ProcessWire template and Vox entry type. Built-in
 * fields are always available; this screen only manages extra fields.
 */
include __DIR__ . '/_vox.design-system.php';

$voxUrl   = wire()->config->urls->admin . 'vox/';
$csrf     = wire()->session->CSRF;
$typeList = [
    'review'   => ['label' => 'Review',   'icon' => 'star',     'desc' => 'Ratings, review text and recommendation.'],
    'question' => ['label' => 'Question', 'icon' => 'question', 'desc' => 'Q&A prompts and answer workflow.'],
    'thread'   => ['label' => 'Thread',   'icon' => 'list',     'desc' => 'Open discussion threads.'],
    'comment'  => ['label' => 'Comment',  'icon' => 'comment',  'desc' => 'Replies and block comments.'],
];
$ftypes   = ['rating','text','textarea','select','bool','photo'];

$selectedTemplate = $selTemplateId ? wire()->templates->get((int) $selTemplateId) : null;
$hasCustomForType = $selTemplateId && in_array($selType, $schemaMap[$selTemplateId] ?? [], true);
$isCustomSchema   = $selTemplateId && ($hasCustomForType || wire()->input->get('create'));

$customCount = 0;
foreach ($schema as $f) { if (($f['builtin'] ?? false) === false) $customCount++; }
$builtinFields = array_values(array_filter($schema, fn($f) => !empty($f['builtin'])));

$typeHasCustom = fn(string $tv): bool => $selTemplateId && in_array($tv, $schemaMap[$selTemplateId] ?? [], true);
$customSchemaCount = 0;
foreach ($schemaMap as $types) $customSchemaCount += count($types);

function schemaUrl(int $tid, string $type, bool $create = false): string {
    $q = ['template_id' => $tid, 'entry_type' => $type];
    if ($create) $q['create'] = 1;
    return '?' . http_build_query($q);
}
?>

<div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin Vox-admin-head">
 <p class="uk-text-meta uk-margin-remove">Extend Vox entries with optional fields for a specific ProcessWire template and entry type.</p>
 <div class="Vox-schema-context">
  <span class="Vox-schema-chip"><span uk-icon="icon:database;ratio:0.75"></span> <?= count($allTemplates) ?> templates</span>
  <span class="Vox-schema-chip"><span uk-icon="icon:bolt;ratio:0.75"></span> <?= $customSchemaCount ?> custom schemas</span>
 </div>
</div>

<!-- Summary tiles -->
<div class="uk-grid-small uk-child-width-1-3@m uk-margin Vox-stat-grid" uk-grid>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-flex uk-flex-between"><span class="uk-text-meta uk-text-uppercase">Content templates</span><span uk-icon="icon:album;ratio:0.8"></span></div>
  <div class="uk-text-large uk-text-bold"><?= count($allTemplates) ?></div>
  <div class="uk-text-meta">All support built-in fields</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-flex uk-flex-between"><span class="uk-text-meta uk-text-uppercase">Extended templates</span><span uk-icon="icon:settings;ratio:0.8"></span></div>
  <div class="uk-text-large uk-text-bold"><?= count($schemaMap) ?></div>
  <div class="uk-text-meta">Have at least one schema</div>
 </div></div>
 <div><div class="uk-card uk-card-default uk-card-small uk-card-body">
  <div class="uk-flex uk-flex-between"><span class="uk-text-meta uk-text-uppercase">Selected schema</span><span uk-icon="icon:list;ratio:0.8"></span></div>
  <div class="uk-text-large uk-text-bold"><?= $selTemplateId ? $customCount : '—' ?></div>
  <div class="uk-text-meta"><?= $selectedTemplate ? htmlspecialchars($selectedTemplate->name) . ' · ' . htmlspecialchars($typeList[$selType]['label']) : 'No template selected' ?></div>
 </div></div>
</div>

<div class="Vox-schema-layout" uk-grid>

<!-- Template navigation -->
<aside class="uk-width-1-4@m">
 <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel Vox-schema-sidebar">
  <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
   <h3 class="uk-card-title uk-margin-remove">Templates</h3>
   <span class="uk-text-meta"><?= count($allTemplates) ?></span>
  </div>
  <input class="uk-input uk-form-small Vox-schema-filter" type="search" placeholder="Filter templates..." aria-label="Filter templates">
  <ul class="uk-nav uk-nav-default Vox-schema-template-list">
<?php foreach ($allTemplates as $t):
    $tTypes = $schemaMap[$t->id] ?? [];
    $isSel  = $selTemplateId === (int) $t->id; ?>
   <li class="<?= $isSel ? 'uk-active' : '' ?>" data-template-name="<?= htmlspecialchars(strtolower($t->name)) ?>">
    <a href="<?= schemaUrl((int)$t->id, $selType) ?>" class="Vox-schema-template">
     <span class="Vox-schema-template__name"><?= htmlspecialchars($t->name) ?></span>
<?php if ($tTypes): ?>
     <span class="Vox-schema-template__meta">
      <span class="uk-label uk-label-success"><?= count($tTypes) ?></span>
      <span class="Vox-schema-template__types"><?= htmlspecialchars(implode(', ', $tTypes)) ?></span>
     </span>
<?php else: ?>
     <span class="Vox-schema-template__meta uk-text-meta">built-in only</span>
<?php endif ?>
    </a>
   </li>
<?php endforeach ?>
<?php if (!$allTemplates): ?>
   <li><span class="uk-text-muted uk-text-small">No content templates found.</span></li>
<?php endif ?>
  </ul>
 </div>
</aside>

<!-- Builder -->
<main class="uk-width-expand@m">
<?php if (!$selTemplateId): ?>

 <div class="uk-card uk-card-default uk-card-body uk-text-center uk-padding Vox-form-panel">
  <div uk-icon="icon:arrow-left;ratio:2" class="uk-text-muted"></div>
  <h3 class="uk-h4 uk-margin-small-top">Pick a template to start</h3>
  <p class="uk-text-muted uk-margin-remove-top">Vox works everywhere with built-in fields. Choose a template only when you want extra fields.</p>
 </div>

<?php else: ?>

 <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel Vox-schema-hero">
  <div>
   <div class="uk-text-meta">Selected template</div>
   <h3 class="uk-card-title uk-margin-remove"><?= htmlspecialchars($selectedTemplate->name ?? '') ?></h3>
  </div>
  <div class="Vox-schema-hero__meta">
   <span class="Vox-schema-chip"><span uk-icon="icon:<?= $typeList[$selType]['icon'] ?>;ratio:0.75"></span> <?= htmlspecialchars($typeList[$selType]['label']) ?></span>
   <span class="Vox-schema-chip<?= $hasCustomForType ? ' Vox-schema-chip--active' : '' ?>"><?= $hasCustomForType ? $customCount . ' custom fields' : 'built-in only' ?></span>
  </div>
 </div>

 <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel">
  <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-small-bottom">
   <h3 class="uk-card-title uk-margin-remove">Entry type</h3>
   <span class="uk-text-meta">Custom fields are stored separately per type.</span>
  </div>
  <ul class="uk-subnav uk-subnav-pill Vox-inline-tabs Vox-schema-type-tabs" aria-label="Entry type">
<?php foreach ($typeList as $tv => $tm): ?>
   <li class="<?= $selType === $tv ? 'uk-active' : '' ?>">
    <a href="<?= schemaUrl($selTemplateId, $tv) ?>">
     <span uk-icon="icon:<?= $tm['icon'] ?>;ratio:0.72"></span>
     <?= htmlspecialchars($tm['label']) ?>
<?php if ($typeHasCustom($tv)): ?>
     <span class="uk-badge"><?= count(array_filter($schemaMap[$selTemplateId] ?? [], fn($v) => $v === $tv)) ?: 1 ?></span>
<?php endif ?>
    </a>
   </li>
<?php endforeach ?>
  </ul>
  <p class="uk-text-meta uk-margin-remove"><?= htmlspecialchars($typeList[$selType]['desc']) ?></p>
 </div>

<?php if ($isCustomSchema): ?>
 <form method="post" action="" id="schema-form">
  <?= $csrf->renderInput() ?>
  <input type="hidden" name="submit_schema" value="1">
  <input type="hidden" name="template_id" value="<?= $selTemplateId ?>">
  <input type="hidden" name="entry_type" value="<?= $selType ?>">

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel Vox-schema-builder">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Custom fields</h3>
    <span class="uk-text-meta"><?= htmlspecialchars($selectedTemplate->name ?? '') ?> · <?= htmlspecialchars($typeList[$selType]['label']) ?></span>
   </div>

   <div class="Vox-schema-field-head" aria-hidden="true">
    <span>Field name</span><span>Label</span><span>Type</span><span>Options / hint</span><span>Req</span><span></span>
   </div>
   <div id="custom-table" class="Vox-schema-field-list">
<?php foreach ($schema as $f): if ($f['builtin'] ?? false) continue; ?>
    <div class="Vox-schema-field-row">
     <div><code><?= htmlspecialchars($f['field_name']) ?></code><input type="hidden" name="field_name[]" value="<?= htmlspecialchars($f['field_name']) ?>"></div>
     <div><input type="text" name="field_label[]" value="<?= htmlspecialchars($f['field_label']) ?>" class="uk-input uk-form-small"></div>
     <div><select name="field_type[]" class="uk-select uk-form-small"><?php foreach ($ftypes as $ft): ?><option value="<?= $ft ?>" <?= $f['field_type'] === $ft ? 'selected' : '' ?>><?= $ft ?></option><?php endforeach ?></select></div>
     <div><input type="text" name="field_options[]" value="<?= htmlspecialchars(is_array($f['field_options']) ? implode(', ', $f['field_options']) : ($f['field_options'] ?? '')) ?>" class="uk-input uk-form-small" placeholder="select options, or style=dot"></div>
     <label class="Vox-schema-required"><input type="checkbox" name="field_required[<?= htmlspecialchars($f['field_name']) ?>]" value="1" <?= $f['required'] ? 'checked' : '' ?> class="uk-checkbox"><span>Required</span></label>
     <div class="uk-text-right"><button type="button" class="uk-icon-button" uk-icon="trash" uk-tooltip="Remove field" onclick="this.closest('.Vox-schema-field-row').remove()"></button></div>
    </div>
<?php endforeach ?>
<?php if ($customCount === 0): ?>
    <div id="custom-empty" class="Vox-empty-state Vox-schema-empty"><div uk-icon="icon:plus-circle;ratio:1.5"></div><p class="uk-margin-small-top">No custom fields yet. Add one below to extend this schema.</p></div>
<?php endif ?>
   </div>

   <div class="Vox-schema-add">
    <div><input id="nname" class="uk-input uk-form-small uk-text-monospace" placeholder="field_name"></div>
    <div><input id="nlabel" class="uk-input uk-form-small" placeholder="Label"></div>
    <div><select id="ntype" class="uk-select uk-form-small"><?php foreach ($ftypes as $ft): ?><option value="<?= $ft ?>"><?= $ft ?></option><?php endforeach ?></select></div>
    <div><input id="nopts" class="uk-input uk-form-small" placeholder="select options, or style=dot"></div>
    <label class="Vox-schema-required"><input type="checkbox" id="nreq" class="uk-checkbox"><span>Required</span></label>
    <div><button type="button" onclick="addField()" class="uk-button uk-button-default uk-button-small"><span uk-icon="icon:plus;ratio:0.75"></span> Add</button></div>
   </div>

   <div class="Vox-entry-savebar">
    <button type="submit" class="uk-button uk-button-primary"><span uk-icon="icon:check;ratio:0.8"></span> Save schema</button>
    <button type="button" onclick="if(confirm('Delete this custom schema? Built-in fields are unaffected.')){document.querySelector('[name=delete_schema]').value=1;document.getElementById('schema-form').submit();}" class="uk-button uk-button-danger">Delete schema</button>
    <input type="hidden" name="delete_schema" value="">
   </div>
  </div>
 </form>

<?php else: ?>
 <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel Vox-schema-ready">
  <div class="Vox-schema-ready__icon" uk-icon="icon:check;ratio:0.9"></div>
  <div>
   <h3 class="uk-card-title uk-margin-remove">Built-in fields are enough right now</h3>
   <p class="uk-text-meta uk-margin-small-top"><strong><?= htmlspecialchars($typeList[$selType]['label']) ?></strong> on <strong><?= htmlspecialchars($selectedTemplate->name) ?></strong> already works. Create a custom schema only for extra data you want editors to collect.</p>
  </div>
  <a href="<?= schemaUrl($selTemplateId, $selType, true) ?>" class="uk-button uk-button-primary"><span uk-icon="icon:plus;ratio:0.85"></span> Add custom fields</a>
 </div>
<?php endif ?>

<?php if ($builtinFields): ?>
 <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel Vox-schema-builtins">
  <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
   <h3 class="uk-card-title uk-margin-remove">Built-in fields</h3>
   <span class="uk-text-meta">Always available</span>
  </div>
  <div class="Vox-schema-builtin-grid">
<?php foreach ($builtinFields as $f): ?>
   <div class="Vox-schema-builtin">
    <div class="Vox-schema-builtin__main">
     <strong><?= htmlspecialchars($f['field_label']) ?></strong>
     <code><?= htmlspecialchars($f['field_name']) ?></code>
    </div>
    <div class="Vox-schema-builtin__badges">
     <span class="Vox-schema-type-badge"><?= htmlspecialchars($f['field_type']) ?></span>
     <span class="Vox-schema-required-badge<?= !empty($f['required']) ? ' is-required' : '' ?>"><?= !empty($f['required']) ? 'required' : 'optional' ?></span>
    </div>
   </div>
<?php endforeach ?>
  </div>
 </div>
<?php endif ?>

<?php endif // selTemplateId ?>
</main>
</div>

<script>
var fieldTypes = <?= json_encode($ftypes) ?>;
function escapeAttr(value){
 return String(value).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function addField(){
 var n=document.getElementById('nname').value.trim();
 var l=document.getElementById('nlabel').value.trim();
 var t=document.getElementById('ntype').value;
 var o=document.getElementById('nopts').value.trim();
 var r=document.getElementById('nreq').checked;
 if(!n||!l){document.getElementById(!n ? 'nname' : 'nlabel').focus();return;}
 var ce=document.getElementById('custom-empty'); if(ce)ce.remove();
 var options='';
 for(var i=0;i<fieldTypes.length;i++){var ft=fieldTypes[i];options+='<option value="'+ft+'"'+(ft===t?' selected':'')+'>'+ft+'</option>';}
 var list=document.getElementById('custom-table');
 var row=document.createElement('div');
 row.className='Vox-schema-field-row';
 row.innerHTML=[
  '<div><code>'+escapeAttr(n)+'</code><input type="hidden" name="field_name[]" value="'+escapeAttr(n)+'"></div>',
  '<div><input type="text" name="field_label[]" value="'+escapeAttr(l)+'" class="uk-input uk-form-small"></div>',
  '<div><select name="field_type[]" class="uk-select uk-form-small">'+options+'</select></div>',
  '<div><input type="text" name="field_options[]" value="'+escapeAttr(o)+'" class="uk-input uk-form-small" placeholder="select options, or style=dot"></div>',
  '<label class="Vox-schema-required"><input type="checkbox" name="field_required['+escapeAttr(n)+']" value="1" '+(r?'checked':'')+' class="uk-checkbox"><span>Required</span></label>',
  '<div class="uk-text-right"><button type="button" class="uk-icon-button" uk-icon="trash" uk-tooltip="Remove field" onclick="this.closest(&quot;.Vox-schema-field-row&quot;).remove()"></button></div>'
 ].join('');
 list.appendChild(row);
 ['nname','nlabel','nopts'].forEach(function(id){document.getElementById(id).value='';});
 document.getElementById('nreq').checked=false;
 document.getElementById('nname').focus();
 if(window.UIkit && UIkit.update) UIkit.update(row);
}
(function(){
 var filter=document.querySelector('.Vox-schema-filter');
 if(!filter) return;
 filter.addEventListener('input', function(){
  var q=filter.value.trim().toLowerCase();
  document.querySelectorAll('.Vox-schema-template-list > li').forEach(function(li){
   li.hidden = q && (li.getAttribute('data-template-name') || '').indexOf(q) === -1;
  });
 });
})();
</script>

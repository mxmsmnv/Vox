<?php namespace ProcessWire;
/**
 * Vox Admin — Embed code generator (native AdminThemeUikit markup).
 *
 * Interactive "widget builder": tick the front-end widgets you want and copy
 * the ready-to-paste PHP snippet for a ProcessWire template file.
 *
 * Expects: $widgets (array), $apiBase (string).
 */
include __DIR__ . '/_vox.design-system.php';

$overview = isset($vox) ? $vox->getAdminOverview() : ['version' => Vox::VERSION];
?>

<p class="uk-text-meta uk-margin-small-bottom">Generate the exact include snippet for templates where Vox widgets should appear.</p>

<div class="Vox-install-layout" uk-grid>

 <div class="uk-width-2-5@m">
  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel Vox-install-guide">
   <h3 class="uk-card-title">Embed Vox widgets</h3>
   <ol class="Vox-install-steps">
    <li><span>1</span><div><strong>Choose widgets</strong><p>Select the discussion blocks that should render on the template.</p></div></li>
    <li><span>2</span><div><strong>Copy generated code</strong><p>The snippet always includes <code>vox.init.php</code> first, then the selected views.</p></div></li>
    <li><span>3</span><div><strong>Paste into template</strong><p>Add it to the ProcessWire template file that displays the page content.</p></div></li>
    <li><span>4</span><div><strong>Tune behavior</strong><p>Adjust moderation, guests, voting and photos under <a href="<?= $voxUrl ?>settings/">Settings</a>.</p></div></li>
   </ol>
  </div>

  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
   <h3 class="uk-card-title">Reference</h3>
   <dl class="uk-description-list uk-description-list-divider">
    <dt>Module version</dt><dd><?= htmlspecialchars((string)$overview['version']) ?></dd>
    <dt>API base</dt><dd><code class="uk-text-small"><?= htmlspecialchars($apiBase) ?></code></dd>
    <dt>Views path</dt><dd><code class="uk-text-small">$config-&gt;paths-&gt;Vox</code></dd>
   </dl>
  </div>

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel">
   <h3 class="uk-card-title">Demo</h3>
   <p class="uk-text-meta">Install a standalone demo section with sample pages and entries. It uses its own template and disables the site's automatic prepend/append files, so <code>_main.php</code> and Markup Regions do not affect it.</p>
<?php if (!empty($demoStatus['installed'])): ?>
   <div class="uk-alert-success" uk-alert>
    <p class="uk-margin-remove">Demo is installed at <a href="<?= htmlspecialchars($demoStatus['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($demoStatus['url']) ?></a> with <?= (int)$demoStatus['entries'] ?> sample entries.</p>
   </div>
<?php else: ?>
   <div class="uk-alert-primary" uk-alert>
    <p class="uk-margin-remove">Demo is not installed.</p>
   </div>
<?php endif ?>
   <form method="post" class="uk-margin-small-top">
    <?= $this->wire->session->CSRF->renderInput() ?>
    <label class="Vox-widget-option Vox-widget-option--inline">
     <input class="uk-checkbox" type="checkbox" name="confirm_demo" value="1">
     <span uk-icon="icon:bolt;ratio:0.95" class="uk-text-primary"></span>
     <span>
      <span class="uk-text-bold">Install demo with sample data</span>
      <span class="uk-text-meta uk-display-block">Creates <code>/vox-demo/</code>, demo child pages, schema fields, entries, reports and stop words.</span>
     </span>
    </label>
    <div class="uk-margin-small-top uk-flex uk-flex-wrap uk-flex-middle" style="gap:8px">
     <button type="submit" name="install_demo" value="1" class="uk-button uk-button-primary uk-button-small">
      <span uk-icon="icon:download;ratio:0.8"></span> Install demo
     </button>
<?php if (!empty($demoStatus['installed'])): ?>
     <button type="submit" name="remove_demo" value="1" class="uk-button uk-button-danger uk-button-small">
      <span uk-icon="icon:trash;ratio:0.8"></span> Remove demo
     </button>
<?php endif ?>
    </div>
   </form>
  </div>
 </div>

 <div class="uk-width-expand@m">

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel">
   <h3 class="uk-card-title">Choose widgets</h3>
   <p class="uk-text-meta uk-margin-small-top">Each widget renders one discussion tab. <code>vox.init.php</code> is always included first.</p>
   <div class="uk-grid-small uk-child-width-1-2@m" uk-grid>
<?php foreach ($widgets as $key => $w): ?>
    <div>
     <label class="Vox-widget-option">
      <input class="uk-checkbox vox-widget" type="checkbox" value="<?= $key ?>"
             data-file="<?= htmlspecialchars($w['file']) ?>" data-label="<?= htmlspecialchars($w['label']) ?>"
             <?= $key === 'reviews' ? 'checked' : '' ?>>
      <span uk-icon="icon:<?= $w['icon'] ?>;ratio:0.95" class="uk-text-primary"></span>
      <span>
       <span class="uk-text-bold"><?= htmlspecialchars($w['label']) ?></span>
       <span class="uk-text-meta uk-display-block"><?= htmlspecialchars($w['desc']) ?> &middot; <code><?= htmlspecialchars($w['file']) ?></code></span>
      </span>
     </label>
    </div>
<?php endforeach ?>
   </div>
   <hr class="uk-margin-small">
   <label class="Vox-widget-option Vox-widget-option--inline">
    <input class="uk-checkbox" type="checkbox" id="vox-opt-block">
    <span uk-icon="icon:comment;ratio:0.95" class="uk-text-primary"></span>
    <span>
     <span class="uk-text-bold">Add an inline block-comment example</span>
     <span class="uk-text-meta uk-display-block">Appends a <code>data-discuss-block</code> snippet you can attach to any element.</span>
    </span>
   </label>
  </div>

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-form-panel">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove">Copy into your template</h3>
    <button type="button" id="vox-copy" class="uk-button uk-button-primary uk-button-small">
     <span uk-icon="icon:copy;ratio:0.8"></span> <span id="vox-copy-label">Copy code</span>
    </button>
   </div>
   <p class="uk-text-meta uk-margin-remove-top">Paste this into the PW template that should display the discussions (e.g. <code>product.php</code>).</p>
   <pre class="Vox-code"><code id="vox-code"></code></pre>

   <div id="vox-block-wrap" hidden>
    <p class="uk-text-meta uk-margin-small-bottom">Then mark any element discussable — comment counts load automatically in one batched request:</p>
    <pre class="Vox-code"><code id="vox-block-code"></code></pre>
   </div>

   <div id="vox-empty" class="uk-text-meta uk-text-warning" hidden><span uk-icon="icon:warning;ratio:0.8"></span> Select at least one widget above to generate the snippet.</div>
  </div>

 </div>
</div>

<script>
(function(){
 var codeEl  = document.getElementById('vox-code');
 var emptyEl = document.getElementById('vox-empty');
 var blockOpt   = document.getElementById('vox-opt-block');
 var blockWrap  = document.getElementById('vox-block-wrap');
 var blockCode  = document.getElementById('vox-block-code');
 var boxes = Array.prototype.slice.call(document.querySelectorAll('.vox-widget'));

 function build(){
  var chosen = boxes.filter(function(b){ return b.checked; });
  var lines = [
   '<' + '?php',
   '// Vox — community discussions',
   '$vox     = $modules->get(\'Vox\');',
   '$voxPath = $config->paths->Vox . \'templates/views/\';',
   '',
   '// Required: registers CSS, JS and window.VoxConfig',
   'include $voxPath . \'vox.init.php\';'
  ];
  chosen.forEach(function(b){
   lines.push('');
   lines.push('// ' + b.dataset.label);
   lines.push('include $voxPath . \'' + b.dataset.file + '\';');
  });
  codeEl.textContent = lines.join('\n');
  emptyEl.hidden = chosen.length > 0;

  blockWrap.hidden = !blockOpt.checked;
  if(blockOpt.checked){
   blockCode.textContent =
    '<div data-discuss-block="tasting-notes">\n' +
    '    <p>Any content here…</p>\n' +
    '    <button data-vox-block-trigger="tasting-notes">\n' +
    '        Comments (<span data-vox-block-count="tasting-notes">0</span>)\n' +
    '    </button>\n' +
    '</div>';
  }
 }

 boxes.forEach(function(b){ b.addEventListener('change', build); });
 blockOpt.addEventListener('change', build);
 build();

 var btn = document.getElementById('vox-copy');
 var lbl = document.getElementById('vox-copy-label');
 btn.addEventListener('click', function(){
  var text = codeEl.textContent + (blockOpt.checked ? '\n\n' + blockCode.textContent : '');
  function done(){ lbl.textContent = 'Copied!'; setTimeout(function(){ lbl.textContent = 'Copy code'; }, 1600); }
  if(navigator.clipboard && navigator.clipboard.writeText){
   navigator.clipboard.writeText(text).then(done, function(){ fallback(text); done(); });
  } else { fallback(text); done(); }
 });
 function fallback(text){
  var ta = document.createElement('textarea');
  ta.value = text; document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); } catch(e){}
  document.body.removeChild(ta);
 }
})();
</script>

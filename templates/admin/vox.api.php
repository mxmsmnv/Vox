<?php namespace ProcessWire;
/**
 * Vox Admin — REST API reference (native AdminThemeUikit markup).
 *
 * Served by the VoxApi module route hook. All endpoints are relative to the
 * base URL shown below.
 */
include __DIR__ . '/_vox.design-system.php';

$apiBase  = wire()->config->urls->root . 'vox-api/';
$overview = isset($vox) ? $vox->getAdminOverview() : ['version' => Vox::VERSION];

// method, path, description, params, auth
$api = [
 ['GET',  'blocks/',         'Comment counts for one or more block ids on a page.', 'page_key, blocks[]', 'Public'],
 ['GET',  'entries/',        'Paginated list of published entries and replies.', 'page_key, type, block_id, parent_key, page, per_page', 'Public'],
 ['POST', 'entries/add',     'Create a review, question, thread or comment.', 'page_key, type, body, rating, recommend, parent_key, photos[]', 'CSRF'],
 ['POST', 'entries/vote',    'Toggle a like / helpful vote on an entry.', 'entry_key, value', 'CSRF'],
 ['POST', 'entries/report',  'Report an entry for moderation.', 'entry_key, reason', 'CSRF'],
 ['POST', 'entries/best',    'Mark a comment as the best answer.', 'entry_key', 'CSRF · login'],
 ['GET',  'leaderboard/',    'Top users by points for a period.', 'period (week|month|all), limit', 'Public'],
 ['GET',  'user-stats/',     'Current user stats, rank and badges.', '—', 'Login'],
];

$methodCount = ['GET' => 0, 'POST' => 0];
foreach ($api as $row) { $methodCount[$row[0]]++; }
?>

<p class="uk-text-meta uk-margin-small-bottom">REST endpoints served by the <code>VoxApi</code> route hook. All paths are relative to the base URL below.</p>

<div uk-grid class="uk-grid-small uk-flex-top">
 <div class="uk-width-expand@m">

  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel">
   <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-small-bottom">
    <h3 class="uk-card-title uk-margin-remove"><span uk-icon="icon:bolt;ratio:0.9" class="uk-text-primary"></span> Endpoints</h3>
    <code class="uk-text-small"><?= htmlspecialchars($apiBase) ?></code>
   </div>
   <div class="uk-overflow-auto">
   <table class="uk-table uk-table-divider uk-table-middle uk-table-small Vox-api-table">
    <thead><tr><th>Method</th><th>Endpoint</th><th>Description</th><th>Params</th><th>Auth</th></tr></thead>
    <tbody>
<?php foreach ($api as [$method, $path, $desc, $params, $auth]):
   $mClass = $method === 'GET' ? 'uk-label-success' : 'uk-label-warning'; ?>
     <tr>
      <td><span class="uk-label <?= $mClass ?> Vox-method"><?= $method ?></span></td>
      <td><code><?= htmlspecialchars($apiBase . $path) ?></code></td>
      <td><?= htmlspecialchars($desc) ?></td>
      <td><span class="uk-text-meta"><?= htmlspecialchars($params) ?></span></td>
      <td><span class="uk-text-meta"><?= htmlspecialchars($auth) ?></span></td>
     </tr>
<?php endforeach ?>
    </tbody>
   </table>
   </div>
  </div>

 </div>

 <div class="uk-width-1-3@m">
  <div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin Vox-table-panel">
   <h3 class="uk-card-title">Overview</h3>
   <dl class="uk-description-list uk-description-list-divider">
    <dt>Base URL</dt><dd><code class="uk-text-small"><?= htmlspecialchars($apiBase) ?></code></dd>
    <dt>Endpoints</dt><dd><?= count($api) ?> (<?= $methodCount['GET'] ?> GET · <?= $methodCount['POST'] ?> POST)</dd>
    <dt>Module version</dt><dd><?= htmlspecialchars((string)$overview['version']) ?></dd>
   </dl>
  </div>
  <div class="uk-card uk-card-default uk-card-body uk-card-small Vox-table-panel">
   <h3 class="uk-card-title">Authentication</h3>
   <ul class="uk-list uk-list-divider uk-text-small uk-margin-remove">
    <li><span class="uk-label uk-label-success Vox-method">GET</span> <strong>Public</strong> — readable without a session.</li>
    <li><span class="uk-label uk-label-warning Vox-method">POST</span> <strong>CSRF</strong> — requires the token from <code>window.VoxConfig</code> (sent automatically by the bundled JS).</li>
    <li><strong>Login</strong> — requires an authenticated ProcessWire user.</li>
   </ul>
  </div>
 </div>
</div>

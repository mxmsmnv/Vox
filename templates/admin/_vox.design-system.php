<?php namespace ProcessWire;
/**
 * Shared Vox admin section navigation.
 * Native ProcessWire AdminThemeUikit markup (uk-tab) — theme-aware via --pw-* tokens.
 */
$voxUrl = wire()->config->urls->admin . 'vox/';
$section = (string) wire()->input->urlSegment1;
$pendingCount = isset($vox) ? $vox->getPendingCount() : 0;

$nav = [
    ''             => 'Dashboard',
    'entries'      => 'Entries',
    'moderation'   => 'Moderation',
    'schemas'      => 'Field Schemas',
    'gamification' => 'Gamification',
    'stopwords'    => 'Stop Words',
    'settings'     => 'Settings',
];
if (wire()->user->isSuperuser() || wire()->user->hasPermission('vox-api-docs')) {
    $nav['api'] = 'API';
}
$nav['install'] = 'Embed';
?>

<ul class="uk-tab uk-margin-medium-bottom Vox-section-nav" aria-label="Vox admin sections">
<?php foreach ($nav as $seg => $label): ?>
  <li class="<?= $section === $seg ? 'uk-active' : '' ?>">
    <a href="<?= $voxUrl . ($seg ? $seg . '/' : '') ?>"><?= $label ?><?php
      if ($seg === 'moderation' && $pendingCount): ?> <span class="uk-badge"><?= (int) $pendingCount ?></span><?php endif ?></a>
  </li>
<?php endforeach ?>
</ul>

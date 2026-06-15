<?php namespace ProcessWire;
/**
 * Vox — Profile badges section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null);
if (!$voxProfile) return;

$earned = $voxProfile['badges']['earned'] ?? [];
$locked = $voxProfile['badges']['locked'] ?? [];
$badgeCard = function(array $badge, bool $lockedState) use ($vox): string {
    $image = (string)($badge['image'] ?? '');
    $mark = $image
        ? '<img src="' . htmlspecialchars($vox->badgeImageUrl($image)) . '" alt="">'
        : vox_icon((string)($badge['icon'] ?: 'star'));
    $meta = $lockedState
        ? number_format((int)($badge['progress_value'] ?? 0)) . ' / ' . number_format((int)($badge['threshold'] ?? 0))
        : ((string)($badge['description'] ?? '') ?: 'Earned');
    return '<article class="vox-profile-badge' . ($lockedState ? ' vox-profile-badge--locked' : '') . '">'
        . '<div class="vox-profile-badge__mark">' . $mark . '</div>'
        . '<strong>' . htmlspecialchars($badge['label']) . '</strong>'
        . '<span>' . htmlspecialchars($meta) . '</span>'
        . '</article>';
};
?>

<section class="vox-wrap vox-profile-section">
    <div class="vox-profile-section__head">
        <h2><?= vox_icon('patch-check') ?> Badges</h2>
        <span><?= count($earned) ?> / <?= count($earned) + count($locked) ?> earned</span>
    </div>

    <?php if ($earned): ?>
    <h3 class="vox-profile-subhead">Earned</h3>
    <div class="vox-profile-badges">
        <?php foreach ($earned as $badge): ?><?= $badgeCard($badge, false) ?><?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if ($locked): ?>
    <h3 class="vox-profile-subhead">Locked</h3>
    <div class="vox-profile-badges">
        <?php foreach ($locked as $badge): ?><?= $badgeCard($badge, true) ?><?php endforeach ?>
    </div>
    <?php endif ?>
</section>

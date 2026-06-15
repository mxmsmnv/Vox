<?php namespace ProcessWire;
/**
 * Vox — Profile rank progression section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null);
if (!$voxProfile) return;

$progress = $voxProfile['rank_progress'];
$ranks = $voxProfile['ranks'];
$currentId = (int)($progress['current']['id'] ?? 0);
$points = (int)$progress['points'];
?>

<section class="vox-wrap vox-profile-section">
    <div class="vox-profile-section__head">
        <h2><?= vox_icon('chart-line') ?> Rank progression</h2>
        <?php if (!empty($progress['next'])): ?>
        <span><?= number_format((int)$progress['to_next']) ?> pts to <?= htmlspecialchars($progress['next']['label']) ?></span>
        <?php else: ?>
        <span>Top rank reached</span>
        <?php endif ?>
    </div>

    <div class="vox-profile-ranks" style="--vox-rank-progress: <?= (int)$progress['percent'] ?>%">
        <div class="vox-profile-ranks__line"></div>
        <div class="vox-profile-ranks__fill"></div>
        <?php foreach ($ranks as $rank):
            $rankId = (int)$rank['id'];
            $state = (int)$rank['min_points'] <= $points ? 'done' : 'locked';
            if ($rankId === $currentId) $state = 'current';
        ?>
        <div class="vox-profile-rank vox-profile-rank--<?= $state ?>">
            <div class="vox-profile-rank__mark"><?= vox_icon((string)($rank['icon'] ?: 'star')) ?></div>
            <strong><?= htmlspecialchars($rank['label']) ?></strong>
            <span><?= number_format((int)$rank['min_points']) ?> pts</span>
        </div>
        <?php endforeach ?>
    </div>
</section>

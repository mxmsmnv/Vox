<?php namespace ProcessWire;
/**
 * Vox — Default profile assembly.
 *
 * This is only a convenience include. For flexible pages, include the profile
 * sections individually in any order.
 */
$vox = $vox ?? wire('modules')->get('Vox');
$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null);
if (!$voxProfile) return;
?>

<div class="vox-profile-layout">
    <div class="vox-profile-layout__full"><?php include __DIR__ . '/vox.profile.header.php'; ?></div>
    <main class="vox-profile-layout__main">
        <?php include __DIR__ . '/vox.profile.rank.php'; ?>
        <?php include __DIR__ . '/vox.profile.badges.php'; ?>
        <?php include __DIR__ . '/vox.profile.activity.php'; ?>
    </main>
    <aside class="vox-profile-layout__side">
        <?php include __DIR__ . '/vox.profile.points.php'; ?>
        <?php include __DIR__ . '/vox.profile.leaderboard.php'; ?>
    </aside>
</div>

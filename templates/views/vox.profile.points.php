<?php namespace ProcessWire;
/**
 * Vox — Profile points breakdown section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null);
if (!$voxProfile) return;

$points = $voxProfile['points'];
$breakdown = $points['breakdown'] ?? [];
$labels = [
    'post' => 'Posts',
    'answer' => 'Answers',
    'best_answer' => 'Best answers',
    'best_answer_revoked' => 'Best answer changes',
    'like_received' => 'Likes received',
    'like_revoked' => 'Like reversals',
];
?>

<section class="vox-wrap vox-profile-section vox-profile-section--compact">
    <div class="vox-profile-section__head">
        <h2><?= vox_icon('bolt') ?> Points</h2>
        <span><?= number_format((int)$points['total']) ?> pts</span>
    </div>
    <div class="vox-profile-points">
        <?php foreach ($breakdown as $row):
            $action = (string)$row['action'];
            $value = (int)$row['points'];
        ?>
        <div class="vox-profile-points__row">
            <span><?= htmlspecialchars($labels[$action] ?? ucfirst(str_replace('_', ' ', $action))) ?></span>
            <strong><?= $value > 0 ? '+' : '' ?><?= number_format($value) ?></strong>
        </div>
        <?php endforeach ?>
        <?php if (!$breakdown): ?><div class="vox-empty">No points yet.</div><?php endif ?>
    </div>
</section>

<?php namespace ProcessWire;
/**
 * Vox — Profile header section.
 *
 * Optional variables:
 *   $voxProfile = $vox->getUserProfileData(...)
 *   $voxProfileUser = user id, user name, user_key or User object
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null);
if (!$voxProfile) return;

$user = $voxProfile['user'];
$stats = $voxProfile['stats'];
$rank = $voxProfile['rank'] ?? [];
$points = (int)($voxProfile['points']['total'] ?? 0);
$joined = !empty($user['created']) ? date('F Y', strtotime($user['created'])) : '';
?>

<section class="vox-wrap vox-profile-head">
    <div class="vox-profile-head__banner"></div>
    <div class="vox-profile-head__body">
        <div class="vox-profile-head__main">
            <?= vox_avatar($user['display_name'], 48) ?>
            <div>
                <h1><?= htmlspecialchars($user['display_name']) ?></h1>
                <div class="vox-profile-head__meta">
                    <?php if (!empty($rank['label'])): ?><?= vox_rank_badge((string)$rank['label'], (string)($rank['icon'] ?? '')) ?><?php endif ?>
                    <span><?= vox_icon('bolt') ?> <?= number_format($points) ?> pts</span>
                    <?php if ($joined): ?><span>Member since <?= htmlspecialchars($joined) ?></span><?php endif ?>
                </div>
            </div>
        </div>

        <div class="vox-profile-stats">
            <div><strong><?= number_format((int)($stats['reviews'] ?? 0)) ?></strong><span>Reviews</span></div>
            <div><strong><?= number_format((int)($stats['questions'] ?? 0)) ?></strong><span>Questions</span></div>
            <div><strong><?= number_format((int)($stats['answers'] ?? 0)) ?></strong><span>Answers</span></div>
            <div><strong><?= number_format((int)($stats['threads'] ?? 0)) ?></strong><span>Threads</span></div>
            <div><strong><?= number_format((int)($stats['likes_received'] ?? 0)) ?></strong><span>Likes</span></div>
            <div><strong><?= number_format((int)($stats['best_answers'] ?? 0)) ?></strong><span>Best answers</span></div>
        </div>
    </div>
</section>

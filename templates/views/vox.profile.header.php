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

<section class="vox-wrap vox-profile-head" aria-labelledby="vox-profile-title">
    <div class="vox-profile-head__body">
        <div class="vox-profile-head__main">
            <?= vox_avatar($user['display_name'], 72, (string)($user['avatar_url'] ?? '')) ?>
            <div>
                <p class="lqrs-detail-eyebrow">Community member</p>
                <h1 class="ds-heading" data-size="lg" id="vox-profile-title"><?= htmlspecialchars($user['display_name']) ?></h1>
                <div class="vox-profile-head__meta">
                    <?php if (!empty($rank['label'])): ?><?= vox_rank_badge((string)$rank['label'], (string)($rank['icon'] ?? '')) ?><?php endif ?>
                    <span><?= vox_icon('bolt') ?> <?= number_format($points) ?> pts</span>
                    <?php if ($joined): ?><span>Member since <?= htmlspecialchars($joined) ?></span><?php endif ?>
                </div>
            </div>
        </div>

        <dl class="vox-profile-stats" aria-label="Community contribution summary">
            <div><dd><?= number_format((int)($stats['reviews'] ?? 0)) ?></dd><dt>Reviews</dt></div>
            <div><dd><?= number_format((int)($stats['questions'] ?? 0)) ?></dd><dt>Questions</dt></div>
            <div><dd><?= number_format((int)($stats['answers'] ?? 0)) ?></dd><dt>Answers</dt></div>
            <div><dd><?= number_format((int)($stats['threads'] ?? 0)) ?></dd><dt>Discussions</dt></div>
            <div><dd><?= number_format((int)($stats['likes_received'] ?? 0)) ?></dd><dt>Likes</dt></div>
            <div><dd><?= number_format((int)($stats['best_answers'] ?? 0)) ?></dd><dt>Best answers</dt></div>
        </dl>
    </div>
</section>

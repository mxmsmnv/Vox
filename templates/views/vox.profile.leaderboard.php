<?php namespace ProcessWire;
/**
 * Vox — Profile leaderboard section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null);
if (!$voxProfile) return;

$period = $voxProfileLeaderboardPeriod ?? 'month';
$rows = $vox->getLeaderboard($period, $voxProfileLeaderboardLimit ?? 10);
$currentUserId = (int)$voxProfile['user']['id'];
?>

<section class="vox-wrap vox-profile-section vox-profile-section--compact">
    <div class="vox-profile-section__head">
        <h2><?= vox_icon('trophy') ?> Leaderboard</h2>
        <span><?= htmlspecialchars($period) ?></span>
    </div>
    <div data-vox-leaderboard>
        <?php foreach ($rows as $i => $row):
            $isCurrent = (int)$row['user_id'] === $currentUserId;
        ?>
        <div class="vox-lb-row <?= $isCurrent ? 'vox-lb-row--you' : '' ?>">
            <span class="vox-lb-pos <?= $i === 0 ? 'vox-lb-pos--gold' : ($i === 1 ? 'vox-lb-pos--silver' : 'vox-lb-pos--muted') ?>"><?= $i + 1 ?></span>
            <?= vox_avatar((string)$row['name'], 24) ?>
            <span class="vox-lb-name"><?= htmlspecialchars($row['name']) ?><?= $isCurrent ? ' <span class="vox-lb-you">you</span>' : '' ?></span>
            <?php if (!empty($row['rank']['label'])): ?><span class="vox-rank-badge"><?= htmlspecialchars($row['rank']['label']) ?></span><?php endif ?>
            <span class="vox-lb-pts"><?= number_format((int)$row['points']) ?></span>
        </div>
        <?php endforeach ?>
        <?php if (!$rows): ?><div class="vox-empty">No leaderboard data yet.</div><?php endif ?>
    </div>
</section>

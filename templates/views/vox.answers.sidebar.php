<?php namespace ProcessWire;
/**
 * Vox — Answers mode sidebar.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';
$stats = $voxAnswersStats ?? $vox->getAnswerStats((int)$page->id);
$rows = $vox->getLeaderboard('month', 5);
?>

<aside class="vox-wrap vox-answers-sidebar">
    <section class="vox-profile-section vox-profile-section--compact">
        <div class="vox-profile-section__head"><h2><?= vox_icon('chart-simple') ?> Q&A stats</h2></div>
        <div class="vox-profile-points">
            <div class="vox-profile-points__row"><span>Questions</span><strong><?= number_format((int)$stats['total']) ?></strong></div>
            <div class="vox-profile-points__row"><span>Unanswered</span><strong><?= number_format((int)$stats['unanswered']) ?></strong></div>
            <div class="vox-profile-points__row"><span>Solved</span><strong><?= number_format((int)$stats['solved']) ?></strong></div>
        </div>
    </section>
    <section class="vox-profile-section vox-profile-section--compact">
        <div class="vox-profile-section__head"><h2><?= vox_icon('trophy') ?> Top contributors</h2><span>month</span></div>
        <div>
            <?php foreach ($rows as $i => $row): ?>
            <div class="vox-lb-row">
                <span class="vox-lb-pos <?= $i === 0 ? 'vox-lb-pos--gold' : ($i === 1 ? 'vox-lb-pos--silver' : 'vox-lb-pos--muted') ?>"><?= $i + 1 ?></span>
                <?= vox_avatar((string)$row['name'], 24) ?>
                <span class="vox-lb-name"><?= htmlspecialchars($row['name']) ?></span>
                <?php if (!empty($row['rank']['label'])): ?><span class="vox-rank-badge"><?= htmlspecialchars($row['rank']['label']) ?></span><?php endif ?>
                <span class="vox-lb-pts"><?= number_format((int)$row['points']) ?></span>
            </div>
            <?php endforeach ?>
            <?php if (!$rows): ?><div class="vox-empty">No contributors yet.</div><?php endif ?>
        </div>
    </section>
</aside>

<?php namespace ProcessWire;
/**
 * Vox — Profile activity section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null, $voxProfileActivityLimit ?? 12);
if (!$voxProfile) return;

$activity = $voxProfile['activity'] ?? [];
$labels = [
    Vox::TYPE_REVIEW => ['Reviewed', 'star'],
    Vox::TYPE_QUESTION => ['Asked', 'question'],
    Vox::TYPE_THREAD => ['Started', 'comment-dots'],
    Vox::TYPE_COMMENT => ['Replied', 'comment'],
];
?>

<section class="vox-wrap vox-profile-section">
    <div class="vox-profile-section__head">
        <h2><?= vox_icon('clock-rotate-left') ?> Recent activity</h2>
        <span><?= count($activity) ?> item<?= count($activity) === 1 ? '' : 's' ?></span>
    </div>

    <div class="vox-profile-activity">
        <?php foreach ($activity as $entry):
            [$verb, $icon] = $labels[$entry['type']] ?? ['Posted', 'comment'];
            $pageTitle = (string)($entry['page_title'] ?? '');
            $body = trim(preg_replace('/\s+/', ' ', strip_tags((string)$entry['body'])));
            if (mb_strlen($body) > 130) $body = mb_substr($body, 0, 127) . '...';
            $likes = $vox->getEntryLikes((int)$entry['id'])['total'];
        ?>
        <article class="vox-profile-activity__item">
            <div class="vox-profile-activity__icon"><?= vox_icon($icon) ?></div>
            <div>
                <div><strong><?= htmlspecialchars($verb) ?></strong><?php if ($pageTitle): ?> on <?= htmlspecialchars($pageTitle) ?><?php endif ?></div>
                <p><?= htmlspecialchars($body) ?></p>
                <span><?= vox_time_ago((string)$entry['created']) ?> · <?= number_format($likes) ?> like<?= $likes === 1 ? '' : 's' ?></span>
            </div>
        </article>
        <?php endforeach ?>
        <?php if (!$activity): ?><div class="vox-empty"><?= vox_icon('comment') ?> No activity yet.</div><?php endif ?>
    </div>
</section>

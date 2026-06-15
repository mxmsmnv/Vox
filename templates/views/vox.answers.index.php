<?php namespace ProcessWire;
/**
 * Vox — Answers mode question index.
 */
$vox = $vox ?? wire('modules')->get('Vox');
$input = wire('input');
$san = wire('sanitizer');
require_once __DIR__ . '/vox.helpers.php';

$pageId = (int)$page->id;
$pageKey = $vox->publicKey('page', $pageId);
$filter = $san->option($input->get('filter') ?? ($voxAnswersFilter ?? 'active'), ['active', 'newest', 'unanswered', 'solved', 'voted']) ?: 'active';
$perPage = (int)($voxAnswersPerPage ?? 15);
$currPage = max(1, (int)($input->get('p') ?? 1));
$result = $vox->getAnswerQuestions($pageId, $filter, $perPage, $currPage);
$questions = $result['entries'];
$total = $result['total'];
$totalPages = (int)ceil($total / max(1, $perPage));
$voxAnswersStats = $vox->getAnswerStats($pageId);
?>

<section class="vox-wrap vox-answers-index" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">
    <div class="vox-answers-hero">
        <div>
            <h1><?= htmlspecialchars($voxAnswersTitle ?? 'Questions') ?></h1>
            <p><?= htmlspecialchars($voxAnswersIntro ?? 'Ask, answer and keep useful knowledge easy to find.') ?></p>
        </div>
        <a class="vox-btn vox-btn--primary" href="#vox-answers-ask"><?= vox_icon('plus') ?> Ask question</a>
    </div>

    <?php include __DIR__ . '/vox.answers.filters.php'; ?>

    <div id="vox-answers-list" class="vox-answers-list" data-vox-entries-list>
    <?php foreach ($questions as $entry):
        $entryKey = $vox->publicKey('entry', (int)$entry['id']);
        $questionUrl = '?' . http_build_query(['question' => $entryKey]);
        $body = trim(preg_replace('/\s+/', ' ', strip_tags((string)$entry['body'])));
        if (mb_strlen($body) > 180) $body = mb_substr($body, 0, 177) . '...';
    ?>
        <article class="vox-answer-row <?= !empty($entry['best_count']) ? 'vox-answer-row--solved' : '' ?>">
            <div class="vox-answer-row__stats">
                <span><strong><?= number_format((int)$entry['votes']) ?></strong> votes</span>
                <span><strong><?= number_format((int)$entry['answer_count']) ?></strong> answers</span>
            </div>
            <div class="vox-answer-row__body">
                <h2><a href="<?= htmlspecialchars($questionUrl) ?>"><?= htmlspecialchars($body ?: 'Untitled question') ?></a></h2>
                <div class="vox-answer-row__meta">
                    <?= !empty($entry['best_count']) ? '<span class="vox-answer-status vox-answer-status--solved">Solved</span>' : '<span class="vox-answer-status">Open</span>' ?>
                    <span>asked by <?= htmlspecialchars($entry['author_name']) ?></span>
                    <span><?= vox_time_ago((string)$entry['created']) ?></span>
                    <span>active <?= vox_time_ago((string)$entry['last_activity']) ?></span>
                </div>
            </div>
        </article>
    <?php endforeach ?>
    <?php if (!$questions): ?><div class="vox-empty"><?= vox_icon('question') ?> No questions match this filter.</div><?php endif ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="vox-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $url = '?' . http_build_query(array_filter(['filter' => $filter === 'active' ? null : $filter, 'p' => $i === 1 ? null : $i]));
        ?>
        <a href="<?= htmlspecialchars($url) ?>" class="vox-btn vox-btn--sm <?= $i === $currPage ? 'vox-btn--primary' : '' ?>"><?= $i ?></a>
        <?php endfor ?>
    </div>
    <?php endif ?>
</section>

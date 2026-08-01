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
$voxAnswersTitleTag = !empty($voxEmbedded) ? 'h2' : 'h1';
$voxAnswersEntryTitleTag = !empty($voxEmbedded) ? 'h3' : 'h2';
?>

<section class="vox-wrap vox-answers-index" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">
    <div class="vox-answers-hero">
        <div>
            <<?= $voxAnswersTitleTag ?> class="ds-heading" data-size="lg"><?= htmlspecialchars($voxAnswersTitle ?? 'Questions') ?></<?= $voxAnswersTitleTag ?>>
            <p class="ds-paragraph" data-size="lg"><?= htmlspecialchars($voxAnswersIntro ?? 'Ask, answer and keep useful knowledge easy to find.') ?></p>
        </div>
        <a class="ds-button vox-btn vox-btn--primary" data-variant="primary" href="#vox-answers-ask"><?= vox_icon('plus') ?> Ask question</a>
    </div>

    <?php include __DIR__ . '/vox.answers.filters.php'; ?>

    <div id="vox-answers-list" class="vox-answers-list" data-vox-entries-list>
    <?php foreach ($questions as $entry):
        $entryKey = $vox->publicKey('entry', (int)$entry['id']);
        $questionUrl = '?' . http_build_query(['question' => $entryKey]);
        $body = trim(preg_replace('/\s+/', ' ', strip_tags((string)$entry['body'])));
        $title = trim((string)($entry['title'] ?? ''));
        $excerpt = $body;
        if ($title === '') {
            $questionMark = mb_strpos($body, '?');
            if ($questionMark !== false && $questionMark < 180) {
                $title = trim(mb_substr($body, 0, $questionMark + 1));
                $excerpt = trim(mb_substr($body, $questionMark + 1));
            } else {
                $title = mb_strlen($body) > 110 ? rtrim(mb_substr($body, 0, 107)) . '…' : $body;
                $excerpt = '';
            }
        } elseif ($excerpt === $title) {
            $excerpt = '';
        }
        if (mb_strlen($excerpt) > 210) {
            $excerpt = trim(mb_substr($excerpt, 0, 207));
            $excerpt = preg_replace('/\s+\S*$/u', '', $excerpt) ?: $excerpt;
            $excerpt = rtrim($excerpt, " \t\n\r\0\x0B,.;:!?") . '…';
        }
        $isSolved = !empty($entry['best_count']);
    ?>
        <article class="vox-answer-row <?= $isSolved ? 'vox-answer-row--solved' : '' ?>">
            <div class="vox-answer-row__body">
                <div class="vox-answer-row__topline">
                    <span class="ds-tag vox-answer-status <?= $isSolved ? 'vox-answer-status--solved' : '' ?>" data-color="<?= $isSolved ? 'success' : 'neutral' ?>" data-size="sm"><?= $isSolved ? vox_icon('circle-check') . ' Solved' : 'Open question' ?></span>
                    <span>Active <?= vox_time_ago((string)$entry['last_activity']) ?></span>
                </div>
                <<?= $voxAnswersEntryTitleTag ?>><a href="<?= htmlspecialchars($questionUrl) ?>"><?= htmlspecialchars($title ?: 'Untitled question') ?></a></<?= $voxAnswersEntryTitleTag ?>>
                <?php if ($excerpt !== ''): ?><p class="vox-answer-row__excerpt"><?= htmlspecialchars($excerpt) ?></p><?php endif ?>
                <div class="vox-answer-row__meta">
                    <span class="vox-answer-row__author">
                        <?= vox_avatar((string)$entry['author_name'], 28, (string)($entry['author_avatar'] ?? $entry['avatar_url'] ?? '')) ?>
                        <span>Asked by <strong><?= htmlspecialchars((string)$entry['author_name']) ?></strong> · <?= vox_time_ago((string)$entry['created']) ?></span>
                    </span>
                    <span class="vox-answer-row__signals" aria-label="Question activity">
                        <span><?= vox_icon('arrow-up') ?> <strong><?= number_format((int)$entry['votes']) ?></strong> vote<?= (int)$entry['votes'] === 1 ? '' : 's' ?></span>
                        <span><?= vox_icon('comment') ?> <strong><?= number_format((int)$entry['answer_count']) ?></strong> answer<?= (int)$entry['answer_count'] === 1 ? '' : 's' ?></span>
                    </span>
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
        <a href="<?= htmlspecialchars($url) ?>" class="ds-button vox-btn vox-btn--sm <?= $i === $currPage ? 'vox-btn--primary' : '' ?>" data-variant="<?= $i === $currPage ? 'primary' : 'tertiary' ?>" data-size="sm" <?= $i === $currPage ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor ?>
    </div>
    <?php endif ?>
</section>

<?php namespace ProcessWire;
/**
 * Vox — Answers mode filters section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
$san = wire('sanitizer');
$pageId = (int)$page->id;
$filter = $san->option(wire('input')->get('filter') ?? ($voxAnswersFilter ?? 'active'), ['active', 'newest', 'unanswered', 'solved', 'voted']) ?: 'active';
$stats = $voxAnswersStats ?? $vox->getAnswerStats($pageId);
$filters = [
    'active' => 'Active',
    'newest' => 'Newest',
    'unanswered' => 'Unanswered',
    'solved' => 'Solved',
    'voted' => 'Most voted',
];
?>

<nav class="vox-answers-filters" aria-label="Question filters">
    <?php foreach ($filters as $key => $label):
        $url = '?' . http_build_query(array_filter(['filter' => $key === 'active' ? null : $key]));
    ?>
    <a class="<?= $filter === $key ? 'is-active' : '' ?>" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach ?>
    <span><?= number_format((int)$stats['total']) ?> questions · <?= number_format((int)$stats['unanswered']) ?> unanswered · <?= number_format((int)$stats['solved']) ?> solved</span>
</nav>

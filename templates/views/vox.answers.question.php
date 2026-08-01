<?php namespace ProcessWire;
/**
 * Vox — Answers mode single question view.
 */
$vox = $vox ?? wire('modules')->get('Vox');
$input = wire('input');
require_once __DIR__ . '/vox.helpers.php';

$pageId = (int)$page->id;
$questionId = isset($voxAnswerQuestion) ? (int)$voxAnswerQuestion : $vox->resolvePublicKey('entry', $input->get('question'));
$question = $questionId ? $vox->getEntry($questionId) : null;
if (!$question || $question['type'] !== Vox::TYPE_QUESTION || $question['status'] !== Vox::STATUS_PUBLISHED) {
    echo '<div class="vox-wrap"><div class="vox-empty">' . vox_icon('question') . ' Question not found.</div></div>';
    return;
}

$schema = $vox->getSchema((int)$page->template->id, Vox::TYPE_QUESTION);
$answers = $vox->getChildEntries($questionId, 50);
$answerCount = $vox->getEntryReplyCount($questionId);
$pageKey = $vox->publicKey('page', $pageId);
$questionKey = $vox->publicKey('entry', $questionId);
$answersBackUrl = trim((string)($voxAnswersBackUrl ?? $page->url)) ?: $page->url;
$answerFormPrefix = vox_control_id('vox-answer');
$answerNameId = $answerFormPrefix . '-name';
$answerBodyId = $answerFormPrefix . '-body';
$questionTitleTagCandidate = (string)($voxAnswerQuestionHeadingTag ?? 'h2');
$questionTitleTag = in_array($questionTitleTagCandidate, ['h1', 'h2', 'h3'], true)
    ? $questionTitleTagCandidate
    : 'h2';
$questionTitleId = 'vox-question-title';
$questionText = trim(strip_tags((string)$question['body']));
$questionTitle = '';
$questionDetailBody = $questionText;
if (preg_match('/^(.{1,220}\?)(?:\s+|$)(.*)$/us', $questionText, $questionParts)) {
    $questionTitle = trim((string)$questionParts[1]);
    $questionDetailBody = trim((string)$questionParts[2]);
}
$isSolved = !empty($question['best_count']);
?>

<section class="vox-wrap vox-answers-question" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">
    <a class="vox-answers-back" href="<?= htmlspecialchars($answersBackUrl) ?>"><?= vox_icon('arrow-left') ?> All questions</a>

    <article class="vox-answers-question__main" aria-labelledby="<?= htmlspecialchars($questionTitleId) ?>">
        <div class="vox-answers-question__statusbar">
            <span class="ds-tag vox-answer-status <?= $isSolved ? 'vox-answer-status--solved' : '' ?>" data-color="<?= $isSolved ? 'success' : 'neutral' ?>" data-size="sm"><?= $isSolved ? vox_icon('circle-check') . ' Solved' : 'Open question' ?></span>
            <span><?= number_format($answerCount) ?> answer<?= $answerCount === 1 ? '' : 's' ?></span>
        </div>
        <?php
        $entry = $question;
        $depth = 0;
        $voxEntryNoChildren = true;
        $voxEntryNoReplyForm = true;
        $voxEntryTitleOverride = $questionTitle;
        $voxEntryTitleTag = $questionTitleTag;
        $voxEntryTitleId = $questionTitleId;
        $voxEntryBodyOverride = $questionDetailBody;
        include __DIR__ . '/vox.entry.php';
        unset($voxEntryNoChildren, $voxEntryNoReplyForm, $voxEntryTitleOverride, $voxEntryTitleTag, $voxEntryTitleId, $voxEntryBodyOverride);
        ?>
    </article>

    <div class="vox-answers-answer-form">
        <div class="vox-answers-answer-form__intro">
            <span class="vox-answers-answer-form__icon" aria-hidden="true"><?= vox_icon('reply') ?></span>
            <div>
                <h2 class="ds-heading" data-size="sm">Write an answer</h2>
                <p>Share practical experience, explain your reasoning, and cite a source when a fact needs verification.</p>
            </div>
        </div>
        <div class="vox-form">
            <form class="vox-form__element" data-vox-form data-entry-list="vox-answer-list">
                <?= vox_csrf() ?>
                <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                <input type="hidden" name="type" value="comment">
                <input type="hidden" name="parent_key" value="<?= htmlspecialchars($questionKey) ?>">
                <?php if (!wire('user')->isLoggedIn()): ?>
                <div class="ds-field vox-field vox-field--compact">
                    <label class="ds-label vox-form__label" for="<?= htmlspecialchars($answerNameId) ?>">Your name</label>
                    <input id="<?= htmlspecialchars($answerNameId) ?>" type="text" name="guest_name" class="ds-input vox-input" placeholder="Your name (optional)">
                </div>
                <?php endif ?>
                <div class="ds-field vox-field">
                    <label class="ds-label vox-form__label" for="<?= htmlspecialchars($answerBodyId) ?>">Answer</label>
                    <textarea id="<?= htmlspecialchars($answerBodyId) ?>" name="body" class="ds-input vox-textarea" rows="6" placeholder="Write a clear, helpful answer…" required></textarea>
                    <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                </div>
                <div class="vox-form__actions">
                    <button type="submit" class="ds-button vox-btn vox-btn--primary" data-variant="primary"><?= vox_icon('paper-plane') ?> Post answer</button>
                    <span data-vox-feedback hidden></span>
                </div>
            </form>
        </div>
    </div>

    <div class="vox-answers-heading">
        <h2 class="ds-heading vox-answers-subtitle" data-size="md">Answers</h2>
        <span><?= number_format($answerCount) ?></span>
    </div>
    <div id="vox-answer-list" data-vox-entries-list>
    <?php foreach ($answers as $answer): ?>
        <?php $entry = $answer; $depth = 0; include __DIR__ . '/vox.entry.php'; ?>
    <?php endforeach ?>
    <?php if (!$answers): ?><div class="vox-empty vox-answers-empty"><?= vox_icon('comment') ?><strong>No answers yet</strong><span>Be the first to share a useful answer with the community.</span></div><?php endif ?>
    </div>
</section>

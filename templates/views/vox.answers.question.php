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
?>

<section class="vox-wrap vox-answers-question" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">
    <a class="vox-answers-back" href="<?= htmlspecialchars($page->url) ?>"><?= vox_icon('arrow-left') ?> All questions</a>

    <div class="vox-answers-question__main">
        <?php $entry = $question; $depth = 0; $voxEntryNoChildren = true; $voxEntryNoReplyForm = true; include __DIR__ . '/vox.entry.php'; unset($voxEntryNoChildren, $voxEntryNoReplyForm); ?>
    </div>

    <div class="vox-answers-answer-form vox-card">
        <div class="vox-card__head"><?= vox_icon('reply') ?> Your answer</div>
        <div class="vox-form">
            <form data-vox-form data-entry-list="vox-answer-list">
                <?= vox_csrf() ?>
                <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                <input type="hidden" name="type" value="comment">
                <input type="hidden" name="parent_key" value="<?= htmlspecialchars($questionKey) ?>">
                <?php if (!wire('user')->isLoggedIn()): ?>
                <div class="vox-field vox-field--compact"><input type="text" name="guest_name" class="vox-input" placeholder="Your name (optional)"></div>
                <?php endif ?>
                <textarea name="body" class="vox-textarea" rows="5" placeholder="Write a helpful answer..." required></textarea>
                <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                <div class="vox-form__actions">
                    <button type="submit" class="vox-btn vox-btn--primary"><?= vox_icon('paper-plane') ?> Post answer</button>
                    <span data-vox-feedback hidden></span>
                </div>
            </form>
        </div>
    </div>

    <h2 class="vox-answers-subtitle"><?= number_format($answerCount) ?> answer<?= $answerCount === 1 ? '' : 's' ?></h2>
    <div id="vox-answer-list" data-vox-entries-list>
    <?php foreach ($answers as $answer): ?>
        <?php $entry = $answer; $depth = 1; include __DIR__ . '/vox.entry.php'; ?>
    <?php endforeach ?>
    <?php if (!$answers): ?><div class="vox-empty"><?= vox_icon('comment') ?> No answers yet.</div><?php endif ?>
    </div>
</section>

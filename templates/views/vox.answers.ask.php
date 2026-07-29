<?php namespace ProcessWire;
/**
 * Vox — Answers mode ask form section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$pageId = (int)$page->id;
$pageKey = $vox->publicKey('page', $pageId);
$voxAnswersListId = $voxAnswersListId ?? 'vox-answers-list';
?>

<section class="vox-card vox-answers-ask" id="vox-answers-ask">
    <div class="vox-card__head"><?= vox_icon('question') ?> Ask a question</div>
    <div class="vox-form">
        <form data-vox-form data-entry-list="<?= htmlspecialchars($voxAnswersListId) ?>">
            <?= vox_csrf() ?>
            <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
            <input type="hidden" name="type" value="question">
            <?php if (!wire('user')->isLoggedIn()): ?>
            <div class="vox-grid-2">
                <div><label class="ds-label vox-form__label" for="vox-ask-name">Your name</label><input id="vox-ask-name" type="text" name="guest_name" class="ds-input vox-input" placeholder="Anonymous-XXX if blank"></div>
                <div><label class="ds-label vox-form__label" for="vox-ask-email">Email</label><input id="vox-ask-email" type="email" name="guest_email" class="ds-input vox-input" placeholder="optional"></div>
            </div>
            <?php endif ?>
            <div class="vox-field">
                <label class="ds-label vox-form__label" for="vox-ask-body">Question</label>
                <textarea id="vox-ask-body" name="body" class="ds-input vox-textarea" rows="4" placeholder="What would you like to know?" required></textarea>
                <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
            </div>
            <div class="vox-form__actions">
                <button type="submit" class="ds-button vox-btn vox-btn--primary" data-variant="primary"><?= vox_icon('paper-plane') ?> Post question</button>
                <span data-vox-feedback hidden></span>
            </div>
        </form>
    </div>
</section>

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
                <div><label class="vox-form__label">Your name</label><input type="text" name="guest_name" class="vox-input" placeholder="Anonymous-XXX if blank"></div>
                <div><label class="vox-form__label">Email</label><input type="email" name="guest_email" class="vox-input" placeholder="optional"></div>
            </div>
            <?php endif ?>
            <div class="vox-field">
                <label class="vox-form__label">Question</label>
                <textarea name="body" class="vox-textarea" rows="4" placeholder="What would you like to know?" required></textarea>
                <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
            </div>
            <div class="vox-form__actions">
                <button type="submit" class="vox-btn vox-btn--primary"><?= vox_icon('paper-plane') ?> Post question</button>
                <span data-vox-feedback hidden></span>
            </div>
        </form>
    </div>
</section>

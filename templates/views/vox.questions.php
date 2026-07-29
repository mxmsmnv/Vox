<?php namespace ProcessWire;
/**
 * Vox — Q&A page template.
 */
$vox      = $vox ?? wire('modules')->get('Vox');
$pageId   = $page->id;
$pageKey  = $vox->publicKey('page', (int)$pageId);
$tmplId   = $page->template->id;
$schema   = $vox->getSchema($tmplId, Vox::TYPE_QUESTION);
$perPage  = 10;
$currPage = max(1, (int)(wire('input')->get('p') ?? 1));

$result    = $vox->getEntries([
    'page_id'  => $pageId,
    'type'     => Vox::TYPE_QUESTION,
    'depth'    => 0,
    'per_page' => $perPage,
    'page'     => $currPage,
]);
$questions = $result['entries'];
$total     = $result['total'];
$totalPages     = (int)ceil($total / $perPage);

require_once __DIR__ . '/vox.helpers.php';
$questionControlPrefix = vox_control_id('vox-question');
$questionNameId = $questionControlPrefix . '-name';
$questionEmailId = $questionControlPrefix . '-email';
$questionBodyId = $questionControlPrefix . '-body';
$questionPhotosId = $questionControlPrefix . '-photos';
?>

<div class="vox-wrap" id="vox-questions" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">

    <!-- Ask a question -->
    <div class="vox-card vox-card--mb-16">
        <div class="vox-card__head">
            <?= vox_icon('question') ?> Ask a question
        </div>
        <div class="vox-form">
            <form class="vox-form__element" data-vox-form data-entry-list="vox-questions-list">
                <?= vox_csrf() ?>
                <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                <input type="hidden" name="type"    value="question">
                <?php if (!wire('user')->isLoggedIn()): ?>
                <div class="vox-grid-2">
                    <div class="ds-field"><label class="ds-label vox-form__label" for="<?= htmlspecialchars($questionNameId) ?>">Your name</label><input id="<?= htmlspecialchars($questionNameId) ?>" type="text" name="guest_name" class="ds-input vox-input" placeholder="Anonymous-XXX if blank"></div>
                    <div class="ds-field"><label class="ds-label vox-form__label" for="<?= htmlspecialchars($questionEmailId) ?>">Email</label><input id="<?= htmlspecialchars($questionEmailId) ?>" type="email" name="guest_email" class="ds-input vox-input" placeholder="optional"></div>
                </div>
                <?php endif ?>
                <div class="ds-field vox-field">
                    <label class="ds-label vox-form__label" for="<?= htmlspecialchars($questionBodyId) ?>">Your question</label>
                    <textarea id="<?= htmlspecialchars($questionBodyId) ?>" name="body" class="ds-input vox-textarea" rows="4" placeholder="What would you like to know?" required></textarea>
                    <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                </div>
                <?php if ($vox->cfg('photo_uploads')): ?>
                <div class="ds-field vox-field">
                    <span class="ds-label vox-form__label" id="<?= htmlspecialchars($questionPhotosId) ?>-label">Attach images <span class="vox-inline-note">(optional, max <?= (int)$vox->cfg('photo_max') ?>)</span></span>
                    <label class="ds-button vox-btn vox-btn--sm vox-file-link" data-variant="tertiary" data-size="sm" for="<?= htmlspecialchars($questionPhotosId) ?>">
                        <?= vox_icon('paperclip') ?> Attach
                    </label>
                    <input id="<?= htmlspecialchars($questionPhotosId) ?>" class="ds-input vox-file-input" type="file" name="photos[]" multiple accept="image/*" data-vox-photo-input aria-labelledby="<?= htmlspecialchars($questionPhotosId) ?>-label">
                    <div data-vox-photo-preview class="vox-photo-preview"></div>
                </div>
                <?php endif ?>
                <div class="vox-form__actions">
                    <button type="submit" class="ds-button vox-btn vox-btn--primary" data-variant="primary"><?= vox_icon('paper-plane') ?> Post question</button>
                </div>
                <span data-vox-feedback hidden></span>
            </form>
        </div>
    </div>

    <div class="vox-count-row"><?= $total ?> question<?= $total !== 1 ? 's' : '' ?></div>

    <div id="vox-questions-list" data-vox-entries-list>
        <?php foreach ($questions as $entry): ?>
            <?php $depth = 0; include __DIR__ . '/vox.entry.php'; ?>
        <?php endforeach ?>
        <?php if (!$questions): ?>
            <div class="vox-empty"><?= vox_icon('question') ?> No questions yet. Be the first to ask!</div>
        <?php endif ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="vox-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?p=<?= $i ?>" class="ds-button vox-btn vox-btn--sm <?= $i === $currPage ? 'vox-btn--primary' : '' ?>" data-variant="<?= $i === $currPage ? 'primary' : 'tertiary' ?>" data-size="sm" <?= $i === $currPage ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor ?>
    </div>
    <?php endif ?>

</div>

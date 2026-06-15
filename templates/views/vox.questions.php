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
?>

<div class="vox-wrap" id="vox-questions" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">

    <!-- Ask a question -->
    <div class="vox-card vox-card--mb-16">
        <div class="vox-card__head">
            <?= vox_icon('question') ?> Ask a question
        </div>
        <div class="vox-form">
            <form data-vox-form data-entry-list="vox-questions-list">
                <?= vox_csrf() ?>
                <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                <input type="hidden" name="type"    value="question">
                <?php if (!wire('user')->isLoggedIn()): ?>
                <div class="vox-grid-2">
                    <div><label class="vox-form__label">Your name</label><input type="text" name="guest_name" class="vox-input" placeholder="Anonymous-XXX if blank"></div>
                    <div><label class="vox-form__label">Email</label><input type="email" name="guest_email" class="vox-input" placeholder="optional"></div>
                </div>
                <?php endif ?>
                <div class="vox-field">
                    <label class="vox-form__label" for="vox-qb">Your question</label>
                    <textarea id="vox-qb" name="body" class="vox-textarea" rows="4" placeholder="What would you like to know?" required></textarea>
                    <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                </div>
                <?php if ($vox->cfg('photo_uploads')): ?>
                <div class="vox-field">
                    <label class="vox-form__label">Attach images <span class="vox-inline-note">(optional, max <?= (int)$vox->cfg('photo_max') ?>)</span></label>
                    <label class="vox-file-link">
                        <?= vox_icon('paperclip') ?> Attach
                        <input type="file" name="photos[]" multiple accept="image/*" data-vox-photo-input>
                    </label>
                    <div data-vox-photo-preview class="vox-photo-preview"></div>
                </div>
                <?php endif ?>
                <div class="vox-form__actions">
                    <button type="submit" class="vox-btn vox-btn--primary"><?= vox_icon('paper-plane') ?> Post question</button>
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
        <a href="?p=<?= $i ?>" class="vox-btn vox-btn--sm <?= $i === $currPage ? 'vox-btn--primary' : '' ?>"><?= $i ?></a>
        <?php endfor ?>
    </div>
    <?php endif ?>

</div>

<?php namespace ProcessWire;
/**
 * Vox — Discussions page template (block comments + free threads).
 */
$vox    = $vox ?? wire('modules')->get('Vox');
$pageId = $page->id;
$pageKey = $vox->publicKey('page', (int)$pageId);
$tmplId = $page->template->id;
$schema = $vox->getSchema($tmplId, Vox::TYPE_COMMENT);

// Free threads
$result  = $vox->getEntries([
    'page_id'  => $pageId,
    'type'     => Vox::TYPE_THREAD,
    'depth'    => 0,
    'per_page' => 20,
]);
$threads = $result['entries'];

// Block IDs that have published entries on this page
$blockIds    = $vox->getPageBlockIds($pageId);
$blockCounts = $vox->getBlockCounts($pageId, $blockIds);

require_once __DIR__ . '/vox.helpers.php';
?>

<div class="vox-wrap" id="vox-discussions" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">

    <!-- Block discussions -->
    <section class="vox-section">
        <h2 class="vox-section-title"><?= vox_icon('table-list') ?> Block discussions</h2>
        <p class="vox-section-subtitle">Click any highlighted section on the page to discuss it.</p>

        <div class="vox-block-list">
        <?php foreach ($blockCounts as $blockId => $cnt):
            $blockEntries = $vox->getEntries([
                'page_id'  => $pageId,
                'block_id' => $blockId,
                'type'     => Vox::TYPE_COMMENT,
                'depth'    => 0,
                'per_page' => 20,
            ])['entries'];
        ?>
        <div class="vox-block-item">
            <button class="vox-btn" data-vox-block-trigger="<?= htmlspecialchars($blockId) ?>">
                <?= vox_icon('comment') ?>
                <span data-vox-block-count><?= $cnt ?></span> comment<?= $cnt !== 1 ? 's' : '' ?>
                on <em><?= htmlspecialchars($blockId) ?></em>
            </button>
            <div data-vox-block-panel="<?= htmlspecialchars($blockId) ?>" class="vox-card vox-block-panel">
                <div id="vox-block-entries-<?= htmlspecialchars($blockId) ?>" data-vox-entries-list>
                <?php foreach ($blockEntries as $entry): ?>
                    <?php $depth = 0; include __DIR__ . '/vox.entry.php'; ?>
                <?php endforeach ?>
                </div>
                <div class="vox-form vox-block-form">
                    <form data-vox-form data-entry-list="vox-block-entries-<?= htmlspecialchars($blockId) ?>">
                        <?= vox_csrf() ?>
                        <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                        <input type="hidden" name="block_id" value="<?= htmlspecialchars($blockId) ?>">
                        <input type="hidden" name="type"     value="comment">
                        <?php if (!wire('user')->isLoggedIn()): ?>
                        <div class="vox-field vox-field--compact">
                            <input type="text" name="guest_name" class="vox-input" placeholder="Your name (optional)">
                        </div>
                        <?php endif ?>
                        <textarea name="body" class="vox-textarea" rows="3" placeholder="Comment on this section&hellip;"></textarea>
                        <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                        <div class="vox-form__actions">
                            <button type="submit" class="vox-btn vox-btn--primary vox-btn--sm"><?= vox_icon('paper-plane') ?> Post</button>
                        </div>
                        <span data-vox-feedback hidden></span>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach ?>
        </div>
    </section>

    <!-- Free threads -->
    <section>
        <h2 class="vox-section-title"><?= vox_icon('comment-dots') ?> Free discussions</h2>

        <div class="vox-card vox-card--mb-16">
            <div class="vox-card__head">
                <?= vox_icon('circle-plus') ?> Start a discussion
            </div>
            <div class="vox-form">
                <form data-vox-form data-entry-list="vox-threads-list">
                    <?= vox_csrf() ?>
                    <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                    <input type="hidden" name="type"    value="thread">
                    <?php if (!wire('user')->isLoggedIn()): ?>
                    <div class="vox-field vox-field--compact">
                        <input type="text" name="guest_name" class="vox-input" placeholder="Your name (optional)">
                    </div>
                    <?php endif ?>
                    <div class="vox-field vox-field--compact">
                        <input type="text" name="title" class="vox-input" placeholder="Title (optional)">
                    </div>
                    <textarea name="body" class="vox-textarea" rows="4" placeholder="What&rsquo;s on your mind?" required></textarea>
                    <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                    <div class="vox-form__actions">
                        <button type="submit" class="vox-btn vox-btn--primary"><?= vox_icon('arrow-right') ?> Create Thread</button>
                    </div>
                    <span data-vox-feedback hidden></span>
                </form>
            </div>
        </div>

        <div id="vox-threads-list" data-vox-entries-list>
            <?php foreach ($threads as $entry): ?>
                <?php $depth = 0; include __DIR__ . '/vox.entry.php'; ?>
            <?php endforeach ?>
            <?php if (!$threads): ?>
                <div class="vox-empty"><?= vox_icon('comment') ?> No discussions yet. Start one above!</div>
            <?php endif ?>
        </div>
    </section>

</div>

<?php namespace ProcessWire;
/**
 * Vox — Inline form template for Textformatter/editorial inserts.
 *
 * Optional variables:
 *   $voxInlineType = 'thread' | 'question' | 'review'
 *   $voxInlineTitle = 'Join the discussion'
 *   $voxInlineIntro = '...'
 *   $voxInlinePlaceholder = '...'
 *   $voxInlineButton = 'Post'
 */
$vox = $vox ?? wire('modules')->get('Vox');
$san = wire('sanitizer');

require_once __DIR__ . '/vox.helpers.php';

$pageId = (int)$page->id;
$pageKey = $vox->publicKey('page', $pageId);
$type = $san->option($voxInlineType ?? 'thread', ['thread', 'question', 'review']);
if (!$type) $type = 'thread';

$defaults = [
    'thread' => [
        'title' => 'Join the discussion',
        'intro' => 'Share your take on this story.',
        'placeholder' => 'What do you think?',
        'button' => 'Post discussion',
        'icon' => 'comment-dots',
    ],
    'question' => [
        'title' => 'Ask a question',
        'intro' => 'Need more context? Ask here.',
        'placeholder' => 'What would you like to know?',
        'button' => 'Post question',
        'icon' => 'question',
    ],
    'review' => [
        'title' => 'Leave a review',
        'intro' => 'Rate your experience and add a short note.',
        'placeholder' => 'Share your experience...',
        'button' => 'Publish review',
        'icon' => 'star-half-stroke',
    ],
];

$copy = $defaults[$type];
$title = trim((string)($voxInlineTitle ?? $copy['title']));
$intro = trim((string)($voxInlineIntro ?? $copy['intro']));
$placeholder = trim((string)($voxInlinePlaceholder ?? $copy['placeholder']));
$button = trim((string)($voxInlineButton ?? $copy['button']));
$schema = $type === 'review' ? $vox->getSchema((int)$page->template->id, Vox::TYPE_REVIEW) : [];
?>

<aside class="vox-wrap vox-inline-form" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">
    <form data-vox-form>
        <?= vox_csrf() ?>
        <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

        <div class="vox-inline-form__head">
            <div class="vox-inline-form__icon"><?= vox_icon((string)$copy['icon']) ?></div>
            <div>
                <h2><?= htmlspecialchars($title) ?></h2>
                <?php if ($intro !== ''): ?><p><?= htmlspecialchars($intro) ?></p><?php endif ?>
            </div>
        </div>

        <?php if (!wire('user')->isLoggedIn()): ?>
        <div class="vox-grid-2 vox-inline-form__guest">
            <div><label class="vox-form__label">Your name</label><input type="text" name="guest_name" class="vox-input" placeholder="Optional"></div>
            <?php if ($type !== 'thread'): ?>
            <div><label class="vox-form__label">Email</label><input type="email" name="guest_email" class="vox-input" placeholder="optional"></div>
            <?php endif ?>
        </div>
        <?php endif ?>

        <?php if ($type === 'review'): ?>
        <div class="vox-field">
            <label class="vox-form__label">Overall rating <span class="vox-field__label-required">*</span></label>
            <?= vox_rating_picker('rating', 'Overall rating', 0, 'stars', true, 'vox-stars-wrap--tight') ?>
        </div>
        <?php
        $customRatingFields = array_filter($schema, fn($f) => !($f['builtin'] ?? false) && $f['field_type'] === 'rating');
        if ($customRatingFields):
        ?>
        <div class="vox-field">
            <label class="vox-form__label">Category ratings</label>
            <div class="vox-params">
            <?php foreach ($customRatingFields as $f): ?>
                <div class="vox-param">
                    <div class="vox-param__name"><?= htmlspecialchars($f['field_label']) ?></div>
                    <?= vox_rating_picker((string)$f['field_name'], (string)$f['field_label'], 0, vox_rating_style($f), false, 'vox-stars-wrap--tight') ?>
                </div>
            <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
        <?php endif ?>

        <div class="vox-field">
            <textarea name="body" class="vox-textarea" rows="3" placeholder="<?= htmlspecialchars($placeholder) ?>" required></textarea>
            <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
        </div>

        <?php if ($type === 'review'): ?>
        <div class="vox-field vox-inline-form__recommend" data-vox-rec>
            <div class="vox-recommend-row">
                <button type="button" class="vox-btn vox-btn--sm" data-rec-value="1"><?= vox_icon('thumbs-up') ?> I recommend</button>
                <button type="button" class="vox-btn vox-btn--sm" data-rec-value="0"><?= vox_icon('thumbs-down') ?> Would not recommend</button>
                <input type="hidden" name="recommend" data-vox-rec-input value="">
            </div>
        </div>
        <?php endif ?>

        <div class="vox-form__actions">
            <button type="submit" class="vox-btn vox-btn--primary"><?= vox_icon('paper-plane') ?> <?= htmlspecialchars($button) ?></button>
            <span data-vox-feedback hidden></span>
        </div>
    </form>
</aside>

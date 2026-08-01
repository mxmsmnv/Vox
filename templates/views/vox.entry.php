<?php namespace ProcessWire;
/**
 * Vox single entry partial — recursive.
 * Renders one entry and its nested children.
 *
 * Variables passed by caller:
 *   $entry  — enriched array from $vox->getEntry() or getEntries()
 *   $schema — field schema from $vox->getSchema()
 *   $depth  — current nesting depth (0/1/2)
 *   $pageId — int, PW page ID
 *   $vox    — Vox module instance
 */
$vox    = $vox ?? wire('modules')->get('Vox');
$depth  = $depth ?? 0;
$user   = wire('user');
$showChildren = empty($voxEntryNoChildren);
$showReplyForm = empty($voxEntryNoReplyForm);
$entryKey = $vox->publicKey('entry', (int)$entry['id']);
$pageKey  = $vox->publicKey('page', (int)$pageId);

// Field values and likes via module methods — no SQL here
$fieldVals = $vox->getEntryFieldValues((int)$entry['id']);
$likes     = $vox->getEntryLikes((int)$entry['id']);
$userLiked = $likes['user_liked'];
$likeTotal = $likes['total'];

// Child entries (replies)
$previewCount = (int)$vox->cfg('preview_count');
$children     = $showChildren ? $vox->getChildEntries((int)$entry['id'], $previewCount) : [];
$hasMore      = count($children) > $previewCount;
if ($hasMore) array_pop($children);

// Can this user mark a best answer on this comment?
$canBest = false;
if ($entry['type'] === 'comment' && $entry['parent_id'] && $user->isLoggedIn()) {
    $parentOwnerId = $vox->getEntryOwnerId((int)$entry['parent_id']);
    $canBest = $user->isSuperuser() || ((int)$user->id === $parentOwnerId);
}

// CSS nesting class
$nestClass = $depth === 1 ? ' vox-nest-l1' : ($depth >= 2 ? ' vox-nest-l2' : '');
$bestClass = !empty($entry['is_best_answer']) ? ' vox-entry--best' : '';

require_once __DIR__ . '/vox.helpers.php';
?>

<div class="vox-entry<?= $nestClass ?><?= $bestClass ?>"
     id="<?= htmlspecialchars($vox->publicAnchor('entry', (int)$entry['id'])) ?>"
     data-vox-entry="<?= htmlspecialchars($entryKey) ?>"
     data-created="<?= htmlspecialchars($entry['created']) ?>"
     data-rating="<?= (int)($fieldVals['rating'] ?? 0) ?>"
     data-likes="<?= $likeTotal ?>">

    <!-- Head -->
    <div class="vox-entry__head">
        <?= vox_avatar($entry['author_name'], $depth === 0 ? 32 : 26, (string)($entry['author_avatar'] ?? '')) ?>
        <?php if (!empty($entry['author_key'])): ?>
            <a class="vox-entry__author" href="/community/?profile=<?= rawurlencode((string)$entry['author_key']) ?>"><?= htmlspecialchars($entry['author_name']) ?></a>
        <?php else: ?>
            <span class="vox-entry__author"><?= htmlspecialchars($entry['author_name']) ?></span>
        <?php endif ?>
        <?php if ($entry['author_rank']): ?>
            <?= vox_rank_badge($entry['author_rank']) ?>
        <?php endif ?>
        <?php if ($entry['is_owner_reply']): ?>
            <span class="vox-rank-badge vox-staff-badge">
                <?= vox_icon('circle-check') ?> Staff
            </span>
        <?php endif ?>
        <span class="vox-entry__time"><?= vox_time_ago($entry['created']) ?></span>
        <span class="vox-best-badge" data-vox-best-badge <?= $entry['is_best_answer'] ? '' : 'hidden' ?>>
            <?= vox_icon('star', 'fill') ?> Best Answer
        </span>
    </div>

    <!-- Stars (reviews only) -->
    <?php if ($entry['type'] === 'review' && !empty($fieldVals['rating'])): ?>
    <div class="vox-entry__stars">
        <?= vox_stars((int)$fieldVals['rating']) ?>
    </div>
    <?php endif ?>

    <!-- Body -->
    <?php if (!empty($voxEntryTitleOverride)): ?>
    <?php
    $voxEntryTitleTagCandidate = (string)($voxEntryTitleTag ?? 'h2');
    $voxEntryTitleTag = in_array($voxEntryTitleTagCandidate, ['h1', 'h2', 'h3'], true)
        ? $voxEntryTitleTagCandidate
        : 'h2';
    $voxEntryTitleId = trim((string)($voxEntryTitleId ?? ''));
    ?>
    <<?= $voxEntryTitleTag ?> class="ds-heading vox-entry__title" data-size="md"<?= $voxEntryTitleId !== '' ? ' id="' . htmlspecialchars($voxEntryTitleId) . '"' : '' ?>><?= htmlspecialchars((string)$voxEntryTitleOverride) ?></<?= $voxEntryTitleTag ?>>
    <?php endif ?>
    <?php $voxEntryDisplayBody = isset($voxEntryBodyOverride) ? (string)$voxEntryBodyOverride : (string)$entry['body']; ?>
    <?php if (trim($voxEntryDisplayBody) !== ''): ?>
    <div class="vox-entry__body">
        <?= nl2br(htmlspecialchars($voxEntryDisplayBody)) ?>
    </div>
    <?php endif ?>

    <?= vox_entry_photos($entry['photos'] ?? []) ?>

    <!-- Parametric ratings (review, root level only) -->
    <?php if ($entry['type'] === 'review' && $depth === 0): ?>
        <?= vox_param_ratings($schema, $fieldVals) ?>
    <?php endif ?>

    <!-- Custom non-rating fields (text/select/bool) -->
    <?= vox_custom_fields($schema, $fieldVals) ?>

    <!-- Recommend -->
    <?php if ($entry['type'] === 'review' && isset($entry['recommend'])): ?>
    <div class="vox-field"><?= vox_rec_pill($entry['recommend']) ?></div>
    <?php endif ?>

    <!-- Actions -->
    <div class="vox-entry__actions">
        <button class="ds-button vox-btn vox-btn--sm vox-vote-btn<?= $userLiked ? ' vox-vote-btn--liked' : '' ?>" data-variant="tertiary" data-size="sm" data-entry-key="<?= htmlspecialchars($entryKey) ?>" data-value="1" aria-label="Like">
            <?= str_replace('<i ', '<i data-vox-heart ', vox_icon('heart', $userLiked ? 'fill' : 'line')) ?>
            <span data-vox-likes><?= $likeTotal ?></span>
        </button>

        <?php if ($showReplyForm && $depth < Vox::MAX_DEPTH): ?>
        <button class="ds-button vox-btn vox-btn--sm vox-reply-btn" data-variant="tertiary" data-size="sm" data-reply-target="reply-<?= htmlspecialchars($entryKey) ?>" aria-label="Reply">
            <?= vox_icon('reply') ?> Reply
        </button>
        <?php endif ?>

        <?php if ($entry['type'] === 'review'): ?>
        <button type="button" class="ds-button vox-btn vox-btn--sm vox-vote-btn" data-variant="tertiary" data-size="sm"
                data-vox-comments-toggle="<?= htmlspecialchars($entryKey) ?>"
                aria-expanded="false">
            <?= vox_icon('comment') ?>
            <span data-vox-toggle-label>Comments</span>
        </button>
        <?php endif ?>

        <?php if ($canBest && !$entry['is_best_answer']): ?>
        <button class="ds-button vox-btn vox-btn--sm vox-vote-btn vox-best-btn" data-variant="tertiary" data-size="sm" data-vox-best-btn data-entry-key="<?= htmlspecialchars($entryKey) ?>">
            <?= vox_icon('star') ?> Mark as best
        </button>
        <?php endif ?>

        <button class="ds-button vox-btn vox-btn--sm vox-report-btn" data-variant="tertiary" data-size="sm" data-entry-key="<?= htmlspecialchars($entryKey) ?>" data-reason="inappropriate">
            <?= str_replace('<i ', '<i data-vox-flag ', vox_icon('flag')) ?>
            <span data-vox-report-label>Report</span>
        </button>

        <span class="vox-action-feedback" data-vox-action-feedback hidden></span>
    </div>

    <!-- Reply form -->
    <?php if ($showReplyForm && $depth < Vox::MAX_DEPTH): ?>
    <?php $replyBodyId = vox_control_id('vox-reply') . '-body'; ?>
    <div class="vox-reply-form" id="reply-<?= htmlspecialchars($entryKey) ?>"
         data-vox-reply-form="reply-<?= htmlspecialchars($entryKey) ?>" hidden>
        <form class="vox-form__element" data-vox-form data-entry-list="replies-<?= htmlspecialchars($entryKey) ?>">
            <?= vox_csrf() ?>
            <input type="hidden" name="page_key"  value="<?= htmlspecialchars($pageKey) ?>">
            <input type="hidden" name="block_id"  value="<?= htmlspecialchars($entry['block_id'] ?? '') ?>">
            <input type="hidden" name="type"      value="comment">
            <input type="hidden" name="parent_key" value="<?= htmlspecialchars($entryKey) ?>">
            <div class="ds-field vox-field">
                <label class="ds-label vox-form__label" for="<?= htmlspecialchars($replyBodyId) ?>">Reply</label>
                <textarea id="<?= htmlspecialchars($replyBodyId) ?>" name="body" class="ds-input vox-textarea" rows="3" placeholder="Write a reply&hellip;"></textarea>
                <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
            </div>
            <div class="vox-form__actions">
                <button type="submit" class="ds-button vox-btn vox-btn--primary vox-btn--sm" data-variant="primary" data-size="sm">
                    <?= vox_icon('paper-plane') ?> Post reply
                </button>
                <button type="button" class="ds-button vox-btn vox-btn--sm" data-variant="tertiary" data-size="sm"
                        onclick="this.closest('[data-vox-reply-form]').hidden=true">Cancel</button>
            </div>
            <span data-vox-feedback hidden></span>
        </form>
    </div>
    <?php endif ?>

    <!-- Children -->
    <?php if ($showChildren): ?>
    <div class="vox-entry__replies" id="replies-<?= htmlspecialchars($entryKey) ?>" data-vox-replies="<?= htmlspecialchars($entryKey) ?>">
    <?php
    // Render children in an isolated scope (see vox_render_entry) so recursion
    // never clobbers this level's $entry/$depth or reserved API variables.
    $childDepth = min(Vox::MAX_DEPTH, $depth + 1);
    foreach ($children as $child) {
        vox_render_entry($child, $childDepth, $pageId, $vox, $schema);
    }
    ?>
    </div>
    <?php endif ?>

    <?php if ($showChildren && $hasMore): ?>
    <button class="ds-button vox-btn vox-btn--sm vox-entry__show-more" data-variant="tertiary" data-size="sm"
            data-vox-show-more
            data-entry-key="<?= htmlspecialchars($entryKey) ?>"
            data-page-key="<?= htmlspecialchars($pageKey) ?>"
            data-loaded="<?= count($children) ?>"
            data-per-page="<?= $previewCount ?>">
        Show more replies
    </button>
    <?php endif ?>
</div>

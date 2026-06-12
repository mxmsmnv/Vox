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
$entryKey = $vox->publicKey('entry', (int)$entry['id']);
$pageKey  = $vox->publicKey('page', (int)$pageId);

// Field values and likes via module methods — no SQL here
$fieldVals = $vox->getEntryFieldValues((int)$entry['id']);
$likes     = $vox->getEntryLikes((int)$entry['id']);
$userLiked = $likes['user_liked'];
$likeTotal = $likes['total'];

// Child entries (replies)
$previewCount = (int)$vox->cfg('preview_count');
$children     = $vox->getChildEntries((int)$entry['id'], $previewCount);
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
     data-vox-entry="<?= htmlspecialchars($entryKey) ?>"
     data-created="<?= htmlspecialchars($entry['created']) ?>"
     data-rating="<?= (int)($fieldVals['rating'] ?? 0) ?>"
     data-likes="<?= $likeTotal ?>">

    <!-- Head -->
    <div class="vox-entry__head">
        <?= vox_avatar($entry['author_name'], $depth === 0 ? 32 : 26) ?>
        <span class="vox-entry__author"><?= htmlspecialchars($entry['author_name']) ?></span>
        <?php if ($entry['author_rank']): ?>
            <?= vox_rank_badge($entry['author_rank']) ?>
        <?php endif ?>
        <?php if ($entry['is_owner_reply']): ?>
            <span class="vox-rank-badge vox-staff-badge">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Staff
            </span>
        <?php endif ?>
        <span class="vox-entry__time"><?= vox_time_ago($entry['created']) ?></span>
        <span class="vox-best-badge" data-vox-best-badge <?= $entry['is_best_answer'] ? '' : 'hidden' ?>>
            <i class="fa-solid fa-star" aria-hidden="true"></i> Best Answer
        </span>
    </div>

    <!-- Stars (reviews only) -->
    <?php if ($entry['type'] === 'review' && !empty($fieldVals['rating'])): ?>
    <div class="vox-entry__stars">
        <?= vox_stars((int)$fieldVals['rating']) ?>
    </div>
    <?php endif ?>

    <!-- Body -->
    <div class="vox-entry__body">
        <?= nl2br(htmlspecialchars($entry['body'])) ?>
    </div>

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
        <button class="vox-vote-btn<?= $userLiked ? ' vox-vote-btn--liked' : '' ?>" data-entry-key="<?= htmlspecialchars($entryKey) ?>" data-value="1" aria-label="Like">
            <i class="<?= $userLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart"
               data-vox-heart aria-hidden="true"></i>
            <span data-vox-likes><?= $likeTotal ?></span>
        </button>

        <?php if ($depth < Vox::MAX_DEPTH): ?>
        <button class="vox-reply-btn" data-reply-target="reply-<?= htmlspecialchars($entryKey) ?>" aria-label="Reply">
            <i class="fa-solid fa-reply" aria-hidden="true"></i> Reply
        </button>
        <?php endif ?>

        <?php if ($entry['type'] === 'review'): ?>
        <button type="button" class="vox-vote-btn"
                data-vox-comments-toggle="<?= htmlspecialchars($entryKey) ?>"
                aria-expanded="false">
            <i class="fa-regular fa-comment" aria-hidden="true"></i>
            <span data-vox-toggle-label>Comments</span>
        </button>
        <?php endif ?>

        <?php if ($canBest && !$entry['is_best_answer']): ?>
        <button class="vox-vote-btn vox-best-btn" data-vox-best-btn data-entry-key="<?= htmlspecialchars($entryKey) ?>">
            <i class="fa-regular fa-star" aria-hidden="true"></i> Mark as best
        </button>
        <?php endif ?>

        <button class="vox-report-btn" data-entry-key="<?= htmlspecialchars($entryKey) ?>" data-reason="inappropriate">
            <i class="fa-regular fa-flag" data-vox-flag aria-hidden="true"></i>
            <span data-vox-report-label>Report</span>
        </button>

        <span class="vox-action-feedback" data-vox-action-feedback hidden></span>
    </div>

    <!-- Reply form -->
    <?php if ($depth < Vox::MAX_DEPTH): ?>
    <div class="vox-reply-form" id="reply-<?= htmlspecialchars($entryKey) ?>"
         data-vox-reply-form="reply-<?= htmlspecialchars($entryKey) ?>" hidden>
        <form data-vox-form data-entry-list="replies-<?= htmlspecialchars($entryKey) ?>">
            <?= vox_csrf() ?>
            <input type="hidden" name="page_key"  value="<?= htmlspecialchars($pageKey) ?>">
            <input type="hidden" name="block_id"  value="<?= htmlspecialchars($entry['block_id'] ?? '') ?>">
            <input type="hidden" name="type"      value="comment">
            <input type="hidden" name="parent_key" value="<?= htmlspecialchars($entryKey) ?>">
            <textarea name="body" class="vox-textarea" rows="3" placeholder="Write a reply&hellip;"></textarea>
            <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
            <div class="vox-form__actions">
                <button type="submit" class="vox-btn vox-btn--primary vox-btn--sm">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Post
                </button>
                <button type="button" class="vox-btn vox-btn--sm"
                        onclick="this.closest('[data-vox-reply-form]').hidden=true">Cancel</button>
            </div>
            <span data-vox-feedback hidden></span>
        </form>
    </div>
    <?php endif ?>

    <!-- Children -->
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

    <?php if ($hasMore): ?>
    <button class="vox-btn vox-btn--sm vox-entry__show-more"
            data-vox-show-more
            data-entry-key="<?= htmlspecialchars($entryKey) ?>"
            data-page-key="<?= htmlspecialchars($pageKey) ?>"
            data-loaded="<?= count($children) ?>"
            data-per-page="<?= $previewCount ?>">
        Show more replies
    </button>
    <?php endif ?>
</div>

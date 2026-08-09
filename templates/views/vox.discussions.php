<?php namespace ProcessWire;
/**
 * Vox — Community discussions: compact index and conversation detail.
 */
$vox = $vox ?? wire('modules')->get('Vox');
$pageId = (int)$page->id;
$pageKey = $vox->publicKey('page', $pageId);
$tmplId = (int)$page->template->id;
$schema = $vox->getSchema($tmplId, Vox::TYPE_COMMENT);
$selectedThread = $communityThread ?? null;

$result = $vox->getEntries([
    'page_id' => $pageId,
    'type' => Vox::TYPE_THREAD,
    'depth' => 0,
    'per_page' => 20,
]);
$threads = $result['entries'];
$blockIds = $vox->getPageBlockIds($pageId);
$blockCounts = $vox->getBlockCounts($pageId, $blockIds);

require_once __DIR__ . '/vox.helpers.php';

$threadExcerpt = static function (string $body, int $limit = 240): string {
    $body = trim((string)preg_replace('/\s+/u', ' ', strip_tags($body)));
    if (mb_strlen($body) <= $limit) return $body;
    return rtrim(mb_substr($body, 0, $limit - 1), " \t\n\r\0\x0B,.;:!?") . '…';
};
$threadTitle = static function (array $entry) use ($threadExcerpt): string {
    $title = trim((string)($entry['title'] ?? ''));
    if ($title !== '') return $title;
    $lines = preg_split('/\R+/u', trim((string)($entry['body'] ?? ''))) ?: [];
    $firstLine = trim(strip_tags((string)($lines[0] ?? '')));
    return $threadExcerpt($firstLine !== '' ? $firstLine : (string)($entry['body'] ?? ''), 110);
};
$threadBody = static function (array $entry): string {
    $body = trim((string)($entry['body'] ?? ''));
    if (trim((string)($entry['title'] ?? '')) !== '') return $body;
    $parts = preg_split('/\R{2,}/u', $body, 2) ?: [];
    return count($parts) > 1 ? trim((string)$parts[1]) : $body;
};
?>

<div class="vox-wrap lqrs-discussions" id="vox-discussions" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">
    <?php if ($selectedThread): ?>
        <?php
        $selectedKey = $vox->publicKey('entry', (int)$selectedThread['id']);
        $selectedReplyCount = $vox->getEntryReplyCount((int)$selectedThread['id']);
        $selectedLikes = $vox->getEntryLikes((int)$selectedThread['id']);
        ?>
        <article class="lqrs-discussion-detail" aria-labelledby="discussion-title">
            <div class="lqrs-discussion-detail__topbar">
                <a class="ds-link lqrs-discussion-back" href="/community/?view=discussions">
                    <?= vox_icon('arrow-left') ?> All discussions
                </a>
                <span class="ds-tag" data-color="neutral" data-size="sm">Open discussion</span>
            </div>
            <header class="lqrs-discussion-detail__header">
                <div class="lqrs-discussion-detail__copy">
                    <p class="lqrs-detail-eyebrow">Community discussion</p>
                    <h1 class="ds-heading" data-size="xl" id="discussion-title"><?= htmlspecialchars($threadTitle($selectedThread)) ?></h1>
                    <div class="lqrs-discussion-detail__identity">
                        <?= vox_avatar((string)$selectedThread['author_name'], 48, (string)($selectedThread['author_avatar'] ?? '')) ?>
                        <div>
                            <?php if (!empty($selectedThread['author_key'])): ?>
                                <a href="/community/?profile=<?= rawurlencode((string)$selectedThread['author_key']) ?>"><?= htmlspecialchars((string)$selectedThread['author_name']) ?></a>
                            <?php else: ?>
                                <strong><?= htmlspecialchars((string)$selectedThread['author_name']) ?></strong>
                            <?php endif; ?>
                            <span>Started <?= vox_time_ago((string)$selectedThread['created']) ?></span>
                        </div>
                        <?php if (!empty($selectedThread['author_rank'])): ?><?= vox_rank_badge((string)$selectedThread['author_rank']) ?><?php endif; ?>
                        <?php if (!empty($selectedThread['is_owner_reply'])): ?>
                            <span class="vox-rank-badge vox-staff-badge"><?= vox_icon('circle-check') ?> Staff</span>
                        <?php endif; ?>
                    </div>
                </div>
                <dl class="lqrs-discussion-detail__metrics" aria-label="Discussion activity">
                    <div><dt>Replies</dt><dd><?= $selectedReplyCount ?></dd></div>
                    <div><dt>Likes</dt><dd><?= (int)$selectedLikes['total'] ?></dd></div>
                </dl>
            </header>

            <section class="lqrs-discussion-conversation" aria-label="Discussion conversation">
                <header class="lqrs-discussion-conversation__header">
                    <div>
                        <p class="lqrs-detail-eyebrow">Conversation</p>
                        <h2 class="ds-heading" data-size="md"><?= $selectedReplyCount ? 'Community replies' : 'Start the conversation' ?></h2>
                        <p><?= $selectedReplyCount ? 'Read the discussion in order and add a useful, focused reply.' : 'No replies yet. Add the first useful response to this discussion.' ?></p>
                    </div>
                    <span class="ds-tag" data-color="neutral" data-size="sm"><?= $selectedReplyCount ?> repl<?= $selectedReplyCount === 1 ? 'y' : 'ies' ?></span>
                </header>
                <?php $entry = $selectedThread; $depth = 0; $voxEntryBodyOverride = $threadBody($selectedThread); include __DIR__ . '/vox.entry.php'; ?>
            </section>
        </article>
    <?php else: ?>
        <?php if ($blockCounts): ?>
        <section class="vox-section lqrs-discussion-blocks">
            <h2 class="ds-heading vox-section-title" data-size="md"><?= vox_icon('table-list') ?> Page discussions</h2>
            <p class="ds-paragraph vox-section-subtitle" data-size="sm">Discuss a specific highlighted section of this page.</p>

            <div class="vox-block-list">
            <?php foreach ($blockCounts as $blockId => $cnt):
                $blockEntries = $vox->getEntries([
                    'page_id' => $pageId,
                    'block_id' => $blockId,
                    'type' => Vox::TYPE_COMMENT,
                    'depth' => 0,
                    'per_page' => 20,
                ])['entries'];
                $blockFormPrefix = vox_control_id('vox-block-' . $blockId);
                $blockNameId = $blockFormPrefix . '-name';
                $blockBodyId = $blockFormPrefix . '-body';
            ?>
            <div class="vox-block-item">
                <button class="ds-button vox-btn" data-variant="tertiary" data-vox-block-trigger="<?= htmlspecialchars($blockId) ?>">
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
                        <form class="vox-form__element" data-vox-form data-entry-list="vox-block-entries-<?= htmlspecialchars($blockId) ?>" aria-label="Comment on this section">
                            <?= vox_csrf() ?>
                            <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                            <input type="hidden" name="block_id" value="<?= htmlspecialchars($blockId) ?>">
                            <input type="hidden" name="type" value="comment">
                            <?php if (!wire('user')->isLoggedIn()): ?>
                            <div class="ds-field vox-field vox-field--compact">
                                <label class="ds-label vox-form__label" for="<?= htmlspecialchars($blockNameId) ?>">Your name</label>
                                <input id="<?= htmlspecialchars($blockNameId) ?>" type="text" name="guest_name" class="ds-input vox-input" placeholder="Your name (optional)">
                            </div>
                            <?php endif ?>
                            <div class="ds-field vox-field">
                                <label class="ds-label vox-form__label" for="<?= htmlspecialchars($blockBodyId) ?>">Comment</label>
                                <textarea id="<?= htmlspecialchars($blockBodyId) ?>" name="body" class="ds-input vox-textarea" rows="3" placeholder="Comment on this section…"></textarea>
                                <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                            </div>
                            <div class="vox-form__actions">
                                <button type="submit" class="ds-button vox-btn vox-btn--primary vox-btn--sm" data-variant="primary" data-size="sm"><?= vox_icon('paper-plane') ?> Post comment</button>
                            </div>
                            <span data-vox-feedback hidden></span>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="lqrs-discussion-index" aria-labelledby="discussion-list-title">
            <header class="lqrs-discussion-index__header">
                <div>
                    <p class="lqrs-detail-eyebrow">Open conversations</p>
                    <h2 class="ds-heading" data-size="md" id="discussion-list-title">Latest discussions</h2>
                    <p><?= (int)$result['total'] ?> conversations from the LQRS community.</p>
                </div>
                <details class="lqrs-discussion-composer">
                    <summary class="ds-button vox-btn vox-btn--primary" data-variant="primary"><?= vox_icon('circle-plus') ?> Start a discussion</summary>
                    <?php
                    $threadFormPrefix = vox_control_id('vox-thread');
                    $threadNameId = $threadFormPrefix . '-name';
                    $threadTitleId = $threadFormPrefix . '-title';
                    $threadBodyId = $threadFormPrefix . '-body';
                    ?>
                    <div class="vox-form lqrs-discussion-composer__form">
                        <form class="vox-form__element" data-vox-form data-entry-list="vox-threads-list" aria-label="Start a discussion">
                            <?= vox_csrf() ?>
                            <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                            <input type="hidden" name="type" value="thread">
                            <?php if (!wire('user')->isLoggedIn()): ?>
                            <div class="ds-field vox-field vox-field--compact">
                                <label class="ds-label vox-form__label" for="<?= htmlspecialchars($threadNameId) ?>">Your name</label>
                                <input id="<?= htmlspecialchars($threadNameId) ?>" type="text" name="guest_name" class="ds-input vox-input" placeholder="Your name (optional)">
                            </div>
                            <?php endif ?>
                            <div class="ds-field vox-field vox-field--compact">
                                <label class="ds-label vox-form__label" for="<?= htmlspecialchars($threadTitleId) ?>">Discussion title</label>
                                <input id="<?= htmlspecialchars($threadTitleId) ?>" type="text" name="title" class="ds-input vox-input" placeholder="Write a clear, specific title" required>
                            </div>
                            <div class="ds-field vox-field">
                                <label class="ds-label vox-form__label" for="<?= htmlspecialchars($threadBodyId) ?>">Discussion</label>
                                <textarea id="<?= htmlspecialchars($threadBodyId) ?>" name="body" class="ds-input vox-textarea" rows="6" placeholder="Add context that helps others respond…" required></textarea>
                                <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                            </div>
                            <div class="vox-form__actions">
                                <button type="submit" class="ds-button vox-btn vox-btn--primary" data-variant="primary"><?= vox_icon('arrow-right') ?> Create discussion</button>
                            </div>
                            <span data-vox-feedback hidden></span>
                        </form>
                    </div>
                </details>
            </header>

            <div class="lqrs-discussion-list" id="vox-threads-list" data-vox-entries-list>
                <?php foreach ($threads as $thread): ?>
                    <?php
                    $threadKey = $vox->publicKey('entry', (int)$thread['id']);
                    $threadUrl = '/community/?' . http_build_query(['view' => 'discussions', 'thread' => $threadKey]);
                    $replyCount = $vox->getEntryReplyCount((int)$thread['id']);
                    $threadLikes = $vox->getEntryLikes((int)$thread['id']);
                    ?>
                    <article class="lqrs-discussion-list-item" id="<?= htmlspecialchars($vox->publicAnchor('entry', (int)$thread['id'])) ?>">
                        <div class="lqrs-discussion-list-item__avatar">
                            <?= vox_avatar((string)$thread['author_name'], 40, (string)($thread['author_avatar'] ?? '')) ?>
                        </div>
                        <div class="lqrs-discussion-list-item__copy">
                            <div class="lqrs-discussion-list-item__byline">
                                <?php if (!empty($thread['author_key'])): ?>
                                    <a href="/community/?profile=<?= rawurlencode((string)$thread['author_key']) ?>"><?= htmlspecialchars((string)$thread['author_name']) ?></a>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars((string)$thread['author_name']) ?></strong>
                                <?php endif; ?>
                                <span><?= vox_time_ago((string)$thread['created']) ?></span>
                            </div>
                            <h3 class="ds-heading" data-size="xs"><a href="<?= htmlspecialchars($threadUrl) ?>"><?= htmlspecialchars($threadTitle($thread)) ?></a></h3>
                            <p><?= htmlspecialchars($threadExcerpt($threadBody($thread))) ?></p>
                            <div class="lqrs-discussion-list-item__stats" aria-label="Discussion activity">
                                <span><?= vox_icon('comment') ?> <?= $replyCount ?> repl<?= $replyCount === 1 ? 'y' : 'ies' ?></span>
                                <span><?= vox_icon('heart') ?> <?= (int)$threadLikes['total'] ?> likes</span>
                            </div>
                        </div>
                        <a class="lqrs-discussion-list-item__arrow" href="<?= htmlspecialchars($threadUrl) ?>" aria-label="Open <?= htmlspecialchars($threadTitle($thread)) ?>">
                            <?= vox_icon('arrow-right') ?>
                        </a>
                    </article>
                <?php endforeach ?>
                <?php if (!$threads): ?>
                    <div class="vox-empty"><?= vox_icon('comment') ?> No discussions yet. Start one above.</div>
                <?php endif ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php namespace ProcessWire;
/**
 * Vox — Forum landing template.
 *
 * Renders a Migipedia-style forum overview:
 * - hero with start/search controls;
 * - recommended threads;
 * - category cards from child pages (or provided $voxForumCategories);
 * - newest threads and all categories.
 *
 * Optional variables:
 *   $voxForumCategories = [$pageA, '/path/', ['page' => $pageB, 'description' => '...']]
 *   $voxForumTitle = 'Forum'
 *   $voxForumIntro = '...'
 */
$vox = $vox ?? wire('modules')->get('Vox');
$input = wire('input');
$san = wire('sanitizer');

require_once __DIR__ . '/vox.helpers.php';

$forumTitle = $voxForumTitle ?? $page->title;
$forumIntro = $voxForumIntro ?? 'General discussions, questions, ideas and community updates.';
$rawCategories = $voxForumCategories ?? iterator_to_array($page->children('include=hidden, sort=sort'));
if (!$rawCategories) $rawCategories = [$page];

$categoryRows = [];
foreach ($rawCategories as $item) {
    $catPage = null;
    $desc = '';

    if ($item instanceof Page) {
        $catPage = $item;
    } elseif (is_string($item)) {
        $catPage = wire('pages')->get($item);
    } elseif (is_array($item)) {
        $catPage = $item['page'] ?? null;
        if (is_string($catPage)) $catPage = wire('pages')->get($catPage);
        $desc = (string)($item['description'] ?? '');
    }

    if (!$catPage instanceof Page || !$catPage->id) continue;
    if ($desc === '') {
        foreach (['summary', 'description', 'body'] as $fieldName) {
            if ($catPage->template && $catPage->template->fieldgroup && $catPage->template->fieldgroup->has($fieldName)) {
                $desc = trim(strip_tags((string)$catPage->get($fieldName)));
                if ($desc !== '') break;
            }
        }
    }
    $catTitle = $vox->displayText((string)$catPage->title);
    if ($desc === '') $desc = 'Discuss topics in ' . $catTitle . '.';

    $result = $vox->getEntries([
        'page_id'  => (int)$catPage->id,
        'type'     => Vox::TYPE_THREAD,
        'depth'    => 0,
        'per_page' => 3,
    ]);

    $categoryRows[] = [
        'page' => $catPage,
        'title' => $catTitle,
        'description' => $desc,
        'threads' => $result['entries'],
        'total' => (int)$result['total'],
        'page_key' => $vox->publicKey('page', (int)$catPage->id),
    ];
}

$allThreads = [];
foreach ($categoryRows as $row) {
    foreach ($row['threads'] as $thread) {
        $thread['_category_title'] = $row['title'];
        $thread['_category_url'] = $row['page']->url;
        $allThreads[] = $thread;
    }
}
usort($allThreads, fn($a, $b) => strcmp((string)$b['created'], (string)$a['created']));

$recommended = $allThreads;
usort($recommended, function($a, $b) use ($vox) {
    $al = $vox->getEntryLikes((int)$a['id'])['total'];
    $bl = $vox->getEntryLikes((int)$b['id'])['total'];
    return $bl <=> $al ?: strcmp((string)$b['created'], (string)$a['created']);
});
$recommended = array_slice($recommended, 0, 2);
$newest = array_slice($allThreads, 0, 3);
$firstCategory = $categoryRows[0] ?? null;

$threadTitle = function(array $entry): string {
    $body = trim(preg_replace('/\s+/', ' ', strip_tags((string)($entry['body'] ?? ''))));
    if ($body === '') return 'Untitled discussion';
    $sentence = preg_split('/(?<=[.!?])\s+/', $body)[0] ?? $body;
    return mb_strlen($sentence) > 86 ? mb_substr($sentence, 0, 83) . '...' : $sentence;
};
$threadStats = function(array $entry) use ($vox): array {
    $replyCount = $vox->getEntryReplyCount((int)$entry['id']);
    $likes = $vox->getEntryLikes((int)$entry['id'])['total'];
    $lastActivity = $vox->getEntryLastActivity((int)$entry['id']) ?: (string)$entry['created'];
    return [$replyCount, $likes, $lastActivity];
};
$threadUrl = function(array $entry, string $fallbackUrl = '') use ($page, $vox): string {
    $baseUrl = trim((string)($entry['_category_url'] ?? $fallbackUrl));
    if ($baseUrl === '') $baseUrl = (string)$page->url;
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    return $baseUrl . $separator . 'view=discussions#' . $vox->publicAnchor('entry', (int)$entry['id']);
};
?>

<div class="vox-wrap vox-forum" id="vox-forum" data-discuss-page-key="<?= htmlspecialchars($firstCategory['page_key'] ?? '') ?>">
    <section class="vox-forum-hero">
        <div>
            <h1 class="ds-heading" data-size="xl"><?= htmlspecialchars($forumTitle) ?></h1>
            <p class="ds-paragraph" data-size="lg"><?= htmlspecialchars($forumIntro) ?></p>
        </div>
        <div class="vox-forum-actions">
            <a class="ds-button vox-btn vox-btn--primary" data-variant="primary" href="#vox-start-discussion">
                <?= vox_icon('circle-plus') ?> Start Discussion
            </a>
            <label class="vox-forum-search">
                <?= vox_icon('magnifying-glass') ?>
                <span class="ds-sr-only">Search discussions</span>
                <input type="search" class="ds-input vox-input" data-vox-forum-search placeholder="Search discussions">
            </label>
        </div>
    </section>

    <?php if ($recommended): ?>
    <section class="vox-forum-band">
        <h2 class="ds-heading" data-size="lg">Recommended by us</h2>
        <div class="vox-forum-recommended">
        <?php foreach ($recommended as $entry):
            [$replyCount, $likes, $lastActivity] = $threadStats($entry);
        ?>
            <article class="vox-forum-feature" data-vox-forum-card>
                <a class="vox-forum-card-link" href="<?= htmlspecialchars($threadUrl($entry)) ?>">
                    <div class="vox-forum-meta">
                        <?= vox_avatar((string)$entry['author_name'], 24, (string)($entry['author_avatar'] ?? '')) ?>
                        <span>from <?= htmlspecialchars($entry['author_name']) ?> · <?= vox_time_ago($entry['created']) ?></span>
                    </div>
                    <h3 class="ds-heading" data-size="sm"><?= htmlspecialchars($threadTitle($entry)) ?></h3>
                    <div class="vox-forum-card-stats">
                        <span><?= vox_icon('comment') ?> <?= $replyCount ?></span>
                        <span><?= vox_icon('heart') ?> <?= $likes ?></span>
                        <span>Last activity <?= htmlspecialchars(vox_time_ago($lastActivity)) ?></span>
                    </div>
                </a>
            </article>
        <?php endforeach ?>
        </div>
    </section>
    <?php endif ?>

    <section class="vox-card vox-card--mb-16" id="vox-start-discussion">
        <div class="vox-card__head"><span class="ds-heading" data-size="xs"><?= vox_icon('pen-to-square') ?> Start a discussion</span></div>
        <div class="vox-form">
            <form data-vox-form data-entry-list="vox-forum-newest-list">
                <?= vox_csrf() ?>
                <input type="hidden" name="page_key" value="<?= htmlspecialchars($firstCategory['page_key'] ?? '') ?>" data-vox-forum-page-key>
                <input type="hidden" name="type" value="thread">
                <?php if (count($categoryRows) > 1): ?>
                <div class="vox-field">
                    <label class="ds-label vox-form__label" for="vox-forum-category">Category</label>
                    <select id="vox-forum-category" class="ds-input vox-input" data-vox-forum-category>
                    <?php foreach ($categoryRows as $row): ?>
                        <option value="<?= htmlspecialchars($row['page_key']) ?>"><?= htmlspecialchars($row['title']) ?></option>
                    <?php endforeach ?>
                    </select>
                </div>
                <?php endif ?>
                <?php if (!wire('user')->isLoggedIn()): ?>
                <div class="vox-field vox-field--compact">
                    <input type="text" name="guest_name" class="ds-input vox-input" aria-label="Your name" placeholder="Your name (optional)">
                </div>
                <?php endif ?>
                <textarea name="body" class="ds-input vox-textarea" aria-label="Discussion" rows="4" placeholder="What would you like to discuss?" required></textarea>
                <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                <div class="vox-form__actions">
                    <button type="submit" class="ds-button vox-btn vox-btn--primary" data-variant="primary"><?= vox_icon('arrow-right') ?> Create Thread</button>
                </div>
                <span data-vox-feedback hidden></span>
            </form>
        </div>
    </section>

    <div class="vox-forum-layout">
        <div class="vox-forum-main">
        <?php foreach ($categoryRows as $row): ?>
            <section class="vox-forum-category" data-vox-forum-card>
                <div class="vox-forum-category__head">
                    <div>
                        <h2 class="ds-heading" data-size="md"><?= htmlspecialchars($row['title']) ?></h2>
                        <p class="ds-paragraph" data-size="sm"><?= htmlspecialchars($row['description']) ?></p>
                    </div>
                    <a class="ds-button vox-btn vox-btn--sm" data-variant="tertiary" href="<?= htmlspecialchars($row['page']->url) ?>">Show All</a>
                </div>

                <?php if ($row['threads']): ?>
                <div class="vox-forum-thread-list">
                    <?php foreach ($row['threads'] as $entry):
                        [$replyCount, $likes, $lastActivity] = $threadStats($entry);
                    ?>
                    <article class="vox-forum-thread" data-vox-forum-card>
                        <a class="vox-forum-card-link" href="<?= htmlspecialchars($threadUrl($entry, (string)$row['page']->url)) ?>">
                            <h3 class="ds-heading" data-size="sm"><?= htmlspecialchars($threadTitle($entry)) ?></h3>
                            <div class="vox-forum-meta">
                                <?= vox_avatar((string)$entry['author_name'], 24, (string)($entry['author_avatar'] ?? '')) ?>
                                <span>from <?= htmlspecialchars($entry['author_name']) ?> · <?= vox_time_ago($entry['created']) ?></span>
                            </div>
                            <div class="vox-forum-card-stats">
                                <span><?= vox_icon('comment') ?> <?= $replyCount ?></span>
                                <span><?= vox_icon('heart') ?> <?= $likes ?></span>
                                <span>Last activity <?= htmlspecialchars(vox_time_ago($lastActivity)) ?></span>
                            </div>
                        </a>
                    </article>
                    <?php endforeach ?>
                </div>
                <?php else: ?>
                    <div class="vox-empty"><?= vox_icon('comment') ?> No discussions in this category yet.</div>
                <?php endif ?>
            </section>
        <?php endforeach ?>
        </div>

        <aside class="vox-forum-side">
            <section class="vox-forum-side-card">
                <h2 class="ds-heading" data-size="md">Newest threads</h2>
                <div id="vox-forum-newest-list" data-vox-entries-list>
                <?php foreach ($newest as $entry):
                    [$replyCount, $likes, $lastActivity] = $threadStats($entry);
                ?>
                    <article class="vox-forum-mini" data-vox-forum-card>
                        <a class="vox-forum-card-link" href="<?= htmlspecialchars($threadUrl($entry)) ?>">
                            <h3 class="ds-heading" data-size="xs"><?= htmlspecialchars($threadTitle($entry)) ?></h3>
                            <div class="vox-forum-meta"><?= htmlspecialchars($entry['_category_title'] ?? '') ?> · <?= vox_time_ago($entry['created']) ?></div>
                            <div class="vox-forum-card-stats"><span><?= $replyCount ?> replies</span><span><?= $likes ?> likes</span></div>
                        </a>
                    </article>
                <?php endforeach ?>
                </div>
            </section>

            <section class="vox-forum-side-card">
                <h2 class="ds-heading" data-size="md">All Categories</h2>
                <div class="vox-forum-category-list">
                <?php foreach ($categoryRows as $row): ?>
                    <a href="<?= htmlspecialchars($row['page']->url) ?>">
                        <span><?= htmlspecialchars($row['title']) ?></span>
                        <strong><?= $row['total'] ?></strong>
                    </a>
                <?php endforeach ?>
                </div>
            </section>
        </aside>
    </div>

    <div data-vox-forum-empty hidden class="vox-empty"><?= vox_icon('magnifying-glass') ?> No discussions match this search.</div>
</div>

<script>
(function(){
    var root = document.getElementById('vox-forum');
    if (!root) return;
    var category = root.querySelector('[data-vox-forum-category]');
    var pageKey = root.querySelector('[data-vox-forum-page-key]');
    if (category && pageKey) {
        category.addEventListener('change', function(){ pageKey.value = category.value; });
    }
    var search = root.querySelector('[data-vox-forum-search]');
    var empty = root.querySelector('[data-vox-forum-empty]');
    if (!search) return;
    search.addEventListener('input', function(){
        var q = search.value.trim().toLowerCase();
        var cards = Array.prototype.slice.call(root.querySelectorAll('[data-vox-forum-card]'));
        var visible = 0;
        cards.forEach(function(card){
            var hit = !q || card.textContent.toLowerCase().indexOf(q) !== -1;
            card.hidden = !hit;
            if (hit) visible++;
        });
        if (empty) empty.hidden = visible > 0;
    });
})();
</script>

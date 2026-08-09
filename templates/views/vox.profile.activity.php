<?php namespace ProcessWire;
/**
 * Vox — Profile activity section.
 */
$vox = $vox ?? wire('modules')->get('Vox');
require_once __DIR__ . '/vox.helpers.php';

$voxProfile = $voxProfile ?? $vox->getUserProfileData($voxProfileUser ?? null, $voxProfileActivityLimit ?? 12);
if (!$voxProfile) return;

$activity = $voxProfile['activity'] ?? [];
$labels = [
    Vox::TYPE_REVIEW => ['Review', 'star'],
    Vox::TYPE_QUESTION => ['Question', 'question'],
    Vox::TYPE_THREAD => ['Discussion', 'comment-dots'],
    Vox::TYPE_COMMENT => ['Reply', 'comment'],
];
?>

<section class="vox-wrap vox-profile-section">
    <div class="vox-profile-section__head">
        <h2 class="ds-heading" data-size="xs"><?= vox_icon('clock-rotate-left') ?> Recent activity</h2>
        <span><?= count($activity) ?> item<?= count($activity) === 1 ? '' : 's' ?></span>
    </div>

    <div class="vox-profile-activity">
        <?php foreach ($activity as $entry):
            [$verb, $icon] = $labels[$entry['type']] ?? ['Post', 'comment'];
            $pageTitle = (string)($entry['page_title'] ?? '');
            $rawBody = trim((string)$entry['body']);
            $bodyLines = preg_split('/\R+/u', $rawBody) ?: [];
            $entryTitle = trim((string)($entry['title'] ?? '')) ?: trim(strip_tags((string)($bodyLines[0] ?? '')));
            if (mb_strlen($entryTitle) > 110) $entryTitle = rtrim(mb_substr($entryTitle, 0, 107)) . '…';
            $body = trim((string)preg_replace('/\s+/u', ' ', strip_tags($rawBody)));
            if ($entryTitle !== '' && str_starts_with($body, trim(strip_tags((string)($bodyLines[0] ?? ''))))) {
                $body = trim(mb_substr($body, mb_strlen(trim(strip_tags((string)($bodyLines[0] ?? ''))))));
            }
            if (mb_strlen($body) > 180) $body = rtrim(mb_substr($body, 0, 177)) . '…';
            $likes = $vox->getEntryLikes((int)$entry['id'])['total'];
            $sourcePage = wire('pages')->get((int)($entry['page_id'] ?? 0));
            $entryUrl = $sourcePage->id
                ? (string)$sourcePage->url . '#' . $vox->publicAnchor('entry', (int)$entry['id'])
                : '/community/?view=activity';
            $linkEntry = $entry;
            if ((string)$entry['type'] === Vox::TYPE_COMMENT) {
                $rootId = (int)(($entry['root_id'] ?? 0) ?: ($entry['parent_id'] ?? 0));
                $rootEntry = $rootId ? $vox->getEntry($rootId) : null;
                if ($rootEntry) $linkEntry = $rootEntry;
            }
            if ((string)$linkEntry['type'] === Vox::TYPE_THREAD) {
                $entryUrl = '/community/?' . http_build_query([
                    'view' => 'discussions',
                    'thread' => $vox->publicKey('entry', (int)$linkEntry['id']),
                ]);
            } elseif ((string)$linkEntry['type'] === Vox::TYPE_QUESTION) {
                $entryUrl = '/community/?' . http_build_query([
                    'view' => 'questions',
                    'question' => $vox->publicKey('entry', (int)$linkEntry['id']),
                ]);
            }
        ?>
        <article class="vox-profile-activity__item" id="<?= htmlspecialchars($vox->publicAnchor('entry', (int)$entry['id'])) ?>">
            <div class="vox-profile-activity__icon"><?= vox_icon($icon) ?></div>
            <div class="vox-profile-activity__copy">
                <div class="vox-profile-activity__context"><strong><?= htmlspecialchars($verb) ?></strong><?php if ($pageTitle): ?><span><?= htmlspecialchars($pageTitle) ?></span><?php endif ?></div>
                <h3 class="ds-heading" data-size="2xs"><a href="<?= htmlspecialchars($entryUrl) ?>"><?= htmlspecialchars($entryTitle ?: $verb) ?></a></h3>
                <?php if ($body !== ''): ?><p><?= htmlspecialchars($body) ?></p><?php endif ?>
                <span><?= vox_time_ago((string)$entry['created']) ?> · <?= number_format($likes) ?> like<?= $likes === 1 ? '' : 's' ?></span>
            </div>
            <a class="vox-profile-activity__arrow" href="<?= htmlspecialchars($entryUrl) ?>" aria-label="Open <?= htmlspecialchars($entryTitle ?: $verb) ?>"><?= vox_icon('arrow-right') ?></a>
        </article>
        <?php endforeach ?>
        <?php if (!$activity): ?><div class="vox-empty"><?= vox_icon('comment') ?> No activity yet.</div><?php endif ?>
    </div>
</section>

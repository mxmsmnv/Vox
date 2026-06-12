<?php namespace ProcessWire;
/**
 * Vox — Reviews (Ratings) page template.
 *
 * Usage in PW template:
 *   include $modules->getPath('Vox') . 'templates/views/vox.reviews.php';
 *
 * Required variables: $page (PW page), $vox (Vox module instance).
 */
$vox      = $vox ?? wire('modules')->get('Vox');
$pageId   = $page->id;
$pageKey  = $vox->publicKey('page', (int)$pageId);
$tmplId   = $page->template->id;
$schema   = $vox->getSchema($tmplId, Vox::TYPE_REVIEW);
$perPage  = 10;
$currPage = max(1, (int)(wire('input')->get('p') ?? 1));

// Summary data
$ratingDist   = $vox->getRatingDistribution($pageId);
$totalReviews = array_sum($ratingDist);
$avgRating    = $vox->getAverageRating($pageId);
$recData      = $vox->getRecommendRate($pageId);
$recRate      = $recData['rate'];

// Entry list
$result  = $vox->getEntries([
    'page_id'  => $pageId,
    'type'     => Vox::TYPE_REVIEW,
    'depth'    => 0,
    'per_page' => $perPage,
    'page'     => $currPage,
]);
$reviews = $result['entries'];
$total   = $result['total'];
$totalPages   = (int)ceil($total / $perPage);

require_once __DIR__ . '/vox.helpers.php';
?>

<div class="vox-wrap" id="vox-reviews" data-discuss-page-key="<?= htmlspecialchars($pageKey) ?>">

    <!-- Rating summary -->
    <div class="vox-review-summary">

        <div class="vox-review-summary__score">
            <div class="vox-review-summary__num"><?= number_format($avgRating, 1) ?></div>
            <div class="vox-review-summary__stars"><?= vox_stars((int)round($avgRating)) ?></div>
            <div class="vox-review-summary__meta"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></div>
        </div>

        <div class="vox-rating-summary">
            <?php for ($i = 5; $i >= 1; $i--):
                $pct = $totalReviews ? round(100 * $ratingDist[$i] / $totalReviews) : 0;
            ?>
            <span class="vox-rating-summary__label"><?= $i ?>&#9733;</span>
            <div class="vox-rating-bar-bg" data-rating-filter="<?= $i ?>">
                <div class="vox-rating-bar-fill" data-vox-width="<?= $pct ?>"></div>
            </div>
            <span class="vox-rating-summary__count"><?= $ratingDist[$i] ?></span>
            <?php endfor ?>
        </div>

        <div class="vox-review-summary__rec">
            <div class="vox-review-summary__recnum"><?= $recRate ?>%</div>
            <div class="vox-review-summary__meta">recommend</div>
        </div>
    </div>

    <!-- Sort row -->
    <div class="vox-toolbar vox-toolbar--dense">
        <div class="vox-sort-switch" data-vox-sort-switch>
            <button type="button" class="vox-sort-btn vox-sort-btn--active" data-vox-sort-btn="newest">Most recent</button>
            <button type="button" class="vox-sort-btn" data-vox-sort-btn="helpful">Most helpful</button>
            <button type="button" class="vox-sort-btn" data-vox-sort-btn="highest">Highest rating</button>
            <button type="button" class="vox-sort-btn" data-vox-sort-btn="lowest">Lowest rating</button>
        </div>
        <select data-vox-sort class="vox-sort-select uk-hidden">
            <option value="newest" selected>Most recent</option>
            <option value="helpful">Most helpful</option>
            <option value="highest">Highest rating</option>
            <option value="lowest">Lowest rating</option>
        </select>
        <div class="vox-count-row vox-count-row--right"><?= $total ?> review<?= $total !== 1 ? 's' : '' ?></div>
    </div>

    <!-- Reviews list -->
    <div id="vox-reviews-list" data-vox-entries-list>
        <?php foreach ($reviews as $entry): ?>
            <?php $depth = 0; include __DIR__ . '/vox.entry.php'; ?>
        <?php endforeach ?>
        <?php if (!$reviews): ?>
            <div class="vox-empty"><i class="fa-regular fa-comment" aria-hidden="true"></i> No reviews yet. Be the first!</div>
        <?php endif ?>
        <div data-vox-no-results hidden class="vox-no-results">No reviews match this filter.</div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="vox-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?p=<?= $i ?>" class="vox-btn vox-btn--sm <?= $i === $currPage ? 'vox-btn--primary' : '' ?>"><?= $i ?></a>
        <?php endfor ?>
    </div>
    <?php endif ?>

    <!-- Write a review -->
    <div class="vox-card vox-card--mt-22" id="write-review">
        <div class="vox-card__head">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Write a review
        </div>
        <div class="vox-form">
            <form data-vox-form data-entry-list="vox-reviews-list">
                <?= vox_csrf() ?>
                <input type="hidden" name="page_key" value="<?= htmlspecialchars($pageKey) ?>">
                <input type="hidden" name="type"    value="review">

                <?php if (!wire('user')->isLoggedIn()): ?>
                <div class="vox-grid-2">
                    <div>
                        <label class="vox-form__label" for="vox-rn">Your name <span class="vox-inline-note">(optional)</span></label>
                        <input id="vox-rn" type="text" name="guest_name" class="vox-input" placeholder="Anonymous-XXX if blank">
                    </div>
                    <div>
                        <label class="vox-form__label" for="vox-re">Email <span class="vox-inline-note">(optional)</span></label>
                        <input id="vox-re" type="email" name="guest_email" class="vox-input" placeholder="your@email.com">
                    </div>
                </div>
                <?php endif ?>

                <div class="vox-field">
                    <label class="vox-form__label">Overall rating <span class="vox-field__label-required">*</span></label>
                    <?= vox_rating_picker('rating', 'Overall rating', 0, 'stars', true) ?>
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

                <div class="vox-field">
                    <label class="vox-form__label" for="vox-rb">Your review <span class="vox-field__label-required">*</span></label>
                    <textarea id="vox-rb" name="body" class="vox-textarea" rows="5" placeholder="Share your experience&hellip;" required></textarea>
                    <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
                </div>

                <div class="vox-field vox-field--spacious" data-vox-rec>
                    <label class="vox-form__label">Recommendation</label>
                    <div class="vox-recommend-row">
                        <button type="button" class="vox-btn" data-rec-value="1"><i class="fa-regular fa-thumbs-up" aria-hidden="true"></i> I recommend</button>
                        <button type="button" class="vox-btn" data-rec-value="0"><i class="fa-regular fa-thumbs-down" aria-hidden="true"></i> Would not recommend</button>
                        <input type="hidden" name="recommend" data-vox-rec-input value="">
                    </div>
                </div>

                <?php if ($vox->cfg('photo_uploads')): ?>
                <div class="vox-field vox-field--spacious">
                    <label class="vox-form__label">Photos <span class="vox-inline-note">(max <?= (int)$vox->cfg('photo_max') ?>)</span></label>
                    <div class="vox-dropzone" data-vox-dropzone>
                        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                        <div class="vox-dropzone__line">Drag & drop or <label class="vox-file-link vox-file-link--compact">browse<input type="file" name="photos[]" multiple accept="image/*" data-vox-photo-input data-max="<?= (int)$vox->cfg('photo_max') ?>"></label></div>
                        <div class="vox-dropzone__meta">Max <?= (int)$vox->cfg('photo_max_size') ?> MB per image</div>
                        <div data-vox-photo-preview class="vox-photo-preview"></div>
                    </div>
                </div>
                <?php endif ?>

                <div class="vox-form__actions">
                    <button type="submit" class="vox-btn vox-btn--primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Publish Review</button>
                </div>
                <span data-vox-feedback hidden></span>
            </form>
        </div>
    </div>

</div>

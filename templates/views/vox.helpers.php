<?php namespace ProcessWire;
/**
 * Vox shared view helpers.
 *
 * Pull into any Vox view partial with:
 *   require_once __DIR__ . '/vox.helpers.php';
 *
 * These were previously duplicated inline in each view behind a
 * `if (!function_exists('vox_stars'))` guard. That guard was ineffective
 * across includes — the functions live in the ProcessWire namespace while
 * `function_exists('vox_stars')` checks the global namespace — so a second
 * view including its own copy hit a "Cannot redeclare" fatal. Defining them
 * once here via require_once removes both the duplication and the bug.
 */

if (!function_exists('ProcessWire\\vox_stars')) {
/**
 * Render an avatar with initials.
 * @param string $name
 * @param int    $size  size bucket: 24, 32, 48
 */
function vox_avatar(string $name, int $size = 32): string {
    $initials = mb_strtoupper(mb_substr($name, 0, 2));
    $cls = $size <= 24 ? 'vox-av vox-av--sm' : ($size >= 48 ? 'vox-av vox-av--lg' : 'vox-av');
    return '<span class="' . $cls . '" aria-hidden="true">' . htmlspecialchars($initials) . '</span>';
}

/**
 * Render a row of stars.
 * @param int $rating  0–5
 * @param int $max
 */
function vox_stars(int $rating, int $max = 5): string {
    $html = '<span class="vox-stars" aria-label="' . $rating . ' out of ' . $max . '">';
    for ($i = 1; $i <= $max; $i++) {
        $html .= $i <= $rating
            ? '<span class="vox-star vox-star-on" aria-hidden="true">★</span>'
            : '<span class="vox-star" aria-hidden="true">★</span>';
    }
    return $html . '</span>';
}

function vox_rating_style(array $field): string {
    $options = $field['field_options'] ?? [];
    if (is_string($options)) {
        $options = array_filter(array_map('trim', explode(',', $options)));
    }
    foreach ((array)$options as $option) {
        $opt = strtolower(trim((string)$option));
        if (in_array($opt, ['dot', 'dots', 'style=dot', 'style=dots', 'style:dot', 'style:dots', 'display=dot', 'display=dots'], true)) {
            return 'dots';
        }
    }
    return 'stars';
}

function vox_rating_picker(string $name, string $label, int $value = 0, string $style = 'stars', bool $required = false, string $class = ''): string {
    $isDots = $style === 'dots';
    $wrapClass = 'vox-stars-wrap' . ($class ? ' ' . $class : '') . ($isDots ? ' vox-dots-wrap' : '');
    $buttonClass = $isDots ? 'vox-dot-pick' : 'vox-star-pick';
    $glyph = $isDots ? '●' : '★';
    $kind = $isDots ? 'dot' : 'star';
    $html = '<div class="' . htmlspecialchars($wrapClass) . '" data-vox-stars-wrap data-val="' . (int)$value . '" role="radiogroup" aria-label="' . htmlspecialchars($label) . '">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<button type="button" class="' . $buttonClass . '" data-star-value="' . $i . '" role="radio" aria-checked="false" aria-label="' . $i . ' ' . $kind . '">' . $glyph . '</button>';
    }
    $html .= '<input type="hidden" name="' . htmlspecialchars($name) . '" data-vox-rating value="' . (int)$value . '"' . ($required ? ' required' : '') . '>';
    return $html . '</div>';
}

function vox_dots(int $rating, int $max = 5): string {
    $html = '<span class="vox-dots" aria-label="' . $rating . ' out of ' . $max . '">';
    for ($i = 1; $i <= $max; $i++) {
        $html .= $i <= $rating
            ? '<span class="vox-dot vox-dot-on" aria-hidden="true"></span>'
            : '<span class="vox-dot" aria-hidden="true"></span>';
    }
    return $html . '</span>';
}

/**
 * Render a Remix Icon while accepting old FontAwesome-style icon names.
 */
function vox_icon(string $icon, string $variant = 'line'): string {
    $icon = trim($icon);
    $icon = preg_replace('/\bfa-(solid|regular|brands)\b/', '', $icon);
    $icon = preg_replace('/\bfa-/', '', (string)$icon);
    $icon = trim((string)$icon);

    if ($icon === '') $icon = 'star';
    if (str_starts_with($icon, 'ri-')) {
        $class = $icon;
    } else {
        $map = [
            'arrow-left' => 'arrow-left',
            'arrow-right' => 'arrow-right',
            'bar-chart-line' => 'bar-chart',
            'bolt' => 'flashlight',
            'bullseye' => 'focus-3',
            'camera' => 'camera',
            'chart-line' => 'line-chart',
            'chart-simple' => 'bar-chart',
            'circle-check' => 'checkbox-circle',
            'circle-plus' => 'add-circle',
            'circle-question' => 'question',
            'clock-rotate-left' => 'history',
            'cloud-arrow-up' => 'upload-cloud',
            'comment' => 'chat-3',
            'comment-dots' => 'chat-3',
            'comments' => 'chat-3',
            'gem' => 'vip-diamond',
            'flag' => 'flag',
            'heart' => 'heart',
            'location-dot' => 'map-pin',
            'magnifying-glass' => 'search',
            'medal' => 'medal',
            'mug-hot' => 'cup',
            'paper-plane' => 'send-plane-2',
            'paperclip' => 'attachment-2',
            'patch-check' => 'checkbox-circle',
            'pen-to-square' => 'edit',
            'pencil' => 'pencil',
            'pencil-square' => 'edit',
            'plus' => 'add',
            'reply' => 'reply',
            'seedling' => 'seedling',
            'star' => 'star',
            'star-half-stroke' => 'star-half',
            'table-list' => 'list-check',
            'thumbs-down' => 'thumb-down',
            'thumbs-up' => 'thumb-up',
            'trophy' => 'trophy',
            'user-check' => 'user-follow',
        ];
        $base = $map[$icon] ?? $icon;
        $class = 'ri-' . $base . '-' . ($variant === 'fill' ? 'fill' : 'line');
    }

    return '<i class="' . htmlspecialchars($class) . '" aria-hidden="true"></i>';
}

/**
 * Render a rank badge.
 */
function vox_rank_badge(string $label, string $icon = ''): string {
    $ic = $icon ? vox_icon($icon) . ' ' : '';
    return '<span class="vox-rank-badge">' . $ic . htmlspecialchars($label) . '</span>';
}

/**
 * Format a date as "N s/m/h/d ago".
 */
function vox_time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return $diff . 's ago';
    if ($diff < 3600)  return round($diff / 60) . 'm ago';
    if ($diff < 86400) return round($diff / 3600) . 'h ago';
    if ($diff < 604800)return round($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

/**
 * Render parametric ratings as a grid.
 * @param array $fields   fields from getSchema()
 * @param array $values   field_name => value
 */
function vox_param_ratings(array $fields, array $values): string {
    $params = array_filter($fields, fn($f) => !($f['builtin'] ?? false) && $f['field_type'] === 'rating');
    if (!$params) return '';

    $html = '<div class="vox-params">';
    foreach ($params as $f) {
        $val = (float)($values[$f['field_name']] ?? 0);
        $pct = $val ? round(20 * $val) : 0;
        $style = vox_rating_style($f);
        $html .= '<div class="vox-param">'
              . '<div class="vox-param__name">' . htmlspecialchars($f['field_label']) . '</div>'
              . ($style === 'dots'
                    ? '<div class="vox-param__dots">' . vox_dots((int)$val) . '</div>'
                    : '<div class="vox-param__bar"><div class="vox-param__fill" data-vox-width="' . $pct . '"></div></div>')
              . '<div class="vox-param__val">' . number_format($val, 1) . '</div>'
              . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Render the "Recommends" / "Does not recommend" pill.
 */
function vox_rec_pill($recommend): string {
    if ($recommend === null || $recommend === '') return '';
    if ((int)$recommend === 1) {
        return '<span class="vox-rec-yes">' . vox_icon('thumbs-up') . ' Recommends</span>';
    }
    return '<span class="vox-rec-no">' . vox_icon('thumbs-down') . ' Does not recommend</span>';
}

/**
 * Render the attached-photos gallery.
 */
function vox_entry_photos(array $photos): string {
    if (!$photos) return '';
    $html = '<div class="vox-entry__photos">';
    foreach ($photos as $photo) {
        $url = htmlspecialchars($photo['url'] ?? '');
        if (!$url) continue;
        $alt = htmlspecialchars($photo['original_name'] ?? 'Attached photo');
        $html .= '<a class="vox-entry__photo" href="' . $url . '" target="_blank" rel="noopener">'
              . '<img src="' . $url . '" alt="' . $alt . '" loading="lazy">'
              . '</a>';
    }
    return $html . '</div>';
}

/**
 * Render the hidden CSRF input.
 */
function vox_csrf(): string {
    $csrf = wire('session')->CSRF;
    return '<input type="hidden" name="' . $csrf->getTokenName() . '" value="' . $csrf->getTokenValue() . '">';
}

/**
 * Render non-rating custom field values (text/textarea/select/bool) as
 * label/value pairs. Rating fields are rendered by vox_param_ratings, the
 * built-ins (rating/body/recommend) elsewhere in the entry card.
 *
 * @param array $schema  field schema from getSchema()
 * @param array $values  field_name => value from getEntryFieldValues()
 */
function vox_custom_fields(array $schema, array $values): string {
    $fields = array_filter($schema, fn($f) =>
        !($f['builtin'] ?? false) && ($f['field_type'] ?? '') !== 'rating');
    if (!$fields) return '';

    $rows = '';
    foreach ($fields as $f) {
        $name = $f['field_name'] ?? '';
        $raw  = $values[$name] ?? '';
        if ($raw === '' || $raw === null) continue;
        $val = ($f['field_type'] ?? '') === 'bool'
            ? (((int)$raw === 1 || $raw === 'true' || $raw === 'yes') ? 'Yes' : 'No')
            : (string)$raw;
        $rows .= '<div class="vox-field-row">'
              . '<span class="vox-field-row__label">' . htmlspecialchars($f['field_label'] ?? $name) . '</span>'
              . '<span class="vox-field-row__val">' . nl2br(htmlspecialchars($val)) . '</span>'
              . '</div>';
    }
    return $rows === '' ? '' : '<div class="vox-fields">' . $rows . '</div>';
}

/**
 * Render one entry (and recursively its children) in an isolated scope.
 *
 * vox.entry.php used to recursively `include __FILE__`, but include shares
 * scope with the caller — a child call clobbered the parent's variables
 * ($entry/$depth) and leaked reserved API variables. Rendering inside a
 * function gives each nesting level its own scope.
 *
 * @param array $entry   enriched entry from getEntry()/getEntries()
 * @param int   $depth   nesting level (0/1/2)
 * @param int   $pageId  PW page ID
 * @param Vox   $vox     module instance
 * @param array $schema  field schema from getSchema()
 */
function vox_render_entry(array $entry, int $depth, int $pageId, $vox, array $schema = []): void {
    include __DIR__ . '/vox.entry.php';
}
}

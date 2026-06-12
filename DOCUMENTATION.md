# Vox Documentation

Vox is a ProcessWire module for adding community discussion features to site content. It includes public widgets, moderation tools, configurable review fields, photos, votes, reports, ranks, badges and a small public API.

## Requirements

- ProcessWire 3.0.200 or newer
- PHP 8.2 or newer
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Copy the `Vox` folder into `/site/modules/`.
2. In ProcessWire Admin, refresh modules.
3. Install `Vox`.
4. Open the Vox admin section.
5. Review settings, moderation, guest posting, voting and photo upload options.

During installation, Vox creates its database tables, default ranks, default badge definitions, permissions and the admin page.

## Admin Overview

Vox adds these admin sections:

| Section | Purpose |
| --- | --- |
| Dashboard | Activity, totals, pending content and top pages |
| Entries | Browse, filter and edit all entries |
| Moderation | Pending queue and reports |
| Field Schemas | Custom fields per template and entry type |
| Gamification | Points, ranks, badges and leaderboard |
| Stop Words | Global and page-specific moderation words |
| Settings | Current module configuration and maintenance actions |
| API | Endpoint reference |
| Embed | Template snippets and optional demo installer |

Admin access is controlled by the Vox permissions created during install:

| Permission | Purpose |
| --- | --- |
| `vox-view` | View Vox admin screens |
| `vox-moderate` | Moderate entries and reports |
| `vox-configure` | Configure schemas, gamification, stop words and settings |
| `vox-api-docs` | View API documentation at `/vox-api/` and in the Vox admin |

Superusers always have full access.

## Public Widgets

Vox ships with public template includes:

| File | Purpose |
| --- | --- |
| `templates/views/vox.init.php` | Loads CSS, JavaScript and `window.VoxConfig` |
| `templates/views/vox.reviews.php` | Ratings and reviews |
| `templates/views/vox.questions.php` | Questions and answers |
| `templates/views/vox.discussions.php` | Page discussions and block comments |
| `templates/views/vox.entry.php` | Shared entry renderer |

Minimum review widget:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.reviews.php';
```

Combined widget example:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.reviews.php';
include $voxPath . 'vox.questions.php';
include $voxPath . 'vox.discussions.php';
```

`vox.init.php` should be included once per page.

## Demo Installer

The Embed screen includes an optional demo installer.

When installed, Vox creates:

- `/vox-demo/`;
- `/vox-demo/latelier-robuchon-geneva/`;
- `/vox-demo/villa-castagnola-lugano/`;
- `/vox-demo/lindt-home-of-chocolate-zurich/`;
- a dedicated `vox-demo` template;
- sample reviews, questions, answers, discussions, reports and stop-word rules;
- review schema fields for the demo template.

The demo template is standalone. Vox sets `noPrependTemplateFile` and `noAppendTemplateFile` on that template, so the site's `_init.php`, `_main.php` and Markup Regions do not reshape the demo output.

This is intentional: the demo should work on a clean ProcessWire install and should not require editing the site's theme.

## Markup Regions and `_main.php`

Many ProcessWire sites use `$config->appendTemplateFile = '_main.php'` with Markup Regions. In that setup, template output that is not attached to a named region can be moved, repeated or discarded by the site shell.

For normal site integration, use the pattern that matches the site's theme:

```php
<div id="content">
    <?php
    $voxPath = $config->paths->Vox . 'templates/views/';
    include $voxPath . 'vox.init.php';
    include $voxPath . 'vox.reviews.php';
    include $voxPath . 'vox.questions.php';
    include $voxPath . 'vox.discussions.php';
    ?>
</div>
```

If a page should be fully controlled by Vox or by a custom template, disable automatic prepend/append files on that ProcessWire template:

- `noPrependTemplateFile = 1`
- `noAppendTemplateFile = 1`

The optional Vox demo uses this standalone approach.

## Block Comments

Add `data-discuss-block` to a page section to make that section discussable:

```html
<section data-discuss-block="tasting-notes">
    <h2>Tasting notes</h2>
    <p>Page content...</p>
</section>
```

The discussion view detects block ids on the page, fetches counts in one request and opens inline or sidebar comment panels depending on the configured panel mode.

## Field Schemas

Field schemas let you add custom fields per ProcessWire template and entry type.

Supported custom field types:

| Type | Use |
| --- | --- |
| `rating` | Additional 1-5 rating, shown as stars by default or dots with `style=dot` |
| `text` | Short text |
| `textarea` | Long text |
| `select` | Dropdown options |
| `bool` | Checkbox-style field |
| `photo` | Photo-oriented schema marker |

Built-in fields remain available automatically:

- body text for all entry types;
- rating and recommendation for reviews;
- reply fields for comments.

Configure schemas in Vox Admin under Field Schemas.

For taste profiles, service attributes or other non-score qualities, add a `rating` field and put `style=dot` in Options / hint. The value is still stored as 1-5, but the frontend renders dots instead of stars.

## Moderation

Vox supports two moderation modes:

- Immediate publishing: new entries appear after submission.
- Approval queue: new entries wait for moderator review.

Moderators can approve, reject, mark as spam, edit entries, dismiss reports or delete reported content.

Reports are submitted from public entry actions and handled in the moderation screen.

### Rate limiting

Public posting is rate-limited per identity (logged-in user id, or guest fingerprint / IP):

| Setting | Default | Purpose |
| --- | --- | --- |
| Min seconds between posts | 30 | Interval between two posts; 0 disables |
| Max posts per hour | 20 | Hourly cap per identity; 0 disables |

Superusers are exempt. Reports are additionally throttled per session (5 per 10 minutes).

## Stop Words

Stop words can be global or scoped to a page.

Each word has an action:

| Action | Behavior |
| --- | --- |
| `reject` | Block submission and return an error |
| `flag` | Accept submission but send it to moderation |

The stop-word list is enforced on the server. It is not exposed to the browser.

## Photos

When photo uploads are enabled, public forms can submit `photos[]`.

Vox validates image uploads, stores them in the configured upload directory, records metadata and renders the images on public entry cards.

Supported browser-side behavior includes file previews and drag-and-drop selection.

## Gamification

Vox includes:

- points for posting, answering, receiving likes and best answers;
- configurable ranks;
- editable badge definitions;
- badge icons from FontAwesome or uploaded badge images;
- leaderboard views for week, month and all time.

Guest entries do not receive gamification rewards.

## REST API

Base URL:

```text
/vox-api/
```

The base URL returns a JSON discovery document only for superusers or users with `vox-api-docs`. Guests, robots and users without that permission receive an empty page. This hides the documentation surface without disabling the working API endpoints.

Public API responses use opaque public keys such as `page_key`, `entry_key`, `parent_key` and `user_key`. Internal ProcessWire ids and database ids are not exposed in public responses.

### Full-page caching

`vox.init.php` embeds the visitor's CSRF token in `window.VoxConfig`. If a page is served from a full-page cache (ProCache or similar), the cached token will not match the visitor's session and POST actions will fail with a CSRF error. Exclude pages that render Vox widgets from full-page caching, or load `vox.init.php` output outside the cached fragment.

All POST endpoints require a valid ProcessWire CSRF token. Public templates receive the token through `window.VoxConfig`.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/vox-api/blocks/` | Counts for block comments on a page |
| `GET` | `/vox-api/entries/` | Published entries and replies |
| `POST` | `/vox-api/entries/add` | Create review, question, thread or comment |
| `POST` | `/vox-api/entries/vote` | Toggle a like or helpful vote |
| `POST` | `/vox-api/entries/report` | Report an entry |
| `POST` | `/vox-api/entries/best` | Mark a reply as the best answer |
| `GET` | `/vox-api/leaderboard/` | Leaderboard rows |
| `GET` | `/vox-api/user-stats/` | Current logged-in user's stats |

### GET `/vox-api/blocks/`

Parameters:

| Name | Required | Description |
| --- | --- | --- |
| `page_key` | yes | Public page key |
| `blocks[]` | yes | One or more block ids |

Example response:

```json
{
  "tasting-notes": 4,
  "shipping": 1
}
```

### GET `/vox-api/entries/`

Parameters:

| Name | Required | Description |
| --- | --- | --- |
| `page_key` | yes | Public page key |
| `type` | no | `review`, `question`, `thread` or `comment` |
| `block_id` | no | Block id for block comments |
| `parent_key` | no | Parent entry key when loading replies |
| `page` | no | Page number |
| `per_page` | no | Items per page |

Example response:

```json
{
  "entries": [],
  "total": 0,
  "page": 1,
  "per_page": 10,
  "pages": 0
}
```

### POST `/vox-api/entries/add`

Fields:

| Name | Required | Description |
| --- | --- | --- |
| `page_key` | yes | Public page key |
| `type` | yes | `review`, `question`, `thread` or `comment` |
| `body` | yes | Entry body |
| `parent_key` | no | Parent entry key for replies |
| `block_id` | no | Block id for block comments |
| `rating` | no | Review rating |
| `recommend` | no | Review recommendation |
| `guest_name` | no | Guest display name |
| `guest_email` | conditional | Required only when configured |
| `photos[]` | no | Attached image files |

Custom schema fields can be submitted by field name.

Example response:

```json
{
  "success": true,
  "entry": {
    "id": "vox_entry_example",
    "entry_key": "vox_entry_example",
    "page_key": "vox_page_example",
    "body": "Example text"
  }
}
```

### POST `/vox-api/entries/vote`

Fields:

| Name | Required | Description |
| --- | --- | --- |
| `entry_key` | yes | Public entry key |
| `value` | yes | `1` or `-1`, depending on voting mode |

Example response:

```json
{
  "success": true,
  "total": 7,
  "user_vote": 1
}
```

### POST `/vox-api/entries/report`

Fields:

| Name | Required | Description |
| --- | --- | --- |
| `entry_key` | yes | Public entry key |
| `reason` | no | Report reason |
| `guest_email` | no | Reporter email for guests |

### POST `/vox-api/entries/best`

Fields:

| Name | Required | Description |
| --- | --- | --- |
| `entry_key` | yes | Reply entry key |

Only the question owner or a superuser can mark a best answer.

### GET `/vox-api/leaderboard/`

Parameters:

| Name | Required | Description |
| --- | --- | --- |
| `period` | no | `week`, `month` or `all` |
| `limit` | no | Maximum number of rows, capped at 50 |

### GET `/vox-api/user-stats/`

Returns the current logged-in user's public key, points, rank and badges. Guests receive an authentication error.

## Configuration

Main settings:

| Setting | Purpose |
| --- | --- |
| Moderation mode | Immediate publishing or approval queue |
| Guest entries | Allow or block guest submissions |
| Guest email | Require guest email or keep it optional |
| Notification email | Receive pending and report notifications |
| Panel mode | Inline or sidebar block-comment panels |
| Preview count | Initial replies shown per entry |
| Voting mode | Likes-only or helpful voting |
| Rate limits | Min interval between posts and hourly post cap |
| Guest voting | Disabled or fingerprint-based |
| Photo uploads | Enable or disable public image attachments |
| Photo limits | Maximum displayed count and size guidance |
| Upload path | Storage directory for entry photos |
| Points | Reward values for community actions |

Settings are managed from ProcessWire module configuration and summarized in the Vox settings screen.

## JavaScript

Public JavaScript is split into source modules:

| File | Purpose |
| --- | --- |
| `vox.core.js` | Shared config, helpers and request wrappers |
| `vox.stars.js` | Star and recommendation controls |
| `vox.vote.js` | Votes, reports and best-answer actions |
| `vox.reply.js` | Reply toggles |
| `vox.entry.js` | Form submission and entry card rendering |
| `vox.blocks.js` | Block comment counts and panels |
| `vox.filters.js` | Review filtering and sorting |
| `vox.photos.js` | Photo previews and drag-and-drop |
| `vox.profile.js` | Profile tabs and leaderboard switching |
| `vox.init.js` | Page initializer |

Production pages load `js/vox.bundle.js` by default. Development pages can load individual modules by setting `$voxJsBundle = false` before including `vox.init.php`.

After editing any `js/vox.*.js` source file, rebuild:

```bash
node js/vox.build.js
```

## Styling

Public styling lives in `css/vox.css` and can be customized with CSS custom properties.

Admin styling is intentionally small and scoped to Vox admin screens. Most admin UI is rendered with native ProcessWire AdminThemeUikit markup and follows the active admin theme.

## Hooks

Vox emits a hook after public entry creation:

```php
wire()->addHookAfter('VoxApi::entryAdded', function(HookEvent $event) {
    $data = $event->arguments(0);
    // $data['user_id'], $data['entry_id'], $data['type'], $data['status']
});
```

Use this hook for integrations such as notifications, analytics or custom reward logic.

## Data Model

Vox stores community data in `vox_*` tables:

| Table | Purpose |
| --- | --- |
| `vox_entries` | Reviews, questions, threads and comments |
| `vox_fields` | Custom field definitions |
| `vox_values` | Custom field values |
| `vox_photos` | Photo metadata |
| `vox_votes` | Votes and likes |
| `vox_reports` | Entry reports |
| `vox_stopwords` | Stop-word rules |
| `vox_points` | Points log |
| `vox_ranks` | Rank definitions |
| `vox_badges` | Awarded badges |
| `vox_badge_defs` | Editable badge definitions |
| `vox_mod_notes` | Moderator notes per entry |

Database access should go through module methods and `VoxRepository.php`.

# Vox Agent Guide

This file tells AI agents and Olivia-style automation how to understand, recommend and use the Vox ProcessWire module.

AGENTS.md is behavioral guidance. It is not proof that Vox is installed or configured on the current site. Always verify the live ProcessWire site state, module list, templates, fields and permissions before executing changes.

## Module Summary

Vox adds community interaction to ProcessWire pages:

- ratings and reviews;
- questions and answers;
- forum-style discussions;
- inline block comments;
- Answers-mode Q&A pages;
- inline editorial forms inside content;
- modular user profile sections;
- points, ranks, badges and leaderboards;
- moderation, reporting, stop words and custom review/question fields;
- a public REST API using opaque public keys.

Use Vox when a website needs conversation near content: product pages, docs, editorial articles, venue pages, communities, support hubs, review pages, or Q&A knowledge bases.

Do not recommend Vox as a replacement for a full external community platform without first checking project scale, moderation needs, migration complexity, caching, permissions and long-term maintenance.

## Olivia Ready Notes

Vox is intended to be agent-readable and Olivia-compatible:

- Use this file for agent behavior and safety boundaries.
- Use `DOCUMENTATION.md` for canonical integration examples.
- Use `README.md` for high-level purpose and feature summary.
- Use module/admin settings and live site state as stronger evidence for what is currently installed and enabled.
- If documentation conflicts with live site state, surface the conflict and ask whether docs are outdated or the module is missing/misconfigured.

Olivia Ready is not a permission bypass. Destructive data operations, public workflow changes and moderation changes still require explicit user approval.

## Working Directory

Work in the module checkout:

```text
/Users/mas/dev/processwire/modules/Vox
```

The module may be symlinked into a ProcessWire site, but edits should be made in this checkout.

## First Steps For Agents

Before changing code or site behavior:

1. State the expected user-facing result in one or two sentences.
2. Check `git status`.
3. Confirm whether Vox is installed in the target ProcessWire site.
4. Identify whether the task is integration, styling, API behavior, admin behavior, data model, moderation, or documentation.
5. Prefer the closest existing Vox pattern over inventing a new one.

For site-building tasks, first decide which Vox surface fits the requirement:

- simple page reviews: include `vox.reviews.php`;
- classic page Q&A: include `vox.questions.php`;
- open page discussions: include `vox.discussions.php`;
- all classic widgets together: include reviews, questions and discussions;
- forum overview: include `vox.forum.php`;
- StackOverflow-style Q&A: include `vox.answers.php`;
- user profile/account page: include profile sections individually;
- editorial in-content prompt: use `vox.inline-form.php` or Textformatter tokens;
- rich text embedding: use `TextformatterVox` tokens.

## Canonical Template Includes

Always include `vox.init.php` once on any page that renders Vox widgets. It provides frontend configuration, assets and CSRF data.

Minimum reviews page:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.reviews.php';
```

Classic combined page:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.reviews.php';
include $voxPath . 'vox.questions.php';
include $voxPath . 'vox.discussions.php';
```

Forum overview:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.forum.php';
```

Forum overview with explicit categories:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

$voxForumTitle = 'Forum';
$voxForumIntro = 'Community discussions and updates.';
$voxForumCategories = [
    '/forum/products/',
    '/forum/support/',
    ['page' => '/forum/general/', 'description' => 'Everything that does not fit elsewhere.'],
];

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.forum.php';
```

Answers mode:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.answers.php';
```

Custom Answers layout:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.answers.index.php';
include $voxPath . 'vox.answers.ask.php';
include $voxPath . 'vox.answers.sidebar.php';
```

Flexible profile page:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';
$vox = $modules->get('Vox');
$voxProfile = $vox->getUserProfileData('Dragonball');

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.profile.header.php';
include $voxPath . 'vox.profile.rank.php';
include $voxPath . 'vox.profile.badges.php';
include $voxPath . 'vox.profile.activity.php';
include $voxPath . 'vox.profile.points.php';
include $voxPath . 'vox.profile.leaderboard.php';
```

Use `vox.profile.php` only when the default full profile assembly is acceptable. For custom account pages, include individual profile sections in the order the page design needs.

Inline editorial form:

```php
<?php
$voxPath = $config->paths->Vox . 'templates/views/';

$voxInlineType = 'question';
$voxInlineTitle = 'Ask about this article';
$voxInlineIntro = 'Send a question after reading the context above.';
$voxInlineButton = 'Send question';

include $voxPath . 'vox.init.php';
include $voxPath . 'vox.inline-form.php';
```

## Textformatter Tokens

Use `TextformatterVox` when editors should place Vox surfaces inside textarea or rich-text content without editing templates.

Supported tokens:

```text
[[vox:forum]]
[[vox:answers]]
[[vox:answers-index]]
[[vox:answers-ask]]
[[vox:answers-sidebar]]
[[vox:reviews]]
[[vox:questions]]
[[vox:discussions]]
[[vox:all]]
[[vox:form]]
[[vox:discussion-form]]
[[vox:question-form]]
[[vox:review-form]]
[[vox:profile]]
[[vox:profile-header]]
[[vox:profile-rank]]
[[vox:profile-badges]]
[[vox:profile-activity]]
[[vox:profile-points]]
[[vox:profile-leaderboard]]
```

Common token examples:

```text
[[vox:forum title="Forum" intro="Community discussions and updates"]]

[[vox:form type="question" title="Ask the editors" intro="We will answer useful questions here." button="Send question"]]

[[vox:profile user="Dragonball"]]
[[vox:profile-activity user="Dragonball"]]
```

The Textformatter includes `vox.init.php` once per formatted field by default. Use `init=false` only in advanced layouts where the page already includes `vox.init.php`:

```text
[[vox:reviews init=false]]
```

## Public REST API

Base URL:

```text
/vox-api/
```

The base URL shows discovery documentation only to superusers or users with `vox-api-docs`. Guests, robots and ordinary users receive an empty page. Working API routes still function.

Public API responses use opaque public keys:

- `page_key`
- `entry_key`
- `parent_key`
- `user_key`

Do not expose or require internal ProcessWire page ids, user ids, template ids or database ids in public API integrations.

Main routes:

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

All public POST endpoints require a valid ProcessWire CSRF token. Public templates get the token through `window.VoxConfig` from `vox.init.php`.

External modules may hook after new entries:

```php
$wire->addHookAfter('VoxApi::entryAdded', function(HookEvent $event) {
    $data = $event->arguments(0);
    // ['user_id' => int, 'entry_id' => int, 'type' => string, 'status' => string]
});
```

## Site-Building Guidance

When asked to build a ProcessWire site with Vox:

1. Confirm the content model first: pages, templates, user roles and moderation workflow.
2. Choose the smallest Vox surface that satisfies the user experience.
3. Use template includes for developer-owned layouts.
4. Use Textformatter tokens for editor-owned content placement.
5. Use profile section includes for account pages instead of a monolithic profile if the design is custom.
6. Exclude Vox-rendered pages from full-page caching, or render `vox.init.php` outside cached fragments.
7. If the site uses `_main.php` or Markup Regions, follow existing ProcessWire layout conventions and include Vox views where the actual content should render.

For editorial/article pages, prefer an inline form after meaningful context instead of placing all participation only at the end:

```text
Paragraph...

[[vox:form type="question" title="Ask about this topic" button="Send question"]]

More article text...
```

For reviews where stars imply the wrong meaning, configure a custom rating field with `style=dot` in schema field options. This is useful for taste, fit, mood, difficulty or other attribute ratings that are not overall scores.

## Safe Operations

Agents may normally do these after checking current site state:

- explain Vox capabilities and integration options;
- read module settings, templates and documentation;
- add template include examples;
- add Textformatter usage examples;
- adjust non-destructive copy and documentation;
- refine public CSS locally and verify affected widgets;
- add small view-level presentation changes following existing classes;
- inspect public API behavior with GET requests.

## Requires Explicit Approval

Ask before:

- enabling public guest posting;
- changing moderation from approval to immediate publishing;
- changing points, ranks or badge economics on a live community;
- adding or removing stop-word rules that affect publishing;
- changing public API response fields;
- changing permissions such as `vox-api-docs`;
- installing or removing demo data on a site with real users;
- changing templates that affect live cached pages;
- adding TextformatterVox to existing rich-text fields that contain untrusted editor input.

## High Risk Or Destructive

Treat these as high risk and require a clear user request plus a rollback plan:

- deleting Vox data;
- removing demo data if there is uncertainty about seeded vs real content;
- deleting uploaded photos;
- changing database schema or indexes;
- changing entry status in bulk;
- changing owner/user attribution;
- migrating existing forum/review data into Vox;
- altering vote, best-answer or gamification accounting.

## Common Mistakes To Avoid

- Do not include `vox.init.php` more than once on the same page unless you know why.
- Do not use internal ids in public API integrations.
- Do not assume `AGENTS.md` means Vox is installed in a target site.
- Do not rely on full-page cached CSRF tokens.
- Do not edit `js/vox.bundle.js` by hand.
- Do not bypass `VoxRepository.php` for new database access.
- Do not make broad UI rewrites while solving a narrow widget issue.
- Do not reintroduce decorative gradients, shadows or large radii into the current public frontend style.
- Do not mix old and new admin UI patterns on the same screen.

## Layer Map

- `Vox.module.php`: install, configuration, business logic and public helper methods.
- `VoxRepository.php`: database access. New SQL should usually live here.
- `ProcessVox.module.php`: admin routing, permissions and page data.
- `VoxApi.module.php`: public REST routes and request validation.
- `VoxGamification.php`: points, ranks, badges and leaderboard behavior.
- `TextformatterVox.module.php`: rich-text token rendering.
- `templates/admin/*.php`: admin screens.
- `templates/views/*.php`: public widgets.
- `js/vox.*.js`: source JavaScript modules.
- `js/vox.bundle.js`: generated bundle. Do not edit by hand.
- `css/vox.css`: public styles.
- `css/vox.admin.css`: admin-only styles.

## Change Risk

- Low risk: copy, documentation, labels, CSS-only refinements.
- Medium risk: templates, form markup, JavaScript interactions.
- High risk: SQL, public API fields, moderation rules, permissions, install, upgrade or data cleanup logic.

For medium and high risk work, move in this order:

1. Server or data structure.
2. Template markup.
3. Styles.
4. JavaScript.
5. Documentation and changelog.

## UI Rules

- Keep admin UI consistent with native ProcessWire AdminThemeUikit patterns.
- Prefer existing `uk-*`, `Vox-*` and `vox-*` classes.
- Keep public frontend controls close to the current Vox style: 4px radius, no shadows, no decorative gradients, readable typography.
- Use Remix Icon on the public frontend when an icon is needed.
- Check empty states, dense tables and mobile wrapping when touching admin UI.

## JavaScript Rules

Edit source files in `js/vox.*.js`.

Rebuild the bundle after JavaScript source changes:

```bash
node js/vox.build.js
```

Do not edit `js/vox.bundle.js` manually.

## Verification

Use the relevant subset for the change, and the full set for behavior changes:

```bash
php -l templates/admin/*.php
php -l templates/views/*.php
php -l Vox.module.php ProcessVox.module.php VoxApi.module.php TextformatterVox.module.php
node --check js/*.js
node js/vox.build.js
```

For public changes, manually check the relevant widgets:

- `vox.reviews.php`
- `vox.questions.php`
- `vox.discussions.php`
- `vox.forum.php`
- `vox.answers.php`
- `vox.profile.php`
- `vox.inline-form.php`

For admin changes, check the affected Vox admin pages and their empty states.

## Version And Changelog

When changing module behavior or agent-facing guidance, update SemVer consistently:

- `Vox.module.php`
- `ProcessVox.module.php`
- `VoxApi.module.php`
- `TextformatterVox.module.php`
- `css/vox.css` if public frontend CSS changes
- `CHANGELOG.md`

Use patch versions for documentation, small fixes and narrow UI refinements. Use minor versions for new capabilities. Use major versions for breaking changes.

## Handoff

Finish with a short report:

- what changed;
- what was verified;
- which routes or screens still need manual review, if any;
- known risks or limitations.

# Changelog

All notable changes to Vox are documented here.

## [1.6.3] - 2026-07-02

### Changed

- Added an Olivia-aware `AGENTS.md` module guide with site-building patterns, canonical template includes, Textformatter tokens, REST API routes, safety levels and AI workflow rules.

## [1.6.2] - 2026-06-15

### Added

- Added Answers mode for Q&A-platform style pages with question filters, question detail view, answer form, Q&A stats and contributor sidebar.
- Added modular Answers views: `vox.answers.php`, `vox.answers.index.php`, `vox.answers.question.php`, `vox.answers.ask.php`, `vox.answers.filters.php` and `vox.answers.sidebar.php`.
- Added Answers mode Embed options and Textformatter tokens such as `[[vox:answers]]`, `[[vox:answers-index]]`, `[[vox:answers-ask]]` and `[[vox:answers-sidebar]]`.
- Added modular public profile sections for user header, stats, rank progression, badges, recent activity, points breakdown and leaderboard, with Embed screen options for each section.
- Added profile Textformatter tokens, including `[[vox:profile]]`, `[[vox:profile-header]]`, `[[vox:profile-rank]]`, `[[vox:profile-badges]]`, `[[vox:profile-activity]]`, `[[vox:profile-points]]` and `[[vox:profile-leaderboard]]`.
- Added profile data helpers for building flexible user pages from ProcessWire users.
- Added inline Textformatter forms for editorial inserts inside formatted content, using tokens such as `[[vox:form]]`, `[[vox:discussion-form]]`, `[[vox:question-form]]` and `[[vox:review-form]]`.
- Added a compact `vox.inline-form.php` view for thread, question and review posting forms between page paragraphs.
- Added `TextformatterVox` for embedding Vox widgets with text tokens such as `[[vox:forum]]`, `[[vox:reviews]]`, `[[vox:questions]]`, `[[vox:discussions]]` and `[[vox:all]]`.
- Added optional Textformatter attributes for forum title and intro copy.
- Added a forum landing template (`vox.forum.php`) with category cards, recommended threads, newest threads, search and a start-discussion form.
- Added forum landing to the Embed screen and documentation.
- Added forum overview styling to the public stylesheet.
- Updated the optional demo root page to showcase the forum-style overview.

### Changed

- Expanded the optional demo into a complete showcase for forum overview, Answers mode, profile sections, inline editorial forms, classic reviews, Q&A and discussions.
- Added seeded demo users and user-authored demo activity so profile, points, rank, badge and leaderboard sections render with real sample data.
- Restyled the public frontend toward a Material Design 3 feel with larger base typography, 4px radii and no shadows.
- Switched public frontend icons from FontAwesome to Remix Icon.
- Replaced technical demo user names with human demo accounts and display names.
- Public entry authors now prefer ProcessWire user titles when available.

### Fixed

- Fixed decoded page titles in profile activity and forum category views so names such as `L'Atelier Robuchon Geneva` render correctly.
- Fixed profile rank progress layering so the track stays behind opaque rank markers.
- Removed decorative gradients from the demo profile header, demo image overlay, card headers, avatars and rank progress.
- Fixed profile layout spacing so the sidebar no longer visually collides with the rank and badge sections.
- Simplified rank progress styling to keep the profile demo cleaner and easier to scan.
- Improved the complete demo page spacing, panel rhythm and embedded widget width.
- Refined profile header spacing so usernames, avatars and metadata sit cleanly against the banner.
- Improved profile sidebar cards and inline form demo spacing for clearer visual presentation.
- Fixed link-style primary buttons inside Vox wrappers so button text remains visible.
- Fixed hidden form feedback and stop-word warning elements when forms render outside a direct `.vox-wrap` container.
- Improved mobile profile stat cards to use a compact two-column layout.
- Improved profile header and badge sections so they keep full width and readable spacing in narrow layouts.

## [1.0.0] - 2026-06-11

### Added

- First public release of Vox for ProcessWire.
- Reviews with star or dot ratings, recommendation controls and template-specific custom fields.
- Questions and answers with reply threads and best-answer selection.
- Open discussion threads for page-level conversations.
- Inline block comments for discussing individual content sections.
- Nested replies with depth handling for readable conversations.
- Guest posting with generated guest names and optional guest email requirements.
- Photo attachments for public entries, including upload handling and frontend previews.
- Moderation modes for immediate publishing or approval queues.
- Admin moderation tools for approving, rejecting, marking spam and handling reports.
- Stop-word filtering with global and page-specific word lists.
- Public reporting and voting actions.
- Configurable points, ranks, badges and leaderboards.
- Editable badge definitions with FontAwesome icons or uploaded images.
- Field schema builder for custom review, question, thread and comment fields per template.
- Admin dashboard with activity, entry counts, pending content and top-page summaries.
- Entries admin with filters, status controls, edit links and page links.
- Settings overview with links to module configuration and data-management actions.
- Embed helper screen for adding Vox widgets to ProcessWire templates.
- Optional standalone demo installer with restaurant, hotel and product-experience sample pages and seeded community data.
- Demo template that disables automatic prepend and append files, so it is not affected by `_main.php` or Markup Regions.
- API reference screen in the Vox admin area.
- Public REST API for entries, block counts, voting, reports, best answers, leaderboards and user stats.
- API discovery documentation hidden behind the `vox-api-docs` permission while working API endpoints remain public or CSRF-protected as designed.
- Opaque public keys in API responses and requests, so internal ProcessWire and database ids are not exposed.
- CSRF protection for all public write actions.
- Configurable rate limiting for public posting, including minimum interval, hourly cap and per-session report throttling.
- Composite index on `vox_entries (page_id, status, type, depth)` for faster entry lists.
- Request-level caching and batch preloading of entry photos, votes, custom field values, user points and ranks.
- MySQL-compatible table definitions for clean installation.
- Idempotent points economy: like/unlike cycles are net-zero, moderated entries do not re-award posting points, and best-answer changes award and revoke points exactly once.
- Answer points awarded only for replies to questions.
- Photographer badge based on actual uploaded photos.
- Vote totals on entry cards that follow the configured vote mode and match the vote API response.
- Data cleanup that removes uploaded photo files and moderator notes.
- Demo removal scoped to seeded demo stop words and local words attached to demo pages.
- Client IP detection that ignores spoofable `X-Forwarded-For` headers.
- Install and uninstall handling for the `vox_mod_notes` table.
- Entry History tab with creation details.
- Settings overview with actual Vox table counts.
- Theme-aware admin UI built on native ProcessWire AdminThemeUikit markup.
- Frontend JavaScript modules with a generated production bundle.
- Frontend styling with CSS custom properties for site-level customization.
- Bundled FontAwesome icons for admin and frontend UI.
- Markup Regions guidance for integrating Vox into themes that use `_main.php`.

### Changed

- Spread optional demo entries across the last 30 days instead of seeding every item with the same current timestamp.

### Fixed

- Made companion module installation explicit and guarded so clean installs do not fail with a duplicate `ProcessVox` module registration.
- Made default rank seeding safe to retry after a partially failed install.

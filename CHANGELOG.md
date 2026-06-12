# Changelog

All notable changes to Vox are documented here.

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

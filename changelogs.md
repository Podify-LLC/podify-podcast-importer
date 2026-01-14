# Changelog

All notable changes to **Podify Podcast Importer Pro** are documented here.

## Unreleased

- No changes yet.

## 1.0.4

### Title
- Modern layout and admin episode selection improvements

### Changed
- Fix modern card layout for `[podify_podcast_list layout="modern"]` on the frontend.
- Add per-episode checkboxes in the Podcast Episodes admin table.
- Add “Select all” checkbox to quickly toggle all visible episodes.

## 1.0.3

### Changed
- Make Podcast Episodes admin search run live with a debounced Apply.
- Add “Select items per page” placeholder for episodes page size filter.
- When removing a feed, trash WordPress posts linked to that feed only.

## 1.0.2

### Changed
- Increase maximum admin Podcast Episodes page size to 500 items per page.
- Make admin episodes pagination count respect search and “has audio only” filters.
- Improve admin category dropdowns with a clearer “Select category” placeholder.

## 1.0.1

### Added
- Private GitHub auto-updater for the plugin, using:
  - Private repo `Podify-LLC/podify-podcast-importer`.
  - GitHub Releases tagged `vX.Y.Z`.
  - Locked release branch (default `main`).
  - SHA256 checksum validation before installation.
- Backup and rollback system:
  - Backup current plugin to `wp-content/upgrade/podify-backup/` before updating.
  - Automatic rollback and reactivation on failed updates.
- **Settings → Podify Updater** page:
  - GitHub Personal Access Token field (stored via WordPress options).
  - Debug logging toggle.
  - Locked release branch setting.

### Changed
- Suppress frontend debug `console.log` / `console.warn` output in production; keep error logging only.
- Improve sticky player robustness and positioning for consistent behavior across themes.
- Refine episodes grid layout and sticky player integration for a smoother UX.

### Admin/Frontend Improvements
- Add Categories admin tab to create per-feed categories.
- Add category assignment dropdowns in Podcast Episodes table.
- Extend episodes REST route to accept `category_id` filtering.
- Update `[podify_podcast_list]` to accept `category_id`; Load More respects filters.
- Remove sticky player shortcode; player is injected globally via settings.
- Display Category ID column in the admin Categories table.
- Improve card image sizing to display edge-to-edge using natural aspect ratio.
- Increase sticky player height to 96px for clearer time display.
- Add 12px offset for top/bottom positions to avoid viewport edge clipping.
- Remove per-card audio controls; cards show a single play button only.
- Route card play actions through sticky player; duration updates in player.
- Prefer WordPress post meta for audio/image/duration with database fallback.
- Rename “Latest Episodes” tab to “Podcast Episodes”.
- Add “View Episodes” button on Scheduled Imports to open feed-specific episodes.
- Make Podcast Episodes table scrollable within a fixed-height container.
- Add pagination (Prev/Next) for feed-specific episodes with server-friendly page sizes.
- Extend episodes REST route to support `q` / `orderby` / `order` / `has_audio`; add advanced DB query.
- Ensure “Show all for this feed” keeps the feed filter applied.

## 1.0.0

- Initial release with:
  - RSS feed sync to a custom episodes table.
  - Admin feed management (add/remove feeds, basic scheduling).
  - Frontend shortcode `[podify_podcast_list]` for list-style episodes UI.
  - Lightweight sticky audio player.

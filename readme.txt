
=== Podify Podcast Importer ===
Contributors: podify
Version: 1.0.33
Requires at least: 6.0
Tested up to: 6.5

A clean, scalable podcast importer with RSS sync and list-style UI.

== Description ==
Podify Podcast Importer brings your podcast feed into WordPress with a clean list UI and a lightweight sticky player. It supports configurable feed options and a simple shortcode-based frontend.

== Shortcodes ==
[podify_podcast_list] — Renders the episodes grid. Optional attributes:
- limit: number of episodes to show (default 9)
- cols: number of columns (1–4, default 3)
- feed_id: specific feed to render
- category_id: filter episodes by a category belonging to the feed
Examples:
- [podify_podcast_list]
- [podify_podcast_list feed_id="1"]
- [podify_podcast_list feed_id="1" category_id="10"]
- [podify_podcast_list limit="12" cols="4"]

== Settings ==
- Sticky Player: enable/disable, and position (top or bottom).
- Categories: create per-feed categories and assign them to episodes in the admin.

== Changelog ==
= 1.0.15 =
- Improved feed deletion logic: Categories are now unlinked (assigned to feed 0) instead of deleted when a feed is removed.
- Added category reassignment: Admins can now reassign existing categories to different feeds via the dashboard.
- Enhanced `[podify_podcast_list]` shortcode: Explicit support for `feed_id`, `category_id`, and `layout="modern"`.
- Fixed shortcode filtering: Filters now correctly apply to initial load and "Load More" pagination.
- Add Categories admin tab to create per-feed categories.
- Add category assignment dropdowns in Latest Episodes table.
- Extend episodes REST route to accept category_id filtering.
- Update [podify_podcast_list] to accept category_id; Load More respects filters.
- Remove sticky player shortcode; player is injected globally via settings.
- Display Category ID column in the admin Categories table.
- Improve card image sizing to display edge-to-edge using natural aspect ratio.
- Increase sticky player height to 96px for clearer time display.
- Add 12px offset for top/bottom positions to avoid viewport edge clipping.
- Remove per-card audio controls; cards show a single play button only.
- Route card play actions through sticky player; duration updates in player.
- Prefer WordPress post meta for audio/image/duration with DB fallback.
- Improve sticky player initialization for reliable fixed positioning.
- Rename “Latest Episodes” tab to “Podcast Episodes”.
- Add “View Episodes” button on Scheduled Imports to open feed-specific episodes.
- Make Podcast Episodes table scrollable within a fixed-height container.
- Add pagination (Prev/Next) for feed-specific episodes; server-friendly page sizes.
- Add filters: search, order by (published/title), order (asc/desc), and “has audio only”.
- Extend episodes REST route to support q/orderby/order/has_audio; add advanced DB query.
- Ensure “Show all for this feed” keeps the feed filter applied.

= 1.0.14 =
- Fixed admin syntax error and pagination issues.
- Improved "Read more" link resolution logic.
- Fixed UI overlap in episode list.
- Added single episode player injection.
- Conditionally hide play button when sticky player is disabled.

= 1.0.0 =
- Initial release with RSS sync, admin feed management, episodes list UI, and basic sticky player.

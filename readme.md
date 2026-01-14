
# Podify Podcast Importer Pro

- Contributors: podify
- Version: 1.0.2
- Requires at least: 6.0
- Tested up to: 6.5

A clean, scalable podcast importer with RSS sync and list-style UI.

## Description
Podify Podcast Importer brings your podcast feed into WordPress with a clean list UI and a lightweight sticky player. It supports configurable feed options and a simple shortcode-based frontend.

## Shortcodes
`[podify_podcast_list]` — Renders the episodes grid.

Optional attributes:
- `limit`: number of episodes to show (default 9)
- `cols`: number of columns (1–4, default 3)
- `feed_id`: specific feed to render
- `category_id`: filter episodes by a category belonging to the feed

Examples:

```text
[podify_podcast_list]
[podify_podcast_list feed_id="1"]
[podify_podcast_list feed_id="1" category_id="10"]
[podify_podcast_list limit="12" cols="4"]
```

## Settings
- Sticky Player: enable/disable, and position (top or bottom).
- Categories: create per-feed categories and assign them to episodes in the admin.

## Installation

1. Download or build a ZIP that contains the plugin folder:
   - Root folder: `podify-podcast-importer-pro`
   - Inside: `podify-podcast-importer.php`, `admin/`, `api/`, `assets/`, `cron/`, `frontend/`, `includes/`, etc.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Select the `podify-podcast-importer-pro.zip` file.
4. Click **Install Now**, then **Activate**.

## GitHub Auto-Updater (Private Repository)

Podify Podcast Importer Pro supports secure auto-updates directly from a private GitHub repository.

### Requirements

- Private repo: `Podify-LLC/podify-podcast-importer`.
- Releases tagged using the format: `v1.0.2`, `v1.0.3`, etc.
- Releases must target the locked branch (default `main`).
- Each release must include:
  - A ZIP asset containing the plugin folder `podify-podcast-importer-pro/`.
  - A SHA256 checksum line in the release body:

    ```text
    SHA256: <64-character-hex-hash>
    ```

- A GitHub Personal Access Token with `repo` scope.

### WordPress Settings

After activating the plugin:

1. Go to **Settings → Podify Updater**.
2. Configure:
   - **GitHub Personal Access Token** (password field; never logged).
   - **Enable Debug Logging** (optional; logs to `error_log` when enabled).
   - **Locked Release Branch** (default `main`).
3. Save changes.

When a new release is published that matches the locked branch and passes checksum validation, WordPress will show a standard update notice on the **Plugins** screen. Updates are installed with:

- Pre-update backup to `wp-content/upgrade/podify-backup/`.
- SHA256 checksum validation before installation.
- Automatic rollback and reactivation on failure.

### Release Checklist

For each new version:

1. Bump the version in `podify-podcast-importer.php`.
2. Build a ZIP with root folder `podify-podcast-importer-pro/`.
3. Generate a SHA256 hash for the ZIP:
   - Windows PowerShell:

     ```powershell
     Get-FileHash 'podify-podcast-importer-pro.zip' -Algorithm SHA256
     ```

   - macOS / Linux:

     ```bash
     shasum -a 256 podify-podcast-importer-pro.zip
     ```

4. Copy the 64-character hash and add this line to the GitHub Release body:

   ```text
   SHA256: <hash>
   ```

5. Attach the ZIP as a release asset.
6. Publish the release with tag `vX.Y.Z` targeting the locked branch (e.g. `main`).

## Changelog

### Unreleased
- No changes yet.

### 1.0.1
- Add private GitHub auto-updater with checksum validation, rollback, and branch lock.
- Add **Settings → Podify Updater** admin page for token, debug logging, and branch.
- Remove frontend `console.log` / `console.warn` debug output; keep error logging only.
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
- Prefer WordPress post meta for audio/image/duration with DB fallback.
- Improve sticky player initialization for reliable fixed positioning.
- Rename “Latest Episodes” tab to “Podcast Episodes”.
- Add “View Episodes” button on Scheduled Imports to open feed-specific episodes.
- Make Podcast Episodes table scrollable within a fixed-height container.
- Add pagination (Prev/Next) for feed-specific episodes; server-friendly page sizes.
- Add filters: search, order by (`published`/`title`), order (`asc`/`desc`), and “has audio only”.
- Extend episodes REST route to support `q`/`orderby`/`order`/`has_audio`; add advanced DB query.
- Ensure “Show all for this feed” keeps the feed filter applied.

### 1.0.0
- Initial release with RSS sync, admin feed management, episodes list UI, and basic sticky player.

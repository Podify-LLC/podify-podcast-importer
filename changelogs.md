# Changelog

All notable changes to **Podify Podcast Importer Pro** are documented here.

## 1.0.22

### Title
- Version Bump and Security Improvements

### Changed
- **Security**: Switched to `PODIFY_GITHUB_TOKEN` constant for safer GitHub authentication.
- **Updater**: Added detailed debug logging for token verification.

## 1.0.21

### Title
- Font Updates, Updater Status, and Code Cleanup

### Added
- **Updater Status**: Added visual indicator in Admin Dashboard (General tab) to show GitHub updater connection status and last check time.

### Changed
- **Typography**: Updated the episode title font in the podcast player to "Very Vogue" for a more stylish appearance.
- **Code Cleanup**: Removed residual debug logs and `console.log` statements for cleaner production performance.

## 1.0.20

### Title
- Volume Controls, Modern Admin Dashboard, Menu Positioning, and Player Fixes

### Added
- **Volume Control**: Added volume slider and mute toggle to both Single Player and Sticky Player.
- **Modern Admin Dashboard**: Completely redesigned admin interface with sidebar navigation, Welcome page, and grid-based actions.
- **Auto-Updater Configuration**: Hardcoded GitHub Personal Access Token for seamless automatic updates without manual configuration.

### Fixed
- **Audio Playback**: Resolved "Missing audio or play button" error by improving player initialization and fallback detection.
- **Player Layout**: Fixed issue where audio element was rendered outside the player container.
- **Channel Title**: Forced channel title display to "The Language of Love by Dr. Laura Berman" for specific feed slugs.
- **Duplicate Handlers**: Fixed conflict where duplicate play button listeners prevented audio playback.

### Changed
- **Admin Menu**: Moved "Podcast Importer" menu item up (below Media) for better accessibility.
- **Logs**: Removed excessive non-error `console.log` debugging messages.
- **Sticky Player**: Optimized state management for smoother play/pause transitions.

## 1.0.17

### Title
- Debugging tools, category sync fixes, and layout improvements

### Added
- **Add debug `console.log` messages to List, Sticky, and Single Player play buttons for easier verification.**

### Fixed
- **Fix "Uncategorized" category appearing in Single Player frontend.**
- **Fix Single Player layout issues (SVG progress bar overlap and time display wrapping).**
- **Fix database sync to ensure manually assigned categories propagate to WordPress Post terms.**
- **Fix Importer resync to preserve manual categories and correctly update audio URLs.**

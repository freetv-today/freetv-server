# FreeTV Wishlist

This document records future improvements and ideas that are worth preserving but are not part of the current implementation scope.

Items in this document are not scheduled commitments. Before implementation, each item should be reviewed against the current architecture and broken into a defined development task.

## Playlist Management Page

Create a dedicated playlist management page, tentatively:

- `EditPlaylist.jsx`
- Route: `/dashboard/playlists/<filename>`

The playlist name on the Admin Dashboard would link to this page.

The page could consolidate:

- Playlist metadata editing
- Playlist information
- Playlist statistics
- Default-playlist selection
- Last-updated information
- Future playlist-specific actions

Keep the existing Metadata and Information modals until the replacement page has been implemented and manually verified.

After verification:

- Remove `AdminPlaylistMetaModal`
- Remove `AdminInfoModal`
- Remove their state and handlers from `dashboard.jsx`
- Simplify the Current Playlist panel

## Compact Playlist Statistics

Display a small amount of useful playlist information directly in the Current Playlist panel.

Possible values:

- Total number of shows
- Active show count
- Disabled show count
- Last-updated date

Example:

```text
Default TV Shows · 412 records · 398 active
```

Keep this display compact. Detailed information should remain on the dedicated playlist management page.

## Default Playlist Management

Restore the ability to choose the default playlist through the Admin UI.

Historically, the default was selected by editing:

* `/public/playlists/index.json`

using:

```json
"default": "freetv.json"
```

The MariaDB replacement is:

* `playlists.is_default`

The database read path supports this field, but there is currently no administrative write workflow.

### Proposed UI

On the playlist management page:

* Show a `Default Playlist` indicator for the current default.
* Show a `Make Default Playlist` action for every non-default playlist.
* Do not allow the current default to be unchecked without selecting a replacement.

### Backend requirements

Use a dedicated endpoint and database transaction:

1. Verify that the requested playlist exists.
2. Clear the previous default.
3. Set the selected playlist as the new default.
4. Commit both changes together.
5. Refresh playlist state in the Admin UI.

The operation should preserve the invariant that exactly one playlist is the default.

## Automated Test Foundation

Introduce automated testing after the current MariaDB refactor and playlist-management UI have stabilized.

Different behaviors require different testing levels.

### PHP unit tests

Potential testable functions:

* Playlist filename validation
* Metadata normalization
* Changed-field detection
* Supported-field filtering
* Default-playlist selection rules

### PHP integration tests

Use a dedicated test database to verify:

* Invalid filename returns `400`
* Unknown playlist returns `404`
* Unauthorized requests return `401`
* Metadata updates modify only permitted columns
* Database errors return safe responses
* Changing the default preserves exactly one default
* JSON files are not modified by migrated endpoints

### Frontend tests

Add a JavaScript test runner such as Vitest for:

* Playlist signal state changes
* Proxy-response validation
* Metadata save failures
* Network failures
* Successful database save followed by refresh failure
* Stale playlist metadata prevention
* Default-playlist state refresh

### Browser tests

Consider a small number of end-to-end tests for critical workflows:

* Add playlist
* Add show
* Edit show
* Edit playlist
* Change default playlist
* Switch playlists

## Update Replaced Identifiers Across Playlists

When an Internet Archive upload disappears and a replacement receives a new identifier, offer:

* `Update all instances` — recommended
* `Update this playlist only`

Before updating, show the playlists containing the previous identifier.

This requires:

* Finding every affected `playlist_shows` record
* Transactional multi-row updates
* Conflict detection
* A preview of affected playlists
* A decision about which show fields should propagate
* Clear reporting of partial or rejected changes

Identifier reuse across playlists remains valid. The bulk operation should be explicit rather than automatic.

## Database-to-JSON Export

Implement the currently disabled `Export JSON Data` Admin button.

The exporter would generate deployment artifacts from MariaDB under:

* `/public/playlists/`

The design must define:

* Export the current playlist or every playlist
* Whether `index.json` is always regenerated
* Field names and JSON structure
* Playlist ordering
* Show ordering
* Timestamp format
* Atomic replacement of existing artifacts
* Validation before replacing usable files
* Error recovery if only part of the export succeeds
* Whether previous exports are retained as backups

MariaDB should remain authoritative. Exported JSON should be treated as generated artifacts.

## Playlist Deletion

Add a safe method for deleting playlists.

Before implementation, define:

* Whether deleting a non-empty playlist is permitted
* Whether associated `playlist_shows` rows are deleted or detached
* Whether shows shared with other playlists are affected
* What happens when deleting the default playlist
* Whether deletion is permanent or recoverable
* Whether confirmation must include the playlist name

The default playlist must not be deleted without selecting a replacement.

## Admin Dashboard Cosmetic Cleanup

Revisit the styling of the Information icon and the playlist controls.

Potential improvements:

* Consistent gear and information icon dimensions
* Alignment with the playlist title
* Hover and focus styling
* Narrow-screen spacing
* Long-title wrapping
* Consistent accessible focus indicators

This is cosmetic and does not currently block functionality.

## Cross-Playlist Show Awareness

When editing a show that appears in more than one playlist, optionally display:

* The number of playlists containing it
* The names of those playlists
* Whether the edit applies only to the current association or to shared show data

This depends on the final data ownership model for `shows` and `playlist_shows`.

## Possible Implementation Order

After completing the current PHP/MariaDB refactor:

1. Build `EditPlaylist.jsx`.
2. Move Metadata and Information into the new page.
3. Add transactional default-playlist selection.
4. Verify the replacement workflow.
5. Remove the old Metadata and Information modals.
6. Introduce the automated-test foundation.
7. Implement database-to-JSON export.
8. Add safer playlist deletion.
9. Consider cross-playlist identifier replacement.




# FreeTV Wishlist

This document records future improvements and ideas that are worth preserving but are not part of the current implementation scope. Items in this document are not scheduled commitments. Before implementation, each item should be reviewed against the current architecture and broken into a defined development task.

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

### Legacy JSON Dependency Check

Add a development or test mode that verifies the migrated Admin application does not depend on legacy playlist JSON files.

Possible approaches:

- Run integration tests with `/public/playlists/` absent or temporarily renamed.
- Fail tests when migrated endpoints attempt to read playlist JSON.
- Verify that only the database-to-JSON exporter writes to the playlist artifact directory.

During the migration, missing JSON files should expose remaining legacy dependencies rather than silently falling back to old behavior.

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

Implement the currently disabled `Export JSON Data` Admin button. We deliberately moved Tooling Architecture Review ahead of publication so we can establish that contract first.

See notes from: freetv-server/json-export-mini-spec.md

```
MariaDB
   ↓
Server publisher/exporter
   ↓
configured artifact root
   ↓
freetv-data (likely)
   ↓
tooling assembles deployment
```

Publication includes the minimal Viewer configuration:

```json
{
  "lastupdated": "...",
  "showads": false
}
```

With show_ads → showads and lastupdated generated by publication.


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

Another area for improvement is the ordering of the items in the navbar. On small screen it's functional and contained under the toggle menu. On large screens it feels randomly placed with lots of space between the elements. 

## Cross-Playlist Resource Awareness

When editing a show that appears in more than one playlist, display:

* The number of playlists containing it
* The names of those playlists
* Whether the edit applies only to the current association or to shared show data

Example: `Thumbnail used by 7 shows across 3 playlists`

playlist_shows is authoritative for playlist show records, and identical Internet Archive identifiers may legitimately appear in multiple playlists.

## Repeatable Development Database Reset

Create a documented or scripted workflow for rebuilding the development database from a known baseline.

The workflow could:

1. Drop the development database.
2. Recreate the database.
3. Import the checked or archived SQL dump.
4. Apply any later schema migrations.
5. Run basic verification queries.

The development database is disposable. A reliable reset process would make destructive testing safer and help expose code that depends on stale or accidental database state.

This should remain development-only and must not be usable against production without explicit safeguards.

## Add An "Activity Log" feature

* Shows the last 10 actions completed in the UI
* An activity_log/audit_log table is a much more natural future architecture.
* Answers questions:

```
who deleted this show?
when?
from which playlist?
what changed?
```

## Improve Problem Reporting / Issue Count Badge

Currently the system only shows problems or issues with the currently-selected playlist. It functions. But, this may allow bugs to go hidden unless the admin purposely switches playlists and checks all of the issues. Ideally, the /dashboard/problems page would show a total count of all problems from all playlists as one number (combined total). Then, on the page itself we can have the display broken down by specific playlist. No matter which playlist is selected, the Admin could view ALL problems from ALL playlists on one screen. Maybe to facilitate this, there is a "Switch Playlist" button next to the items which are not on the current playlist? The admin could click the button, switch to that playlist, and fix the problem(s).

## Add more Automation Tools to Run on Cron 

The ability to check each item in the database against Internet Archive status (e.g to find "is_dark" items) and detect/report dead items would be useful.

## Create an Intake Hopper to Add New Titles to the DB

This would work in correlation with a Chrome browser extension that activates and functions on the Internet Archive and IMDB sites.

```
Archive.org
    ↓
"Add to FreeTV"
    ↓
incoming item

IMDb
    ↓
"Add to <archive identifier>"
    ↓
enrich same pending item
    ├── imdb
    ├── description
    ├── years
    └── thumbnail
```

Then:

```
Admin review
    ↓
approve
    ↓
playlist_shows
    ↓
next publication
    ↓
Viewer
```

Ingestion API can cheaply perform an immediate duplicate check against MariaDB. Cron can reconcile/clean up later, but there's no reason to knowingly accept obvious duplicates and wait for cron. The browser extension potentially eliminates the need for FreeTV to scrape IMDb server-side. The Admin's browser already has access to the loaded IMDb page and its image.


## Thumbnail Ingestion and Normalization

```
Thumbnail identity:
/thumbs/<imdb>.jpg

Storage:
filesystem, not MariaDB

Input:
JPEG

Normalization:
maximum width 1000px
preserve aspect ratio
never upscale

Sharing:
one physical thumbnail may serve multiple show rows/playlists

Manual workflow:
Add/Edit provides optional JPG upload

Future primary workflow:
Hopper/extension supplies thumbnail

Replacement:
warn about global impact before overwriting shared thumbnail
```

## Prevent stale Admin alerts across navigation

Fix state-lifecycle/UI messaging bug. The message system appears to behave like a global transient signal with a timeout, but it is not scoped to a route, navigation event, or application state transition. So:

```
logout
  ↓
set "You have been logged out"
  ↓
navigate to login
  ↓
message timer still alive
  ↓
login again before timeout
  ↓
navigate to dashboard
  ↓
old message still exists
```

That means any sufficiently fast navigation can potentially carry a message into a context where it no longer makes sense. We need to define message lifetime explicitly, for example:

- clear transient messages on route change unless they were intentionally created for the destination route;
- attach a destination/context to messages, such as login, dashboard, or global;
- distinguish one-navigation “flash” messages from persistent alerts;
- clear incompatible messages when authentication state changes.

See freetv-server/bugs.md for more detail.

## Responsive layout for the Thumbnail Manager

Most of the other areas of the Admin Dashboard were made responsive so they look good on large and small screens. The Thumbnail Manager was the most recent addition to the Dashboard and we haven't done anything regarding responsive layout. This page should look good on small screens too. We'll have to test first, make notes, and then come up with a plan to make this UI responsive. 

```
inspect current responsive behavior
→ document breakpoints/problems
→ design layout changes
→ implement
→ acceptance test
```

## Refine Thumbnail Manager Search

Improve Thumbnail Manager search so Admins can perform more precise searches and better understand the results.

Ideas:

* Display the total number of matching results.
* Support whole-word matching in addition to the current substring matching.
* Allow more targeted searches by title and IMDb ID.
* Consider simple search operators or an advanced search mode if useful.
* Clearly distinguish between exact, whole-word, and partial matches.

Example:

A substring search for red currently matches:

* Alfred Hitchcock Presents
* Clifford the Big Red Dog
* Freddy's Nightmares
* The Incredible Hulk

A whole-word search for red should match only:

* Clifford the Big Red Dog

Could add some UI options like:

```
Search: [ red                 ]

Match:
(•) Contains
( ) Whole word
( ) Exact
```

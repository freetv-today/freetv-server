# Testing FreeTV Admin

## Overview

FreeTV Admin has four complementary test groups:

1. **Database-independent PHPUnit tests** exercise backend services with synthetic fixtures, injected dependencies, and private temporary roots.
2. **Default Node HTTP tests** start an isolated loopback PHP server and check authorization and request-validation boundaries without connecting to MariaDB.
3. **Specialized Node contracts** protect frontend source wiring, build-output rules, readiness behavior, and feature flags. They are separate from `npm test`.
4. **Guarded database integration tests** use an explicitly installed MariaDB clone and are always opt-in.

The default commands, `composer test` and `npm test`, do not install, delete, or modify a MariaDB test database.

## Prerequisites

Install PHP dependencies with `composer install` and Node dependencies with `npm install`.

The repository metadata requires PHP 8.4.1 or newer, Node.js 22 or newer, Composer, npm, and the PHP cURL, Imagick, PDO, PDO MySQL, and ZIP extensions. PHPUnit 13 is installed as a Composer development dependency. ZIP is required by dataset, package, and snapshot archive tests. The image-processing tests explicitly report skips when Imagick is unavailable, although Imagick is also a declared application dependency and a normal Composer installation requires it.

MariaDB and its client utilities are needed only for the guarded database workflow. They are not needed by the default PHPUnit or Node HTTP suites.

## Quick start

Run the two independent default suites from the repository root:

```bash
composer test
npm test
```

Run one PHPUnit class by path:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/php/Unit/PublicationUndoServiceTest.php
```

Filter by PHPUnit class or test name:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter PublicationUndo
```

The filter is a name match, not a file selector, and can select tests from more than one class.

Run several related files before the complete suite by passing each path:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/php/Unit/AllPlaylistsPublicationServiceTest.php \
  tests/php/Unit/AllPlaylistsPublicationRollbackTest.php \
  tests/php/Unit/PublicationUndoServiceTest.php
```

## PHPUnit reference by feature

The database-independent suite contains 44 test classes. Every class is listed once below.

### Publication and Undo

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Publish All rollback | `AllPlaylistsPublicationRollbackTest.php` | Restores publication files, prior Undo state, and authoritative database timestamps—including prior `NULL` values—after partial timestamp-update failure. |
| Publish All | `AllPlaylistsPublicationServiceTest.php` | Selective, default-only, no-op, new-artifact, repeated, and writer-failure publication behavior. |
| Playlist index serialization | `PlaylistIndexSerializerTest.php` | Ordering, default selection, deterministic output, and preservation and validation of published timestamps. |
| Single-playlist rollback | `PlaylistPublicationRollbackTest.php` | File and database timestamp restoration when single-playlist publication fails. |
| Playlist serialization | `PlaylistPublicationSerializerTest.php` | Viewer-facing fields, show ordering, groups, and publication timestamps. |
| Single-playlist publication | `PlaylistPublicationServiceTest.php` | Publication success, validation, file replacement, Undo preparation, and failures. |
| Semantic comparison | `PublicationSemanticDeltaTest.php` | Playlist and config semantic differences, including duplicate identifiers. |
| Publication status | `PublicationStatusServiceTest.php` | Clean, changed, invalid, and default-change publication status. |
| Publication Undo | `PublicationUndoServiceTest.php` | Playlist, config, and Publish All Undo; slot replacement; integrity guards; and failure recovery. |
| Shared-host Undo | `SharedHostPublicationUndoTest.php` | Undo against an isolated `public_html` layout with private state outside the public root. |

### Configuration and serialization

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Config serialization | `ConfigPublicationSerializerTest.php` | Boolean setting conversion, defaults, timestamps, and invalid values. |
| Config publication | `ConfigPublicationServiceTest.php` | Exact JSON writes, permissions, responses, Undo integration, and invalid roots. |
| Show groups | `ShowGroupTest.php` | Group normalization and serialized group behavior. |

### Initialization, sessions, and readiness

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Admin sessions | `AdminSessionTest.php` | Session destruction, authentication retention on failure, cookie configuration, and isolated session state. |
| Fresh bootstrap | `BootstrapperTest.php` | Fresh initialization, initialized guards, artifacts, status, and rollback. |
| Initialization endpoint wiring | `InitializationBootstrapContractTest.php` | Read-only bootstrap selection and endpoint assembly without entering a MariaDB success path. |
| Initialization errors | `InitializationErrorResponseTest.php` | Stable, safe API responses for recognized initialization failures. |
| Package bootstrap | `PackageBootstrapperTest.php` | Baseline, sample, and official package orchestration and failure cleanup. |
| Database readiness | `DatabaseReadinessTest.php` | Ready, missing, unavailable, incomplete, and permission-related readiness states. |

### Database and path safety

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Capability probing | `DatabaseCapabilityProbeTest.php` | Disposable database/table probes, permission classification, cleanup, and safe generated names. |
| Database configuration | `DatabaseConfigTest.php` | Required values, aliases, ports, password handling, and configuration precedence. |
| Schema bootstrap | `SchemaBootstrapperTest.php` | Schema creation, existing/partial schemas, unsafe names, and initialization state. |
| Runtime paths | `ServerPathsTest.php` | Constructor and environment path resolution, custom public roots, and traversal rejection. |
| Test dump preparation | `TestDatabaseDumpPreparationTest.php` | Safe MariaDB dump rewriting and rejection of unexpected `USE` statements. |

### Dataset validation, providers, and installation

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Bundled baseline package | `BaselineDatasetPackageProviderTest.php` | Bundled ZIP discovery, validation, cleanup, and failure mapping. |
| Package installation | `DatasetPackageInstallationTest.php` | SQL validation, database-install lifecycle, artifact promotion, verification, and rollback. |
| Package metadata | `DatasetPackageMetadataTest.php` | Dataset definitions, malformed metadata, and safe endpoint responses. |
| Remote package provider | `DatasetPackageProviderTest.php` | Metadata/download ordering, hashes, archive validation, injected transport failures, and cleanup. |
| Package validation | `DatasetPackageValidatorTest.php` | Archive layout, required files, hashes, unsafe paths, case conflicts, and malformed packages. |
| Fresh artifact installation | `FreshArtifactInstallerTest.php` | Managed-file replacement, exact-byte restoration, staging cleanup, and unrelated-file preservation. |

### SQL package generation and execution

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Candidate package directory | `SqlPackageCandidateDirectoryTest.php` | Package allowlists and rejection of unexpected directory entries. |
| SQL execution | `SqlPackageExecutorTest.php` | Comment- and quote-aware statement splitting, ordered execution, and first-failure termination. |
| SQL generation | `SqlPackageGeneratorTest.php` | Deterministic package variants, byte-safe literals, ID translation, sampling, validation, and sensitive-table exclusion. |

### Snapshots and exports

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Data export | `DataExportServiceTest.php` | Manifest hashes, publication consistency, source/Undo immutability, lock creation, path rejection, and cleanup. |
| Snapshot status | `DataSnapshotStatusServiceTest.php` | Manifest validation, database time boundaries, and new/updated record counts. |
| Snapshot archives | `ProductionContentSnapshotArchiveServiceTest.php` | ZIP construction, completed-snapshot validation, resolution, and failure cleanup. |
| Snapshot endpoint boundaries | `ProductionContentSnapshotEndpointTest.php` | Safe authorization, role, method, identifier, and request boundaries that finish before live dependencies. |
| Snapshot capture | `ProductionContentSnapshotServiceTest.php` | Snapshot files and manifests, state preservation, validation, and failure cleanup. |

### Thumbnail management

| Feature area | PHPUnit test file | What it tests |
|---|---|---|
| Thumbnail export | `ThumbnailExportServiceTest.php` | Exact image bytes and hashes, deterministic manifests, path safety, validation, and partial-export cleanup. |
| Thumbnail integrity | `ThumbnailIntegrityServiceTest.php` | Present, missing, orphaned, and invalid thumbnail reporting without filesystem mutation. |
| Orphan cleanup | `ThumbnailOrphanCleanupServiceTest.php` | Dry runs, reference rechecks, symlinks, collisions, failures, quarantine manifests, and Undo preservation. |
| Thumbnail helpers | `ThumbnailServiceTest.php` | IMDb validation, safe filenames and paths, placeholders, and summary calculations. |
| Thumbnail Undo | `ThumbnailUndoServiceTest.php` | Upload/replace recovery, one-use state, hashes, stale/corrupt metadata, and exact-byte restoration. |
| Thumbnail upload | `ThumbnailUploadServiceTest.php` | Image conversion and sizing, replacement, validation, and rollback. |

## Node tests and specialized contracts

`npm test` runs only `tests/regression/api/authorization.test.js`. It starts an isolated PHP server on a loopback port and checks authorization, role, method, and malformed-request behavior using deliberately unusable database settings. It does not run every Node test and does not run the database-backed API suite.

The specialized contracts are independently runnable:

| Command | Contract | Purpose |
|---|---|---|
| `npm run test:admin-dist` | `AdminDistContractTest.js` | Validates the Admin distribution allowlist and excludes runtime or credential files. |
| `npm run test:data-snapshot-nav` | `DataSnapshotNavigationFlagTest.js` | Protects the Admin-only snapshot navigation feature flag. |
| `npm run test:data-initialization` | `DataInitializationContractTest.js` | Protects First Run modes, UI wiring, provider separation, and transport trust settings. |
| `npm run test:database-readiness` | `DatabaseReadinessContractTest.js` | Protects readiness endpoint/UI wiring, states, and provider-neutral guidance. |
| `npm run test:error-page` | `ErrorPageContractTest.js` | Protects initialization failure presentation, retry behavior, and no-fallback rules. |

Run all five sequentially with:

```bash
npm run test:contracts
```

The aggregate stops and fails when any constituent command fails. It does not build or deploy the application.

## Guarded database integration tests

The MariaDB workflow is opt-in and is not invoked by `composer test` or `npm test`:

```bash
npm run test:db:check
npm run test:db:install
npm run test:api:db
npm run test:db:clean
```

- `test:db:check` is read-only. It validates configuration, source schema access, and any recorded test-database state.
- `test:db:install` creates a uniquely named database, clones the configured source using the MariaDB client utilities, verifies table sets and row counts, performs a reversible write check, and records guarded state under `temp/testing/database-state.json`.
- `test:api:db` requires that recorded clone to be in the `ready` state. It runs the database-backed HTTP regressions serially against the clone.
- `test:db:clean` accepts no database-name argument. In an interactive terminal it requires the exact recorded generated name, drops only that validated database, and then removes its state file.

These commands require a correctly configured MariaDB environment and suitable privileges. Never point the guarded workflow at production, bypass its generated-name checks, or edit its recorded state to target another database. Installation and cleanup have real database side effects; inspect preflight output before proceeding.

## Retained snapshot endpoint test

`tests/ProductionContentSnapshotEndpointTest.php` remains a specialized historical test and is excluded from every default suite. Permanent PHPUnit already covers the endpoint branches that can be isolated safely. Its remaining authenticated case writes a ZIP beneath the application-root snapshot directory and verifies byte-exact streaming, so it still depends on operational paths.

Do not run this script casually against a production or development runtime containing real snapshot state. It is retained until authenticated streaming can be tested with a fully isolated application root; it is not considered fully migrated.

## Adding or modifying tests

- Match each PHPUnit class name to its `*Test.php` filename.
- Prefer synthetic fixtures, existing dependency-injection seams, and uniquely named private temporary roots.
- Keep real database and network access out of default PHPUnit tests.
- Isolate process-global session or environment behavior in private subprocesses.
- Assert exact bytes and hashes when testing publication, export, or Undo integrity.
- Report dependency-based skips explicitly rather than silently omitting coverage.
- Clean up only resources created and owned by the test.
- Do not add production seams solely to make a test convenient.

## Troubleshooting

- **Composer or PHPUnit is missing:** run `composer install` and verify the PHP version and declared extensions satisfy `composer.json`.
- **An image test is skipped:** check whether Imagick is loaded in the PHP CLI with `php -m`. The test output must retain the explicit skip until the dependency is available.
- **ZIP tests fail early:** verify the PHP CLI has the ZIP extension used by `ZipArchive`.
- **`npm test` cannot start its server:** the suite needs permission to bind an ephemeral loopback port. Check sandbox/network policy and inspect whether another process claimed the selected port; rerun normally rather than killing unrelated processes.
- **A PHPUnit filter selects nothing or too much:** filters match class and test names. Use an exact test-file path when file-level selection is intended.
- **Database preflight fails:** read the reported configuration, connectivity, schema, or permissions error. Do not proceed to installation until preflight is understood.
- **A test database is already tracked:** inspect `npm run test:db:check`. Use only the guarded interactive cleanup after confirming the recorded generated database is test-owned.
- **A fixture remains:** identify the uniquely named test-owned path and the failing test before removing it. Never broadly delete `/tmp`, repository runtime directories, or database state.

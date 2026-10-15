# Historical Test Inventory and Coverage Audit

## Executive Summary

The repository contains **47 historical executable tests** at the top level of `tests/`: 42 PHP scripts and 5 Node/JavaScript contract suites. The framework-based permanent suites contain **25 test files**: 21 PHPUnit classes under `tests/php/Unit/` and 4 Node HTTP regression files under `tests/regression/api/`.

Classification totals for the 47 historical executables are:

| Classification | Count |
|---|---:|
| A — Fully migrated | 18 |
| B — Partially migrated | 1 |
| C — Not yet migrated | 22 |
| D — Retain as a specialized test | 5 |
| E — Obsolete or superseded | 1 |

The permanent suite now protects publication serialization, semantic status/delta, single and multi-playlist publication, publication rollback and Undo, first-run readiness/schema/Fresh/package orchestration, guarded dump rewriting, database capability probing/configuration, server-path safety, dataset archive validation, remote and bundled package providers, metadata, and SQL candidate-directory safety. It does not yet replace the concrete package installers; session services; snapshot/export/archive services; or the thumbnail lifecycle.

Recommended next actions are to migrate the safety-critical concrete package installers, then filesystem-heavy snapshot/export and thumbnail tests. After equivalence verification, retire only the A files and the explicitly superseded E file. Keep the D contract suites as separately invoked workflows.

### Slice 3I-B1 completion

Permanent PHPUnit coverage now replaces the meaningful behavior of the historical database-capability, database-configuration, and server-path scripts. The new tests use injected connections, synthetic environment values, and unique temporary application roots; they do not connect to MariaDB or write into repository/production paths. They additionally cover all supported permission codes, failed statements, cleanup failures, strict generated-name validation, numeric-port whitespace, explicit empty legacy passwords, malformed path segments, constructor/environment precedence, and private Undo lock placement. The historical files remain present pending the planned B7 retirement pass.

### Slice 3I-B2 completion

Permanent PHPUnit coverage now replaces the five historical dataset validator/provider/metadata and SQL candidate-directory scripts. It executes the real archive and filesystem boundaries with synthetic ZIPs and temporary roots, and uses injected callbacks for remote transport. Lower-level cURL TLS, redirect, and response-size behavior remains covered only by the retained static initialization contract because production exposes no safe transport seam. PHP `ZipArchive` cannot reliably construct NUL-bearing or duplicate-name entries, so executable coverage uses the validator's other malformed-path and case-conflict branches instead.

## Current Test Architecture

### PHPUnit

`composer test` runs `phpunit --configuration phpunit.xml.dist`. PHPUnit discovers every test class in `tests/php/Unit/`; it is database-independent and currently contains 21 files. No top-level historical PHP executable is included.

### Node HTTP regression tests

`npm test` runs only `tests/regression/api/authorization.test.js`. It launches an isolated loopback PHP server using `tests/regression/support/php-test-server.js`, temporary sessions, and deliberately unusable database settings. It covers authorization, method and request validation without MariaDB.

`npm run test:api:db` runs, serially:

- `tests/regression/api/database.test.js`
- `tests/regression/api/show-management.test.js`
- `tests/regression/api/problem-reporting.test.js`

These require an explicitly installed guarded clone. They cover database connectivity, Add/Edit Show behavior, publication timestamp rollback, public problem reporting, playlist-scoped identity, disabled-show rejection, and rate limiting.

### Guarded database lifecycle

- `npm run test:db:check` runs the read-only `tests/regression/support/database-preflight.php`.
- `npm run test:db:install` runs `database-install.php`, creates and verifies a uniquely named clone, and records state under `temp/testing/database-state.json`.
- `npm run test:db:clean` runs `database-clean.php`, which accepts no arbitrary database name and operates only on validated recorded state.

Supporting files are `TestDatabaseSupport.php`, `database-api-test-bridge.php`, `php-test-server.js`, and `test-database.js`. They are infrastructure, not standalone regression tests.

### Specialized Node contracts

- `npm run test:admin-dist` runs `tests/AdminDistContractTest.js`.
- `npm run test:data-snapshot-nav` runs `tests/DataSnapshotNavigationFlagTest.js`.
- `DataInitializationContractTest.js`, `DatabaseReadinessContractTest.js`, and `ErrorPageContractTest.js` have no package script and must currently be invoked directly with `node --test`.

### Documentation assessment

The README accurately warns that database installation and cleanup are explicit and that `npm test` is database-isolated. It is now misleading where it says PHPUnit does not cover the historical standalone tests: substantial historical coverage has been migrated. It also does not inventory the three unreferenced Node contract suites or any remaining top-level PHP commands. `npm test` is named like a general Node suite but executes only authorization tests; the package scripts should make that scope more obvious or aggregate the safe contract suites in 3I-B.

## Complete Historical Test Inventory

| Historical test | Purpose | Permanent replacement | Classification | Recommendation |
|---|---|---|---|---|
| `tests/AdminSessionTest.php` | Session creation, cookie parameters and Admin session shape | Authorization HTTP tests consume seeded sessions but do not test session creation | C | Migrate to PHPUnit; retain until then |
| `tests/AllPlaylistsPublicationServiceTest.php` | Selective Publish All, no-op/default-only, rollback, new artifacts and Undo | `AllPlaylistsPublicationServiceTest`, `AllPlaylistsPublicationRollbackTest`, `PublicationUndoServiceTest` | E | Retire after final verification; its newly-published timestamp failure assertion predates and conflicts with the Slice 3E authoritative rollback contract |
| `tests/BaselineDatasetPackageProviderTest.php` | Bundled ZIP discovery, validation, cleanup and failure mapping | `tests/php/Unit/BaselineDatasetPackageProviderTest.php` | A | Candidate for retirement after final side-by-side verification |
| `tests/BootstrapperTest.php` | Fresh initialization, artifacts, status, rollback and initialized guard | `tests/php/Unit/BootstrapperTest.php` | A | Candidate for retirement |
| `tests/ConfigPublicationSerializerTest.php` | Boolean settings and timestamp serialization/validation | Indirect Fresh and publication-status coverage only | C | Add focused serializer PHPUnit tests |
| `tests/ConfigPublicationServiceTest.php` | Config write, permissions, response and invalid root failure | No permanent service test | C | Migrate to PHPUnit |
| `tests/DataExportServiceTest.php` | Manifest, hashes, immutable source/DB/Undo state, invalid destinations and cleanup | None | C | Migrate; filesystem integrity is important |
| `tests/DataInitializationContractTest.js` | Four initialization modes, UI wiring, provider separation and TLS/trust source contracts | Package PHPUnit tests cover orchestration, not frontend/source wiring | D | Retain as a specialized static contract; add package script |
| `tests/DataSnapshotNavigationFlagTest.js` | Admin-only feature flag behavior | None; separately scripted | D | Retain; already has `test:data-snapshot-nav` |
| `tests/DataSnapshotStatusServiceTest.php` | Manifest parsing, database boundary and new/updated/deleted counts | None | C | Migrate service behavior to PHPUnit |
| `tests/DatabaseCapabilityProbeTest.php` | Disposable database/table probes, permission classification, cleanup and unsafe names | `tests/php/Unit/DatabaseCapabilityProbeTest.php` | A | Candidate for retirement after final side-by-side verification |
| `tests/DatabaseConfigTest.php` | DB port/default/password/required values and `VITE_DB_*` aliases | `tests/php/Unit/DatabaseConfigTest.php` | A | Candidate for retirement after final side-by-side verification |
| `tests/DatabaseReadinessContractTest.js` | Endpoint/UI readiness wiring and permission guidance | Readiness PHPUnit covers service results, not UI/endpoint source contract | D | Retain; add explicit package script |
| `tests/DatabaseReadinessTest.php` | Ready/missing/error/incomplete readiness service behavior | `tests/php/Unit/DatabaseReadinessTest.php` | A | Candidate for retirement |
| `tests/DatasetPackageInstallationTest.php` | Concrete SQL validation/database installation and artifact promotion/verification rollback | Package Bootstrapper uses injected doubles | C | Migrate concrete installers; do not treat callback invocation as byte-level coverage |
| `tests/DatasetPackageMetadataTest.php` | Metadata values, malformed definitions and endpoint response | `tests/php/Unit/DatasetPackageMetadataTest.php` | A | Candidate for retirement after final side-by-side verification |
| `tests/DatasetPackageProviderTest.php` | Metadata/download ordering, hashes, archive handling, cleanup and provider failures | `tests/php/Unit/DatasetPackageProviderTest.php` | A | Candidate for retirement after final side-by-side verification; retain static transport contract separately |
| `tests/DatasetPackageValidatorTest.php` | Archive layout, required files, paths, hashes and malformed packages | `tests/php/Unit/DatasetPackageValidatorTest.php` | A | Candidate for retirement after final side-by-side verification |
| `tests/ErrorPageContractTest.js` | Initialization error UI, retries, hard failures and no fallback | No executable UI replacement | D | Retain; add explicit package script |
| `tests/FreshArtifactInstallerTest.php` | Real managed-file replacement, byte rollback, staging cleanup and unrelated files | Fresh Bootstrapper exercises real installer success/failure, including restoration | C | Still migrate lower-level installer branches and verification failures independently |
| `tests/InitializationBootstrapContractTest.php` | Endpoint bootstrap selection/wiring and initialization contract | Bootstrapper PHPUnit tests services, not endpoint assembly | C | Retain until safe endpoint/assembly contract is permanent |
| `tests/InitializationErrorResponseTest.php` | Stable initialization failure response mapping | Node UI contracts check codes, not executable PHP response mapping | C | Migrate response mapper without a real DB |
| `tests/PackageBootstrapperTest.php` | Baseline/sample/official orchestration and failure cleanup | `tests/php/Unit/PackageBootstrapperTest.php` | A | Candidate for retirement |
| `tests/PlaylistIndexSerializerTest.php` | Ordering, default validation and preservation/validation of unselected timestamps | Indirectly exercised by single/Publish All service tests | B | Add direct edge cases before retirement, especially zero defaults and malformed timestamp variants |
| `tests/PlaylistPublicationSerializerTest.php` | Viewer fields, ordering, groups and timestamps | `tests/php/Unit/PlaylistPublicationSerializerTest.php` | A | Candidate for retirement |
| `tests/PlaylistPublicationServiceTest.php` | Single-playlist publish, validation and rollback | `PlaylistPublicationServiceTest`, `PlaylistPublicationRollbackTest` | A | Candidate for retirement |
| `tests/ProductionContentSnapshotArchiveServiceTest.php` | Snapshot archive construction, validation and cleanup | None | C | Migrate concrete archive behavior |
| `tests/ProductionContentSnapshotEndpointTest.php` | Snapshot endpoint authorization/request behavior | No permanent snapshot endpoint test | C | Add database-independent endpoint cases where safe |
| `tests/ProductionContentSnapshotServiceTest.php` | Snapshot creation, manifests, state preservation and failures | None | C | Migrate service tests |
| `tests/PublicationSemanticDeltaTest.php` | Playlist/config semantic delta details and invalid duplicate identifiers | `tests/php/Unit/PublicationSemanticDeltaTest.php` | A | Candidate for retirement |
| `tests/PublicationStatusServiceTest.php` | Status/no-op/error/default and semantic delta behavior | `tests/php/Unit/PublicationStatusServiceTest.php` | A | Candidate for retirement |
| `tests/PublicationUndoServiceTest.php` | Playlist/config Undo, slot replacement and integrity rejection | `tests/php/Unit/PublicationUndoServiceTest.php` | A | Candidate for retirement |
| `tests/SchemaBootstrapperTest.php` | Creation, existing schema, partial failure, unsafe names and initialization state | `tests/php/Unit/SchemaBootstrapperTest.php` | A | Candidate for retirement |
| `tests/ServerPathsTest.php` | Environment/path derivation and unsafe path rejection | `tests/php/Unit/ServerPathsTest.php` | A | Candidate for retirement after final side-by-side verification |
| `tests/SharedHostPublicationUndoTest.php` | Shared-host Undo endpoint/path behavior | Undo service tests do not cover shared-host endpoint assembly | C | Retain until guarded endpoint coverage exists |
| `tests/ShowGroupTest.php` | Show group normalization/serialization behavior | Serializer covers group output, but not all input/service behavior | C | Migrate remaining group validation cases |
| `tests/SqlPackageCandidateDirectoryTest.php` | Candidate directory allowlist and unexpected files | `tests/php/Unit/SqlPackageCandidateDirectoryTest.php` | A | Candidate for retirement after final side-by-side verification |
| `tests/SqlPackageExecutorTest.php` | SQL splitting across comments/quoted semicolons and canonical statement count | Schema tests use executor but do not protect parser details | C | Migrate parser unit tests |
| `tests/SqlPackageGeneratorTest.php` | Generated package variants and content distinctions | None | C | Retain/migrate as packaging workflow test |
| `tests/TestDatabaseDumpPreparationTest.php` | Safe dump rewriting and rejection of ordinary `USE` | `tests/php/Unit/TestDatabaseDumpPreparationTest.php` | A | Candidate for retirement; guarded support remains |
| `tests/ThumbnailExportServiceTest.php` | Export manifest/hashes, image validation, path safety and cleanup | None | C | Migrate concrete filesystem tests |
| `tests/ThumbnailIntegrityServiceTest.php` | Missing, invalid, orphaned and valid thumbnail reporting | None | C | Migrate to PHPUnit |
| `tests/ThumbnailOrphanCleanupServiceTest.php` | Guarded orphan deletion, Undo preservation and failure recovery | None | C | P0/P1 migration before any retirement |
| `tests/ThumbnailServiceTest.php` | Filename and IMDb validation | None | C | Small focused PHPUnit migration |
| `tests/ThumbnailUndoServiceTest.php` | Upload/replace Undo, hashes, stale state and malformed metadata | None | C | Migrate integrity and recovery cases |
| `tests/ThumbnailUploadServiceTest.php` | Image conversion/sizing, replacement, validation and rollback | None | C | Migrate; requires Imagick but no DB/network |
| `tests/AdminDistContractTest.js` | Distribution allowlist and credential/runtime-file exclusion | None; separately scripted | D | Retain; already has `test:admin-dist` |

## Detailed Coverage Gaps

### P0 — Safety critical

1. **Concrete dataset installation boundary** (`DatasetPackageInstallationTest.php`). Archive traversal/layout rejection, metadata/archive hashes, provider cleanup and candidate-directory allowlists are now permanent. SQL validation before DDL, concrete database installation, managed-artifact restoration, and partial cleanup still require migration without implying transactional MariaDB DDL.

2. **Thumbnail mutation and recovery** (`ThumbnailUploadServiceTest.php`, `ThumbnailUndoServiceTest.php`, `ThumbnailOrphanCleanupServiceTest.php`). No permanent suite protects destructive file replacement/deletion, stale Undo detection, corrupt metadata, rollback, symlink/path rejection, or preservation of unrelated thumbnails. Destination: isolated Imagick-backed PHPUnit tests; no DB for most cases.

3. **Production snapshot/export filesystem integrity** (`DataExportServiceTest.php`, `ProductionContentSnapshotServiceTest.php`, `ProductionContentSnapshotArchiveServiceTest.php`). Missing coverage includes byte/hash manifests, immutable publication/Undo sources, invalid destinations, partial staging cleanup, archive validation and restoration. Destination: PHPUnit with temporary roots and injected readers; DB loaders should remain doubles.

4. **Shared-host publication path assembly** (`SharedHostPublicationUndoTest.php`). Core path validation, environment selection, and private Undo placement are permanent, but endpoint-level shared-host assembly remains historical. Destination: an isolated PHP subprocess.

### P1 — Important regressions

- **Admin session construction:** authorization tests seed expected sessions but do not prove `AdminSession` creates the same secure cookie/session state.
- **Config publication:** direct serializer and service contracts, permissions, atomic replacement and invalid-root behavior are absent.
- **Snapshot status:** timestamp boundary calculations and new/updated/deleted counts remain standalone only.
- **Initialization endpoint error mapping:** service orchestration is covered, but stable PHP endpoint error codes/messages are not executed permanently.
- **SQL package parser/generator:** quoted semicolons, comments, canonical package shape and sample/full distinctions lack permanent coverage.
- **Concrete Fresh artifact installer:** orchestration covers a promotion failure, but lower-level input validation, verification mismatch, backup failure and cleanup branches remain standalone.
- **Playlist index serializer:** service tests exercise common paths, but direct zero/multiple-default, ordering and timestamp-validation cases should be retained in a focused class.

### P2 — Useful coverage

- Show-group input normalization beyond serializer output.
- Dataset metadata endpoint and configuration matrices.
- Snapshot endpoint request/authorization cases that can terminate before database access.
- Thumbnail filename/IMDb helpers and integrity reporting.
- Explicit package scripts for the three currently unreferenced Node contract suites.

## Specialized Tests to Retain

The five D tests remain valuable because they validate build/UI/source-wiring contracts not represented by PHPUnit:

- `AdminDistContractTest.js`
- `DataInitializationContractTest.js`
- `DataSnapshotNavigationFlagTest.js`
- `DatabaseReadinessContractTest.js`
- `ErrorPageContractTest.js`

The guarded Node HTTP tests and everything under `tests/regression/support/` must also remain. Database install and cleanup are operational safety tools, not migration-era tests. `SqlPackageGeneratorTest.php` may ultimately remain a specialized packaging test rather than join the default unit suite, but it is conservatively classified C until that workflow is formalized.

## Obsolete or Superseded Tests

`AllPlaylistsPublicationServiceTest.php` is the sole E classification. Its useful success, no-op, default-only, new-artifact, writer-failure and Undo behaviors are covered by three permanent PHPUnit classes. Its database-update failure section explicitly permits a newly published playlist to retain the attempted timestamp after a later updater fails; Slice 3E established that successfully updated rows must be restored to their authoritative pre-operation values, including `NULL`. The permanent rollback class protects that corrected behavior. Retaining the old executable as a required green test would preserve a superseded contract.

The 18 A tests are not obsolete; they are redundant migration artifacts and are safe retirement candidates only after one final side-by-side verification:

- `BootstrapperTest.php`
- `BaselineDatasetPackageProviderTest.php`
- `DatabaseCapabilityProbeTest.php`
- `DatabaseConfigTest.php`
- `DatabaseReadinessTest.php`
- `DatasetPackageMetadataTest.php`
- `DatasetPackageProviderTest.php`
- `DatasetPackageValidatorTest.php`
- `PackageBootstrapperTest.php`
- `PlaylistPublicationSerializerTest.php`
- `PlaylistPublicationServiceTest.php`
- `PublicationSemanticDeltaTest.php`
- `PublicationStatusServiceTest.php`
- `PublicationUndoServiceTest.php`
- `SchemaBootstrapperTest.php`
- `ServerPathsTest.php`
- `SqlPackageCandidateDirectoryTest.php`
- `TestDatabaseDumpPreparationTest.php`

Together with the superseded Publish All executable, these produce 19 retirement candidates.

## Recommended 3I-B Work

1. **3I-B1: Safety foundations (complete).** `DatabaseCapabilityProbeTest`, `DatabaseConfigTest`, and `ServerPathsTest` now have isolated permanent PHPUnit coverage with no real database/path access.
2. **3I-B2: Dataset validation and providers (complete).** Metadata, candidate-directory, archive validator, baseline provider and remote provider behavior now has permanent coverage using synthetic packages and injected I/O.
3. **3I-B3: Concrete installers.** Migrate `FreshArtifactInstallerTest` and `DatasetPackageInstallationTest`, including byte-for-byte rollback and partial cleanup.
4. **3I-B4: Snapshot/export services.** Migrate export, snapshot status/service/archive, and safe endpoint cases.
5. **3I-B5: Thumbnail lifecycle.** Migrate helper, upload, integrity, Undo, orphan cleanup and export tests in independently reviewable classes.
6. **3I-B6: Remaining application contracts.** Migrate AdminSession, config publication, playlist-index direct coverage, show groups, SQL executor/generator, and initialization PHP response/assembly contracts.
7. **3I-B7: Retirement and command cleanup.** Run historical and permanent equivalents side by side; remove only verified A/E executables; add scripts for retained Node contracts; update README wording and command inventory.
8. **3I-B8: Final verification.** Run PHPUnit, all safe Node contracts, guarded HTTP tests, and—only with explicit configured state—the database-backed suite. Confirm fixture and state cleanup.

## Open Decisions and Risks

- Slice 3I-B1 validates configured and isolated readiness connection configuration without opening a socket. Direct bootstrap connection inspection was omitted because the current Illuminate factory emits an undefined `database`-key warning when constructing the intentionally database-less connection; capability-probe tests still verify bootstrap SQL behavior through an injected fake.
- Slice 3I-B2 does not execute the provider's built-in cURL transport. TLS verification, redirect restrictions and byte limits remain static-contract coverage until a separately reviewed transport seam exists.
- `ZipArchive` normalizes or rejects duplicate entry names and NUL-bearing names during fixture construction. Permanent validator tests cover traversal, absolute/drive/backslash paths, unexpected paths, case conflicts and Unix symlink types without claiming direct duplicate/NUL archive fixtures.

- Decide whether concrete dataset ZIP tests belong in default PHPUnit when `ext-zip` is already required, or in a specialized packaging command.
- Decide whether Imagick-backed thumbnail tests should remain in default PHPUnit or have an explicit capability-gated command.
- The package Bootstrapper contract proves that `rollback()` is requested, but does not itself prove concrete artifact byte restoration; the concrete installer migration is required before making that claim.
- Package cleanup failures after a successful database commit are intentionally logged and do not change the success result. Operational monitoring expectations should be documented.
- MariaDB schema DDL is not transactional. Unit tests must not imply rollback of partially installed tables; retry/idempotency remains the supported recovery model.
- Explicit Undo of a newly published playlist removes the artifact without inventing a previous publication timestamp; review whether the retained database timestamp is an acceptable architectural state.
- Multi-playlist explicit Undo can recover filesystem bytes after a timestamp-updater failure, but earlier successful database timestamp callbacks are not transactionally compensated.
- Determine whether safe endpoint subprocess tests should join `npm test` or remain explicitly named to keep the default suite fast and database-isolated.

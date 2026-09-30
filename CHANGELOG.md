# Changelog

All notable changes to Draw by xdecaro follow Semantic Versioning.

## [1.1.2] - 2026-10-01

### Fixed
- Added the missing administrator system-language keys `COM_XDECARODRAW_DASHBOARD` and `COM_XDECARODRAW_INFORMATION`, preventing raw constants in Joomla's Components menu.
- Prepared a non-destructive repair release that reinstalls the complete Draw component file set on Joomla 6 installations showing an inconsistent or partial administrator install.

### Validation
- Release/build gates verify that the installable component ZIP contains `admin/services/provider.php`, `admin/src/Service/DrawReadService.php` and the required administrator `.sys.ini` language keys.
- Runtime remains verified on Joomla 6.1.3 with PHP 8.3.

### Compatibility
- Joomla 6 only. Joomla 4 and Joomla 5 are not supported.
- No database schema or deterministic Draw-engine behavior changes.

## [1.1.1] - 2026-09-29

### Changed
- Draw now explicitly targets Joomla 6 only in the component manifest, package manifest and update server.
- Removed Joomla 4.4 and Joomla 5.4 runtime jobs; CI now gates Joomla 6.1.3 with PHP 8.3.
- Raised the update-server PHP baseline to 8.3.

### Compatibility
- Joomla 4 and Joomla 5 are not supported.
- No database or Draw-engine behavior changes.

## [1.1.0] - 2026-09-29

### Added
- Public optional integration facade exposed as `DrawComponent::getIntegrationService()`.
- Versioned normalized request contract `xdecaro.draw.request.v1` for external owners such as Competitions.
- Strict normalization for source references, entries, targets and supported declarative constraints before delegating to the existing Draw engine.
- Stable source references in `xdecaro.draw.result.v1` for the source entity and each assigned entry.
- Structured `sequence` and `target` fields in the v1 result while preserving existing live-snapshot aliases for backward compatibility.
- Integration contract and behavior regression tests.

### Changed
- CI version checks/build paths now derive from `VERSION` instead of being hardcoded to 1.0.0.
- Integration documentation now uses current `com_xdecarocompetitions` season/participation references.

### Compatibility
- The deterministic solver, seed/hash behavior, lifecycle, audit history and existing live reveal remain unchanged.
- Draw still has no mandatory dependency on Competitions and never reads or writes `#__xdecarocompetitions_*` tables.
- Consumers must treat Draw as optional and use the public integration facade rather than private services/storage.

## [1.0.0] - 2026-09-09

### Added
- Server-side deterministic draw engine with cryptographically secure automatic seed generation and reproducible explicit seeds.
- Generic entries, targets, pots and validated constraints (`one_per_pot`, distinct metadata, allowed/forbidden targets and forbidden pairs).
- Backtracking solver that rejects impossible configurations instead of weakening constraints.
- Lifecycle `draft -> ready -> live -> completed -> published` with ACL and CSRF-protected administrative actions.
- Immutable entries, targets and constraints after the first execution; restart creates a new `execution_no` and preserves previous assignments.
- Progressive public live reveal using read-only polling; the browser never computes or changes draw results.
- Public result contract `xdecaro.draw.result.v1`, input/result hashes and post-completion seed disclosure for verification.
- Draw-owned audit history for creation, configuration, execution, reveal, completion and publication.
- Optional Xdecaro Core 1.4 capability registration and entity-reference integration without making Core mandatory.
- Standard Xdecaro Information page, diagnostics, responsive administration UI, light/dark-compatible styling and reduced-motion support.
- English and Italian language files for administrator and site views.
- Deterministic Joomla package build and stable GitHub release workflow.
- CI gates for PHP 8.1/8.3 and Joomla 4.4.14, 5.4.8 and 6.1.3.

### Fixed
- Historical component manifest SQL charset declaration changed from `utf8mb4` to Joomla-compatible `utf8` while retaining utf8mb4 in the SQL schema itself.
- Non-destructive `1.0.0.sql` repair recreates any missing Draw 0.2.0 tables with `CREATE TABLE IF NOT EXISTS` and preserves existing data.

### Compatibility
- `com_xdecarodraw`, `pkg_xdecarodraw`, `xdecaro\Component\Draw` and `#__xdecarodraw_*` remain the stable technical identifiers.
- Draw does not read or write private Competitions tables; integrations must use stable public contracts and remain optional.

## [0.2.0] - 2026-09-08

- Prerelease technical baseline for the standalone Draw component and package.
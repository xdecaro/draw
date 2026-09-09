# Changelog

All notable changes to Draw by xdecaro follow Semantic Versioning.

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

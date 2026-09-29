# Draw–Competitions Integration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** expose a stable, optional public integration facade in Draw so Competitions can create/configure a draw from normalized season/participation data without reading or writing Draw private tables, while preserving Draw 1.0.0 behavior and allowing Competitions to keep working when Draw is absent.

**Architecture:** Draw remains the draw-domain owner and exposes a narrow `DrawIntegrationService` through `DrawComponent`. The facade validates a versioned normalized request, delegates persistence/execution to the existing `DrawService`, and returns public versioned snapshots through `DrawReadService`. Competitions remains the source owner and later calls this facade through Joomla's optional component boot mechanism. No direct cross-product table access is allowed. The first increment stops at creating/configuring/opening the Draw session; automatic persistence into Competitions groups/brackets is deferred until those authoritative domain models exist.

**Tech Stack:** PHP 8+, Joomla DI/component boot APIs, Joomla DatabaseInterface, existing Draw services, PHP contract/smoke tests, GitHub Actions, Semantic Versioning.

**Status:** ready for implementation on `feature/draw-competitions-integration`.

**Related specs:** `docs/integration-contract.md` in Draw and `docs/draw-integration.md` in Competitions.

---

### Task 1: Lock the public integration contract with tests

**Files:**
- Create: `tests/draw-integration-contract.php`
- Modify: `.github/workflows/build.yml` only if the current test runner does not auto-run the new contract test.

**Step 1: Write the failing contract test**

Assert that the component exposes a dedicated public integration facade rather than requiring consumers to call private persistence internals. The contract must cover:

- `DrawComponent::getIntegrationService()` exists;
- a `DrawIntegrationService` class exists;
- request schema identifier `xdecaro.draw.request.v1` is supported;
- result schema remains `xdecaro.draw.result.v1`;
- the service accepts normalized source/entry/target/constraint arrays;
- no reference to `#__xdecarocompetitions_*` exists in Draw integration code.

**Step 2: Run the test and verify RED**

Run: `php tests/draw-integration-contract.php`

Expected: FAIL because the public facade does not exist yet.

**Step 3: Commit the failing contract test**

Commit: `test: define public Draw integration facade`

---

### Task 2: Implement the Draw public integration facade

**Files:**
- Create: `component/admin/src/Service/DrawIntegrationService.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/DrawComponent.php`

**Step 1: Implement the minimum public facade**

Add a final service with a narrow stable API, initially:

```php
public function createFromPayload(array $payload, int $actorId = 0): array;
public function getResult(int $drawId): array;
```

`createFromPayload()` must:

- require `schema = xdecaro.draw.request.v1`;
- validate `source.component`, `source.entity`, `source.id`;
- validate title/mode;
- normalize entries from public contract fields (`key`, `source`, `name`, `pot`, `seed`, `metadata`);
- normalize generic targets (`type`, `key`, `position`, `metadata`);
- accept only declarative constraints supported by the existing engine;
- delegate create/configure to `DrawService`;
- return a public snapshot/reference, not database rows.

`getResult()` delegates to `DrawReadService::getPublicSnapshot()` and therefore preserves `xdecaro.draw.result.v1`.

Do not expose DatabaseInterface or table names through the facade.

**Step 2: Register through Joomla DI**

Register `DrawIntegrationService` in `component/admin/services/provider.php`, inject existing `DrawService` and `DrawReadService`, and wire it into `DrawComponent`.

**Step 3: Expose the component API**

Add `setIntegrationService()` and `getIntegrationService()` to `DrawComponent`. Existing service getters remain unchanged for backward compatibility.

**Step 4: Run focused tests**

Run:
- `php tests/draw-integration-contract.php`
- `php tests/draw-engine.php`
- `php tests/core-integration-smoke.php`

Expected: PASS.

**Step 5: Commit**

Commit: `feat: add public Draw integration facade`

---

### Task 3: Add behavioral validation for normalized payloads

**Files:**
- Modify: `tests/draw-integration-contract.php`
- Modify: `component/admin/src/Service/DrawIntegrationService.php`

**Step 1: Add RED cases**

Cover malformed or unsafe input:

- unsupported request schema;
- missing source reference;
- duplicate entry keys;
- missing target descriptors;
- mismatched entry/target counts;
- unknown constraint type;
- oversized or malformed identifiers;
- source presentation names are not used as identifiers.

**Step 2: Run and verify failures**

Run: `php tests/draw-integration-contract.php`

**Step 3: Implement validation using existing Draw domain rules**

Keep validation at the public boundary and let `DrawService` remain authoritative for engine-specific invariants. Do not duplicate the solver.

**Step 4: Run and verify GREEN**

Run: `php tests/draw-integration-contract.php`

**Step 5: Commit**

Commit: `test: harden normalized Draw integration payload`

---

### Task 4: Align documentation with current component identities

**Files:**
- Modify: `docs/integration-contract.md`
- Modify: `README.md` if public integration entry points are documented there.

**Step 1: Replace historical examples**

Update examples using `com_decarodcl` to the real current owner identifier `com_xdecarocompetitions` and use current source entities such as `season` and `participation` where appropriate.

**Step 2: Document the facade**

Document:

- optional consumer pattern via `bootComponent('com_xdecarodraw')`;
- `getIntegrationService()` as the supported public entry point;
- request schema `xdecaro.draw.request.v1`;
- result schema `xdecaro.draw.result.v1`;
- no direct cross-table access;
- Draw absence must be handled gracefully by consumers.

**Step 3: Commit**

Commit: `docs: align Draw public integration contract`

---

### Task 5: Prepare Draw 1.1.0 metadata without publishing prematurely

**Files:**
- Modify: `VERSION`
- Modify: `component/xdecarodraw.xml`
- Modify: `package/pkg_xdecarodraw.xml`
- Modify: `CHANGELOG.md`
- Modify: update-server metadata only if required by the repository release workflow.

**Step 1: Bump version to 1.1.0**

This is a backward-compatible public feature, therefore a SemVer minor release.

**Step 2: Update changelog**

Record the public integration facade, request schema, compatibility behavior and unchanged engine semantics.

**Step 3: Run repository validation/build**

Run the repository's standard build/test commands from `AGENTS.md` / workflow equivalents and confirm the installable ZIP is generated from the same version metadata.

**Step 4: Commit**

Commit: `chore: prepare Draw 1.1.0`

Do not publish/tag until all verification is green.

---

### Task 6: Competitions adapter as a separate follow-on branch/release

**Repository:** `xdecaro/competitions`

**Planned version:** `1.6.0` (new backward-compatible feature).

**Files expected:**
- Create: `component/admin/src/Service/DrawIntegrationService.php`
- Modify: `component/admin/services/provider.php`
- Modify: relevant season/participation administrator view and toolbar entry point
- Create: `tests/draw-integration-contract.php`
- Modify: `docs/draw-integration.md`
- Version/release metadata after implementation.

**Scope:**

- detect optional `com_xdecarodraw` availability/version;
- export only eligible/approved season participations through a normalized payload;
- call Draw through `getIntegrationService()`;
- never query Draw private tables;
- keep Competitions fully functional when Draw is absent;
- open/link the created Draw session and show published-result status;
- validate source identity before any future application.

**Explicitly deferred:**

Automatic application of Draw assignments into persistent Competitions groups/brackets. The current Competitions 1.5.57 codebase has no authoritative group model/table/view to receive those assignments, so creating such storage as part of this integration would mix unrelated domain design into the adapter. That feature must be designed in Competitions first, then connected to the already-versioned Draw result contract.

---

### Final verification before completion

Run all Draw tests and build checks, then verify:

- existing Draw 1.0.0 engine behavior still passes unchanged;
- Joomla component boots with and without Core optional capabilities as currently supported;
- public integration service is available through the component object;
- request validation rejects malformed/unsupported data cleanly;
- no `#__xdecarocompetitions_*` references exist anywhere in Draw runtime integration code;
- result remains `xdecaro.draw.result.v1`;
- no PHP warnings/notices;
- install/upgrade package validation succeeds;
- no release/tag is created before CI is green.
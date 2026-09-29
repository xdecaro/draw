# Draw integration contract

## Status

This document defines the public integration boundary for Draw 1.1.x.

It is an API contract, not permission for consumers to read Draw private tables or depend on internal persistence classes.

The stable Joomla component element is `com_xdecarodraw`.

## Public entry point

Optional consumers should boot Draw through Joomla and use the component facade:

```php
$drawComponent = Factory::getApplication()->bootComponent('com_xdecarodraw');
$integration = $drawComponent->getIntegrationService();
```

The supported public integration entry point is `DrawIntegrationService` as exposed by `DrawComponent::getIntegrationService()`.

Consumers must not use Draw table names or private storage as an integration API. If Draw is not installed or is incompatible, optional consumers must fail gracefully and keep their own manual workflows available.

## Core references

Use xdecaro Core `EntityReference` / `RelationReference` when referring to entities owned by another xdecaro product where those APIs are available.

A source draw may therefore refer to a Competitions season without knowing its database schema.

Example source reference:

```json
{
  "component": "com_xdecarocompetitions",
  "entity": "season",
  "id": "42"
}
```

The reference identifies the source only. It does not grant read/write permission and does not transfer ownership.

## Request schema

The first public request schema is:

`xdecaro.draw.request.v1`

Integrations pass normalized entries instead of allowing Draw to query another product's private tables.

Example:

```json
{
  "schema": "xdecaro.draw.request.v1",
  "title": "DCL Futsal Men 2026",
  "source": {
    "component": "com_xdecarocompetitions",
    "entity": "season",
    "id": "42"
  },
  "mode": "groups",
  "entries": [
    {
      "key": "participation:10",
      "source": {
        "component": "com_xdecarocompetitions",
        "entity": "participation",
        "id": "10"
      },
      "name": "Example Club",
      "pot": "1",
      "seed": 1,
      "metadata": {
        "country": "IT"
      }
    }
  ],
  "targets": [
    {
      "type": "group",
      "key": "A",
      "position": 1,
      "metadata": {
        "group": "A"
      }
    }
  ],
  "constraints": [
    {
      "type": "max_same_metadata_per_target",
      "field": "country",
      "max": 1
    }
  ]
}
```

Rules:

- `schema` must be `xdecaro.draw.request.v1`;
- `key` must be unique within the draw input;
- `source` references are integration identifiers; names are presentation metadata only;
- entries and targets must have the same count and contain at least two items;
- `metadata` contains only fields explicitly needed by configured constraints/presentation;
- Draw must not infer additional source data from private tables;
- sensitive/unnecessary source data must not be copied into Draw;
- unknown constraint types fail clearly instead of being ignored.

## Supported declarative constraints

The v1 facade maps only supported declarative rules into the existing Draw engine:

- `one_per_pot`;
- `distinct_metadata` with `field`;
- `max_same_metadata_per_target` with `field` and currently `max = 1`;
- `allowed_targets` with `entry_key` and `targets`;
- `forbidden_targets` with `entry_key` and `targets`;
- `forbidden_pair` with exactly two entry keys.

A consumer must never send an executable callback/class name as a constraint.

## Result payload

The stable result schema remains:

`xdecaro.draw.result.v1`

Example:

```json
{
  "schema": "xdecaro.draw.result.v1",
  "draw_id": 81,
  "source": {
    "component": "com_xdecarocompetitions",
    "entity": "season",
    "id": "42"
  },
  "mode": "groups",
  "status": "published",
  "assignments": [
    {
      "sequence": 1,
      "entry_key": "participation:10",
      "entry_source": {
        "component": "com_xdecarocompetitions",
        "entity": "participation",
        "id": "10"
      },
      "target": {
        "type": "group",
        "key": "A",
        "position": 1,
        "metadata": {
          "group": "A"
        }
      }
    }
  ]
}
```

For backward compatibility with the existing Draw live snapshot, v1 may also expose the legacy aliases `sequence_no`, `target_type`, `target_key`, `position_no` and `target_metadata`. Consumers should prefer the structured `sequence`, `entry_source` and `target` fields for cross-product integration.

Draw owns the meaning of the result schema. Consumers map generic assignments into their own domain data.

For example, Competitions may turn `target.type = group`, `target.key = A`, `target.position = 1` into its own group membership. Draw must not create that membership directly.

## Target types

The v1 architecture is intended for generic target descriptors such as:

- `group`;
- `bracket_slot`;
- `pairing`;
- `preliminary_slot`.

Do not encode sport-specific qualification logic in these target descriptors.

If a new target type becomes part of the supported public contract, treat its identifier and required fields as a versioned API change.

## Lifecycle

Public lifecycle values are:

- `draft`;
- `ready`;
- `live`;
- `completed`;
- `published`;
- `cancelled` when supported by the lifecycle implementation.

A consumer should normally apply a result only after the state required by that integration, typically `completed` or `published`.

## Live event stream

The live UI may expose already-authorized reveal events such as:

- `draw.started`;
- `draw.entry.revealed`;
- `draw.entry.assigned`;
- `draw.pot.completed`;
- `draw.completed`;
- `draw.published`;
- `draw.cancelled`.

The public live endpoint must never expose future/unrevealed assignment data merely because the full result has already been computed server-side.

The event model remains transport-neutral. Polling, SSE or WebSocket are transport choices and are not part of the domain contract.

## Competitions adapter

Competitions integration is optional.

### Competitions owns

- competition/season and authoritative participants;
- eligibility and ACL to start/use a draw;
- normalized export of entries/metadata;
- validation that a published result still maps to current eligible participants;
- its own groups, bracket, pairings, fixtures and sport rules;
- audit metadata recording the external Draw reference when useful.

### Draw owns

- draw lifecycle and configuration;
- normalized input snapshot for audit;
- pots/seeds/constraints;
- server-side execution;
- reveal/result history;
- live/public state;
- the versioned result payload.

Draw never writes directly into Competitions private tables.

## Failure behaviour

Integrations must fail clearly and without fatal cross-product coupling when:

- Draw is not installed;
- required public APIs are unavailable/incompatible;
- the source entity no longer exists;
- participants changed after draw preparation;
- constraints are impossible;
- the draw is not in a state that permits the requested action;
- the current user lacks permission;
- a request/result schema version is unsupported.

## Idempotency

Applying a published Draw result to another product should be idempotent where technically possible.

A retry must not create duplicate groups/pairings simply because the same result was submitted twice.

Consumers should record the Draw result identifier/schema version or equivalent integration key so repeated application can be detected.

## Security boundary

Knowing a Core/source entity reference does not imply authorization.

Each owning product keeps responsibility for its ACL.

Draw validates permissions at its own HTTP/controller boundary and validates integration payloads at the service boundary. Competitions must validate permissions before exporting authoritative participants and before applying a Draw result to competition data.

Never rely on frontend visibility, disabled buttons or a result URL as authorization.
# Draw integration contract

## Status

This document defines the intended first public integration boundary for Draw.

It is an architecture contract, not permission for consumers to read Draw private tables or internal classes.

The final Joomla component element and namespace must be fixed by the Draw manifest before they are treated as stable public identifiers.

## Core references

Use Xdecaro Core `EntityReference` / `RelationReference` when referring to entities owned by another Xdecaro product.

A source draw may therefore refer to a competition without knowing its database schema.

Example source reference:

```json
{
  "component": "com_decarodcl",
  "entity": "competition",
  "id": "42"
}
```

The reference identifies the source only. It does not grant read/write permission and does not transfer ownership.

## Normalized input

Integrations should pass normalized entries instead of allowing Draw to query another product's private tables.

Recommended input shape:

```json
{
  "source": {
    "component": "com_decarodcl",
    "entity": "competition",
    "id": "42"
  },
  "mode": "groups",
  "entries": [
    {
      "key": "team:10",
      "source": {
        "component": "com_decarodcl",
        "entity": "participant",
        "id": "10"
      },
      "name": "Roma Deaf",
      "pot": "1",
      "seed": 1,
      "metadata": {
        "country": "IT",
        "zone": "EU"
      }
    }
  ]
}
```

Rules:

- `key` must be unique within the draw input;
- `source` is preferred when the entry maps to an entity owned by another component;
- `name` is presentation metadata, not an integration identifier;
- `metadata` contains only fields explicitly needed by configured constraints;
- Draw must not infer additional source data from private tables;
- sensitive/unnecessary source data must not be copied into Draw.

## Result payload

The result contract should remain independent from Competitions internals.

Recommended v1 shape:

```json
{
  "schema": "xdecaro.draw.result.v1",
  "draw_id": "81",
  "source": {
    "component": "com_decarodcl",
    "entity": "competition",
    "id": "42"
  },
  "mode": "groups",
  "status": "published",
  "assignments": [
    {
      "sequence": 1,
      "entry_key": "team:10",
      "entry_source": {
        "component": "com_decarodcl",
        "entity": "participant",
        "id": "10"
      },
      "target": {
        "type": "group",
        "key": "A",
        "position": 1
      }
    }
  ]
}
```

Draw owns the meaning of the result schema. Consumers map the generic assignment into their own domain data.

For example, Competitions may turn `group/A/position/1` into a Competitions group membership. Draw must not create that membership directly.

## Target types

The initial architecture should support generic target descriptors such as:

- `group`;
- `bracket_slot`;
- `pairing`;
- `preliminary_slot`.

Do not encode sport-specific qualification logic in these target descriptors.

If a new target type is added to the public contract, treat its identifier and required fields as a versioned API.

## Constraints

Constraints may be passed as normalized configuration, but Draw remains authoritative for validating and executing them.

A consumer must not send an arbitrary executable callback/class name as a constraint.

Prefer declarative rules such as:

```json
{
  "type": "max_same_metadata_per_target",
  "field": "country",
  "max": 1
}
```

Only supported/validated rule types may be accepted.

Unknown rule types must fail clearly rather than being ignored silently.

## Lifecycle

Recommended public lifecycle values:

- `draft`;
- `ready`;
- `live`;
- `completed`;
- `published`;
- `cancelled`.

A consumer should normally apply a result only after the Draw result reaches the state required by that integration, typically `completed` or `published`.

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

The event model must remain transport-neutral. Polling, SSE or WebSocket are transport choices and are not part of the domain contract.

## Competitions adapter

Competitions integration is optional.

Preferred responsibilities:

### Competitions

- owns the competition and authoritative participants;
- checks user permission to start/use a draw for that competition;
- exports normalized entries/metadata;
- invokes a documented Draw service/API;
- receives a completed/published result;
- validates that the result still maps to current eligible participants;
- creates/updates its own groups, bracket or pairings;
- records the external Draw reference where useful.

### Draw

- owns draw lifecycle and configuration;
- snapshots/normalizes draw inputs as required for audit;
- validates pots/seeds/constraints;
- executes the draw server-side;
- stores reveal/result history;
- provides live/public state;
- returns a versioned result payload;
- never writes directly into Competitions private tables.

## Failure behaviour

Integrations must fail gracefully when:

- Draw is not installed;
- required Core APIs are unavailable/incompatible;
- the source entity no longer exists;
- participants changed after draw preparation;
- constraints are impossible;
- the draw is not in a state that permits the requested action;
- the current user lacks permission;
- a result schema version is unsupported.

Never convert these cases into an opaque fatal error.

## Idempotency

Applying a published Draw result to another product should be idempotent where technically possible.

A retry must not create duplicate groups/pairings simply because the same result was submitted twice.

Consumers should record the Draw result identifier/schema version or equivalent integration key so repeated application can be detected.

## Security boundary

Knowing a Core entity reference does not imply authorization.

Each owning product keeps responsibility for its ACL.

Draw must validate permissions for draw operations; Competitions must validate permissions before applying a Draw result to competition data.

Never rely on frontend visibility or disabled buttons as authorization.

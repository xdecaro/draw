# Draw — Codex Repository Rules

## Xdecaro Core integration

Draw is part of the Xdecaro Joomla ecosystem and must use **Xdecaro Core** only for infrastructure that is genuinely shared and domain-neutral.

Good Core responsibilities include:

- `EntityReference` and `RelationReference` cross-product contracts;
- shared `.xdecaro-*` UI primitives and design tokens;
- Web Asset Manager helpers;
- responsive/light/dark foundations;
- generic AJAX/CSRF helpers;
- dependency/version diagnostics;
- common information/update UI;
- generic events/contracts proven reusable by multiple products.

Keep Draw business logic here, including:

- draw sessions;
- pots/fasce;
- seeding;
- constraints;
- draw algorithms;
- reveal sequence;
- assignments to groups, pairings or bracket slots;
- draw history;
- draw-specific audit events;
- live/public draw state;
- draw animations and presentation behaviour.

Never move Draw algorithms or state into Core.

Dependency direction:

`Draw -> Xdecaro Core`

Never:

`Xdecaro Core -> Draw`

Before adding a generic helper locally, inspect whether Core already exposes a stable public API. Do not invent or depend on undocumented Core internals.

## Product purpose

Draw is a reusable Joomla component for controlled and auditable draws.

It must be usable independently of Competitions and must not become a hidden submodule of Competitions.

Primary capabilities:

- group draws;
- bracket-slot draws;
- pairings;
- seeded pairings;
- pots;
- configurable constraints;
- preview and validation;
- live execution;
- public presentation;
- result publication;
- history and audit.

## Competitions boundary

Competitions is an optional integration.

Draw must not:

- query or update private Competitions tables;
- duplicate Competitions standings, match, fixture or sports-rule logic;
- require Competitions to install or run;
- infer Competitions storage paths or classes from IDs.

Use stable public contracts/services/events.

Competitions may provide:

- an `EntityReference` for the source competition;
- normalized participant input;
- requested draw mode;
- supported constraint metadata.

Draw returns a stable result payload describing assignments. Competitions remains responsible for creating/updating its own groups, bracket or pairings.

If Draw is missing, Competitions must continue to function with its manual workflows.

## Ownership

Draw owns its own persisted data and lifecycle.

Source entities remain owned by their source component. Importing or referencing an entry does not transfer ownership.

Do not create cross-component foreign keys to another component's private tables.

Store external relationships using stable component/entity/id references and local metadata required by Draw.

## Draw integrity

The server is authoritative.

The browser must never decide:

- which entry is drawn;
- which slot is assigned;
- whether a constraint can be ignored;
- whether a draw is valid;
- whether a result is confirmed/published.

Animation is presentation only.

For a confirmed/public draw, preserve enough information to audit the result, including where applicable:

- normalized inputs;
- configuration;
- constraints;
- algorithm/version;
- execution sequence;
- assignments;
- actor;
- timestamps;
- status transitions;
- restart/cancellation history.

Do not silently rewrite a published result.

A re-draw/restart must be an explicit audited operation.

## Constraint engine

Constraints must be explicit and testable.

Potential constraints include:

- pot distribution;
- seeded positions;
- same/different country;
- same/different geographical zone;
- same/different club/organization;
- allowed/forbidden pairings;
- group capacity;
- bracket-slot restrictions.

When constraints cannot all be satisfied, stop and explain the conflict. Never silently weaken a rule.

Prefer deterministic, testable search/backtracking behaviour over fragile repeated random retries.

Random selection must use an appropriate secure source of randomness for real draw decisions.

## Live/public architecture

Public live state is read-only for anonymous/public viewers.

State-changing controls require authenticated administrator/operator authorization.

The event/state model should be transport-neutral. Start with Joomla-compatible AJAX/polling when adequate; SSE/WebSocket may be added later without changing the domain contract.

Possible Draw-owned event names:

- `draw.started`;
- `draw.entry.revealed`;
- `draw.entry.assigned`;
- `draw.pot.completed`;
- `draw.completed`;
- `draw.published`;
- `draw.cancelled`.

These events belong to Draw, not Core.

## Animation rules

Live animation may:

- reveal an entry in the centre;
- hold it briefly for readability;
- move/scale it toward the assigned group or slot;
- update the destination layout;
- proceed to the next reveal.

Animations must:

- never calculate results;
- never hide the textual result;
- support `prefers-reduced-motion`;
- remain understandable when animations are disabled;
- avoid layout shifts that make the page unreadable;
- remain smooth on normal mobile hardware;
- not create horizontal overflow.

## Joomla architecture

Target Joomla 4, 5 and 6 where technically possible.

Use modern Joomla APIs:

- namespaces;
- MVC;
- service providers;
- dependency injection where appropriate;
- Web Asset Manager;
- `DatabaseInterface`;
- Form API;
- Language API;
- Router;
- Joomla events;
- ACL;
- input/filter APIs.

Use `#__` for tables.

Do not use direct global database access when a service dependency is appropriate.

## Security

Check server-side for every state-changing action:

- ACL;
- CSRF;
- filtered/validated input;
- session/operator permissions;
- current draw state;
- allowed state transition;
- participant/slot validity;
- constraint validity.

Also check:

- XSS and escaping;
- bound SQL parameters;
- unauthorized public endpoints;
- information leakage before reveal;
- replay/double-submit actions;
- race conditions during live operation;
- rate abuse of live endpoints.

A public endpoint must not expose unrevealed results merely because they are already computed internally.

## Concurrency

Live draw actions are sensitive to double clicks and concurrent operators.

Design state transitions so the same reveal/assignment cannot be committed twice.

Use transactional/locking techniques where required. Re-read authoritative state server-side before committing a transition.

Frontend button disabling is UX only and is not sufficient protection.

## Database

Keep Draw-domain tables inside Draw.

Database updates must preserve existing data and configuration.

Do not drop/recreate production tables for routine updates when a safe migration is possible.

Index common lookup/order fields such as session, state, sequence and external reference where needed.

Avoid queries in loops in live/status endpoints.

## UI/UX

Follow the shared Xdecaro visual language and use Core assets where appropriate.

Administrator UI should prioritize:

- clear setup steps;
- compact participant/pot management;
- fast search/filtering;
- visible validation errors;
- preview before live start;
- explicit status;
- clear operator controls;
- safe restart/cancel confirmation;
- accessible audit history.

Public UI should prioritize:

- readability at distance;
- fullscreen/projector mode;
- strong current-entry emphasis;
- clear destination movement;
- responsive groups/bracket;
- smartphone fallback;
- light/dark mode;
- accessibility and reduced motion.

Do not rely on colour alone for pots, states or result meaning.

## Multilingual

Use Joomla language files for all translatable UI text.

Default language follows Joomla.

Do not hardcode public/operator labels in PHP/JS when they should be language constants.

## Public API stability

Treat published Draw integration contracts as APIs.

This includes:

- entity type names;
- result payload fields;
- event names;
- service identifiers;
- routes/endpoints documented for integrations;
- public Web Asset identifiers;
- stable status values.

For incompatible changes, introduce a compatible replacement/deprecation path and reserve removals for an appropriate major version.

Do not expose internal database schemas as the integration API.

## Versioning and releases

Use Semantic Versioning.

Keep versions coherent across manifest, package metadata, changelog, update feed, SQL migrations, tags, releases and ZIP filenames.

Never distribute different code using the same version.

ZIPs must install directly in Joomla.

Do not include backups, logs, IDE files, `.DS_Store`, temporary files or nested release ZIPs.

## Testing

Important tests include:

- clean install/update;
- ACL and CSRF;
- state-transition validation;
- impossible constraint detection;
- deterministic algorithm tests around fixed test inputs;
- secure/random draw integration tests without relying on statistically flaky assertions;
- concurrent/double-submit protection;
- live public state not leaking unrevealed results;
- Competitions integration available/unavailable;
- Core available/minimum version handling;
- Joomla 4/5/6 where supported;
- PHP errors/warnings;
- JavaScript Console;
- desktop/tablet/smartphone;
- light/dark mode;
- reduced motion;
- keyboard/accessibility basics.

Do not claim live tests were executed when only static review was performed.

## Before modifying

Before changing Draw:

1. inspect the current implementation and manifests;
2. inspect Core public APIs used by the change;
3. inspect Competitions integration if affected;
4. identify data/API compatibility impact;
5. preserve working behaviour;
6. avoid unrelated refactors;
7. choose the smallest robust design;
8. verify security and concurrency implications;
9. verify responsive/light/dark impact;
10. update docs/contracts when a public integration changes.

## Working rule

When the user says **"procedi"**, execute the requested work directly after inspection.

Do not ask for another confirmation when requirements are already clear.

If a proposed solution is weaker than a safer or more maintainable alternative, use or recommend the stronger solution and explain the reason.

# Draw by xdecaro

**Draw by xdecaro** is the Joomla component dedicated to controlled draws, group composition, pairings and bracket assignments for the Xdecaro ecosystem.

Repository: `xdecaro/draw`

The component is intentionally separate from Competitions. It can integrate deeply with Competitions while remaining reusable by future Xdecaro products.

## Product boundary

Draw owns:

- draw sessions;
- draw participants/entries as imported or referenced inputs;
- pots and seeding configuration;
- draw constraints;
- draw execution;
- assignment of an entry to a group, bracket slot, pairing or other target slot;
- preview, confirmation and publication workflow;
- immutable/traceable draw history after confirmation;
- public live presentation state;
- draw-specific audit events;
- draw-specific frontend presentation and animation.

Draw does not own:

- competitions, seasons, matches, standings or sports rules;
- the authoritative participant/team record of Competitions;
- Forms submissions;
- Membership records;
- Events registrations;
- Documents storage;
- Finance/accounting data;
- generic shared infrastructure that belongs in Xdecaro Core.

## Xdecaro Core integration

Draw is designed to integrate with **Xdecaro Core** from the start.

Core may provide:

- `EntityReference` / `RelationReference` for cross-product references;
- shared Web Asset Manager registration;
- `.xdecaro-*` UI primitives and design tokens;
- responsive/light/dark foundations;
- generic AJAX/CSRF helpers;
- extension/dependency diagnostics;
- common information/update UI;
- generic events/contracts that are truly reusable by multiple products.

Core must never contain Draw-specific algorithms, pots, seeds, draw constraints, reveal sequence, group/bracket logic or public live draw state.

The dependency direction is:

`Draw -> Xdecaro Core`

Never:

`Xdecaro Core -> Draw`

## Competitions integration

Competitions is the first important consumer.

The preferred flow is:

1. Competitions provides a stable reference to the competition and a normalized participant input payload.
2. Draw creates and owns the draw session.
3. Draw validates pots, seeds and constraints server-side.
4. Draw determines the assignment server-side.
5. The frontend only reveals/animates already-authorized draw results.
6. Draw publishes a stable result payload.
7. Competitions imports/applies that result through a public integration boundary and creates or updates its own groups/bracket/pairings.

Draw must never write directly to private Competitions tables.

Competitions must continue to work when Draw is not installed. Manual group/bracket management remains available in Competitions; automatic/live draw features can be hidden or reported as unavailable.

## Public live draw

Draw includes a public-facing live view suitable for website visitors, large screens and projectors.

The live experience may show:

- competition/event title and branding;
- current pot;
- available entries;
- currently revealed entry;
- destination group/slot;
- progressively completed groups or bracket;
- draw progress;
- status: waiting, live, completed;
- final published result.

Typical animation flow:

1. the entry appears prominently in the centre;
2. the entry is revealed;
3. the card remains readable briefly;
4. the card moves visually toward its assigned group/slot;
5. the destination layout updates;
6. the next reveal begins.

Animation is presentation only. It must never calculate or alter the draw result.

Respect `prefers-reduced-motion` and provide a readable non-animated state.

## Draw modes

The architecture must allow multiple modes without coupling the component to one sport:

- groups;
- bracket slots;
- pairings;
- seeded pairings;
- preliminary rounds;
- custom slot assignment where the contract remains generic.

Sport-specific qualification and competition rules remain in Competitions.

## Pots, seeds and constraints

Draw may support:

- pots/fasce;
- seeded entries;
- one or more entries per pot in each group;
- geographical constraints;
- country/zone constraints;
- club/organization constraints;
- explicit allowed/forbidden pairings;
- configurable group sizes;
- deterministic retry/backtracking when a valid assignment exists.

Constraint validation must happen on the server before publication.

The system must detect an impossible configuration and explain it instead of silently weakening constraints.

## Integrity and auditability

A public draw must be reproducible and auditable.

Store enough information to explain the result, including where applicable:

- normalized input set;
- configuration and constraints;
- draw mode;
- execution/reveal sequence;
- actor;
- timestamps;
- status changes;
- result assignments;
- cancellation/restart history;
- algorithm/version identifier;
- cryptographically secure seed/entropy metadata when appropriate, without exposing secrets that would permit manipulation before execution.

Once a draw is confirmed/published, changes must not be silent. Corrections require an explicit audited action.

## Suggested lifecycle

Recommended states:

- `draft`;
- `ready`;
- `live`;
- `completed`;
- `published`;
- `cancelled`.

A restart/re-draw must create an auditable new execution rather than overwriting historical evidence.

## Live transport

Start with a robust Joomla-compatible implementation such as polling/AJAX when sufficient.

The public event model should remain transport-neutral so a future implementation can use Server-Sent Events or WebSockets without redesigning draw-domain state.

Example Draw-owned event names:

- `draw.started`;
- `draw.entry.revealed`;
- `draw.entry.assigned`;
- `draw.pot.completed`;
- `draw.completed`;
- `draw.published`;
- `draw.cancelled`.

These are Draw-domain events, not Core business events.

## Joomla architecture

Target Joomla 4, 5 and 6 where technically possible.

Use modern Joomla APIs:

- namespaces;
- MVC;
- service providers;
- dependency injection where appropriate;
- `DatabaseInterface`;
- Web Asset Manager;
- Language API;
- Form API;
- ACL;
- Joomla events;
- Router for public views.

Use `#__` for all database tables.

## Security

All state-changing actions require server-side authorization.

Check at minimum:

- ACL;
- CSRF tokens;
- input filtering and validation;
- escaped output;
- bound database queries;
- protection against unauthorized start/reveal/confirm/publish/restart operations;
- public/private draw visibility;
- prevention of client-side result manipulation;
- rate limiting or request controls where live endpoints can be abused.

Never trust an entry ID, target slot or result assignment supplied by the browser without server validation.

## UI/UX

Use the Xdecaro shared design language where Core provides it.

Administrator priorities:

- fast setup;
- clear pot/seed visualization;
- immediate validation of impossible constraints;
- preview before going live;
- explicit confirmation for destructive/restart actions;
- clear status and audit history;
- minimal clicks.

Frontend priorities:

- large readable typography;
- responsive groups/bracket layout;
- smooth but restrained animation;
- projector/fullscreen mode;
- accessible keyboard/focus behaviour where controls exist;
- light/dark compatibility;
- reduced-motion support;
- no horizontal overflow on smartphone.

## Planned repository layout

Create implementation directories only when real code is added. The intended structure is:

- `component/` — `com_draw` source;
- `media/` — component assets where not packaged under the component source tree;
- `modules/` — optional public modules only when justified;
- `plugins/` — integration plugins only when a stable event boundary requires them;
- `package/` — package manifest if Draw ships as a package;
- `docs/` — architecture and public integration contracts;
- `tests/` — domain/integration tests;
- `tools/` or `build/` — build tooling;
- `updates/` — Joomla update metadata;
- `dist/` — generated release ZIPs only.

Do not create empty directories prematurely.

## Versioning

Use Semantic Versioning.

The first implementation should begin in the `0.x` series until the public integration contracts and installation/update path are proven.

Do not publish different code under the same version number.

Generated ZIPs must be installable directly through Joomla.

## Implementation order

1. finalize stable component identifier/namespace and manifest;
2. create database schema and safe update path;
3. implement draw session lifecycle and ACL;
4. implement normalized entries/pots/seeds/constraints;
5. implement deterministic server-side assignment engine with validation;
6. implement result/audit event persistence;
7. implement administrator setup/preview/run UI;
8. implement public live/read-only view;
9. implement reveal/move animations with reduced-motion fallback;
10. implement Core integration using existing public APIs;
11. implement optional Competitions adapter through stable public boundaries;
12. add automated tests, build workflow, update server and installable ZIP;
13. verify Joomla 4/5/6, desktop/tablet/smartphone, light/dark and accessibility.

The detailed component implementation belongs in the Draw project; this repository architecture is the source of truth for its product boundary and integrations.

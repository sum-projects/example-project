# Architecture

This document is the source of truth for the architecture rules. `CLAUDE.md` and `.claude/rules/` carry short digests of it and point back here.

## How to read the rules

Every rule has an identifier (`L1`, `C2`) and a status. The letters stand for layers and modules (L), boundaries (B), contracts (C), domain (D), queries and commands (Q), entry points (E) and integration between modules (I); `M1` to `M6` are the roadmap's milestones, not rules.

- **Invariant.** Deviating from it needs the user's approval before the change is made.
- **Default.** A different solution may be chosen when it is better in the situation at hand. Every deviation is reported with its reasoning and recorded as an ADR.

A rule that does not hold up in practice is changed here, through a question to the user and an ADR. It is never worked around silently.

## Style

A modular monolith: one deployable Symfony application, divided into modules that match the bounded contexts, plus a delivery layer for the channels that clients talk to and a minimal shared kernel. Each module is layered hexagonally and applies CQRS. See [ADR 0001](adr/0001-modular-monolith-with-hexagonal-layers.md), [ADR 0006](adr/0006-delivery-layer-for-client-facing-channels.md) and [ADR 0007](adr/0007-contracts-as-the-public-api-of-a-module.md).

## Directory layout

```
src/
  Leads/                     a module; every module has the same structure
    Contracts/               the module's only public API
      Command/               commands exposed to the delivery layer
      Query/                 queries exposed to the delivery layer, with their result DTOs
      LeadsApi.php           facade: narrow synchronous API for other modules
      Dto/                   DTOs of the facade
      Event/                 integration events, versioned
    Domain/                  aggregates, value objects, domain events, exceptions, repository ports
    Application/
      Command/ Query/        private commands and queries, and the handlers of all of them
      EventHandler/          reactions to domain events and to other modules' integration events
    Infrastructure/
      Persistence/           Doctrine repositories, Mapping/ (XML), Migrations/, custom DBAL types
      ReadModel/             DBAL implementations of the read ports
      Messaging/             outbox publishing
      Acl/                   adapters to other modules' facades
    UserInterface/           optional: Http/, Cli/ for the module's technical adapters only
    config/                  the module's services, routing and slice of bundle configuration
  Delivery/                  entry points of the client-facing channels
    Api/ Admin/ Cli/         one directory per channel, created with its first entry point
    config/
  Shared/                    shared kernel
    Domain/                  base domain classes (see the domain model)
    Application/             CommandBus, QueryBus, EventBus interfaces; handler markers
    Infrastructure/          Messenger bus adapters, middleware, clock, health checks
    UserInterface/           technical entry points that belong to no module (the health endpoint)
    config/
tests/
  Unit/ Integration/ Functional/     each mirrors the src/ structure
config/ docker/
```

The namespace root is `App\`. `Shared` and `Delivery` are reserved names; every other directory in `src/` is a module. Directories are created when they get their first class, never ahead of it.

## Modules and layers

| Rule | Status | |
| --- | --- | --- |
| L1 | Invariant | A module is a bounded context and lives in `src/<Module>/`. Its boundary follows business language and processes, not entities or tables. |
| L2 | Invariant | Dependencies point inward, as set out in [Dependency rules](#dependency-rules). Never the other way round. |
| L3 | Invariant | `src/Shared/` is a minimal shared kernel: base domain classes, bus interfaces, technical adapters. It holds no business logic. |
| L4 | Default | A simple CRUD module does not need full DDD inside. Its boundary and its `Contracts` apply all the same. |

### Dependency rules

| Code in | May depend on | Must not contain |
| --- | --- | --- |
| `Contracts` | PHP itself; validation constraints as attributes on commands and queries | Domain types, behaviour |
| `Domain` | `Shared\Domain`, PHP itself | Framework or ORM classes, attributes, I/O |
| `Application` | own `Domain` and `Contracts`; `Shared\Domain`, `Shared\Application`; validation constraints | HTTP, SQL, Doctrine, Messenger, another module |
| `Application\EventHandler` | the same, and other modules' `Contracts\Event` | Another module's facade |
| `Infrastructure` | own `Domain`, `Application` and `Contracts`; `Shared\Domain`, `Shared\Application`, `Shared\Infrastructure`; vendor libraries | Business rules |
| `Infrastructure\Acl` | own `Domain` and `Application`; `Shared\Domain`, `Shared\Application`, `Shared\Infrastructure`; other modules' facade and its DTOs; vendor libraries except Doctrine | Business rules, persistence |
| `UserInterface` | own `Application`, `Contracts\Command` and `Contracts\Query`; `Shared\Domain`, `Shared\Application`, `Shared\Infrastructure`; vendor libraries except Doctrine | Business rules, Domain types, repository or ORM access |
| `Delivery` | every module's `Contracts\Command` and `Contracts\Query`; `Shared\Domain`, `Shared\Application`; vendor libraries except Doctrine | Business rules, module internals, persistence |
| `Shared\Domain` | PHP itself | Business logic |
| `Shared\Application` | `Shared\Domain` | Business logic |
| `Shared\Infrastructure` | `Shared\Domain`, `Shared\Application`; vendor libraries | Business logic |
| `Shared\UserInterface` | `Shared\Domain`, `Shared\Application`, `Shared\Infrastructure`; vendor libraries except Doctrine | Business logic |

PHP's built-in classes are available everywhere. Validation constraints are the `Symfony\Component\Validator\Constraints` namespace, the only vendor code `Contracts` and `Application` may name ([ADR 0009](adr/0009-vendor-free-application-and-contracts.md)). "Doctrine" means the `Doctrine\` namespace and Symfony's Doctrine bridge.

`deptrac.php` encodes this table and fails the build when it is broken. It finds the modules by listing `src/`, so a new module gets the rules without a configuration change. A class under `App\` outside these layers fails the build as soon as it uses, or is used by, any other class of the project or a vendor class.

## Module boundaries

| Rule | Status | |
| --- | --- | --- |
| B1 | Invariant | A module uses another module only through its `Contracts`: the facade, through an adapter in `Infrastructure/Acl/`, and integration events, in handlers in `Application/EventHandler/`. |
| B2 | Invariant | No foreign keys, joins or ORM relations between modules. One module refers to another's data by identifier only. |
| B3 | Invariant | Each module owns its tables and its migrations. |
| B4 | Default | One database schema per module, one connection for the whole application ([ADR 0008](adr/0008-module-owned-schema-migrations-and-configuration.md)). |
| B5 | Invariant | Boundaries are checked by a tool in CI (Deptrac), not by convention alone. |

## Contracts

`Contracts` has three parts, each with its own audience:

| Part | Holds | Used by |
| --- | --- | --- |
| `Contracts/Command/`, `Contracts/Query/` | Use cases exposed outside the module, with the queries' result DTOs | The delivery layer, and the module itself |
| `Contracts/<Module>Api`, `Contracts/Dto/` | The facade interface and its DTOs: a narrow, synchronous API | Other modules, through an ACL adapter |
| `Contracts/Event/` | Integration events with a stable, versioned schema | Other modules' event handlers |

| Rule | Status | |
| --- | --- | --- |
| C1 | Invariant | Other modules use only the facade and the events. Outside the owning module, the commands and queries in `Contracts` are used by the delivery layer and by nothing else. |
| C2 | Invariant | No domain types in `Contracts`: only `readonly` DTOs made of scalars and identifiers. An identifier is carried as a string. |
| C3 | Default | A command or query is placed in `Contracts` only when an entry point outside the module uses it. Otherwise it stays in `Application/`. |
| C4 | Default | The two audiences get separate DTO families. A query's result lives next to the query; the facade's DTOs live in `Contracts/Dto/`; neither refers to the other. |

C4 lets the channel-facing and the module-facing contract change independently, and lets Deptrac enforce C1 exactly instead of approximately.

## Domain

| Rule | Status | |
| --- | --- | --- |
| D1 | Invariant | Zero dependencies on the framework, the ORM or infrastructure. |
| D2 | Default | `final` classes, `readonly` value objects, enums for closed sets, named constructors. State changes only through methods that express business intent; no setters. |
| D3 | Default | Small aggregates that enforce their invariants inside. One aggregate refers to another by identifier. |
| D4 | Default | The identifier is a value object, a UUID v7 generated before persistence ([ADR 0005](adr/0005-application-generated-uuid-v7.md)). |
| D5 | Default | Time comes from the `Clock` port of the shared kernel. Aggregates carry a version for optimistic locking. |
| D6 | Default | The aggregate records its domain events. They are published after the write, in the same transaction. |

The domain model, with every invariant and transition, is in [02-domain-model.md](02-domain-model.md).

## CQRS

Three Symfony Messenger buses, hidden behind the interfaces in `Shared\Application`. See [ADR 0002](adr/0002-cqrs-on-symfony-messenger.md).

| Bus | Purpose | Middleware | Handlers |
| --- | --- | --- | --- |
| `command.bus` | Change state | validation, Doctrine transaction, domain event dispatch | Exactly one per command, returns `void` |
| `query.bus` | Read state | validation, no transaction | Exactly one per query, returns a read DTO |
| `event.bus` | Announce facts | none required | Zero or more per event |

| Rule | Status | |
| --- | --- | --- |
| Q1 | Invariant | A query never changes state. |
| Q2 | Default | `Application` depends on the `CommandBus`, `QueryBus` and `EventBus` interfaces from `Shared`, not on Messenger. A handler joins its bus by implementing a marker interface, not by attribute ([ADR 0009](adr/0009-vendor-free-application-and-contracts.md)). |
| Q3 | Default | A command returns nothing. One command is one transaction and changes one aggregate. |
| Q4 | Default | A query handler reads through DBAL straight into a `readonly` DTO and bypasses the aggregates. |
| Q5 | Default | Writes and reads use the same database. A separate read model, a projection built from events, is introduced only for a concrete need. |
| Q6 | Default | Event sourcing is used only where the history of changes is a business requirement. It is decided per module, after asking the user. |

### Write side

1. The entry point builds an immutable command and dispatches it.
2. The handler loads one aggregate through its repository port, calls one intention-revealing method, and saves it.
3. The transaction middleware commits. Domain events recorded by the aggregate are collected and dispatched inside the same transaction.

Handlers orchestrate; they do not decide. A conditional on aggregate state in a handler is a rule that belongs in the aggregate.

### Read side

`Application` defines the read port (for example `LeadReadModel`); `Infrastructure/ReadModel` implements it with SQL against the module's own tables and maps rows to immutable DTOs. Lists are paginated: the result DTO carries the items together with `page`, `perPage` and `total`. A result exposed in `Contracts` declares that page shape itself, because `Contracts` may not name a type from `Shared`.

## Entry points

An entry point is where the outside world reaches a use case: an HTTP controller, a console command, a scheduler task, a webhook receiver. It has two possible homes. Both are valid, and each has situations where it is the better one. See [ADR 0006](adr/0006-delivery-layer-for-client-facing-channels.md).

**Variant A: the delivery layer, `src/Delivery/<Channel>/`.** Controllers, request and response DTOs, and the mappers that assemble a response.

- Strengths: data from several modules can be composed; a channel's conventions (authorization, versioning, error format, OpenAPI) live in one place; modules are tested against their contract, without HTTP.
- Weaknesses: the commands and queries a channel uses become public; a feature needs changes in two places.

**Variant B: the module, `src/<Module>/UserInterface/`.** The entry point is part of the module, and its commands and queries stay private.

- Strengths: full encapsulation and autonomy; the whole feature in one place; the module is easy to extract together with its API.
- Weaknesses: no natural place for composition across modules; channel conventions scattered over the modules.

### Selection rule

| Rule | Status | |
| --- | --- | --- |
| E1 | Invariant | The home of an entry point is decided by the steps below, in order. |

1. It composes data or triggers use cases from more than one module: the delivery layer.
2. It belongs to a channel with its own contract towards a client (frontend API, admin API, partner API): `Delivery/<Channel>/`, even when it touches a single module.
3. It is a technical adapter of one module (a webhook from an external system that this module integrates with, a maintenance console command of the module, a scheduler task of the module): `<Module>/UserInterface/`.
4. It is a technical entry point that belongs to no module, such as the health endpoint: `Shared/UserInterface/`.
5. No clear fit: ask the user.

The REST API described in [04-api.md](04-api.md) is the `Api` channel, so all of its endpoints fall under step 2.

Moving the whole project to variant B makes sense when modules get separate teams, when endpoints rarely cross module boundaries, or when a module is to be extracted into its own service together with its API. That is a project-level decision, made together with the user and recorded as an ADR.

### Rules of the delivery layer

| Rule | Status | |
| --- | --- | --- |
| E2 | Invariant | Of a module it sees only the commands and queries in `Contracts`, never the facade, the events or anything inside the module. Modules never depend on it. |
| E3 | Invariant | No business logic: only the shape of the input, coarse-grained authorization, mapping and composition. |
| E4 | Invariant | One endpoint dispatches at most one command. A write that spans several modules is carried out by events or by a process manager inside a module. |
| E5 | Invariant | Reads are composed only by identifier and in batches (for example `GetCustomersByIds`). |
| E6 | Invariant | A list that filters, sorts or paginates over data from several modules needs a read model in one module, not composition in the delivery layer. |
| E7 | Invariant | A mapper in the delivery layer translates contract DTOs into the channel's response. A module does not know the shape of an API response. |

Besides `Contracts`, the delivery layer uses the shared kernel: it dispatches through the bus interfaces and recognises failures by the shared failure types (see [Error handling](#error-handling)).

## Communication between modules

| Rule | Status | |
| --- | --- | --- |
| I1 | Default | Synchronous: the facade from `Contracts`. The owning module implements it in its `Application` layer; the consumer wraps it in an adapter in `Infrastructure/Acl/` behind a port of its own. No call chains: a facade implementation never calls another module. |
| I2 | Default | The facade reads. A side effect in another module travels asynchronously: private domain event, public integration event, outbox, asynchronous transport. |
| I3 | Invariant | The integration event is stored in the same transaction as the aggregate change (transactional outbox, [ADR 0003](adr/0003-transactional-outbox-for-integration-events.md)). |
| I4 | Invariant | Consumers are idempotent and tolerate messages that arrive out of order. |
| I5 | Invariant | No transaction spans several modules. Consistency between modules is eventual. |

I2 protects I5: a facade call runs on the caller's connection, so a facade that wrote from inside a command would pull another module's change into the caller's transaction. A deviation from I2 has to show that I5 still holds.

### Two kinds of event

| | Domain event | Integration event |
| --- | --- | --- |
| Audience | Own module | Other modules |
| Location | `<Module>/Domain/Event` | `<Module>/Contracts/Event` |
| Content | Value objects | Scalars only, versioned by class name |
| Delivery | Synchronous, same transaction | Asynchronous, through the outbox |

### Flow of a lead conversion

```
POST /api/v1/leads/{id}/convert        Delivery/Api controller
  └─ ConvertLead (Leads/Contracts/Command) ─► ConvertLeadHandler ─► Lead::convert() records LeadConverted
       └─ same transaction:
            Leads EventHandler maps LeadConverted ─► LeadConvertedV1, stored in the outbox table
       └─ commit
  ◄─ 202 Accepted with customerId and dealId

worker (messenger:consume outbox)
  ├─ Customers: RegisterCustomerOnLeadConverted ─► RegisterCustomerFromLead command
  └─ Sales:     OpenDealOnLeadConverted         ─► OpenDeal command
```

The outbox is a Messenger Doctrine transport on the application's database connection, so the event is stored atomically with the aggregate change. Delivery is at-least-once; failed messages are retried and then moved to a failure transport.

## Persistence

- PostgreSQL with Doctrine ORM for aggregates. Mapping is XML in `Infrastructure/Persistence/Mapping`, so Domain classes carry no ORM metadata ([ADR 0004](adr/0004-doctrine-xml-mapping.md)).
- Value objects map as embeddables or custom DBAL types.
- A module's tables live in its own schema, named after the module in snake case (`leads.lead`, `sales.deal`). Tables that belong to no module are owned by `Shared` on the same terms.
- A module's migrations live in `Infrastructure/Persistence/Migrations`, in that namespace, and touch only its own schema. A migration never relies on another module's migration having run: across modules the order of execution is alphabetical by namespace, not chronological.
- Aggregates carry a version column for optimistic locking.
- Repositories expose only what the write side needs: `get(Id)`, `save(Aggregate)`, and the occasional existence check. `get` throws a not-found domain exception.

## Module configuration

Every top-level directory of `src/` wires itself. Its `config/` mirrors the application's:

| File | Holds |
| --- | --- |
| `config/services.yaml` | The services of its `Application`, `Infrastructure` and `UserInterface` classes. `Domain`, `Contracts` and the migrations are not services. |
| `config/routes.yaml` | The import of its own controllers. A channel sets its path and name prefixes here. |
| `config/packages/*.yaml` | Its slice of bundle configuration: its Doctrine mapping, its migrations path, the routing of its messages. |

The application's `config/services.yaml` and `config/routes.yaml` only import these files by pattern. `config/packages/` keeps what is truly application-wide.

The file names are exact, because a file the pattern does not match is skipped without an error. There is no `services.php`, no `services_test.yaml` and no `packages/test/` in a module: a setting for one environment goes under `when@<env>` inside the file. A test fails when a `config/` directory holds any other file or lacks `services.yaml`. A directory that serves routes needs its `routes.yaml`; without it its controllers answer 404. Route names are unique across the application, so a directory prefixes its own.

## Identifiers

UUID v7, generated at the entry point before a command is dispatched and passed in the command as a string ([ADR 0005](adr/0005-application-generated-uuid-v7.md)). This keeps commands free of return values and lets the API answer with the new resource's location immediately.

## Error handling

- A domain rule violation throws a specific exception. Each one extends a failure type of the shared kernel and carries a stable, machine-readable code such as `lead-not-qualified`.
- A failure crosses a module boundary only as those shared types. The delivery layer never names a module's exception class.
- Each channel decides how a failure looks to its client. The `Api` channel has one listener that converts failures to RFC 9457 problem details: the failure type gives the status and the code gives the problem `type`, as described in the [API document](04-api.md). Controllers contain no `try`/`catch`.

## Validation

Two levels with different jobs:

- **Input validation** (shape, types, formats). Constraints are attributes on the command or query and are checked by the bus, so they hold for every entry point, not only for HTTP. A request DTO in an entry point checks only what the channel adds, such as how the payload is read. Failures return 422.
- **Invariants** in value objects and aggregates. Failures return 409 or 422 depending on the rule. The domain never relies on input validation having run.

## Enforcement

Every rule should be enforced automatically where that is possible. This table is the honest state; a rule listed under "Review" is checked by the `code-reviewer` agent or a human and by nothing else.

| Enforced by | Rules |
| --- | --- |
| Deptrac (`make deptrac`, part of `make qa` and CI) | L2, L3 in part (what `Shared` may import), B1, B5, C1, C4, D1, Q2, E2, I1 in part (only ACL adapters reach a facade) |
| `ModuleConfigurationTest` | Every top-level directory of `src/` has its `config/services.yaml` and no configuration file the application would skip |
| PHP-CS-Fixer, Rector, PHPStan | Language-level coding rules; see [06-quality-standards.md](06-quality-standards.md) |
| Unit tests | Domain invariants; idempotency of consumers (I4), one test per consumer |
| Review | L1, L4, B2, B3, B4, C2, C3, D2 to D6, Q1, Q3 to Q6, E1, E3 to E7, I2, I3, I5 |

`make migration MODULE=` limits a generated diff to the module's schema. That keeps a correct mapping's migration clean; it does not check that the mapping puts a table in the right schema, so B3 and B4 stay under review.

Which of the reviewed rules a tool could check, and what that needs, is tracked in [06-quality-standards.md](06-quality-standards.md#architecture-rules-without-automatic-enforcement).

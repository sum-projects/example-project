# Architecture

## Style

A modular monolith: one deployable Symfony application, internally divided into modules that match the bounded contexts. Each module follows a hexagonal (ports and adapters) layering and applies CQRS. See [ADR 0001](adr/0001-modular-monolith-with-hexagonal-layers.md).

## Directory layout

```
src/
  Leads/
    Domain/            Lead, value objects, events, exceptions, LeadRepository (interface)
    Application/
      Command/         CaptureLead + CaptureLeadHandler, ...
      Query/           GetLead + GetLeadHandler, LeadView (read DTO), ...
      EventHandler/    reactions to domain events inside the module
    Infrastructure/
      Persistence/     Doctrine repository, XML mappings, custom DBAL types
      ReadModel/       DBAL implementations of query handlers' ports
      Messaging/       outbox publisher, integration event mapping
    UserInterface/
      Http/            invokable controllers, request DTOs
    Contract/          integration events published by this module
  Customers/           same structure
  Sales/               same structure
  Shared/
    Domain/            shared kernel (see domain model)
    Application/       CommandBus, QueryBus, EventBus interfaces; Command, Query markers
    Infrastructure/    Messenger bus adapters, middleware, problem-details listener, clock
tests/
  Unit/ Integration/ Functional/     mirror the src/ structure
config/ migrations/ docker/
```

Namespace root is `App\`.

## Layer rules

| Layer | May depend on | Must not contain |
| --- | --- | --- |
| Domain | `Shared\Domain`, PHP itself | Framework or ORM classes, attributes, I/O, other modules |
| Application | own Domain, `Contract` namespaces, `Shared\Application`, `Shared\Domain` | HTTP, SQL, Doctrine, other modules' internals |
| Infrastructure | everything in its own module, `Shared`, other modules' `Contract`, vendor libraries | Business rules |
| UserInterface | own Application, `Shared`, vendor libraries | Business rules, direct repository or ORM access |
| Contract | nothing (scalars only) | Domain types |

Across modules, the only allowed import is another module's `Contract` namespace. `Contract` namespaces are imported by Application, which maps domain events to its own module's integration events and reacts to those of other modules, and by Infrastructure; Domain never sees them. Vendor code is reachable only from Infrastructure and UserInterface, while PHP's built-in classes are available to every layer.

These rules are encoded in `deptrac.yaml`, one layer per module and layer, and fail the build when broken. A new module needs its layers and rules added there.

## CQRS

Three Symfony Messenger buses, hidden behind the interfaces in `Shared\Application` so that Application code never imports Messenger. See [ADR 0002](adr/0002-cqrs-on-symfony-messenger.md).

| Bus | Purpose | Middleware | Handlers |
| --- | --- | --- | --- |
| `command.bus` | Change state | validation, Doctrine transaction, domain event dispatch | Exactly one per command, returns `void` |
| `query.bus` | Read state | validation | Exactly one per query, returns a read DTO |
| `event.bus` | Announce facts | none required | Zero or more per event |

### Write side

1. The controller builds an immutable command from the request and dispatches it.
2. The handler loads one aggregate through its repository interface, calls one intention-revealing method, and saves it.
3. The transaction middleware commits. Domain events recorded by the aggregate are collected and dispatched inside the same transaction.

Handlers orchestrate; they do not decide. A conditional on aggregate state in a handler is a rule that belongs in the aggregate.

### Read side

Query handlers bypass the aggregates. They run SQL through Doctrine DBAL against the same database and map rows to immutable view DTOs (`LeadView`, `DealListItem`). The Application layer defines the port (for example `LeadReadModel`); Infrastructure implements it. Lists are paginated and return a `Page<T>`.

There are no separate projection tables: the read side queries the write tables. Projections can be introduced per query later without touching the write side.

## Events

Two kinds, kept strictly apart:

| | Domain event | Integration event |
| --- | --- | --- |
| Audience | Own module | Other modules |
| Location | `<Module>/Domain/Event` | `<Module>/Contract` |
| Content | Value objects | Scalars only, versioned |
| Delivery | Synchronous, same transaction | Asynchronous, through the outbox |

### Flow of a lead conversion

```
POST /leads/{id}/convert
  └─ ConvertLead ─► ConvertLeadHandler ─► Lead::convert() records LeadConverted
       └─ same transaction:
            Leads EventHandler maps LeadConverted ─► LeadConvertedV1, stored in the outbox table
       └─ commit
  ◄─ 202 Accepted with customerId and dealId

worker (messenger:consume outbox)
  ├─ Customers: RegisterCustomerOnLeadConverted ─► RegisterCustomerFromLead command
  └─ Sales:     OpenDealOnLeadConverted         ─► OpenDeal command
```

The outbox is a Messenger Doctrine transport sharing the application's database connection, so the event is stored atomically with the aggregate change. Delivery is at-least-once; consumers are idempotent and failed messages are retried and then moved to a failure transport. See [ADR 0003](adr/0003-transactional-outbox-for-integration-events.md).

## Persistence

- PostgreSQL with Doctrine ORM for aggregates. Mapping is XML in `Infrastructure/Persistence/Mapping` so Domain classes carry no ORM metadata ([ADR 0004](adr/0004-doctrine-xml-mapping.md)).
- Value objects map as embeddables or custom DBAL types.
- Each module owns its tables, prefixed with the module name (`leads_lead`, `sales_deal`). No foreign keys across modules.
- Aggregates carry a version column for optimistic locking.
- Repositories expose only what the write side needs: `get(Id)`, `save(Aggregate)`, and the occasional existence check. `get` throws a not-found domain exception.

## Identifiers

UUID v7, generated by the application before a command is dispatched ([ADR 0005](adr/0005-application-generated-uuid-v7.md)). This keeps commands free of return values and lets the API answer with the new resource's location immediately.

## Error handling

- Domain rule violations throw exceptions extending a module-level `DomainException`.
- Not-found cases throw dedicated exceptions.
- A single listener in `Shared\Infrastructure` converts exceptions to RFC 9457 problem details; the mapping to status codes is described in the [API document](04-api.md). Controllers contain no `try`/`catch`.

## Validation

Two levels with different jobs:

- **Input validation** (shape, types, formats) on request DTOs with Symfony Validator. Failures return 422.
- **Invariants** in value objects and aggregates. Failures return 409 or 422 depending on the rule. The domain never relies on input validation having run.

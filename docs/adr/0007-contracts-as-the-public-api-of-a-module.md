# 0007. Contracts as the public API of a module

Status: Accepted (2026-11-14). Supersedes [0001](0001-modular-monolith-with-hexagonal-layers.md) in part: what a module exposes and how modules reach each other.

## Context

Under ADR 0001 a module's public surface was a `Contract` namespace that held integration events only, and modules never called each other. Two things changed. The delivery layer ([ADR 0006](0006-delivery-layer-for-client-facing-channels.md)) needs commands and queries it can dispatch without reaching into a module. And a module that needs a fact owned by another module had only one way to get it: keep its own copy, fed by events, even for a simple read.

## Decision

Rename the namespace to `Contracts` and give it three parts, each with one audience:

| Part | Audience |
| --- | --- |
| `Contracts/Command/`, `Contracts/Query/`: use cases with the queries' result DTOs | Outside the module, the delivery layer only |
| `Contracts/<Module>Api` and `Contracts/Dto/`: a narrow synchronous facade | Other modules, through an adapter in `Infrastructure/Acl/` |
| `Contracts/Event/`: versioned integration events | Other modules' handlers in `Application/EventHandler/` |

`Contracts` holds no domain types, only `readonly` DTOs made of scalars and identifiers. The two DTO families are kept apart: a query result never refers to a facade DTO, nor the other way round.

The facade reads. A side effect in another module still travels as an integration event through the outbox ([ADR 0003](0003-transactional-outbox-for-integration-events.md)), because a facade call runs on the caller's connection and a write there would join the caller's transaction. A facade implementation never calls another module.

A command or query moves to `Contracts` only when an entry point outside the module uses it.

## Consequences

- A module can ask another for a fact without maintaining a copy of it. The price is temporal coupling for that read: the consumer's use case fails when the owner's facade fails.
- The consumer's `Application` layer stays unaware of the other module: it depends on a port of its own, and only the ACL adapter knows the facade and its DTOs.
- Each audience is a separate Deptrac layer, so the rule "modules use the facade and events, the delivery layer uses commands and queries" is checked on every build. Another module's event may be named only in `Application/EventHandler/`, its facade only in `Infrastructure/Acl/`.
- The public surface is larger than before and has to be kept narrow by review. Two DTO families occasionally describe the same data twice.

## Alternatives considered

- **Integration events only (ADR 0001).** Rejected as the only channel: it forces a local copy for every read, however small. It remains the preferred way to cause a side effect.
- **One DTO family shared by the facade and the queries.** Rejected: the channel-facing and the module-facing contract would have to change together, and the audiences could no longer be told apart by a tool.
- **Calling another module's command or query bus directly.** Rejected: it exposes every use case to every module and hides the dependency from static analysis.

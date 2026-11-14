# 0001. Modular monolith with hexagonal layers

Status: Accepted (2026-11-14)

## Context

The domain has three bounded contexts with clear upstream and downstream relationships. The project must demonstrate strict boundaries, but it is small, has one team, and has no independent scaling or deployment needs.

## Decision

Build one Symfony application divided into modules, one per bounded context, plus a minimal shared kernel. Inside each module use four layers (Domain, Application, Infrastructure, UserInterface) with dependencies pointing inward, and a `Contract` namespace as the module's only public surface. Enforce both sets of rules with Deptrac.

## Consequences

- Boundaries are as strict as in separate services, at the operational cost of one deployable.
- A module can later be extracted into a service: its only coupling to others is asynchronous integration events.
- More directories and classes than a layered CRUD application of the same size.
- Discipline depends on tooling, so Deptrac is part of the mandatory quality gate.

## Alternatives considered

- **Microservices.** Rejected: distributed-system cost with no benefit at this scale.
- **Single layered application without modules.** Rejected: the contexts would blur and the project would not demonstrate strategic DDD.

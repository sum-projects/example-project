# 0002. CQRS on Symfony Messenger with a shared database

Status: Accepted (2026-11-14)

## Context

Writes must go through aggregates that protect invariants. Reads need flat, filterable, paginated data and gain nothing from loading aggregates. The project should show CQRS without the operational weight of separate read stores.

## Decision

Separate commands and queries at the code level:

- Three Messenger buses (`command.bus`, `query.bus`, `event.bus`) behind interfaces owned by `Shared\Application`.
- Command handlers return nothing and run in a Doctrine transaction supplied by middleware.
- Query handlers return immutable view DTOs produced by DBAL SQL against the same database and tables as the write side.

## Consequences

- Read and write models evolve independently in code; reads are always consistent with writes because they share tables.
- Application code is not coupled to Messenger and can be unit tested with trivial bus doubles.
- Read SQL depends on the write schema, so a mapping change may require updating queries. Integration tests on read models catch this.
- Dedicated projections can be added for a single query later without changing the write side.

## Alternatives considered

- **Separate read database with projections.** Rejected for now: eventual consistency on every read and projection rebuild tooling are not justified by the scope.
- **Reading through ORM entities.** Rejected: leaks aggregates to the API and invites business logic in read paths.
- **Event sourcing.** Rejected: valuable for audit-heavy domains, out of proportion here.

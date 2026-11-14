# 0003. Transactional outbox for integration events

Status: Accepted (2026-11-14). Its premise that modules may not call each other is refined by [0007](0007-contracts-as-the-public-api-of-a-module.md): a module may read another through a facade, while every side effect still travels through the outbox.

## Context

Converting a lead must result in a customer and a deal in two other modules. Modules may not call each other, and changing three aggregates in one transaction would violate the one-aggregate-per-transaction rule. Publishing an event after commit risks losing it; publishing before commit risks announcing something that never happened.

## Decision

Integration events are written to an outbox in the same database transaction as the aggregate change, using a Messenger Doctrine transport on the application's connection. A worker process consumes the outbox and invokes the subscribing modules' handlers. Delivery is at-least-once, so every consumer is idempotent; identifiers of the resources to create are carried in the event to make that straightforward.

## Consequences

- An event is published if and only if the state change committed.
- Cross-module effects are eventually consistent. The API makes this explicit by answering `202 Accepted` for conversion.
- A worker process must run; failures are retried and then parked in a failure transport for inspection.
- Replacing the transport with a message broker later changes only configuration and the worker, not module code.

## Alternatives considered

- **Synchronous in-process event handling across modules.** Rejected: couples the modules' transactions and failure modes.
- **Dispatching to a broker after commit.** Rejected: events can be lost between commit and publish.
- **A message broker from the start.** Rejected: extra infrastructure that the outbox would still be needed in front of.

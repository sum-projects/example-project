# Architecture decision records

| # | Decision | Status |
| --- | --- | --- |
| [0001](0001-modular-monolith-with-hexagonal-layers.md) | Modular monolith with hexagonal layers | Accepted; superseded in part by 0006 and 0007 |
| [0002](0002-cqrs-on-symfony-messenger.md) | CQRS on Symfony Messenger with a shared database | Accepted |
| [0003](0003-transactional-outbox-for-integration-events.md) | Transactional outbox for integration events | Accepted; premise refined by 0007 |
| [0004](0004-doctrine-xml-mapping.md) | Doctrine XML mapping | Accepted |
| [0005](0005-application-generated-uuid-v7.md) | Application-generated UUID v7 identifiers | Accepted |
| [0006](0006-delivery-layer-for-client-facing-channels.md) | Delivery layer for client-facing channels | Accepted |
| [0007](0007-contracts-as-the-public-api-of-a-module.md) | Contracts as the public API of a module | Accepted |
| [0008](0008-module-owned-schema-migrations-and-configuration.md) | Module-owned schema, migrations and configuration | Accepted |
| [0009](0009-vendor-free-application-and-contracts.md) | Vendor-free Application and Contracts, with one exception for validation constraints | Accepted |

## When to write one

- A decision that has a real alternative.
- Every deviation from a rule marked Default in the [architecture document](../03-architecture.md), with the reasoning.
- Every change to a rule marked Invariant, after the user approved it.

## Format

Each record has: Status, Context, Decision, Consequences, Alternatives considered. Records are short and numbered sequentially. The body of a record is never edited after acceptance; a changed decision is a new record that supersedes the old one, and the only line that changes in the old record is its Status, which then names the record that replaces it.

Where an older record and a newer one disagree, the newer one holds. The current rules are always the ones in the [architecture document](../03-architecture.md).

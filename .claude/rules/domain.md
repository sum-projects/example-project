---
paths:
  - "src/*/Domain/**"
---

# Domain layer

Digest for these paths. The binding text is `docs/03-architecture.md` ("Domain", "Dependency rules") and `docs/02-domain-model.md`.

- **D1, invariant.** Import only `App\Shared\Domain` and PHP itself (`Shared\Domain` imports only PHP). No Symfony, no Doctrine, no attributes, no other module, and not the module's own `Application`, `Contracts` or `Infrastructure`.
- Use the words of the ubiquitous language in `docs/02-domain-model.md`. A new concept is added there first.
- **D2.** `final` classes, `final readonly` value objects, enums for closed sets, named constructors, no setters. State changes only through methods that express business intent and guard the invariants.
- **D3.** Small aggregates; another aggregate is referenced by its identifier.
- **D4.** The identifier is a value object (UUID v7) that exists before persistence.
- **D5.** "Now" comes from the `Clock` port, never from `new DateTimeImmutable()`. Aggregates carry a version for optimistic locking.
- **D6.** A state change records a domain event; a no-op records none.
- A broken rule throws a specific exception with a named constructor. It extends a failure type of the shared kernel and carries a stable code.
- Every invariant and transition has a unit test for the allowed path and for each rejected path, in `tests/Unit/<Module>/Domain`, without the kernel and without mocks.
- Persistence mapping is XML in `Infrastructure/Persistence/Mapping`: a new or changed property needs the mapping change in the same task.

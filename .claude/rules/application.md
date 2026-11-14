---
paths:
  - "src/*/Application/**"
---

# Application layer

Digest for these paths. The binding text is `docs/03-architecture.md` ("CQRS", "Dependency rules", "Communication between modules").

- **L2, invariant.** Import only the module's own `Domain` and `Contracts`, `App\Shared\Domain` and `App\Shared\Application`. No HTTP, SQL, Doctrine or Messenger. Another module's `Contracts\Event` may be named in `EventHandler/` and nowhere else.
- The only vendor namespace allowed is `Symfony\Component\Validator\Constraints`, as attributes on commands and queries (ADR 0009).
- **Q2.** Dispatch through the `CommandBus`, `QueryBus` and `EventBus` interfaces. A handler joins its bus by implementing `CommandHandler`, `QueryHandler` or `EventHandler`; do not use `#[AsMessageHandler]`.
- **Q3.** A command handler returns `void`, loads one aggregate, calls one method, saves it. A condition on aggregate state belongs in the aggregate, not in the handler.
- **Q1, invariant.** A query never changes state. **Q4.** A query handler returns a `readonly` DTO through a read port defined here and implemented in `Infrastructure/ReadModel`; it never loads an aggregate.
- **C3.** A command or query stays here unless an entry point outside the module uses it; then it lives in `Contracts`.
- **B1, invariant.** Another module's integration event is handled in `EventHandler/`. Another module's facade is never named here: define a port of your own and let `Infrastructure/Acl/` implement it.
- **I4, invariant.** A handler of an integration event is idempotent and tolerates events that arrive out of order; it has a unit test that handles the same event twice.
- The facade from the module's `Contracts` is implemented here. It reads only, and it never calls another module (I1, I2).
- Handlers are unit tested with in-memory repositories and a fixed clock, in `tests/Unit/<Module>/Application`.

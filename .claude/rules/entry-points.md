---
paths:
  - "src/Delivery/**"
  - "src/*/UserInterface/**"
---

# Entry points

Digest for these paths. The binding text is `docs/03-architecture.md` ("Entry points"), `docs/04-api.md` and ADR 0006.

Before adding an entry point, place it with the selection rule (**E1**): several modules or a client-facing channel means `src/Delivery/<Channel>/`; a technical adapter of one module means `src/<Module>/UserInterface/`; a technical entry point of no module means `src/Shared/UserInterface/`; no clear fit means asking the user.

In the delivery layer, all invariants:

- **E2.** Of a module, import only `Contracts\Command` and `Contracts\Query`. Never its `Application`, `Domain`, `Infrastructure`, facade or events. Besides that: `App\Shared\Domain`, `App\Shared\Application` and vendor code; no Doctrine.
- **E3.** No business logic: only the shape of the input, coarse-grained authorization, mapping and composition.
- **E4.** One endpoint dispatches at most one command. A write that spans modules is done by events or by a process manager inside a module.
- **E5.** Compose reads only by identifier and in batches.
- **E6.** A list that filters, sorts or paginates over several modules needs a read model in one module.
- **E7.** A mapper here turns contract DTOs into the channel's response. A module never knows the response shape.

In a module's `UserInterface`: import the module's own `Application`, `Contracts\Command` and `Contracts\Query`, never its `Domain` or `Infrastructure`.

In the delivery layer and in a module's `UserInterface`, an entry point runs a use case:

- A controller is invokable, one per endpoint: build the command or query, dispatch it on the bus, shape the response. No repository access and no `try`/`catch`; failures are converted centrally by the channel.
- Identifiers of new resources are generated here, before the command is dispatched (ADR 0005).
- Constraints on what a command accepts are declared on the command, not repeated on the request DTO.

In `src/Shared/UserInterface`: technical entry points of no module, such as the health endpoint. There is no use case to dispatch; they call the technical adapters in `Shared\Infrastructure` directly and hold no business logic.

Everywhere: routes are imported in the owning directory's `config/routes.yaml`, where a channel sets its prefix. Without that file the controllers answer 404.

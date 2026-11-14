---
paths:
  - "src/*/Infrastructure/**"
  - "src/*/config/**"
  - "config/**"
---

# Infrastructure layer and configuration

Digest for these paths. The binding text is `docs/03-architecture.md` ("Persistence", "Module configuration", "Communication between modules") and `docs/05-infrastructure.md`.

- No business rules here. An adapter that needs a rule is missing a port or a domain method: report it instead of adding the rule.
- Doctrine mapping is XML in `Persistence/Mapping` (ADR 0004). Never put ORM attributes on a Domain class.
- **B2, invariant.** No foreign key, join or ORM association that reaches another module's tables; identifiers only.
- **B3, B4.** The module's tables live in its own schema, named after the module in snake case. Its migrations live in `Persistence/Migrations`, touch only that schema, and never rely on another module's migration. Generate one with `make migration MODULE=<Module>`, then review it by hand; add the `DROP SCHEMA` that the generated `down()` of a first migration lacks.
- A repository implements the Domain port and nothing more. A read model implements the Application read port with DBAL and returns DTOs; it never hydrates an aggregate.
- **B1, invariant.** Within `Infrastructure`, only `Acl/` may name another module, and only its facade and the facade's DTOs. The adapter implements a port owned by this module and translates into this module's types. It does not touch persistence.
- **I3, invariant.** An integration event goes to the outbox inside the command's transaction, on the application's connection.
- A module wires itself in `src/<Module>/config/`: `services.yaml` (its `Application`, `Infrastructure` and `UserInterface` classes; not `Domain`, `Contracts` or the migrations), `routes.yaml`, and `packages/*.yaml` for its Doctrine mapping, its migrations path and the routing of its messages. The application's `config/` imports these by pattern; do not register a module's services or routes there. The file names are exact: any other file (`services.php`, `services_test.yaml`, `packages/test/`) is skipped without an error, so a setting for one environment goes under `when@<env>` inside the file.
- Do not add a dependency, a service or an infrastructure component that the docs do not mention without asking the user.

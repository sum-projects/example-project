---
paths:
  - "src/*/Contracts/**"
---

# Contracts

Digest for these paths. The binding text is `docs/03-architecture.md` ("Contracts") and ADR 0007. Everything here is public: a change can break another module or a channel.

- **C2, invariant.** No domain types. Only `final readonly` DTOs made of scalars and identifiers, with identifiers as strings. No behaviour.
- Import nothing but PHP itself. The one exception: `Symfony\Component\Validator\Constraints` attributes on commands and queries (ADR 0009).
- **C1, invariant.** Each part has one audience:
  - `Command/`, `Query/` (queries with their result DTOs): outside this module, the delivery layer only.
  - A paginated result declares its own page fields (`items`, `page`, `perPage`, `total`); nothing here may name a type from `Shared`.
  - `<Module>Api` and `Dto/`: other modules, through an adapter in their `Infrastructure/Acl/`.
  - `Event/`: other modules' handlers in `Application/EventHandler/`.
- **C4.** A query result and a facade DTO never refer to each other, even when they describe the same data.
- **C3.** Add a command or query here only when an entry point outside the module uses it.
- An integration event contains scalars only and carries its version in the class name (`LeadConvertedV1`). A published event is never changed; a different payload is a new version.
- The facade is narrow and reads only. A side effect in another module is an integration event (I2).
- Adding to `Contracts` widens the public surface: say so in the feedback.

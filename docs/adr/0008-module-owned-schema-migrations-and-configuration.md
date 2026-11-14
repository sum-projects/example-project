# 0008. Module-owned schema, migrations and configuration

Status: Accepted (2026-11-14)

## Context

The first version of the documentation kept all tables in one schema with a module prefix (`leads_lead`), all migrations in one root `migrations/` directory, and registered every class under `src/` as a service from the application's `config/services.yaml`. A module then owned its tables by naming convention only, and its wiring lived outside it. No ADR recorded these choices.

## Decision

A module owns everything that makes it run:

- **Schema.** Its tables live in a PostgreSQL schema named after the module in snake case (`leads.lead`). The application keeps one database and one connection.
- **Migrations.** They live in `src/<Module>/Infrastructure/Persistence/Migrations`, in the matching namespace, and touch only the module's schema. `make migration MODULE=<Module>` generates one, with the diff limited to that schema.
- **Configuration.** `src/<Module>/config/` mirrors the application's `config/`: `services.yaml`, `routes.yaml` and `packages/*.yaml` for the module's slice of bundle configuration (its Doctrine mapping, its migrations path, the routing of its messages). The application's `config/services.yaml` and `config/routes.yaml` import these files by pattern and register nothing themselves.

`Shared` and `Delivery` are wired the same way. Tables that belong to no module are owned by `Shared`.

## Consequences

- The boundary is visible in the database, so a foreign key that crosses it can be found with a query on the catalogue, and a module can be moved out with its schema.
- Adding a module needs no change to the application's configuration, and removing one leaves nothing behind.
- Doctrine Migrations runs versions in alphabetical order of their class names. Across modules that is the order of the namespaces, not of time, so a migration must never rely on another module's migration. The rule that modules share no foreign keys makes this hold.
- The generated `down()` does not drop the schema that `up()` created; the first migration of a module is completed by hand.
- This was checked against the installed versions (Doctrine ORM 3.8, DBAL 4.5, Migrations 3.9, PostgreSQL 17) with a throwaway module: the schema is created by the generated migration, the filtered diff sees only the module's tables, and `doctrine:schema:validate` reports the schema in sync. Placing the Messenger transport table in a schema of `Shared` is not verified yet and is checked when the outbox arrives.

## Alternatives considered

- **One schema with table prefixes.** Rejected: the simplest for the tooling, but ownership is a naming convention that nothing can check.
- **A database or a connection per module.** Rejected: the transactional outbox needs the event and the aggregate change on one connection, and the project has no need for separate scaling.
- **Central migrations and configuration with a schema per module.** Rejected: a module would still be spread over the repository, and every new module would edit shared files.

# Infrastructure

## Stack

| Concern | Choice |
| --- | --- |
| Language | PHP 8.5, `declare(strict_types=1)` everywhere |
| Framework | Symfony 8.1 |
| Application server | FrankenPHP (Caddy plus PHP in one container) |
| Database | PostgreSQL 17 |
| ORM and SQL | Doctrine ORM (write side), Doctrine DBAL (read side), Doctrine Migrations |
| Messaging | Symfony Messenger, Doctrine transport |
| API description | NelmioApiDocBundle |

The project tracks the newest stable PHP and Symfony releases rather than an LTS line. Versions are pinned in `composer.json` and the Dockerfile, and this table is updated when they change. PHP-CS-Fixer and Rector read the required versions from `composer.json`, so raising a version there also raises the language level they enforce.

## Docker

`compose.yaml` defines the development stack:

| Service | Purpose |
| --- | --- |
| `php` | FrankenPHP serving the API on `https://localhost` |
| `worker` | Same image, runs `messenger:consume outbox`; added in milestone M4 together with the outbox |
| `database` | PostgreSQL with a named volume and a health check |

The Dockerfile is multi-stage:

- `base`: PHP extensions and Composer
- `dev`: Xdebug and development configuration, source mounted as a volume
- `prod`: dependencies installed without dev packages, optimised autoloader, opcache preloading, no source mount, non-root user

`compose.override.yaml` holds development-only settings. A separate `app_test` database is created by the database container's init script so tests never touch development data; in the `test` environment Doctrine selects it by appending `_test` to the database name.

Working with the stack:

- After a fresh clone run `make up`, then `make install` and `make migrate`.
- Always start the stack through `make`. It passes the host user's uid and gid to the build, so the `dev` container runs as that user and the files it creates in the mounted source stay owned by the developer.
- Host ports can be changed with `HTTP_PORT`, `HTTPS_PORT`, `HTTP3_PORT` and `POSTGRES_PORT`. PostgreSQL is published on `127.0.0.1` only.
- The stack has no restart policy: it runs only between `make up` and `make down`.
- Caddy executes only the front controller `public/index.php`; every other file in `public/` is served as a static file and other PHP files are never executed or exposed.

## Makefile contract

The Makefile is the only entry point for developers, agents and CI. Every target runs inside the `php` container.

| Target | Action |
| --- | --- |
| `up`, `down`, `logs` | Manage the stack |
| `sh` | Shell in the `php` container |
| `install` | `composer install` |
| `migrate` | Run migrations; succeeds when there is nothing to run |
| `migration` | Generate a migration for one module (`MODULE=Leads`) from its mapping changes, limited to the module's schema, and format it; fails when the mapping has none |
| `cs`, `cs-fix` | PHP-CS-Fixer dry run, fix |
| `stan` | PHPStan; compiles the dev container first, because the Symfony extension reads it |
| `deptrac` | Deptrac with the rules in `deptrac.php` |
| `rector`, `rector-fix` | Rector dry run, apply |
| `test` | All suites; accepts `FILTER=` and `FILE=` |
| `test-unit`, `test-integration`, `test-functional` | One suite |
| `infection` | Mutation testing |
| `audit` | `composer audit` |
| `qa` | `cs`, `rector`, `stan`, `deptrac`, `test` in that order |

`test`, `test-integration` and `test-functional` first bring the schema of `app_test` up to date with the migrations, so the tests run against the same schema as the application, locally and in CI.

## Configuration

- Configuration comes from environment variables. `.env` contains safe development defaults; real secrets are never committed.
- Every top-level directory of `src/` (a module, `Shared`, `Delivery`) wires itself in its own `config/`: `services.yaml`, `routes.yaml` and `packages/*.yaml` for its slice of bundle configuration. The application's `config/services.yaml` and `config/routes.yaml` import those files by pattern and register nothing themselves, so adding a module changes no shared file. See the [architecture document](03-architecture.md#module-configuration).
- Service wiring uses autowiring and autoconfiguration. Handlers join their bus through the marker interfaces `CommandHandler`, `QueryHandler` and `EventHandler`, declared once for the whole application, which keeps the Application layer free of Messenger ([ADR 0009](adr/0009-vendor-free-application-and-contracts.md)).

## Database

- One database and one connection. Each module has its own schema, named after the module in snake case, and tables that belong to no module are owned by `Shared` ([ADR 0008](adr/0008-module-owned-schema-migrations-and-configuration.md)).
- Schema changes go through migrations only; `doctrine:schema:update` is never used.
- A module's migrations live in `src/<Module>/Infrastructure/Persistence/Migrations` and are registered in the module's `config/packages/doctrine_migrations.yaml`. There is no root `migrations/` directory. Until the first module registers a path, `make migrate` finds nothing to run and succeeds.
- The first migration of a module creates its schema. The generated `down()` does not drop it, so that statement is added by hand.
- Migrations are forward-only and safe to run against a live database.
- A connection that the server does not accept gives up after three seconds instead of the driver's default thirty, so a database outage fails requests fast instead of holding them.
- The uniqueness of active lead emails is enforced by a partial unique index in addition to the domain check.

## Messaging transports

| Transport | Backing | Carries |
| --- | --- | --- |
| `sync` | In-process | Commands, queries, domain events |
| `outbox` | Doctrine, table `messenger_messages` | Integration events |
| `failed` | Doctrine | Messages that exhausted their retries |

Retry policy for `outbox`: three attempts with exponential backoff, then `failed`. In the test environment `outbox` uses the in-memory transport so functional tests can assert on published events and consume them deterministically.

## Continuous integration

A GitHub Actions workflow starts the stack with `make up` and runs `make install`, `make qa`, `make infection` and `make audit` on every push and pull request. The same commands run locally, so CI never fails for a reason that cannot be reproduced on a developer machine.

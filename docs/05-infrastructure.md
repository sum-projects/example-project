# Infrastructure

## Stack

| Concern | Choice |
| --- | --- |
| Language | PHP 8.4, `declare(strict_types=1)` everywhere |
| Framework | Symfony 7.4 LTS |
| Application server | FrankenPHP (Caddy plus PHP in one container) |
| Database | PostgreSQL 17 |
| ORM and SQL | Doctrine ORM (write side), Doctrine DBAL (read side), Doctrine Migrations |
| Messaging | Symfony Messenger, Doctrine transport |
| API description | NelmioApiDocBundle |

Versions are pinned in `composer.json` and the Dockerfile; this table records the intended baseline at design time and is updated when they change.

## Docker

`compose.yaml` defines the development stack:

| Service | Purpose |
| --- | --- |
| `php` | FrankenPHP serving the API on `https://localhost` |
| `worker` | Same image, runs `messenger:consume outbox`; added in M4 together with the outbox |
| `database` | PostgreSQL with a named volume and a health check |

The Dockerfile is multi-stage:

- `base`: PHP extensions and Composer
- `dev`: Xdebug and development configuration, source mounted as a volume
- `prod`: dependencies installed without dev packages, optimised autoloader, opcache preloading, no source mount, non-root user

`compose.override.yaml` holds development-only settings. A separate `app_test` database is created by the database container's init script so tests never touch development data.

Working with the stack:

- After a fresh clone run `make up`, then `make install`.
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
| `migrate` | Run migrations |
| `migration` | Generate a migration from mapping changes |
| `cs`, `cs-fix` | PHP-CS-Fixer dry run, fix |
| `stan` | PHPStan |
| `deptrac` | Deptrac |
| `rector` | Rector dry run |
| `test` | All suites; accepts `FILTER=` and `FILE=` |
| `test-unit`, `test-integration`, `test-functional` | One suite |
| `infection` | Mutation testing |
| `qa` | `cs`, `stan`, `deptrac`, `test` in that order |

Implemented so far: `up`, `down`, `logs`, `sh`, `install`. The remaining targets arrive with the tooling in M1.

## Configuration

- Configuration comes from environment variables. `.env` contains safe development defaults; real secrets are never committed.
- Service wiring uses autowiring and autoconfiguration. Handlers are registered on their bus by attribute-free interface tagging (`CommandHandler`, `QueryHandler`, `EventHandler` marker interfaces configured with `_instanceof`), which keeps the Application layer free of framework attributes.

## Database

- One database, one schema. Tables are prefixed by module.
- Schema changes go through migrations only; `doctrine:schema:update` is never used.
- Migrations are forward-only and safe to run against a live database.
- The uniqueness of active lead emails is enforced by a partial unique index in addition to the domain check.

## Messaging transports

| Transport | Backing | Carries |
| --- | --- | --- |
| `sync` | In-process | Commands, queries, domain events |
| `outbox` | Doctrine, table `messenger_messages` | Integration events |
| `failed` | Doctrine | Messages that exhausted their retries |

Retry policy for `outbox`: three attempts with exponential backoff, then `failed`. In the test environment `outbox` uses the in-memory transport so functional tests can assert on published events and consume them deterministically.

## Continuous integration

A GitHub Actions workflow builds the image and runs `make qa`, `make infection` and `composer audit` on every push and pull request. The same commands run locally, so CI never fails for a reason that cannot be reproduced on a developer machine.

# CRM API: Symfony, CQRS and DDD showcase

A small CRM REST API that takes a sales lead from first contact to a won or lost deal. The business scope is deliberately modest; the point of the project is to show how it is built:

- Domain-Driven Design with three bounded contexts in a modular monolith
- CQRS on Symfony Messenger with a transactional outbox between modules
- A fully Dockerized development environment
- A strict quality gate: PHPStan at max level, Deptrac architecture rules, mutation testing
- AI-assisted development with a team of specialised Claude Code agents, each running on the model tier that fits its job

## Status

Early implementation. The design is complete; the Docker environment, the Symfony skeleton with Doctrine, the quality tooling and a health endpoint are in place, and the rest follows the [roadmap](docs/08-roadmap.md).

## Getting started

Requirements: Docker with Compose, and GNU Make.

```
make up        # build and start the stack
make install   # install Composer dependencies
make migrate   # run the database migrations
make qa        # run the quality gate
```

The application answers on https://localhost, with a health check at https://localhost/health (the certificate is issued by Caddy's local authority, so the browser asks for confirmation once). `make help` lists the available targets and `make down` stops the stack.

## Documentation

Start with the [documentation index](docs/README.md).

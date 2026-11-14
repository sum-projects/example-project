# CRM API: Symfony, CQRS and DDD showcase

A small CRM REST API that takes a sales lead from first contact to a won or lost deal. The business scope is deliberately modest; the point of the project is to show how it is built:

- Domain-Driven Design with three bounded contexts in a modular monolith
- CQRS on Symfony Messenger with a transactional outbox between modules
- A fully Dockerized development environment
- A strict quality gate: PHPStan at max level, Deptrac architecture rules, mutation testing
- AI-assisted development with a team of specialised Claude Code agents, each running on the model tier that fits its job

## Status

Documentation phase. The design is complete and the implementation follows the [roadmap](docs/08-roadmap.md).

## Documentation

Start with the [documentation index](docs/README.md).

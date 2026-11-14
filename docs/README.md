# Documentation

| # | Document | Contents |
| --- | --- | --- |
| 01 | [Product overview](01-product-overview.md) | Business process, scope, non-goals |
| 02 | [Domain model](02-domain-model.md) | Ubiquitous language, bounded contexts, aggregates, invariants, events |
| 03 | [Architecture](03-architecture.md) | The binding architecture rules: modules and layers, contracts, entry points, CQRS, communication between modules, persistence |
| 04 | [API](04-api.md) | Conventions, endpoints, error format |
| 05 | [Infrastructure](05-infrastructure.md) | Docker stack, Makefile contract, persistence, messaging |
| 06 | [Quality standards](06-quality-standards.md) | Coding rules, testing strategy, quality gate |
| 07 | [Agent workflow](07-agent-workflow.md) | Subagents, model routing, delegation rules |
| 08 | [Roadmap](08-roadmap.md) | Implementation milestones |
| | [Architecture decision records](adr/) | Why the key decisions were made |

## Conventions

- All documentation is written in English.
- A change in behaviour updates the affected document in the same change.
- Each rule has one source of truth, and other files point to it instead of repeating it. Architecture rules live in [03-architecture.md](03-architecture.md); `CLAUDE.md` and `.claude/rules/` hold short digests of them.
- A change to an architectural decision is made by adding a new ADR that supersedes the old one. The body of an accepted ADR is not edited; only its Status line changes.

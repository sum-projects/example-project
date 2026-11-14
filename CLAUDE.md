# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Showcase CRM REST API (lead-to-deal pipeline): Symfony, CQRS, DDD, Docker, developed with a team of Claude Code subagents.

**Milestone M1 in progress:** the Docker environment, a clean Symfony 8.1 skeleton on PHP 8.5 and the QA tooling exist; `/health` and application code do not yet. `docs/` is the source of truth. Do not start a milestone from [docs/08-roadmap.md](docs/08-roadmap.md) until the user asks. If code and docs disagree, resolve it in the same change (update the doc or add an ADR).

## Language

- Everything in the repository is English: code, comments, docs, commits, agent definitions.
- Talk to the user in Polish. Polish text goes into files only on explicit request.

## Where to look

| Topic | Doc |
| --- | --- |
| Business process, scope | [docs/01-product-overview.md](docs/01-product-overview.md) |
| Aggregates, invariants, events | [docs/02-domain-model.md](docs/02-domain-model.md) |
| Layers, module boundaries, CQRS, where code goes | [docs/03-architecture.md](docs/03-architecture.md) |
| Endpoints, error format | [docs/04-api.md](docs/04-api.md) |
| Docker, all `make` targets, persistence, messaging | [docs/05-infrastructure.md](docs/05-infrastructure.md) |
| Coding rules, testing strategy, definition of done | [docs/06-quality-standards.md](docs/06-quality-standards.md) |
| Subagents, model routing, delegation flow | [docs/07-agent-workflow.md](docs/07-agent-workflow.md) |
| Why decisions were made | [docs/adr/](docs/adr/) |

## Commands

Everything runs in Docker through the Makefile; never run PHP, Composer or QA tools on the host. Every target of the Makefile contract exists except `migrate` and `migration`, which arrive with Doctrine.

```
make up                    # start the stack
make qa                    # full gate: cs, rector, stan, deptrac, test
make cs-fix rector-fix     # apply the mechanical fixes
make test FILTER=LeadTest  # single test; FILE=path for a single file
```

A task is finished only when `make qa` passes.

## Commits

Conventional Commits with the module as scope, e.g. `feat(leads): qualify a lead`; one logical change per commit. Details: [docs/06-quality-standards.md](docs/06-quality-standards.md#git).

## Rules that are easy to break

- Dependencies point inward: `UserInterface`/`Infrastructure` → `Application` → `Domain`. `Domain` is pure PHP.
- Modules (`Leads`, `Customers`, `Sales`) never call each other; they react to integration events from another module's `Contract` namespace, delivered through the outbox.
- Command handlers return nothing and change one aggregate per transaction; query handlers return DTOs via DBAL, never aggregates.
- Aggregates change only through intention-revealing methods that guard invariants; no setters.
- The project tracks the newest stable PHP and Symfony (versions in `composer.json`). Write with their current features, not older idioms; `make rector` and `make cs` enforce what can be automated.
- Every PHP file starts with `<?php declare(strict_types=1);` on one line.

## Agents

Subagents in `.claude/agents/` each pin their own model, so pick the agent and never pass a model override: `architect` (fable), `domain-developer` and `code-reviewer` (opus), `infrastructure-developer`, `test-engineer` and `docs-writer` (sonnet), `qa-runner` (haiku). Do trivial edits directly.

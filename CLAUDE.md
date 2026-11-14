# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Showcase CRM REST API (lead-to-deal pipeline): Symfony, CQRS, DDD, Docker, built as a modular monolith and developed with a team of Claude Code subagents.

**Milestone M1 is done:** the Docker environment, a Symfony 8.1 skeleton on PHP 8.5 with Doctrine, the QA tooling and `/health` exist; the shared kernel, the buses, the modules and the delivery layer do not yet. `docs/` is the source of truth. Do not start a milestone from [docs/08-roadmap.md](docs/08-roadmap.md) until the user asks. If code and docs disagree, resolve it in the same change (update the doc or add an ADR).

## Language

- Everything in the repository is English: code, comments, docs, commits, agent definitions.
- Talk to the user in Polish. Polish text goes into files only on explicit request.

## Working mode

- Ask through AskUserQuestion early and often: for an unclear requirement, an architectural decision, a choice between variants, a change that is hard to undo, installing a dependency, and any deviation from an invariant. Give 2 to 4 options, the recommended one first, each with a short justification. One question too many is better than a guess.
- Do not ask what the code or the documentation can answer.
- Before working in an area, read its rules in [docs/03-architecture.md](docs/03-architecture.md). The files in `.claude/rules/` load the digest for the paths you touch.
- Check anything version-dependent in the official documentation for the version installed here, not from memory.
- Do not add anything beyond the scope of the task: no code, package, tool or empty directory "for later".
- If a rule does not hold up in practice, propose changing it: a question, then an update to the documentation and an ADR. Never work around it silently.
- End every task with feedback: what was done, where and why you deviated from a default, the risks you noticed, and the changes to the rules you propose.

## Where to look

| Topic | Doc |
| --- | --- |
| Business process, scope | [docs/01-product-overview.md](docs/01-product-overview.md) |
| Aggregates, invariants, events | [docs/02-domain-model.md](docs/02-domain-model.md) |
| Architecture rules with reasoning: modules, layers, contracts, entry points, CQRS, where code goes | [docs/03-architecture.md](docs/03-architecture.md) |
| Endpoints, error format | [docs/04-api.md](docs/04-api.md) |
| Docker, all `make` targets, persistence, messaging | [docs/05-infrastructure.md](docs/05-infrastructure.md) |
| Coding rules, testing strategy, definition of done, enforcement gaps | [docs/06-quality-standards.md](docs/06-quality-standards.md) |
| Subagents, model routing, delegation flow | [docs/07-agent-workflow.md](docs/07-agent-workflow.md) |
| Why decisions were made | [docs/adr/](docs/adr/) |

## Commands

Everything runs in Docker through the Makefile; never run PHP, Composer or QA tools on the host.

```
make up                        # start the stack
make qa                        # full gate: cs, rector, stan, deptrac, test
make cs-fix rector-fix         # apply the mechanical fixes
make test FILTER=LeadTest      # single test; FILE=path for a single file
make migration MODULE=Leads    # generate a migration for one module's schema
```

A task is finished only when `make qa` passes.

## Architecture rules

This is a digest. The binding text, the reasoning and the defaults are in [docs/03-architecture.md](docs/03-architecture.md); the identifiers match. `L1` to `I5` are rules; `M1` to `M6` are roadmap milestones.

**Invariants.** Deviating from one needs the user's approval before the change.

- **L1** A module is a bounded context in `src/<Module>/`; its boundary follows business language and processes, not tables. `Shared` and `Delivery` are not modules.
- **L2** Dependencies point inward: `Domain` on `Shared\Domain` only; `Application` on its own `Domain` and `Contracts`; `Infrastructure` and `UserInterface` on the inner layers.
- **L3** `src/Shared/` is a minimal shared kernel with no business logic.
- **B1** A module uses another module only through its `Contracts`: the facade from an adapter in `Infrastructure/Acl/`, integration events in `Application/EventHandler/`.
- **B2** No foreign keys, joins or ORM relations between modules; identifiers only.
- **B3** Each module owns its tables and its migrations.
- **B5** Boundaries are checked by Deptrac in CI, not by convention.
- **C1** Other modules use only the facade and the events; outside the owning module, commands and queries in `Contracts` are for the delivery layer alone.
- **C2** No domain types in `Contracts`: `readonly` DTOs of scalars and identifiers.
- **D1** `Domain` has zero dependencies on the framework, the ORM or infrastructure.
- **Q1** A query never changes state.
- **E2** to **E7** Of a module, the delivery layer sees only the commands and queries in `Contracts`; it holds no business logic; dispatches at most one command per endpoint; composes reads only by identifier and in batches; leaves cross-module lists to a read model in one module; and maps contract DTOs to the channel's response itself.
- **I3** An integration event is stored in the same transaction as the aggregate change (outbox). **I4** Consumers are idempotent and tolerate out-of-order messages. **I5** No transaction spans several modules.

**Defaults** (L4, B4, C3, C4, D2 to D6, Q2 to Q6, I1, I2). A better solution for the case at hand may replace one; report the deviation with its reasoning and record it as an ADR.

## Where an entry point goes (E1)

Decide in order:

1. It composes data or triggers use cases from more than one module: `src/Delivery/<Channel>/`.
2. It belongs to a channel with its own contract towards a client (the REST API, an admin API, a partner API): `src/Delivery/<Channel>/`, even for a single module.
3. It is a technical adapter of one module (a webhook of a system that module integrates with, its maintenance command, its scheduler task): `src/<Module>/UserInterface/`.
4. It is a technical entry point of no module (the health endpoint): `src/Shared/UserInterface/`.
5. No clear fit: ask the user.

## Rules that are easy to break

- Command handlers return nothing and change one aggregate per transaction; query handlers return DTOs via DBAL, never aggregates.
- Aggregates change only through intention-revealing methods that guard invariants; no setters.
- `Application` and `Contracts` name no vendor code except validation constraints as attributes on commands and queries; handlers join a bus by marker interface, not by `#[AsMessageHandler]`.
- A module wires itself in `src/<Module>/config/`; the application's `config/` registers no module services or routes.
- The project tracks the newest stable PHP and Symfony (versions in `composer.json`). Write with their current features, not older idioms; `make rector` and `make cs` enforce what can be automated.
- Every PHP file starts with `<?php declare(strict_types=1);` on one line.

## Commits

Conventional Commits with the module as scope, e.g. `feat(leads): qualify a lead`; one logical change per commit. Details: [docs/06-quality-standards.md](docs/06-quality-standards.md#git).

## Agents

Subagents in `.claude/agents/` each pin their own model, so pick the agent and never pass a model override: `architect` (fable), `domain-developer` and `code-reviewer` (opus), `infrastructure-developer`, `test-engineer` and `docs-writer` (sonnet), `qa-runner` (haiku). Do trivial edits directly. A subagent cannot ask the user: it reports the question, and the main session asks.

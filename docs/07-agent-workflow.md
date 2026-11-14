# Agent workflow

The project is developed with Claude Code. The main session acts as the orchestrator and delegates to specialised subagents defined in `.claude/agents/`. Each agent pins the model tier suited to its work, so that expensive reasoning is spent where mistakes are costly and cheap, fast models handle mechanical work.

## Model routing principle

| Tier | Strength | Given to |
| --- | --- | --- |
| Fable | Deepest reasoning, highest cost | Decisions that shape everything after them: domain modelling, architecture |
| Opus | Strong reasoning on complex code | Business logic and independent review |
| Sonnet | Fast and reliable on well-specified work | Adapters, tests, documentation that follow established patterns |
| Haiku | Fastest and cheapest | Running tools and reporting, mechanical fixes |

The question when routing a task is: how expensive is a subtle mistake here, and how much of the answer is already determined by the docs and existing code? The less determined and the more expensive, the higher the tier.

## Agents

| Agent | Model | Writes to | Responsibility |
| --- | --- | --- | --- |
| `architect` | fable | `docs/` only | Domain modelling, cross-module design, ADRs, resolving conflicts between docs and code |
| `domain-developer` | opus | `src/*/Domain`, `src/*/Application`, `src/*/Contracts`, `src/Shared/Domain`, `src/Shared/Application`, their unit tests | Aggregates, value objects, events, commands, queries, handlers, facades and contract DTOs |
| `code-reviewer` | opus | nothing | Reviews a change against the docs, ADRs and quality standards |
| `infrastructure-developer` | sonnet | `src/*/Infrastructure`, `src/*/UserInterface`, `src/Delivery`, `src/*/config`, `config/`, `docker/`, build files | Persistence, migrations, read models, ACL adapters, entry points, messaging, Docker, tooling configuration |
| `test-engineer` | sonnet | `tests/` | Integration tests, module tests against the contract, thin channel tests, builders, in-memory doubles, contract tests |
| `docs-writer` | sonnet | `docs/`, `README.md` | Keeps documentation consistent with a finished change |
| `qa-runner` | haiku | formatting fixes only | Runs `make` targets, reports failures precisely, applies `make cs-fix` |

Ownership of paths is exclusive while agents run in parallel: two agents never edit the same files in the same step.

## Working mode

The working mode in `CLAUDE.md` binds the orchestrator and every agent: read the rules for the area first, do not exceed the scope of the task, and end with feedback. Only the orchestrator can ask the user, through AskUserQuestion; a subagent has no such tool. A subagent that meets a question the rules do not answer stops and reports it with the options it sees, and the orchestrator asks.

A rule changes in `docs/03-architecture.md` first. The orchestrator then updates the digests in `CLAUDE.md` and `.claude/rules/` and the agent definitions in the same change, so that no file states an outdated rule.

## Standard feature flow

```
1. architect            only if the docs do not already settle the design
2. domain-developer     domain and application code with unit tests
3. infrastructure-developer  ┐ in parallel: adapters and endpoints
   test-engineer             ┘ integration and functional tests
4. qa-runner            make qa; failures go back to the owning agent
5. code-reviewer        independent review; findings go back to the owning agent
6. docs-writer          only if behaviour or the API changed
```

Steps 4 and 5 repeat until the gate is green and the review has no open findings.

## Delegation rules

- **Brief completely.** A subagent knows only its prompt. Name the goal, the docs to read, the files in scope, what is already decided, and what "done" means.
- **Do not delegate trivial work.** A change the orchestrator can make in a few tool calls is made directly.
- **Never override the model.** Choose a different agent instead. If no agent fits, the orchestrator does the work or the agent set is extended deliberately.
- **Escalate, do not guess.** An agent that meets a design question outside its remit stops and reports it. Design questions go to `architect`; a deviation from a rule marked Invariant, a decision that changes scope or an accepted ADR, and a new dependency go to the user.
- **Review is independent.** `code-reviewer` receives the diff and the docs, not the author's reasoning.
- **Verify reports.** An agent's summary is a claim. The gate (`make qa`) and the review are the evidence.

## Escalation examples

| Situation | Route |
| --- | --- |
| A new invariant is unclear or contradicts the domain model | `architect`, then update `docs/02-domain-model.md` |
| A handler needs data from another module | `architect`: this is a boundary decision, a facade read or an integration event |
| It is unclear whether an entry point belongs to the delivery layer or to a module | Apply the selection rule; if no step fits, ask the user |
| A rule marked Default does not fit the case | `architect` decides and records the deviation as an ADR; it is reported in the feedback |
| A rule marked Invariant does not fit the case | Ask the user before changing anything |
| PHPStan reports a type error in a mapping file | `infrastructure-developer` |
| A mutation survives in an aggregate | `domain-developer` to add the missing unit test |
| Code style failures only | `qa-runner` |
| The change requires replacing an accepted ADR | Ask the user |

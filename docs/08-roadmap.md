# Roadmap

Each milestone ends with a green `make qa` and updated docs. Milestones are implemented one at a time, on request.

| # | Milestone | Delivers |
| --- | --- | --- |
| M0 | Documentation and agents | This documentation set, `CLAUDE.md`, agent definitions (done) |
| M1 | Skeleton and tooling | Symfony skeleton, Docker stack, Makefile, PHP-CS-Fixer, PHPStan, Deptrac, PHPUnit suites, Infection, CI workflow, `/health` (done) |
| M2 | Shared kernel and buses | `Shared`: aggregate root, identifiers, `Money`, `Email`, `Clock`, the shared failure types, the three buses with middleware |
| M3 | Leads module | `Lead` aggregate with all transitions, commands and queries, persistence, its endpoints in the `Api` channel with problem-details error handling, full test pyramid, transaction-per-test isolation for database tests |
| M4 | Outbox and Customers module | Integration event contract, outbox transport and worker, `Customer` aggregate, registration on `LeadConvertedV1` |
| M5 | Sales module | `Deal` aggregate, opening on `LeadConvertedV1`, pipeline endpoints, end-to-end conversion test |
| M6 | Hardening | OpenAPI description, production image without development defaults, stateless authentication, mutation score at threshold across modules |

## Order rationale

Tooling comes before code so that the quality gate constrains every line from the first commit. `Leads` is built first because it is upstream and contains the richest state machine; the outbox is introduced only when the first consumer exists, so it is never built speculatively.

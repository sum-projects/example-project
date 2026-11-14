# Roadmap

Each milestone ends with a green `make qa` and updated docs. Milestones are implemented one at a time, on request.

| # | Milestone | Delivers |
| --- | --- | --- |
| M0 | Documentation and agents | This documentation set, `CLAUDE.md`, agent definitions (done) |
| M1 | Skeleton and tooling | Symfony skeleton, Docker stack, Makefile, PHP-CS-Fixer, PHPStan, Deptrac, PHPUnit suites, Infection, CI workflow, `/health` (in progress: Docker stack, stack Makefile targets and Symfony skeleton are done) |
| M2 | Shared kernel and buses | `Shared` module: aggregate root, identifiers, `Money`, `Email`, `Clock`, the three buses with middleware, problem-details error handling |
| M3 | Leads module | `Lead` aggregate with all transitions, commands and queries, persistence, endpoints, full test pyramid |
| M4 | Outbox and Customers module | Integration event contract, outbox transport and worker, `Customer` aggregate, registration on `LeadConvertedV1` |
| M5 | Sales module | `Deal` aggregate, opening on `LeadConvertedV1`, pipeline endpoints, end-to-end conversion test |
| M6 | Hardening | OpenAPI description, production image, stateless authentication, mutation score at threshold across modules |

## Order rationale

Tooling comes before code so that the quality gate constrains every line from the first commit. `Leads` is built first because it is upstream and contains the richest state machine; the outbox is introduced only when the first consumer exists, so it is never built speculatively.

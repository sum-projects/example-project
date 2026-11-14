# Quality standards

## Quality gate

A change is complete when `make qa` passes. CI also runs mutation testing and a dependency audit.

| Tool | Standard |
| --- | --- |
| PHP-CS-Fixer | `@PER-CS` and `@Symfony` rule sets, the PHP and PHPUnit migration sets for the versions required in `composer.json`, `<?php declare(strict_types=1);` on the opening line, ordered imports |
| Rector | No pending changes for the PHP sets of the required PHP version, the upgrade sets of the installed Symfony, PHPUnit and Doctrine versions, and Symfony code quality |
| PHPStan | Level max with strict rules and the Symfony, Doctrine and PHPUnit extensions; no baseline |
| Deptrac | Layer and module rules from the architecture document; zero violations |
| PHPUnit | All suites green; no skipped, incomplete or risky tests, no warnings or notices, and no deprecations that project code triggers or calls into (deprecations raised between two vendor packages are not reported) |
| Infection | Minimum 85% mutation score on `Domain` and `Application`, measured with the unit suite and counting code no test covers |
| Composer audit | No known vulnerabilities |

Suppressions (`@phpstan-ignore`, baseline entries, Deptrac skips) are not used to get a change through. If a rule is wrong for the project, change the rule in its own commit and explain why.

Two settings exist only because the project has no Domain or Application code yet, and are removed when it arrives: `failOnEmptyTestSuite="false"` in `phpunit.dist.xml` goes with the first unit test, when all three suites have one, and `ignoreMsiWithNoMutations` in `infection.json5` together with the `src/` path filter in the `infection` target go with the first Domain or Application class that has logic to mutate.

## Coding rules

- Code is written for the newest stable PHP and Symfony and uses their current features where they make it clearer, for example property hooks, asymmetric visibility, `new` without wrapping parentheses and the pipe operator, instead of the older idiom. Rector and PHP-CS-Fixer rewrite what can be detected mechanically; the rest is a review point.
- Every PHP file starts with `<?php declare(strict_types=1);` on one line. Only the generated `config/bundles.php` and `config/reference.php` are exempt.
- Classes are `final` by default; value objects, commands, queries, events and DTOs are `final readonly`.
- Constructor promotion, typed properties, and enums instead of constants for closed sets.
- No setters on aggregates. Named constructors (`Lead::capture(...)`) instead of public constructors for aggregates and for value objects with more than one way to be built.
- No `null` where a missing value has business meaning that deserves a name; no arrays as data structures across a class boundary, use typed objects or generics-annotated collections.
- Exceptions are specific classes with named constructors (`LeadCannotBeConverted::becauseNotQualified($status)`).
- No static service access, no service locator, no `DateTimeImmutable('now')` outside the `Clock` implementation.
- Comments explain why, not what. Public API of the domain is documented by its tests.
- One command, one handler, one use case. Handlers are invokable.

## Testing strategy

| Suite | Scope | Uses | Speed |
| --- | --- | --- | --- |
| Unit | Domain and Application | Plain PHPUnit, in-memory repositories, fixed clock; no kernel, no database | Milliseconds |
| Integration | Infrastructure adapters | Kernel and real PostgreSQL; each test in a rolled-back transaction | Fast |
| Functional | Whole use cases over HTTP | Kernel browser, real database, in-memory outbox transport | Slower |

The rollback of each integration test is set up together with the first test that writes to the database, in M3; no test writes before that.

What each suite is responsible for:

- **Unit, Domain:** every invariant and every transition in the domain model document has a test for the allowed path and for each rejected path, asserting on recorded events and thrown exceptions.
- **Unit, Application:** handlers tested with in-memory repositories: the right aggregate is loaded, changed and saved; not-found cases; idempotency of integration event consumers.
- **Integration:** repositories round-trip aggregates including all value objects; read models return correct DTOs, filters and pagination; the unique index and optimistic locking behave as designed.
- **Functional:** one happy path per endpoint, the main error path per endpoint, and the end-to-end conversion flow from lead to customer and deal.

Rules:

- Tests are named after behaviour: `test_a_lead_without_owner_cannot_be_marked_contacted`.
- Test data is built with builders or object mothers (`LeadMother::qualified()`), not with fixtures files or repeated constructor calls.
- Mocks are used only for ports at the edge of the system. Domain objects are never mocked.
- In-memory repository implementations live in `tests/` and are verified against the same contract test as the Doctrine implementations.
- A bug fix starts with a failing test.

## Git

- Conventional Commits (`feat(leads): qualify a lead`), written in English, one logical change per commit.
- `master` is always green. Work happens on short-lived branches and is merged through pull requests.

## Definition of done

1. Behaviour matches the docs, or the docs were updated in the same change.
2. Tests exist at the levels listed above for the change.
3. `make qa` passes and the mutation score did not drop below the threshold.
4. The change was reviewed by the `code-reviewer` agent or a human, and findings were resolved.

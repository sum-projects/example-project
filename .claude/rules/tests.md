---
paths:
  - "tests/**"
---

# Tests

Digest for these paths. The binding text is `docs/06-quality-standards.md` ("Testing strategy").

- Each suite mirrors `src/`: `tests/Unit/<Module>/Domain`, `tests/Integration/<Module>/Infrastructure`, `tests/Functional/<Module>`, `tests/Functional/Delivery/<Channel>`.
- **Unit:** Domain without the kernel and without mocks; handlers with in-memory repositories and a fixed clock.
- **Integration:** adapters against real PostgreSQL through the kernel, each test in a rolled-back transaction.
- **Functional, module:** dispatch the commands and queries from the module's `Contracts` on the bus, without HTTP. This is where a module's behaviour is proven.
- **Functional, channel:** thin. One happy path and the main error path per endpoint: routing, status, headers, body shape, problem details. Business rules are not re-tested here.
- Name tests after behaviour: `test_a_lead_without_owner_cannot_be_marked_contacted`.
- Build data with builders or object mothers. Mock only ports at the edge of the system; never a domain object.
- Never weaken an assertion or skip a test to get a suite green. A failing test that shows a defect in `src/` is a result to report.
- A bug fix starts with a failing test.

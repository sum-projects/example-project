# 0004. Doctrine XML mapping

Status: Accepted (2026-11-14)

## Context

The Domain layer must not depend on the ORM. Doctrine's attribute mapping places persistence metadata, and an import of the ORM namespace, directly on domain classes.

## Decision

Map aggregates and embeddable value objects with XML files kept in each module's `Infrastructure/Persistence/Mapping` directory. Use custom DBAL types for identifiers and single-value value objects.

## Consequences

- Domain classes are plain PHP and the Deptrac rule "Domain imports nothing from vendors" holds without exceptions.
- Mapping lives away from the class, so a property change requires editing two files. Repository integration tests that round-trip every aggregate catch omissions.
- XML is more verbose than attributes.

## Alternatives considered

- **Attributes on domain classes.** Rejected: breaks the dependency rule that the architecture is built on.
- **Separate persistence entities mapped by hand to domain objects.** Rejected: purest option, but doubles the model and adds mapping code with little benefit at this size.

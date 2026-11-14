# 0009. Vendor-free Application and Contracts, with one exception for validation constraints

Status: Accepted (2026-11-14)

## Context

The architecture baseline adopted for the project lets a handler carry Symfony's `#[AsMessageHandler]` attribute and names the time port `ClockInterface`. The project had already chosen stricter forms, documented but not recorded as a decision. Separately, the command bus validates commands, and a command placed in `Contracts` or `Application` could not declare what is valid, because neither layer was allowed to name vendor code.

## Decision

`Application` and `Contracts` name no vendor code, with one exception.

- **Handlers** join their bus by implementing a marker interface from `Shared\Application` (`CommandHandler`, `QueryHandler`, `EventHandler`). The buses are configured for these interfaces once, for the whole application. `#[AsMessageHandler]` is not used.
- **Time** in the domain comes from the project's own `Clock` port in `Shared\Domain`, implemented in `Shared\Infrastructure`. No vendor clock interface is named in `Domain`.
- **The exception:** constraints from `Symfony\Component\Validator\Constraints` may be declared as attributes on commands and queries, in `Contracts` and in `Application`. The bus validates every message against them.

Deptrac allows that one namespace in those layers and nothing else from vendors.

## Consequences

- What a command accepts is part of its visible contract and is enforced for every entry point: HTTP, the console, an event handler, a test that dispatches on the bus.
- A handler cannot land on the wrong bus: the interface it implements decides, and there is no bus name to mistype.
- `Application` code is unit tested without the framework; attributes are inert until something reads them.
- The meaning of a command now depends on Symfony Validator being present. Replacing the validator would mean rewriting the constraints.
- The rule has an exception to explain, which the Deptrac configuration states in one place.

## Alternatives considered

- **`#[AsMessageHandler(bus: ...)]` on every handler.** Rejected: it brings Messenger into `Application` and repeats the bus name on each handler.
- **Constraints in validator mapping files in the module's `config/`.** Rejected: the layers would stay free of vendor code, as with the Doctrine mapping, but the constraints would sit away from the class they describe and drift from it.
- **Validation only on the request DTO at the entry point.** Rejected: a command dispatched from anywhere else would skip it, and the module would not defend its own contract.
- **PSR-20 or Symfony `ClockInterface` in the domain.** Rejected: one more exception for no gain; the adapter over Symfony Clock is a few lines.

# 0006. Delivery layer for client-facing channels

Status: Accepted (2026-11-14). Supersedes [0001](0001-modular-monolith-with-hexagonal-layers.md) in part: where entry points live.

## Context

ADR 0001 put every entry point in its module's `UserInterface` layer. The REST API, however, is one channel with one contract towards its clients: a base path and version, an error format, authorization, an OpenAPI description. Spread over three modules, those conventions have no owner. Some endpoints also answer with data that concerns more than one module, and a module has no natural place to compose it.

The default root namespace is `App\`, so a layer named `App` would produce `App\App\...`.

## Decision

Add a delivery layer, `src/Delivery/` (`App\Delivery`), with one directory per channel (`Api`, `Admin`, `Cli`), created when the channel gets its first entry point. It holds controllers, request and response DTOs, and the mappers that assemble a response. Of a module it sees only the commands and queries in `Contracts`, and it contains no business logic.

A module's `UserInterface` layer stays, for the module's own technical adapters. Technical entry points that belong to no module, such as the health endpoint, live in `Shared/UserInterface`.

Where a given entry point goes is decided by the selection rule in the [architecture document](../03-architecture.md#selection-rule), which is the binding text. In short: several modules or a client-facing channel means the delivery layer; a technical adapter of one module means that module; no clear fit means asking the user.

The root namespace stays `App\` and the layer is called `Delivery`.

## Consequences

- A channel's conventions live in one place, and Deptrac checks that the layer reaches nothing but `Contracts` and the shared kernel.
- Modules are tested against their contract, by dispatching commands and queries, without HTTP.
- Every command and query a channel uses becomes public, in `Contracts`. A feature usually touches two places: the module and the channel.
- Extracting a module into a service no longer takes its HTTP API along; the API has to be split out of the channel.

## Alternatives considered

- **Entry points only in the modules (ADR 0001).** Kept for technical adapters, rejected as the only option: channel conventions scatter and composition across modules has no home. It becomes the better choice for the whole project when modules get separate teams, when endpoints rarely cross module boundaries, or when a module is to be extracted together with its API. That switch is a new ADR.
- **Root namespace `Crm\` with the layer in `src/App/`.** Rejected: every Symfony Flex recipe assumes `App\`, so each new one would need a manual correction, and `App` next to a module's `Application` layer invites confusion.

# API

## Conventions

- Base path `/api/v1`. JSON only (`application/json`); errors use `application/problem+json`.
- Property names are `camelCase`. Timestamps are ISO 8601 in UTC. Identifiers are UUID v7 strings.
- Money is an object: `{"amount": 150000, "currency": "EUR"}` with the amount in minor units.
- Resources are created with `POST` on the collection and answer `201 Created` with a `Location` header and `{"id": "..."}`.
- State changes are modelled as actions: `POST /resource/{id}/action`. They answer `204 No Content`. Actions express business intent and map one-to-one to commands; there is no generic `PATCH`.
- Operations completed asynchronously answer `202 Accepted`.
- Lists accept `page` (default 1) and `perPage` (default 20, maximum 100) and answer:

```json
{
  "items": [],
  "page": 1,
  "perPage": 20,
  "total": 0
}
```

- An OpenAPI description is generated from the code and served at `/api/doc.json`, with a browsable UI at `/api/doc` in the development environment.

## Endpoints

### Leads

| Method | Path | Body | Success | Command or query |
| --- | --- | --- | --- | --- |
| POST | `/leads` | `firstName`, `lastName`, `email`, `companyName?`, `source` | 201 | `CaptureLead` |
| GET | `/leads` | filters: `status`, `ownerId`, `source` | 200 | `ListLeads` |
| GET | `/leads/{id}` | | 200 | `GetLead` |
| POST | `/leads/{id}/assign` | `ownerId` | 204 | `AssignLeadOwner` |
| POST | `/leads/{id}/contact` | | 204 | `MarkLeadContacted` |
| POST | `/leads/{id}/qualify` | `estimatedValue` | 204 | `QualifyLead` |
| POST | `/leads/{id}/disqualify` | `reason` | 204 | `DisqualifyLead` |
| POST | `/leads/{id}/convert` | | 202 | `ConvertLead` |

Converting answers `202` because the customer and the deal are created asynchronously:

```json
{
  "customerId": "0193a1b2-...",
  "dealId": "0193a1b2-...",
  "links": {
    "customer": "/api/v1/customers/0193a1b2-...",
    "deal": "/api/v1/deals/0193a1b2-..."
  }
}
```

The linked resources may answer `404` for a short moment until the event is processed.

Lead representation:

```json
{
  "id": "0193a1b2-...",
  "firstName": "Ada",
  "lastName": "Nowak",
  "email": "ada@example.com",
  "companyName": "Example Ltd",
  "source": "website",
  "status": "qualified",
  "ownerId": "0193a1b2-...",
  "estimatedValue": {"amount": 150000, "currency": "EUR"},
  "disqualificationReason": null,
  "createdAt": "2026-11-14T10:00:00Z",
  "updatedAt": "2026-11-14T12:30:00Z"
}
```

### Customers

| Method | Path | Body | Success | Command or query |
| --- | --- | --- | --- | --- |
| GET | `/customers` | filters: `status` | 200 | `ListCustomers` |
| GET | `/customers/{id}` | | 200 | `GetCustomer` |
| POST | `/customers/{id}/archive` | | 204 | `ArchiveCustomer` |

Customers are created only by converting a lead.

### Deals

| Method | Path | Body | Success | Command or query |
| --- | --- | --- | --- | --- |
| GET | `/deals` | filters: `stage`, `customerId`, `ownerId` | 200 | `ListDeals` |
| GET | `/deals/{id}` | | 200 | `GetDeal` |
| POST | `/deals/{id}/advance` | | 204 | `AdvanceDeal` |
| POST | `/deals/{id}/change-value` | `value` | 204 | `ChangeDealValue` |
| POST | `/deals/{id}/win` | | 204 | `WinDeal` |
| POST | `/deals/{id}/lose` | `reason` | 204 | `LoseDeal` |

Deals are created only by converting a lead.

### Operations

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/health` | Liveness and database connectivity; outside the versioned base path |

`/health` answers `200` when the application is up and the database answers a query, and `503` when the database does not answer it, whether it is unreachable, refuses the credentials or does not exist. A database that does not respond at all still gets its `503` within about three seconds, because the connection gives up after that long. Both answers are `application/json`, have the same shape and carry a `Cache-Control` header with the `no-store` directive:

```json
{
  "status": "ok",
  "checks": {
    "database": "ok"
  }
}
```

Every value is `ok` or `fail`, and `status` is `fail` as soon as one check fails. The `503` reports a state rather than a failed request, so it is not a problem details document, and it exposes no internal details such as exception messages. The cause is written to the log instead.

## Errors

Every error is an RFC 9457 problem details document:

```json
{
  "type": "https://crm.example/problems/lead-not-qualified",
  "title": "Lead is not qualified",
  "status": 409,
  "detail": "Only a qualified lead can be converted; the lead is in status \"contacted\".",
  "instance": "/api/v1/leads/0193a1b2-.../convert"
}
```

Validation errors add a `violations` array:

```json
{
  "type": "https://crm.example/problems/validation-failed",
  "title": "Validation failed",
  "status": 422,
  "violations": [
    {"property": "email", "message": "This value is not a valid email address."}
  ]
}
```

| Status | When |
| --- | --- |
| 400 | Malformed JSON or an invalid identifier format |
| 404 | The resource does not exist |
| 409 | The operation is not allowed in the resource's current state, a uniqueness conflict, or an optimistic-lock conflict |
| 422 | The request is well formed but a value is invalid |
| 500 | Unexpected error; no internal details are exposed |

Each domain exception maps to one stable problem `type`; the list is maintained next to the exception mapping in code and exposed in the OpenAPI description.

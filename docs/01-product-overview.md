# Product overview

## Purpose

A back-office CRM API for a small sales team. It tracks a potential customer from the moment they show interest until the sale is won or lost.

## The process

```
            capture        contact         qualify          convert
 (nothing) ────────► New ─────────► Contacted ─────► Qualified ─────► Converted
                      │                 │                │                │
                      └──── disqualify ─┴────────────────┘                │
                                        ▼                                 │ LeadConverted
                                  Disqualified                            ▼
                                                        ┌─────────────────┴────────────────┐
                                                        ▼                                  ▼
                                               Customer registered                    Deal opened
                                                                                           │
                                             Discovery ─► Proposal ─► Negotiation ─► Won   │
                                                  └───────────┴────────────┴───────► Lost ◄┘
```

1. **Capture.** A lead (a person, optionally from a company, with an email address and a source) is entered into the system.
2. **Assign and contact.** A sales representative is assigned as the owner and records that the lead was contacted.
3. **Qualify or disqualify.** After contact, the owner either qualifies the lead by estimating its value or disqualifies it with a reason.
4. **Convert.** A qualified lead is converted. This closes the lead and, as a consequence, registers a customer and opens a deal for that customer with the estimated value.
5. **Work the deal.** The deal advances through its stages until it is won or lost.

## Users

One role: the sales representative. Every authenticated user may perform every operation. Authentication arrives in the last milestone; until then the API is open and the acting representative is passed explicitly where the process needs one.

## Scope

In scope:

- Lead lifecycle: capture, assign, contact, qualify, disqualify, convert
- Customers: created from converted leads, listed, viewed, archived
- Deals: created from converted leads, advanced, won, lost
- Listing and filtering for all three

Out of scope, on purpose:

- A user interface
- Roles, permissions and teams
- Activities, notes, tasks, email integration
- Products, quotes, invoicing
- Reporting and forecasting
- Multi-tenancy

The scope is kept small so that every part of it can be built to a production standard.

## Quality goals

| Goal | How it is met |
| --- | --- |
| Business rules cannot be bypassed | Invariants live in aggregates and are unit tested without a framework |
| Modules stay independent | Communication only through a module's `Contracts`, with integration events for every side effect; boundaries checked by Deptrac |
| No lost or phantom events | Transactional outbox, idempotent consumers |
| Predictable API | One delivery channel that owns the conventions, RFC 9457 errors, generated OpenAPI description |
| Reproducible environment | Everything runs in Docker through one Makefile |

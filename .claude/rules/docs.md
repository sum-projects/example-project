---
paths:
  - "docs/**"
---

# Documentation

- Each rule has one source of truth. Architecture rules live in `docs/03-architecture.md`; `CLAUDE.md` and `.claude/rules/` hold digests that use the same rule identifiers. When a rule changes, change the source first and then every digest that mentions it.
- The body of an accepted ADR is never edited. A changed decision is a new ADR; the old one only gets a new Status line that names it. Add every ADR to `docs/adr/README.md`.
- An ADR is written for a decision with a real alternative, for every deviation from a rule marked Default, and for every approved change to a rule marked Invariant.
- Describe what the code does now. Verify each statement against the code or the installed version before writing it.
- English only. Edit in place; no change logs or "updated on" notes.

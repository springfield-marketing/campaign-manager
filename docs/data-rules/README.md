# Data Rules — edge-case registry

This folder is the **single source of truth for *why*** our import/export logic
handles the edge cases it does. Code tells you *what* happens; this tells you
*why*, *what real incident forced it*, and *how to verify it still holds* —
without code archaeology or relying on memory.

## How it works

Each edge case gets a stable ID and an entry in one of:

- [`imports.md`](imports.md) — IDs prefixed **`IMP-`**
- [`exports.md`](exports.md) — IDs prefixed **`EXP-`**

That ID is the thread that ties three things together:

1. **The registry entry** (here) — the full story: symptom, root cause, the rule,
   trade-offs, and a link to the proof.
2. **A breadcrumb comment in the code** — a one-liner at the guard, e.g.
   `// IMP-001: …  See docs/data-rules/imports.md`. Anyone reading the code is
   one search away from the reasoning.
3. **A regression test** named after the ID, so the rule can't silently regress
   and the test itself documents the intent.

To find everything about a rule, grep the repo for its ID (e.g. `IMP-001`).

## Adding a new edge case

1. Pick the next ID in the relevant file.
2. Copy the entry template (top of `imports.md` / `exports.md`) and fill it in.
3. Add the `// <ID>: … See docs/data-rules/<file>` breadcrumb at the code guard.
4. Add or extend a regression test referencing the ID in its docblock.
5. Reference the ID in the commit message.

## Running the regression tests

The tests in `tests/Feature/Modules/` use `RefreshDatabase`. The phpunit config
points at SQLite `:memory:`, but several migrations are Postgres-only and
unguarded, so the suite must be run against a **dedicated Postgres test
database** (never the dev DB — `RefreshDatabase` wipes it). When verifying a
rule manually against live data, wrap the check in a `DB::beginTransaction()` /
`DB::rollBack()` so nothing persists.

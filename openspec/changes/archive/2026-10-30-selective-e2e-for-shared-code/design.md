# Design

## Context

See proposal.md — Why. The current rule (`CLAUDE.md`, `docs/e2e-testing.md`, `openspec/config.yaml` → `rules.tasks`) has two levels: the spec of the changed area during work, and a full run after any edit of "shared code" (the shell/sidebar, `DataTable`, authentication, i18n, `playwright.config.js`, `e2e/global-setup.js`). The consumers of `DataTable` in the code: `Pages/Admin/ContentTypes/Index.tsx`, `Pages/Admin/ContentFields/Index.tsx`, `Pages/Admin/Ui/DataTableDemo.tsx`. The pages of types and blocks are opened on the pages of the section of types, so their specs also depend on these components (this is checked in task 1.1).

## Goals / Non-Goals

**Goals:**
- For a shared component run only the dependent specs; keep the full set for changes that touch everything.
- Make the choice transparent through a report of the run and skipped specs, without an extra confirmation step.

**Non-Goals:**
- Speeding up the tests, CI, `--only-changed` (it does not see the application code).
- An automatic tool that derives the dependencies (a table in docs and a search for uses are enough).

## Decisions

- **Three levels instead of two.** (1) The area is changed — its spec. (2) A shared component — the specs of its consumers. (3) The config/`global-setup`/authentication/shell — the full set. i18n moves from level 3 to level 2: it is shared code, but its impact is enough to list by the specs that check the labels (defined in task 1.1 by the table).
- **The source of truth is docs.** The column/table "Shared code → specs" in `docs/e2e-testing.md` complements the existing "area → spec"; in addition, the consumers are checked by searching the use of the component (`grep` of imports). A mismatch of the table with the code is a signal to fix it.
- **A report instead of a confirmation.** The choice does not need a question: it is a repeatable mechanic, and a mistake is visible in the report. A question is asked only in two cases — the boundary cannot be determined from the code ("no assumptions" in `CLAUDE.md`) or the set is almost full (then it is simpler to run the full one).
- **Maintaining the table.** A new spec or a new consumer updates the table in the same change; this is fixed by the rule `tasks` in `openspec/config.yaml`.
- **Where the rule lives.** `CLAUDE.md` (Playwright) — the daily work; `openspec/config.yaml` → `rules.tasks` — so that the tasks do not require a full run for a shared component; `docs/e2e-testing.md` — the details and the tables.

## Risks / Trade-offs

- [An incorrectly determined dependency skips a regression] → the report lists what was skipped with a reason, and the table and the search for uses limit the error; in a doubtful case — a question or the full set.
- [The table becomes outdated] → the update is part of each change with a new spec/consumer (the rule `tasks`).

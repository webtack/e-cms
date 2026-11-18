# Design

## Context

See proposal.md — Why. Right now `playwright.config.js` has `workers: 1` (the specs share the seeded user and `users.locale`), so a full run is sequential and grows linearly with the number of specs (`smoke`, `admin-shell`, `admin-i18n`, `admin-data-table`, `admin-content-types`, `admin-content-fields`, `admin-page-types`). The tasks in OpenSpec changes contain the check "`npm run test:e2e` is green". The commands for running are described in `docs/e2e-testing.md` and `CLAUDE.md`; `e2e/global-setup.js` resets the language regardless of the set of specs.

## Goals / Non-Goals

**Goals:**
- Shorten the cycle "edit → check" without losing the safety net against regressions: the full set is run where a regression is most likely — after changes of shared code.
- Make the rule explicit for a person and for an agent (CLAUDE.md, `openspec/config.yaml`), so that tasks no longer require a full run every time.

**Non-Goals:**
- Speeding up the tests themselves (parallelization, sharding, CI) — separate changes.
- Automatic selection of specs by changes in the application: Playwright `--only-changed` analyses only the changed test files and their imports, so an edit of `ContentTypeForm.tsx` would not run a single spec.

## Decisions

- **Two levels of running.** (1) During work — selective: `npm run test:e2e -- e2e/<спек>`; the spec is chosen by the table "area → spec" in `docs/e2e-testing.md`; if the change does not touch the browser behaviour, e2e is skipped. (2) The full `npm run test:e2e` — only after changes of shared code (the shell/sidebar, `DataTable`, authentication, i18n, `playwright.config.js`, `e2e/global-setup.js`). A full run at the end of each OpenSpec change is rejected: by then the changed specs have already passed selectively, and the rest does not depend on the changed code, so a run would only repeat the checks. A scheduled run is rejected: the project has no CI.
- **No new command is needed.** `npm run test:e2e -- <путь>` already passes arguments to Playwright, and `globalSetup` always runs. A separate npm script for each spec would duplicate the list of files and would diverge from it, so we only document it; the spec records the requirement that this continues to work.
- **Where the rule lives.** `CLAUDE.md` (Playwright) — for everyday work; `openspec/config.yaml` → `rules.tasks` — so that the generated tasks contain the selective check, and the full run only where shared code is touched; `docs/e2e-testing.md` — the section "When to run e2e" with a correspondence table.
- **The correspondence table** (the initial one, in the docs): the shell/navigation → `admin-shell`; the language → `admin-i18n`; the tables and columns → `admin-data-table`; the section of content types → `admin-content-types`; the fields of a type → `admin-content-fields`; the page types → `admin-page-types`; anything shared → the full run.

## Risks / Trade-offs

- [A regression in an "other" spec is not noticed if the change was considered insignificant] → the list of shared code is explicit in CLAUDE.md; in doubtful cases the full run is chosen.
- [The table "area → spec" becomes outdated] → its update is part of the task of each change that adds a new spec (a row in the rule `tasks`).

# Proposal

## Why

The rule "a full `npm run test:e2e` after any edit of shared code" is too crude: a change of `DataTable` needs only the specs that use it (now 5 of 9), not `smoke` or `admin-i18n`. The full set runs in one thread and grows with the number of specs, so development is slowed down without a benefit for the protection against regressions.

## What Changes

- For changes in a **shared component** (for example, `DataTable`) only the dependent specs are run. They are determined by the use of the component in the application and by the new column "Shared code → specs" in the table of `docs/e2e-testing.md`.
- The **full run** `npm run test:e2e` remains only for changes that touch all specs: `playwright.config.js`, `e2e/global-setup.js`, authentication, the shell/sidebar.
- At the end of a task/edit the report **always names the run and skipped specs and the reason for skipping**. Before the run we do not ask about the choice; we ask only when the boundary of the impact cannot be determined from the code or the set has become almost full.
- A new spec or a new consumer of a shared component updates the column "Shared code → specs" (next to the existing row "area → spec").
- The rule is fixed in `CLAUDE.md` (the Playwright section), `openspec/config.yaml` (`rules.tasks`) and `docs/e2e-testing.md` (the section "When to run e2e").

## Capabilities

### New Capabilities

### Modified Capabilities
- `e2e-testing`: a requirement is added about the choice of specs for changes of shared code and about the report with the run and skipped specs.

## Impact

- Rules: `CLAUDE.md`, `openspec/config.yaml`. This does not change the application code or the tests.
- Docs: update `docs/e2e-testing.md` (the section "When to run e2e", a new table "Shared code → specs"); `docs/README.md` — unchanged, the file name does not change.
- Not included: parallelization (`workers`), CI, `--only-changed` (it does not see the application code).

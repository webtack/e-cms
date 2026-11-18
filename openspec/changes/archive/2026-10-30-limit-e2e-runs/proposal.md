# Proposal

## Why

The set of e2e tests grows with every change (now 7 specs, `workers: 1`), and the tasks in OpenSpec changes require "`npm run test:e2e` is green" after every edit. A full run each time is longer, so development slows down. A rule is needed: during work only the specs that the change touches are run, and the full set only after changes of shared code.

## What Changes

- A new run policy: **during work** (each task/edit) only the e2e spec that covers the changed area is run (`npm run test:e2e -- e2e/<файл>.spec.js`), or none, if the change does not touch the behaviour in the browser (only PHP logic with Feature tests, documentation, translations without a UI change).
- **The full run** `npm run test:e2e` is performed only after changes of shared code on which all specs depend (the shell/sidebar, `DataTable`, authentication, i18n, `playwright.config.js`, `e2e/global-setup.js`). There is no separate full run at the end of an OpenSpec change: the needed specs have already been run selectively.
- The rules are fixed in `CLAUDE.md` (the Playwright section), in `openspec/config.yaml` (the rule for `tasks`: do not require a full run in the tasks, except those that touch shared code) and in `docs/e2e-testing.md` (the section "When to run" with the correspondence "area → spec").
- The selective run of a spec keeps working: `globalSetup` (the language reset) runs also for a single file.

## Capabilities

### New Capabilities

### Modified Capabilities
- `e2e-testing`: a requirement about the selective run of individual specs with the same command is added.

## Impact

- Configuration/rules: `CLAUDE.md`, `openspec/config.yaml`. This does not change the application code or the tests.
- Docs: update `docs/e2e-testing.md` (the section "When to run e2e", the commands of the selective run, the table "area → spec"); `docs/README.md` — unchanged, if the file name does not change.
- Not included: parallelization (`workers`), CI, `--only-changed` (it sees only the changed test files, not the application code, so it would wrongly skip specs).

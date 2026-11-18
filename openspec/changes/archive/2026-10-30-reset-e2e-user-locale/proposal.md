# Proposal

## Why

The data of the seeded administrator is shared by all e2e specs, and the choice of language is stored in `users.locale` and has a higher priority than `Accept-Language`. If a scenario that switches the interface to Ukrainian (`admin-i18n`, the Ukrainian checks in `admin-data-table` and `admin-content-types`) is interrupted before `English` is restored, the next run opens the admin in Ukrainian and most tests fail on English labels. This has already happened twice; right now the only way out is to run the `tinker` command manually.

## What Changes

- Before each run of `npm run test:e2e`, Playwright automatically restores the stored language of the seeded administrator to `en` (a global setup), so a leftover Ukrainian no longer breaks the run.
- The manual reset command from `docs/e2e-testing.md` is replaced by a description of the automatic behaviour (the manual command remains as a fallback).
- The existing `afterEach`/`finally` in the specs remain: they protect against a language leak between specs within one run.

## Capabilities

### New Capabilities

### Modified Capabilities
- `e2e-testing`: a requirement is added that an e2e run starts with the English interface of the seeded administrator regardless of the state left by the previous (interrupted) run.

## Impact

- Code: the new `e2e/global-setup.js`, the connection in `playwright.config.js`.
- Documentation: update `docs/e2e-testing.md` (the sections on structure, on the isolation of the language state and the closing paragraph about `users.locale`). `docs/README.md` does not change (there are no new files in `docs/`).
- The application's dependencies and PHP code do not change.

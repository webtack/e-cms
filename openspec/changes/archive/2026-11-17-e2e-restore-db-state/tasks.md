# Tasks

## 1. Snapshot and restore helper

- [x] 1.1 Add `e2e/db-snapshot.js` with JSDoc (English, `@example`): `takeSnapshot()` dumps `app_settings`, `app_languages`, `admin_menu_entries`, `user_favorite_types` and the `site_setting` rows of `media_relations` to `e2e/.auth/db-snapshot.json` via `tinker`; `restoreSnapshot()` replaces these rows in one transaction (parents of `admin_menu_entries` first) and deletes the file only after success. Verify: with `tinker`, change `site.name`, add a language, call restore from a node one-liner, and see the original rows back.

## 2. Wiring into the run

- [x] 2.1 In `e2e/global-setup.js`, restore a leftover snapshot first, then take a new one before the administrator is prepared; add `e2e/global-teardown.js` that restores it and register `globalTeardown` in `playwright.config.js`. Verify: set a site name and a language in `/admin/settings`, run `npm run test:e2e -- e2e/admin-site-settings.spec.js e2e/admin-content-languages.spec.js`, and see the name and the language unchanged after the run.
- [x] 2.2 Check the interrupted run: start `npm run test:e2e -- e2e/admin-site-settings.spec.js`, stop it with Ctrl+C mid-run, confirm `e2e/.auth/db-snapshot.json` exists, run `npm run test:e2e -- e2e/smoke.spec.js` and confirm the original data is back. Verify: the data matches the state before the first run.
- [x] 2.3 Run the full `npm run test:e2e` once (the task changes `playwright.config.js` and `e2e/global-setup.js`) and confirm the covered data is unchanged and no test fails because of the snapshot.

## 3. Documentation

- [x] 3.1 Update `docs/e2e-testing.md` (English): the snapshot and restore, the covered tables, recovery after an interrupted run, the note that edits made in the browser during a run are overwritten; adjust the "Site settings" and "Content languages" scenario paragraphs, which say the settings are deleted. Verify: `grep -n "db-snapshot" docs/e2e-testing.md` finds the section and the `docs/README.md` links stay valid.

# Design

## Context

E2E specs seed and clean data through `tinker` in the `app` container and share the database with manual work (see `docs/e2e-testing.md`). Specs wipe state in their own `reset` blocks (`admin-site-settings`, `admin-content-languages`, `admin-favorites-menu`, `admin-dashboard`, `admin-shell`, `resetTestLanguages` in `e2e/support.js`). `workers: 1`, a single `globalSetup` already exists in `e2e/global-setup.js`. The project directory is bind-mounted into `ecms_app` (`./:/var/www/html`), so a file under `e2e/.auth/` (already git-ignored) is visible on both sides.

## Goals / Non-Goals

**Goals:**
- After any run the covered data equals the data before the run.
- No changes to the existing specs.

**Non-Goals:**
- A separate e2e database or container.
- Restoring data created by specs under the `e2e_` / `e2e-` prefixes; their cleanup already exists.
- Protecting against manual edits made in the browser while the run is in progress.

## Decisions

1. **Snapshot scope: whole tables, except `media_relations`.** `app_settings`, `app_languages`, `admin_menu_entries`, `user_favorite_types` are dumped completely; from `media_relations` only rows with `owner_type = 'site_setting'` (logos). Restore deletes the covered rows and inserts the saved ones in one transaction, keeping ids (`admin_menu_entries.parent_id` is a self reference, so rows are inserted ordered by parent first, or with the same ids in one batch). Alternative: only the keys the specs touch (`site.%`, `content.%`, `dashboard.%`). Rejected: a spec that starts touching a new key would silently lose data again; whole tables are robust to that.
2. **Snapshot lives in `e2e/.auth/db-snapshot.json`, written by PHP in the container.** No data goes through process output or arguments, and nothing needs parsing on the host. The path is already git-ignored. Alternative: keep it in memory in the Node process; rejected because it would not survive Ctrl+C, which is the main failure case.
3. **Restore in `globalTeardown`, plus recovery in `globalSetup`.** Playwright runs `globalTeardown` after failed tests too. If the file exists when `globalSetup` starts, it is restored first, then deleted, then a fresh snapshot is written. The file is deleted only after a successful restore, so a failed restore keeps the data recoverable.
4. **Logo files are not snapshotted.** Specs delete only files named `e2e-%`; the logo files of the developer stay in `files`, only their relations are dumped and restored, so `FileStorage` is not needed.
5. **Code in one helper `e2e/db-snapshot.js`** with `takeSnapshot()` and `restoreSnapshot()`, each running a PHP snippet through the existing `tinker` helper, used by `global-setup.js` and `global-teardown.js`.

## Risks / Trade-offs

- [Run is killed with `kill -9`, teardown does not run] → the snapshot file stays and the next run restores it; the developer can also restore it by running any spec.
- [Restore fails, for example the container is stopped] → teardown throws an error naming the snapshot file, the file is kept.
- [A restored dump conflicts with rows created by specs] → covered rows are deleted before the insert, inside a transaction.
- [Developer works in the browser while the run is in progress] → their changes are overwritten by the restore; documented as a non-goal.
- [Run time] → two extra `tinker` calls (about 0.7 s) per run.

## Docs

`docs/e2e-testing.md` describes the components: the snapshot, the covered tables and the recovery.

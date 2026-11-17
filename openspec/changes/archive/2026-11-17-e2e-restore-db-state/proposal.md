# Proposal

## Why

The e2e suite runs against the same database as manual work on `http://localhost:8080`. Several specs wipe real data to start from a clean installation: `admin-site-settings` deletes every `site.%` setting and the logo relations, `admin-content-languages` deletes all rows of `app_languages` and the `content.*` settings, `resetTestLanguages` forgets `content.multilingual` and `content.base_lang` in eight specs, `admin-shell` forgets `site.name`, and `admin-favorites-menu` / `admin-dashboard` clear the admin menu, favorites and `dashboard.create_types`. After a run the developer's site name, contacts, languages and menu are gone.

## What Changes

- `globalSetup` takes a snapshot of the state the specs wipe (`app_settings`, `app_languages`, `admin_menu_entries`, `user_favorite_types`, and the `site_setting` rows of `media_relations`) and saves it to `e2e/.auth/db-snapshot.json`.
- A new `globalTeardown` restores the snapshot after the run, including runs of a single spec and failed runs.
- A snapshot left by an interrupted run (Ctrl+C, crash) is restored at the start of the next run, before a new snapshot is taken, so the polluted state is never saved as the "original".
- Specs keep their own cleanup and are not changed.
- `docs/e2e-testing.md` documents the snapshot, the covered tables and the recovery after an interrupted run.

## Capabilities

### New Capabilities

### Modified Capabilities
- `e2e-testing`: an e2e run returns the settings, languages, admin menu and favorites to their pre-run state.

## Impact

- Code: `e2e/global-setup.js`, new `e2e/global-teardown.js` (and a shared snapshot helper in `e2e/`), `playwright.config.js` (`globalTeardown`).
- Docs: `docs/e2e-testing.md` (sections "Saved administrator session" neighbourhood and the site settings / languages scenarios).
- Not covered: content types, nodes, taxonomy and users created by specs; they use the `e2e_` / `e2e-` prefixes and are already removed by their own cleanup.

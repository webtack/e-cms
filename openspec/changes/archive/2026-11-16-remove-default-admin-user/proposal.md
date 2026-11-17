# Proposal

## Why

The seeded administrator `admin@example.com` / `password` (user 1) exists in the development database and is also the account that the e2e tests log in with. The default `password` is guessable, so no account with such a password may exist in a database or be recreatable by the tooling. The real authors of the content already live under other users (283 and others), so user 1 only owns a few leftovers (12 nodes, 23 media files) and is needed only by the tests.

## What Changes

- Move the authorship of user 1 to user 283: `content_nodes.user_id` (12 rows) and `media.user_id` (23 rows). This is a one-off operation on the existing database, not a schema change.
- User 1 stays, but only as the e2e account: its email becomes `e2e-admin@example.com` and its password the value of `E2E_ADMIN_PASSWORD`; its sessions are deleted. Keeping id 1 keeps the specs that take the node author as "the first user" working without edits.
- `e2e/global-setup.js` creates (or updates) the e2e administrator by `E2E_ADMIN_EMAIL` (default `e2e-admin@example.com`) with the administrator role and the password from `E2E_ADMIN_PASSWORD`, resets its language to English, and signs it in once. A fresh database gets the account from this step. A run without `E2E_ADMIN_PASSWORD` fails with a clear error; there is no default password.
- `DatabaseSeeder` no longer falls back to `admin@example.com` / `password`: `ADMIN_EMAIL` and `ADMIN_PASSWORD` are required in every environment, so a re-seed can never recreate a weak account.
- `.env.example` and the documentation drop the weak defaults.
- **BREAKING**: running `db:seed` or `npm run test:e2e` without the corresponding variables now fails instead of using `admin@example.com` / `password`.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-auth`: the initial administrator is created only from explicitly provided credentials; there is no default email or password in any environment.
- `e2e-testing`: the e2e suite logs in as its own administrator whose credentials come from the environment (no default password), and the language reset and the single login apply to that account.

## Impact

- Code: `database/seeders/DatabaseSeeder.php`, `e2e/global-setup.js`, `e2e/support.js`, `.env.example`.
- Tests: `tests/Feature/Auth/DatabaseSeederTest.php` (no `env()` defaults, covers the new requirement). E2e specs are not edited; the ones that depend on the first user are verified by targeted runs.
- Data: the local database (user 1 renamed and re-secured, its sessions deleted, 12 nodes and 23 media files moved to user 283).
- Docs: `docs/admin-auth.md` (seeder section, procedure for an existing installation), `docs/e2e-testing.md` (credentials, global setup, `E2E_ADMIN_PASSWORD`), `docs/README.md` only if links change.
- Out of scope: the historical migration `2026_10_31_100000_add_service_attributes_to_content_nodes_table` keeps its `ADMIN_EMAIL` fallback because it only backfills authors of pre-existing nodes.

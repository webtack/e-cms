# Design

## Context

See proposal.md for the motivation. Facts that shape the approach (read from the code and the local database):

- `content_nodes.user_id` is `restrictOnDelete`; `media.user_id` is `nullOnDelete`; `sessions.user_id` has no cascade. User 1 owns 12 nodes and 23 media files and has 24 sessions; `user_favorite_types` holds no rows of user 1.
- PHPUnit runs on sqlite `:memory:` with factories, so it does not touch the development user 1. Only `DatabaseSeederTest` reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` with `env()` defaults.
- The e2e suite depends on user 1 in two ways: `e2e/support.js` and `e2e/global-setup.js` log in as the seeded administrator, and most specs take the node author with `User::query()->value('id')` (the lowest id, now user 1). `admin-content-nodes.spec.js` reads the author name through the same query.
- Playwright runs on the host and does not read Laravel's `.env`, so the e2e credentials must come from the host environment.
- Docs: `docs/admin-auth.md` (seeder), `docs/e2e-testing.md` (credentials, global setup).

## Goals / Non-Goals

**Goals:**
- No account with a guessable password remains in the database or can be recreated by the seeder or the test tooling.
- The e2e suite works with a non-default password supplied through the environment, and its specs need no edits.

**Non-Goals:**
- Changing `ADMIN_EMAIL` handling in the historical migration `add_service_attributes_to_content_nodes_table` (it only backfills authors of old nodes).
- A generic "transfer authorship" feature in the admin panel or a reusable artisan command.
- Making the specs independent of "the first user" as the node author (it only works because id 1 remains the lowest id).

## Decisions

**1. User 1 becomes the e2e account instead of being deleted.** Its email changes to `e2e-admin@example.com` and its password to `E2E_ADMIN_PASSWORD`; its sessions are deleted so no old session survives. Its authorship moves away first (decision 2), so it owns no real content. Alternatives: delete it and give the e2e suite a new account with a higher id (rejected: every spec that takes "the first user" as author would silently switch to another real user, which would need either spec edits or a fragile dependence on user 32), and keep `admin@example.com` / `password` locally (rejected: the weak account would live in a database that can be dumped to a public instance).

**2. The data move is a documented one-off, not a migration or a new command.** Order: verify that user 283 exists and has the administrator role, update `content_nodes.user_id` and `media.user_id` from 1 to 283, then re-secure user 1 (decision 1). The steps run through `artisan tinker` in one transaction and are listed in tasks.md with their verification counts (12 nodes, 23 media files). Alternatives: a migration (would also run on installations where user 283 does not exist) and an artisan command (public code for a single use).

**3. `global-setup` owns the e2e account state.** `e2e/global-setup.js` runs one tinker call that does `updateOrCreate` on `E2E_ADMIN_EMAIL` (default `e2e-admin@example.com`) with the administrator role, `is_active = true`, a verified email, the password from `E2E_ADMIN_PASSWORD` and `locale = en`. It replaces the current locale-only reset. On the local database it finds user 1 by the new email (see decision 1), so it must run after the data step; on a fresh database it creates the account. The password reaches tinker as an environment variable of the child process, not interpolated into the PHP code, so quotes in a password cannot break it and it does not appear in the process arguments. `E2E_ADMIN_PASSWORD` has no default; `global-setup` throws before any browser work when it is missing. `adminEmail` / `adminPassword` in `e2e/support.js` read `E2E_ADMIN_EMAIL` / `E2E_ADMIN_PASSWORD`, and `ADMIN_EMAIL` / `ADMIN_PASSWORD` are no longer used by the e2e suite.

**4. The seeder requires both variables everywhere.** `DatabaseSeeder` drops the `app()->isProduction()` branches and defaults, and throws a `RuntimeException` naming the missing variables before touching the database. `.env.example` keeps the two keys with empty values and a comment. The local `.env` already defines the variables for the seeder; the developer sets strong values there. Alternative: keep the dev default and only warn (rejected: the weak account could return on every fresh `migrate --seed`).

**5. `DatabaseSeederTest` sets its own credentials.** The test sets `ADMIN_EMAIL` / `ADMIN_PASSWORD` itself (a long password defined in the test) instead of relying on `env()` defaults, and adds a case for the missing variables and a case that `admin@example.com` is not created for another `ADMIN_EMAIL`.

## Risks / Trade-offs

- [On a fresh database the e2e account is created by the first e2e run and gets a higher id than the seeded administrator, so "the first user" is then the seeded administrator] → Acceptable: specs only need some existing author, and `admin-content-nodes` reads the name through the same query. The targeted runs confirm it.
- [Order matters: if `global-setup` runs before the data step on the local database, it creates a second account and leaves user 1 with the weak password] → Tasks order the data step before the first e2e run with the new setup, and a check confirms that no user with `admin@example.com` remains.
- [Another installation still has the seeded `admin@example.com` / `password`] → The docs describe how to rename or re-secure it; the procedure from decision 2 is recorded there.
- [A developer runs e2e without `E2E_ADMIN_PASSWORD`] → The run fails once with a clear message that is documented in `docs/e2e-testing.md`.
- [Specs that assumed the signed-in administrator is `admin@example.com`] → `admin-users` and `admin-profile` are run; a failure is fixed in the spec.

## Migration Plan

1. Land the seeder, test and docs changes.
2. Set a strong `ADMIN_PASSWORD` in `.env` and export `E2E_ADMIN_PASSWORD` on the host.
3. Run the data step from decision 2 on the local database (move authorship, re-secure user 1).
4. Land the e2e changes and run the full e2e suite once.

Rollback: code is a plain revert; the data step is deliberately not reverted (it would restore the weak account).

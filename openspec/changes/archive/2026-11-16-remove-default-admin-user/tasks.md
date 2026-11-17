# Tasks

## 1. Seeder without default credentials

- [x] 1.1 In `database/seeders/DatabaseSeeder.php` remove the `admin@example.com` / `password` defaults and the `isProduction()` branches; throw a `RuntimeException` naming `ADMIN_EMAIL` / `ADMIN_PASSWORD` when either is missing, before any write. Update the class docblock. Verify: `docker compose exec app php artisan db:seed` with an empty `ADMIN_PASSWORD` fails with the message and creates no user.
- [x] 1.2 Update `tests/Feature/Auth/DatabaseSeederTest.php`: set the credentials inside the test (no `env()` defaults), keep the login and idempotency cases, add cases for a missing variable (no user created) and for `admin@example.com` not being created when `ADMIN_EMAIL` is another address. Verify: `docker compose exec app php artisan test --compact --filter=DatabaseSeederTest` passes; run `docker compose exec app vendor/bin/pint --dirty --format agent`.
- [x] 1.3 Set `ADMIN_EMAIL=` and `ADMIN_PASSWORD=` (empty, with a comment that they are required) in `.env.example`, and rewrite the "Initial administrator (seeder)" section of `docs/admin-auth.md` (no defaults, failure when missing, the warning that an `admin@example.com` / `password` account must not exist on a reachable installation). Verify: the doc and `.env.example` contain no `password` default; links in `docs/README.md` stay valid.

## 2. Move authorship and re-secure user 1

- [x] 2.1 On the local database, check the preconditions through tinker: user 283 exists and has the administrator role; user 1 owns 12 nodes and 23 media files. Verify: the counts are printed and match; stop if they differ.
- [x] 2.2 In one transaction through tinker: set `content_nodes.user_id` and `media.user_id` from 1 to 283; set user 1's email to `e2e-admin@example.com` and its password to `E2E_ADMIN_PASSWORD` (read from the environment, not typed into the command); delete the `sessions` rows of user 1. Verify: no row in `content_nodes` or `media` references user 1; user 283 has 190 nodes (178 + 12) and 637 media files (614 + 23); no user has the email `admin@example.com`; `Hash::check('password', user1.password)` is false.
- [x] 2.3 Record in `docs/admin-auth.md` the procedure for neutralising a seeded `admin@example.com` / `password` account on an existing installation (move nodes and media to a real author first, then change the email and password or delete the account). Verify: the steps match what was run in 2.2.

## 3. E2E administrator

- [x] 3.1 In `e2e/support.js` read `adminEmail` from `E2E_ADMIN_EMAIL` (default `e2e-admin@example.com`) and `adminPassword` from `E2E_ADMIN_PASSWORD` (no default; accessing it without the variable throws an error naming it). Update the JSDoc and `@example` blocks, including the `'password'` examples. Verify: `grep -n "'password'" e2e/support.js` finds nothing.
- [x] 3.2 In `e2e/global-setup.js` replace the locale-only reset with one tinker call that `updateOrCreate`s the e2e administrator by email (administrator role, active, verified email, `locale = en`, password from the child-process environment, not interpolated into the PHP code); fail with a clear error before any browser work when `E2E_ADMIN_PASSWORD` is missing; update the file's JSDoc and the error text of the login step. Verify: a run without the variable fails once with the message; a run with it signs in as user 1 (still id 1, not a new user) and `npm run test:e2e -- e2e/smoke.spec.js` passes.
- [x] 3.3 Fix the specs that still hard-code credentials of the seeded administrator, found with `grep -rn "admin@example\|ADMIN_" e2e`. Verify: the grep finds nothing.
- [x] 3.4 Update `docs/e2e-testing.md`: credentials section (`E2E_ADMIN_EMAIL` / `E2E_ADMIN_PASSWORD`, no defaults, how to export them, the account created or updated by global setup) and the helper description for `adminEmail` / `adminPassword`. Verify: no remaining mention of `ADMIN_EMAIL` / `ADMIN_PASSWORD` as e2e credentials in `docs/`.
- [x] 3.5 Run the full e2e suite once, because `e2e/global-setup.js` and the sign-in changed: `E2E_ADMIN_PASSWORD=<value> npm run test:e2e`. Verify: it passes, and the e2e account is still user 1 (no second administrator was created).

# Tasks

## 1. Migration set

- [x] 1.1 In the package, keep all demo migrations with identical file names except `0001_01_01_00000{0,1,2}_*` and `2026_11_07_100000_create_legacy_import_map`; register the directory with `loadMigrationsFrom` in the provider. Verify: a script lists the package migration files and compares names with the demo's `migrations` table rows (all package names exist in the demo DB: `docker compose exec app php artisan migrate:status`).
- [x] 1.2 Change the package copy of `2026_11_03_100000_create_roles_and_permissions` so it creates the `admin` role, leaves `users.role_id` nullable and does not update existing users; update the `2026_10_31_100000_…` migration to use the user resolver. Verify on an empty SQLite database that the full chain migrates (temporary host script) and that an existing user row is not given a role.
- [x] 1.3 Verify the extension migrations preserve rows: run them on a table seeded with two users (including the `avatar` column added by `2026_11_16_110000_add_avatar_to_users_table`) and assert both rows are intact, active, with `role_id` null and `avatar` null.

## 2. Access without a role

- [x] 2.1 Extend `HasEcmsAccess` with `initializeHasEcmsAccess()` merging `role_id`, `is_active` into fillable and the boolean cast. Verify: a user model using the trait accepts mass-assignment of `role_id` and casts `is_active`.
- [x] 2.2 In the package copy, guard `AccessResolver::role()` against `role_id = null` (empty permission set, no database query); type `role_id` as `?int` in `AccessResolver` (cache docblock and `load()`) and in the user contract/trait docblocks; make `UserPolicy` read `$target->role?->is_system` (a roleless target counts as non-system) and `UserController` read `$user->role?->name` and show a "No role" label (new `lang` key) in the users list. Verify with a script: a roleless user gets `allows()` false for every system permission, `level()`/`nodeLevel()`/`mediaLevel()` `none`, `canEditAnyContent()` false, and `forClient()` returns without error; policies deny `users`, `media` and `node:*`; an administrator can open, edit and delete a roleless user without an error and can assign them a role. Update `docs/access-control.md`.

## 3. Installer

- [x] 3.1 Add `AdminSeeder` (credentials from options/env, no default credentials in any environment, existing email promotes the role only) and verify with scripts: new admin created with verified email; rerun changes nothing; missing email or password throws naming the missing values and creates nothing; re-seeding after `admin@example.com` was deleted does not recreate it.
- [x] 3.2 Implement `EcmsInstallCommand` (`ecms:install`, options `--admin-email`, `--admin-password`, `--force`) with the steps from design.md, registered by the provider (console only). Verify in a temporary host: first run reaches a working admin login, second run reports all steps as done.
- [x] 3.3 Implement the user-model preparation (stock shape edit, otherwise print instructions) and the users-table precondition. Verify with fixtures: stock model edited once (idempotent), customised model not touched and a non-zero exit, UUID key stops before migrating.
- [x] 3.4 Write `docs/package-installer.md` (commands, options, the existing-users rule, limitations: `users` table and integer id), update `docs/admin-auth.md` (initial administrator) and `docs/README.md`; verify all relative links resolve.
- [x] 3.5 PHP tests: package suite arrives in `package-tests-on-testbench`; here report the scripts used. Because migrations, providers and authentication change, the full demo suite is NOT run (demo code is unchanged); the package suite will cover them in step 5.

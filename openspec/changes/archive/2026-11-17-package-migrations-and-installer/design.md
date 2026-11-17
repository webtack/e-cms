# Design

## Context

Observed in the demo (the number of migrations changes with every feature; count them with `ls database/migrations | wc -l`): three are Laravel defaults (`users`, `cache`, `jobs`; `0001_01_01_00000x`). `2026_10_28_190000_add_locale_to_users_table` and `2026_11_03_100000_create_roles_and_permissions` alter `users` (`locale`, `role_id`, `is_active`); `2026_11_16_110000_add_avatar_to_users_table` adds `avatar`. The roles migration creates the `admin` role, **updates every existing user to it**, then makes `users.role_id` NOT NULL. `2026_10_31_100000_add_service_attributes_to_content_nodes_table` backfills `content_nodes.user_id` from `ADMIN_EMAIL` when the table is non-empty (it uses the user model, replaced in step 1 by the resolver). `2026_11_07_100000_create_legacy_import_map` belongs to the legacy import that stays in the demo. `DatabaseSeeder` creates the admin with `updateOrCreate` and `Role::admin()`. See proposal.md for motivation.

Constraint: the host `users` table already exists, was created by a migration dated `0001_01_01_000000` (Laravel skeleton), so it precedes all dated package migrations.

`AccessResolver` reads `$user->role_id` and calls `load($user->role_id)`. Verified in `app/Access/AccessResolver.php` (`role()`, `load(int $roleId)`): there is no guard, so a `null` role raises a `TypeError` (the file is not strict-types, but a user function does not coerce `null` to `int`). Every public method goes through `role()`, and `forClient()` runs on each Inertia request, so a roleless user would get a 500 on every page instead of a denial. `UserController` also reads `$user->role->name` in the users list (the profile controller already uses `?->`). The demo never hits this because its `users.role_id` is NOT NULL.

## Goals / Non-Goals

**Goals:** a safe migration chain for fresh and existing hosts; one command to a working admin; no silent privilege grants.

**Non-Goals:** non-integer user keys or a differently named users table; consolidating the 35 migrations into a baseline; an interactive web installer.

## Decisions

**`loadMigrationsFrom`, same file names.** The migrations table of the demo already lists these names; keeping them means the demo is not re-migrated and a fresh host replays the full chain. Alternative: publish migrations into the host — rejected, package updates would not deliver schema changes. Alternative: a baseline squash — rejected, it needs a rename and breaks the demo history.

**Drop `create_users/cache/jobs` from the package** — the host owns them. The demo keeps its copies in its own `database/migrations` (it already ran).

**Roles migration is changed in place for the package, not the demo.** `role_id` becomes `nullable` permanently and the statement `UPDATE users SET role_id = admin` is removed; the `admin` role is still created. For the demo database (already migrated) nothing re-runs. For fresh hosts the result is "no user has a role until the installer assigns one". Alternative: keep the update but only when `users` has exactly zero or one row — rejected, still surprising.

**Null role handling.** In the package copy, `AccessResolver::role()` returns the empty set `['system' => false, 'levels' => []]` for a user whose `role_id` is `null`, without querying the database, so every ability is false and every scope is `none`. `role_id` is typed `?int` in the resolver (cache docblock, `load()`) and in the user contract/trait docblocks. `UserController` reads `$user->role?->name` and the users list shows a "No role" label (new `lang` key). A roleless user is also the *target* of admin actions: `UserPolicy` reads `$target->role?->is_system` and treats a roleless target as non-system (the `users` right then lets an administrator manage them; the account has no privileges to protect). The fix is made only in the package: the demo's `app/` is frozen and cannot hit `null` (NOT NULL column). The test that locks this in is written in `package-tests-on-testbench`.

**Trait with initializers.** `HasEcmsAccess` (step 1) gets `initializeHasEcmsAccess()` which merges `role_id` and `is_active` into `$fillable` and the `is_active` boolean cast, so a host `User` only needs `use HasEcmsAccess` and `implements EcmsUser`. `locale` stays non-mass-assignable (set only by `LocaleController`). Alternative: ask the host to edit `#[Fillable]` — rejected, error-prone.

**Installer.** One class `EcmsInstallCommand`, each step a small method returning done/skipped/failed:
1. Preconditions: users table named `users`, integer primary key, otherwise stop.
2. `vendor:publish --tag=ecms-config` (skips if present, unless `--force`).
3. `migrate` (the package paths are registered by the provider).
4. User model preparation: if `app/Models/User.php` matches the stock shape (`use HasFactory, Notifiable;`), insert the `use` import, `implements EcmsUser` and `use HasEcmsAccess`; otherwise print lines. Idempotent by checking for the trait name.
5. Administrator: `AdminSeeder` logic (email/password from options → env → prompt; never defaults, in any environment, so without interaction and without values it fails naming the missing ones — this keeps the existing `admin-auth` rule "no default account"). Existing email → confirm, assign role only, never change password unless given.
6. `storage:link` check; publishing of built assets (`ecms-assets`) is added in step 4 as one more step.
7. Print a summary and the login URL.
Alternative: a seeder only — rejected, it cannot edit the user model or prompt.

**Naming.** Config key `ecms`, view namespace `ecms`, tags `ecms-*`, command `ecms:install`. Existing `theme:check` and `image-styles:flush` keep their names because `docs/` and project rules mention them; adding `ecms:` aliases is a later, separate option.

Docs: new `docs/package-installer.md`; updates to `docs/admin-auth.md` (initial administrator) and `docs/access-control.md` (null role).

## Risks / Trade-offs

- [Host has users already; nobody can enter the admin] → The installer's promote flow, and the docs show `ecms:install --admin-email=…` for an existing account.
- [Editing the host's `User` by text insertion breaks formatting] → Only the stock shape is edited, a backup is not needed because the diff is shown; otherwise instructions are printed. Pint is not run on host code.
- [Migration order if a host's users table is created after the package's] → Documented requirement; the skeleton's date `0001_01_01_000000` guarantees order in standard apps.
- [`content_nodes.user_id` foreign key assumes `users.id` bigint] → Checked by the installer precondition.
- [`AccessResolver` throws `TypeError` for `role_id = null`, giving a 500 on every Inertia request of a roleless user] → Guard in `role()` (task 2.2) and a package test in step 5; without it the "no role, no access" rule would be a server error instead of a denial.
- [Changing the roles migration only in the package makes the demo and fresh schemas differ in `role_id` nullability] → The demo made it NOT NULL; the code must work with both. A package test (step 5) runs the AccessResolver against a user with a null role.

## Migration Plan

Fresh host: `composer require`, `php artisan ecms:install`. Existing demo database: nothing to run, the chain is already recorded; step 7 verifies `php artisan migrate:status` shows no pending package migrations.

## Open Questions

- Whether the installer should also seed sample content types. Deferred; does not affect specs or tasks.

### Audit 2026-11-17 (series deferred)

- **A fourth `users` extension (resolved).** `2026_11_16_110000_add_avatar_to_users_table` adds the nullable `users.avatar`. It is part of the "Users table extension" requirement and of task 1.3; the host model gets `avatarUrl()` and `MEDIA_OWNER` from the package contract and trait (step 1), which read this column.
- **Migration count (not recorded).** The number changes with every feature (33 at planning, 39 at the audit); the text no longer states it.
- **Admin credentials.** The `admin-auth` main spec already forbids default credentials in every environment (archived `remove-default-admin-user`); the deltas of this change were aligned with it and need no further change.

#### Addendum (2026-11-17): environment keys

The installer does not write or change the host's `.env` (apart from the administrator values given on the command line or already in the environment). The keys added to the demo's `.env.example` after planning (`SESSION_SECURE_COOKIE`, `LIMIT_*`) are not part of the installer; the limits have defaults in `config/limits.php` and are documented in the package's installation guide (`package-docs-and-history`, task 2.1).

#### Pre-start audit (2026-11-17, task 0.1)

Baseline commit: `45d2095`. `git diff --stat 124812e..45d2095` outside `openspec/` is empty: no code, config, route, middleware or docs changed since the security-hardening addendum, so the facts and counts of this series stay valid (532 files reference `App\` in `app/`, `config/`, `database/`, `routes/`, `themes/`, `resources/`, `tests/` and `bootstrap/` (564 with `docs/`); the next audit starts from this commit).

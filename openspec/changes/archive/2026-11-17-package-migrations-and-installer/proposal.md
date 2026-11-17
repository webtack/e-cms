# Proposal

## Why

The package must create its database schema in a host that already has its own `users` table, and a first administrator must be set up without writing a seeder by hand. The current migration set cannot be shipped as is: it creates Laravel-default tables the host already has, and the roles migration gives the `admin` role to **every existing user** — harmless in a fresh app, a privilege escalation in a host with existing accounts. This is step 3 of 7 (series in `package-scaffold-and-namespace`).

## What Changes

- Load the package migrations with `loadMigrationsFrom()` (no publishing, so package updates deliver new migrations). The chain keeps the **same file names** as in the demo, so the demo database, which already ran them, does not re-run anything.
- Remove from the package set: `0001_01_01_000000_create_users_table`, `…_create_cache_table`, `…_create_jobs_table`, and the legacy import map migration (stays in the demo).
- The migrations that alter `users` (`locale`, `role_id`, `is_active`, `avatar`) are kept as alterations of the host table.
- **BREAKING vs. the demo migration**: the roles migration no longer assigns `admin` to existing users and leaves `users.role_id` nullable. A user without a role has no CMS access. The installer assigns the role explicitly.
- Add the artisan command `ecms:install` (idempotent): publishes `config/ecms.php`, runs the package migrations, makes the user model use the CMS trait/contract (edits a default `User` automatically, prints exact instructions otherwise), creates or promotes the first administrator, and checks the storage link. Options: `--admin-email`, `--admin-password`, `--no-interaction`, `--force`.
- Replace the demo `DatabaseSeeder` admin logic by a package seeder `AdminSeeder` reused by the installer (same `ADMIN_EMAIL`/`ADMIN_PASSWORD` rules as today: no default credentials in any environment, explicit values required).
- Short public names: the config key `ecms`, view namespace `ecms`, publish tags `ecms-config`, `ecms-lang`, `ecms-views`, `ecms-assets` (registered in step 4), command prefix `ecms:`. The existing commands `theme:check` and `image-styles:flush` keep their names (documented in `docs/` and project rules).

## Capabilities

### New Capabilities

- `package-installer`: database schema delivery in a host application, the installer command, the rules for the first administrator and for existing users.

### Modified Capabilities

- `access-control`: a user without a role has no access, and existing accounts are never promoted automatically.
- `admin-auth`: the initial administrator is created by the installer/seeder with explicitly provided credentials in every environment (no defaults); the system role `admin` is assigned.

## Impact

- New: `src/Console/Commands/EcmsInstallCommand.php`, `database/seeders/AdminSeeder.php`; changed migrations (roles, users alterations, content_nodes service attributes).
- Package `composer.json` (no change to provider discovery from step 2); provider registers migrations and the command.
- Docs (package): `docs/package-installer.md` (new), `docs/admin-auth.md`, `docs/access-control.md`, `docs/README.md` index.

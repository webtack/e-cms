# Spec Delta

## Purpose

Defines how the package's database schema is delivered into a host application and how a first administrator is set up, without disturbing the host's existing users.

## ADDED Requirements

### Requirement: Migrations ship with the package
The package SHALL provide its migrations through the package itself, so they run with the host's `php artisan migrate` and updates of the package deliver new migrations without a publish step. The package SHALL NOT create the host's `users`, `cache`, `jobs`, `sessions` or `password_reset_tokens` tables.

#### Scenario: Migrating a fresh host
- **WHEN** the host runs its migrations after installing the package
- **THEN** all CMS tables exist and the host's own tables are untouched

#### Scenario: Already migrated database
- **WHEN** a database that ran the CMS migrations before they were packaged is migrated again
- **THEN** no CMS migration runs a second time

### Requirement: Users table extension
The package SHALL extend the host `users` table with a nullable role reference, an activity flag (default active), an interface language and an optional avatar reference, and SHALL NOT change existing rows' meaning or any other column.

#### Scenario: Existing accounts
- **WHEN** the extension migration runs on a table with existing users
- **THEN** their data is preserved, they are active, they have no role and no avatar

### Requirement: Existing users get no access automatically
The package migrations SHALL NOT assign any role to existing users. A user without a role SHALL have no permissions in the CMS and SHALL NOT be able to open the admin panel.

#### Scenario: Host user without a role
- **WHEN** a pre-existing host user without a role opens `/admin/dashboard`
- **THEN** access is denied

### Requirement: Installer command
The command `ecms:install` SHALL publish the configuration, run the package migrations, prepare the user model, set up the first administrator and report each step. It SHALL be safe to run repeatedly: a repeated run SHALL NOT duplicate data, overwrite changed files without `--force`, or fail because a step was already done.

#### Scenario: First run
- **WHEN** `ecms:install` is run on a fresh host with a default user model
- **THEN** the schema exists, the user model has the CMS trait, and an administrator can sign in at `/login`

#### Scenario: Repeated run
- **WHEN** `ecms:install` is run a second time
- **THEN** it reports that the steps are already done and changes nothing

### Requirement: User model preparation
The installer SHALL add the CMS contract and trait to the host's default user model automatically. If the model is not the default shape, the installer SHALL NOT edit it and SHALL print the exact lines to add.

#### Scenario: Customised user model
- **WHEN** the user model file cannot be edited safely
- **THEN** the installer prints the contract and trait to add and exits with a failure status after the other steps

### Requirement: First administrator
The installer SHALL create the administrator with the email and password given by options, prompts or `ADMIN_EMAIL`/`ADMIN_PASSWORD`, SHALL mark the email verified, and SHALL give the account the system role `admin`. If a user with that email already exists, the installer SHALL promote that account only after confirmation (or `--force`) and SHALL NOT change its password unless one is given explicitly. The installer SHALL NOT supply default credentials in any environment; when they are missing and cannot be asked for, it SHALL stop.

#### Scenario: No credentials without interaction
- **WHEN** `ecms:install --no-interaction` runs, in any environment, with no email/password
- **THEN** it fails with a message naming the missing values and creates no administrator

#### Scenario: Promote an existing user
- **WHEN** the given email belongs to an existing user and the operator confirms
- **THEN** that user receives the `admin` role and keeps their password

### Requirement: Unsupported user tables are reported
The package SHALL support a user table named `users` with an integer primary key. The installer SHALL check this and report a clear error otherwise, before creating tables.

#### Scenario: UUID users
- **WHEN** the host user model uses a non-integer key
- **THEN** the installer stops before migrating and explains the limitation

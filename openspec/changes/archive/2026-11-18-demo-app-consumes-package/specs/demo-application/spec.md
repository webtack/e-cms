# Spec Delta

## Purpose

Defines the demo site as a consumer of the `webtack/e-cms` package: which code it owns, how it links to the package during development, and that its behaviour is the same as before the CMS was extracted.

## ADDED Requirements

### Requirement: Package consumption
The demo site SHALL obtain the CMS only by requiring the package through a local Composer path repository, and SHALL NOT contain a second copy of CMS code, migrations, translations or admin frontend sources.

#### Scenario: No duplicated CMS code
- **WHEN** the demo's source tree is inspected
- **THEN** it contains no CMS models, controllers, services, field types, admin pages or CMS migrations

#### Scenario: Live link during development
- **WHEN** a file in the package directory is edited
- **THEN** the change is visible in the demo without reinstalling the package

### Requirement: Host-owned code only
The demo SHALL own only site-specific code: its user model (using the package contract and trait), the `dcculture` theme, the legacy import, deployment and environment files, and the browser e2e suite.

#### Scenario: Theme and import present
- **WHEN** the demo runs with `APP_THEME=dcculture`
- **THEN** the public site renders with the `dcculture` theme and the `legacy:import` command is available

### Requirement: Behaviour preserved
After the switch, the demo's public site and admin panel SHALL behave as before: the same URLs, data, permissions and pages, using the existing database without data loss and without re-running migrations.

#### Scenario: Existing database
- **WHEN** the demo is started against the database that was migrated before the extraction
- **THEN** no migration is pending and all existing content, users and files are intact

#### Scenario: Browser regression suite
- **WHEN** the full e2e suite is run against the demo
- **THEN** it passes without changes to the tests' expectations

### Requirement: Assets published from the package
The demo SHALL serve the admin frontend from assets published by the package, and its repository SHALL ignore the published files.

#### Scenario: Fresh clone
- **WHEN** the demo is cloned and set up following its README
- **THEN** the package assets are published and the admin loads

### Requirement: Documentation split
The demo's documentation SHALL describe only demo-specific topics (e2e testing, the legacy import, the `dcculture` theme) and SHALL link to the package documentation for everything else.

#### Scenario: Link to the CMS docs
- **WHEN** a reader opens the demo's documentation index
- **THEN** it points to the package documentation for the CMS topics

# Spec Delta

## Purpose

Defines how the package proves its own behaviour independently of any host application, and which guarantees its test suite and continuous integration provide.

## ADDED Requirements

### Requirement: Host-independent test suite
The package SHALL have a test suite that runs from the package directory alone, using a framework test harness and an in-memory database, without a host application, a running web server or built frontend tooling.

#### Scenario: Clean checkout
- **WHEN** a developer clones the package, installs its dependencies and runs the test command
- **THEN** the suite runs and passes without any other project present

### Requirement: Behaviour is preserved
The package suite SHALL contain the behavioural tests of the CMS (access, admin controllers, content model and values, files, menus, navigation, public site, settings, taxonomy, themes, locale, unit tests) so that moving the code does not lose coverage. Tests of host-only code (the legacy import) SHALL NOT be part of the package suite.

#### Scenario: Coverage carried over
- **WHEN** the test files of the demo application (excluding the legacy import) are compared with the package suite
- **THEN** each of them exists in the package suite with the same scenarios

### Requirement: Test user model
The suite SHALL run against a fixture user model that uses the package's user trait and contract, exactly as a host user model would.

#### Scenario: Role relation in tests
- **WHEN** a test creates a user through its factory with a role
- **THEN** the user has that role and the access resolver evaluates its rights

#### Scenario: Roleless user in tests
- **WHEN** a test evaluates a fixture user whose `role_id` is `null` (the users table of Testbench has a nullable `role_id`)
- **THEN** every system permission is denied, every scope is `none`, `forClient()` returns without error and the admin users list renders

### Requirement: Package-specific guarantees
The suite SHALL verify the guarantees only a package has: automatic registration, middleware of the web group, the order of routes (host routes before the public catch-all, also with cached routes), the `public_site` and `auth_routes` switches, theme lookup (application over package), translation override, denial for users without a role, asset version from the package manifest, the missing-assets message, factory resolution for every model, and installer behaviour including repeated runs.

#### Scenario: Route order guard
- **WHEN** the host defines a route after the package has booted
- **THEN** a test shows that the host route is answered before the public catch-all

#### Scenario: Installer is idempotent
- **WHEN** the installer test runs the command twice
- **THEN** the second run reports every step as already done and the data is unchanged

### Requirement: Decisions of the package bootstrap are locked in by tests
The suite SHALL verify the language-prefix behaviour (registered only when the public site is on, safe before the package migrations, language codes protected from host routes), the interface-locale configuration in a host without the application's own locale list, the translation override rule, type template cloning without an application themes directory, the roleless user as an action target, and the publish tags of the package (reference theme, theme tooling, static files).

#### Scenario: Host not migrated yet
- **WHEN** a test requests a host URL such as `/api/x` before the package migrations ran
- **THEN** the host answers and no database error occurs

#### Scenario: Stock host locales
- **WHEN** a test removes the application's own locale list from the configuration and sends a web request
- **THEN** the request succeeds and the interface languages come from the package configuration

#### Scenario: Publish tags
- **WHEN** a test publishes the reference theme and the theme tooling tags, and the assets tag with and without static files
- **THEN** the complete theme tree and the tooling files are copied, a repeat with force restores an edited file, and publishing the assets succeeds in both cases

### Requirement: Continuous integration
The package SHALL define an automated pipeline that runs the test suite, the code style check and the frontend build freshness check on every push and pull request, for each supported PHP version.

#### Scenario: Stale frontend build
- **WHEN** a pull request changes frontend sources without updating the committed build
- **THEN** the pipeline fails on the freshness check

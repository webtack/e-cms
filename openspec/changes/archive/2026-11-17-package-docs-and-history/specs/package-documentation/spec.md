# Spec Delta

## Purpose

Defines the documentation and development history that every release of the package must carry, so that a new user can install and use it, and a maintainer can understand how it came to be.

## ADDED Requirements

### Requirement: Entry documentation
The package SHALL include a README that states what the package is, its requirements, a quick start with the exact commands, and links to the detailed documents, plus a license file, a changelog, an upgrade guide and contribution notes.

#### Scenario: Quick start works as written
- **WHEN** a person follows the README quick start on a clean Laravel application
- **THEN** they reach a working admin login without consulting any other source

### Requirement: Installation and configuration guides
The documentation SHALL describe installation, upgrade and removal, and SHALL list every configuration key and environment variable with its default and meaning, including the switches that control the public site and the authentication routes, and SHALL give production notes for the settings the package relies on (trusted proxies for the per-IP request limits, secure session cookies, the request-limit keys).

#### Scenario: Every key documented
- **WHEN** the configuration files of the package are compared with the configuration guide
- **THEN** each key appears in the guide with its default

### Requirement: Integration guide
The documentation SHALL explain how a host application integrates the package: the user model contract and trait, the handling of existing users and roles, how host routes relate to the public catch-all, using the host's own login, the reservation of language codes as first segments of the host's routes, the interface languages (`ecms.locales`), and the limits (the admin prefix is fixed; a `users` table with integer keys).

#### Scenario: Host with existing users
- **WHEN** a developer reads the integration guide
- **THEN** they find how to give an existing account the administrator role

### Requirement: Theming guide
The documentation SHALL explain how to create an application theme: required files, template cascade, hooks, Blade directives, assets, building, and overriding the shipped `default` theme.

#### Scenario: Minimal theme
- **WHEN** a developer follows the theming guide
- **THEN** they can create a theme that overrides one template and activate it

### Requirement: Topic documentation of components
The package SHALL carry the per-topic documentation of its components (access control, content model, files, menus, taxonomy, themes, admin sections), written for the package (package class names and commands), each linked from a documentation index, with all relative links valid.

#### Scenario: Link integrity
- **WHEN** the relative links of all documents are checked
- **THEN** none points to a missing file or heading

### Requirement: Development history
The package SHALL include the OpenSpec history: the main specifications of the package's capabilities and the full archive of completed changes, plus a generated chronological index of the history with a short explanation of how to read it. Demo-only capabilities SHALL NOT appear among the package specifications.

#### Scenario: History index complete
- **WHEN** the index is regenerated from the archive
- **THEN** it lists every archived change exactly once, in date order, with its reason

### Requirement: Working rules travel with the code
The package SHALL include the contributor and AI-assistant working rules adapted to the package (how to run PHP and tests, the OpenSpec workflow, documentation language), without machine-local settings.

#### Scenario: No local settings
- **WHEN** the package file list is inspected
- **THEN** it contains no `settings.local.json` and no `.env`

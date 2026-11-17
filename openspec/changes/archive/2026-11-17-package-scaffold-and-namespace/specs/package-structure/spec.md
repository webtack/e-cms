# Spec Delta

## Purpose

Defines how the CMS is packaged as the Composer library `webtack/e-cms`: its identity, namespace, directory layout, and the boundary between what the package ships and what stays in a host application.

## ADDED Requirements

### Requirement: Package identity and namespace
The package SHALL be named `webtack/e-cms`, SHALL autoload everything under the root namespace `Webtack\Ecms\` from `src/`, and SHALL NOT declare or use any class in the host application's `App\` namespace. Factories, seeders and test support classes SHALL live under `Webtack\Ecms\Database\Factories\`, `Webtack\Ecms\Database\Seeders\` and `Webtack\Ecms\Tests\`.

#### Scenario: Autoloading
- **WHEN** Composer's autoloader is dumped for the package
- **THEN** every class under `src/` resolves from the namespace `Webtack\Ecms\` and no file in the package declares `namespace App\…`

#### Scenario: No leftover references to the old namespace
- **WHEN** the package source, configuration, themes and templates are searched for `App\`
- **THEN** no class reference to the host namespace remains, in code, configuration class lists or documentation of classes

### Requirement: Package layout
The package repository SHALL contain `src/`, `config/`, `database/migrations`, `database/factories`, `database/seeders`, `lang/`, `resources/` (admin frontend sources and views), `routes/`, `themes/default/`, `tests/`, `docs/` and `openspec/`, and SHALL ship the theme `default` inside the package.

#### Scenario: Default theme is part of the package
- **WHEN** the package is installed without any application theme
- **THEN** the theme `default` (`theme.json`, `views/`, `assets/`) is available from the package

### Requirement: Declared dependencies
The package SHALL declare in its `composer.json` the runtime dependencies it needs (PHP, Laravel framework, Inertia for Laravel, Intervention Image, Symfony HTML sanitizer) and SHALL NOT require application-level packages that the CMS does not use.

#### Scenario: Installable constraints
- **WHEN** `composer validate` is run in the package
- **THEN** the manifest is valid and lists the runtime dependencies with version constraints

### Requirement: Host-specific code stays out of the package
The package SHALL NOT include the host's user model, the Laravel-default `users`, `cache` and `jobs` migrations, the one-off legacy import (command, models, configuration) or any application theme other than `default`.

#### Scenario: Excluded files
- **WHEN** the file list of the package is inspected
- **THEN** it contains no `Models/User.php`, no migration creating `users`, `cache` or `jobs`, no `Legacy` directory and no `themes/dcculture`

### Requirement: Independence from the concrete user class
Package code SHALL obtain the user model class from the application's authentication configuration (`auth.providers.users.model`) instead of naming a class, SHALL type authenticated users as the framework's authenticatable contract, and SHALL provide a contract and a trait that a host user model uses to take part in roles and favorites.

#### Scenario: Custom user model
- **WHEN** the host configures its own user model that uses the package trait
- **THEN** relations from nodes and files to the author resolve to that model

#### Scenario: Role relation from the trait
- **WHEN** a user model uses the package trait
- **THEN** the user has a role relation and a favorite-types relation, and its active flag is cast to boolean

### Requirement: Explicit factory binding
Every package model that supports factories SHALL bind its factory explicitly, so factories resolve without relying on the `Database\Factories\` naming convention.

#### Scenario: Factory resolution
- **WHEN** a package model's `factory()` method is called
- **THEN** it returns the factory from `Webtack\Ecms\Database\Factories\`

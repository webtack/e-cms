# Spec Delta

## MODIFIED Requirements

### Requirement: Theme structure and the active theme
A theme SHALL be a directory `<name>/` with the files `theme.json`, `hooks.php` (optional), and the directories `views/` and `assets/`. Themes SHALL be looked up first in the application's `themes/` directory and then in the package's own themes directory; a theme found in the application wins over a package theme of the same name. The active theme SHALL be determined by the environment setting `APP_THEME` (default `default`). The theme `default` SHALL be shipped with the package, SHALL be required and SHALL always be the last source of templates, so another theme contains only the overridden templates. If the active theme does not exist or has no `theme.json`, the system SHALL refuse the public response with a theme exception, and not silently replace it with `default`.

#### Scenario: Default theme
- **WHEN** `APP_THEME` is not set
- **THEN** the active theme is `default`

#### Scenario: Another theme overrides part of the templates
- **WHEN** the active theme `custom` has only `views/layout.blade.php`
- **THEN** the template `layout` is taken from `custom`, and the rest of the templates from `default`

#### Scenario: The active theme does not exist
- **WHEN** `APP_THEME=missing`, and neither the application nor the package has a theme `missing`
- **THEN** the public request fails with a theme exception with a message about the missing theme, and the admin panel works as before

#### Scenario: Application theme shadows a package theme
- **WHEN** the application has `themes/default/` and the package also ships `default`
- **THEN** the application's directory is used for that theme name

## ADDED Requirements

### Requirement: Template copy on type cloning
Cloning a content type SHALL copy the type's templates only within the themes of the application, SHALL never write into the package's themes directory, and SHALL succeed when the application has no themes directory (nothing is copied).

#### Scenario: Application theme with type templates
- **WHEN** an application theme has `views/news/` and the type `news` is cloned as `press`
- **THEN** `views/press/` is created in that theme and nothing is written under the package

#### Scenario: Host without a themes directory
- **WHEN** the application has no `themes/` directory and a type is cloned
- **THEN** the clone succeeds and no template files are created

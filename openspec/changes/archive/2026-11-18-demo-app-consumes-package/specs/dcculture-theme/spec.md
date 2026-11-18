# Spec Delta

## MODIFIED Requirements

### Requirement: Theme structure and activation
The theme `dcculture` SHALL be a valid theme directory with a `theme.json` and its own `assets/` and `views/`. It SHALL contain only the templates that differ from `default`; every other template (pages, content, fields, blocks, menus, 404 content) SHALL still be taken from `default`. The theme SHALL be active when `APP_THEME=dcculture`. It SHALL live in the application's `themes/` directory and be resolved ahead of the package theme `default`, which supplies every template it does not override.

#### Scenario: Theme check passes
- **WHEN** `APP_THEME=dcculture` and the theme check is run
- **THEN** the check reports no problems for `dcculture` and for `default`

#### Scenario: A template the theme does not override
- **WHEN** a visitor opens a page whose template exists only in `default`
- **THEN** that template is rendered by `default` inside the `dcculture` frame

#### Scenario: Hooks use the packaged API
- **WHEN** the theme's `hooks.php` is loaded
- **THEN** it registers its hooks through the packaged theme hooks class and the theme check reports no problems

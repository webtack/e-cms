# Spec Delta

## Purpose

The system of themes of the public site: the directory of the theme, the choice of the active theme, the cascade of candidates of templates from specific to general, the hooks for changing the data and the candidates, the check of the completeness of a theme.

## ADDED Requirements

### Requirement: Структура теми й активна тема
A theme SHALL be a directory `themes/<name>/` with the files `theme.json`, `hooks.php` (optional), the directories `views/` and `assets/`. The active theme SHALL be defined by the environment setting `APP_THEME` (by default `default`). The theme `default` SHALL be mandatory and SHALL always be the last source of templates, so another theme contains only the overridden templates. If the active theme does not exist or has no `theme.json`, the system SHALL refuse the public response with an exception of the theme, and not silently substitute `default`.

#### Scenario: Default theme
- **WHEN** `APP_THEME` is not set
- **THEN** the active theme is `default`

#### Scenario: Another theme overrides part of the templates
- **WHEN** the active theme `custom` has only `views/layout.blade.php`
- **THEN** the template `layout` is taken from `custom`, and the other templates from `default`

#### Scenario: No active theme
- **WHEN** `APP_THEME=missing`, and the directory `themes/missing` does not exist
- **THEN** the public request ends with an exception of the theme with a message about the missing theme, and the admin works as before

### Requirement: Вибір шаблона зі списку кандидатів
The system SHALL choose a template by an ordered list of candidates: for each candidate (from specific to general) a file is first searched in the active theme, then in `default`, and the first found is taken. If no candidate exists in any theme, the system SHALL throw a theme exception with the list of all attempted names in the order of enumeration. When `app.debug` is enabled, the rendered HTML SHALL contain a comment with the list of candidates and the chosen template; when disabled, it SHALL NOT contain it.

#### Scenario: Specific candidate beats the general one
- **WHEN** the candidates are `pages/page-landing--5`, `pages/page-landing`, `pages/page`, and files exist for `pages/page-landing` and `pages/page`
- **THEN** `pages/page-landing` is used

#### Scenario: Order of candidates matters more than the order of themes
- **WHEN** the active theme has `pages/page-landing`, and `default` has `pages/page-landing--5`
- **THEN** `pages/page-landing--5` from `default` is used, because the candidate defines the order, and then the theme

#### Scenario: No candidate at all
- **WHEN** no candidate exists in either the active theme or `default`
- **THEN** a theme exception is thrown, its message contains all the attempted names

#### Scenario: Candidate comment in debug
- **WHEN** `app.debug` is enabled and any public page is rendered
- **THEN** the HTML contains a comment with the list of candidates and the chosen template

#### Scenario: No debug comment without debug
- **WHEN** `app.debug` is disabled
- **THEN** the HTML contains no comments with the list of candidates

### Requirement: Хуки теми
A theme MAY have a `hooks.php` that returns a function with a registry: `preprocess(name, callback)` changes the template variables before rendering, `suggest(name, callback)` changes the list of candidates. The name of a hook SHALL be dotted from general to specific (`field`, `field.youtube`); on a call the general hooks run first, then the specific ones. The hooks of `themes/default/hooks.php` SHALL always be loaded, then the hooks of the active theme. If `hooks.php` exists but does not return a function, the system SHALL throw a theme exception.

#### Scenario: Order of preprocess
- **WHEN** `preprocess('field')` and `preprocess('field.youtube')` are registered, and a field of the type `youtube` is rendered
- **THEN** the hook `field` runs first, then `field.youtube`, and the template variables contain the changes of both

#### Scenario: Hooks of default and of the active theme
- **WHEN** `default` and the active theme register `preprocess('field')`
- **THEN** the hook of `default` runs first, then the hook of the active theme

#### Scenario: Suggest changes the candidates
- **WHEN** the hook `suggest('page', fn)` adds the candidate `pages/page-special` at the beginning of the list, and such a file exists
- **THEN** `pages/page-special` is used

#### Scenario: Invalid hooks.php
- **WHEN** `hooks.php` returns not a function
- **THEN** a theme exception is thrown with an indication of the file

### Requirement: Ресурси теми
The resources of a theme (`assets/`) SHALL be built by Vite together with the rest of the application, and the templates of the active theme SHALL connect their own resources by the directory of the theme. The absence of resources in the active theme SHALL NOT break the rendering.

#### Scenario: Building the resources of a theme
- **WHEN** the build of the frontend is performed
- **THEN** the resources `themes/*/assets` have got into the manifest of the build

#### Scenario: Theme without resources
- **WHEN** the active theme has no `assets` directory
- **THEN** the page is displayed without an error, without connecting the resources of this theme

### Requirement: Перевірка теми командою
The system SHALL have the command `theme:check`, which checks the active theme: the presence of `theme.json` and of the theme `default`, the presence of the required templates (`html`, `layout`, `home`, `errors/404`, `pages/page`, `content/list`, `content/details`) in the theme or in `default`, the correctness of `hooks.php`. The command SHALL output each problem on a separate line and finish with a non-zero code if there are problems, and zero without them.

#### Scenario: Complete theme
- **WHEN** `theme:check` is run for a correct theme
- **THEN** the success is reported, the exit code is 0

#### Scenario: A required template is missing
- **WHEN** `views/layout.blade.php` is removed from `default`, and the active theme does not override it
- **THEN** the command outputs a line about the missing `layout`, the exit code is non-zero

#### Scenario: Invalid hooks.php
- **WHEN** `hooks.php` of the active theme returns not a function
- **THEN** the command outputs the corresponding error, the exit code is non-zero

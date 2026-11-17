# Spec Delta

## ADDED Requirements

### Requirement: Versioned URLs of theme static files
The core SHALL provide `ThemeManager::assetUrl($path)` and the global helper `theme_asset($path)` that turn a path relative to a theme's static directory (for example `images/logo1.png`) into a URL of the form `/app/<theme>/<path>?v=<mtime>`, where `<mtime>` is the modification time of the file in `public/app/<theme>/`. The file SHALL be looked up in the active theme first and then in `default`; the URL names the theme where it was found. When the file exists in neither, the URL SHALL be `/app/<active>/<path>` without `?v` and no error SHALL be raised. A leading slash in `$path` SHALL be ignored, and a path with a `..` segment SHALL be treated as a file that does not exist. The helper SHALL NOT depend on the theme having JavaScript or a Vite manifest.

#### Scenario: Existing file in the active theme
- **WHEN** `public/app/custom/images/logo.png` exists and `custom` is active
- **THEN** `theme_asset('images/logo.png')` returns `/app/custom/images/logo.png?v=<mtime of that file>`

#### Scenario: File only in the default theme
- **WHEN** `public/app/default/images/logo.png` exists, `public/app/custom/images/logo.png` does not, and `custom` is active
- **THEN** `theme_asset('images/logo.png')` returns `/app/default/images/logo.png?v=<mtime>`

#### Scenario: Replaced file changes the URL
- **WHEN** the file is replaced and its modification time changes
- **THEN** the next call returns a URL with a different `?v`

#### Scenario: Missing file
- **WHEN** the file exists in neither theme
- **THEN** `theme_asset('images/missing.png')` returns `/app/<active>/images/missing.png` without `?v` and does not throw

#### Scenario: Path outside the static directory
- **WHEN** the path contains `..`, for example `../../.env`
- **THEN** it is treated as a missing file: the URL has no `?v` and no file outside `public/app/` is read

#### Scenario: Helper in a template
- **WHEN** a template contains `<img src="{{ theme_asset('images/logo1.png') }}">`
- **THEN** the rendered `src` equals the escaped result of `theme_asset('images/logo1.png')`

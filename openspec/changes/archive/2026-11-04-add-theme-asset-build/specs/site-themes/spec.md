# Spec Delta

## MODIFIED Requirements

### Requirement: Ресурси теми
The resources of a theme (`assets/`) SHALL be built by a separate build of the theme, independent of the build of the admin, and the result SHALL lie in a separate directory of the theme with its own manifest. The template `html` of the active theme SHALL connect from the manifest of the theme the files `vendor` (if any) and `app` (if any) in this order: `vendor` before `app`. The absence of resources in the active theme SHALL NOT break the rendering.

#### Scenario: Building the resources of a theme
- **WHEN** the build of the theme `default` is performed
- **THEN** the manifest of the theme contains its entries `app`, and the build files lie in the directory of this theme, not in the build directory of the admin

#### Scenario: Order of vendor and app
- **WHEN** a theme has both `vendor` and `app`
- **THEN** in `<head>` the stylesheet and the script of `vendor` are connected before `app`

#### Scenario: A theme without resources
- **WHEN** the active theme has no `assets` directory or there is no built manifest of the theme
- **THEN** the page is displayed without an error, without connecting the resources of this theme

## ADDED Requirements

### Requirement: Окрема збірка теми
The system SHALL build the resources of each theme separately from the admin and from the other themes. The build of one theme SHALL NOT require the build of the admin and SHALL NOT delete or change the build files of the admin or of the other themes; the build of the admin SHALL NOT delete or change the build files of the themes. The system SHALL provide a command to build one theme by its name, a command to build all themes and a command of the dev mode of one theme. The dev mode of a theme SHALL work simultaneously with the dev mode of the admin without a conflict and SHALL apply only to the public pages.

#### Scenario: Building one theme
- **WHEN** the theme `custom` is built
- **THEN** only the build files of the theme `custom` change, and the build files of the admin and of the theme `default` remain unchanged

#### Scenario: Building the admin does not erase the themes
- **WHEN** the admin is built after the build of the theme `default`
- **THEN** the build files of the theme `default` and its manifest remain in place

#### Scenario: Building all themes
- **WHEN** the command of building all themes is run
- **THEN** each theme with an `assets` directory received its own build and its own manifest

#### Scenario: Dev mode of a theme together with the dev mode of the admin
- **WHEN** the dev mode of a theme and the dev mode of the admin are running at the same time
- **THEN** the public pages take the resources of the theme from the dev server of the theme, and the pages of the admin take them from the dev server of the admin

### Requirement: Входи теми за конвенцією
The entry files of the build of a theme SHALL be only `assets/app` and `assets/vendor` (in each of the extensions CSS, SCSS and JS that exist). All other files in `assets/` SHALL be considered parts (partials) that are connected by import, and SHALL NOT become separate entries. The compiled own styles and scripts of the theme SHALL get into the files `app`, and the styles and scripts of the libraries connected in `vendor` — into the files `vendor`.

#### Scenario: A partial is not an entry
- **WHEN** `assets/` has a file `_variables.scss` which is imported by `app.scss`
- **THEN** its contents are included in the compiled `app`, and there is no separate build file for it

#### Scenario: Own code and libraries in different files
- **WHEN** `vendor.js` imports a library, and `app.js` contains the code of the theme
- **THEN** the code of the library is in the compiled `vendor`, and the code of the theme is in the compiled `app`

#### Scenario: Only app
- **WHEN** the theme has only `assets/app.css` and no `vendor`
- **THEN** the build is successful, and the page connects only `app`

### Requirement: SCSS і бібліотеки в темі
A theme SHALL be able to write styles in SCSS and to compile JavaScript. A theme SHALL be able to connect libraries both as packages from the `node_modules` of the project and as files that lie inside the theme itself. A library connected in `vendor` SHALL be available to the code of `app` through global variables that `vendor` itself sets. A failed build (an SCSS error, a missing import) SHALL finish with a non-zero code with a message about the file and the reason.

#### Scenario: SCSS is compiled
- **WHEN** the theme has `assets/app.scss` with variables and nested rules
- **THEN** the compiled `app` is ordinary CSS in which the variables are expanded and the rules are unfolded

#### Scenario: Library from node_modules
- **WHEN** `vendor.scss` and `vendor.js` connect a package installed in `node_modules`
- **THEN** the styles and the code of the package are in the compiled `vendor`

#### Scenario: Library inside the theme
- **WHEN** `vendor.js` imports a file by a path inside the `assets/` of the theme
- **THEN** the code of this file is in the compiled `vendor`

#### Scenario: Global variable from vendor
- **WHEN** `vendor.js` sets a library as a global variable, and `app.js` uses it
- **THEN** the page executes the code of `app` without an error, because `vendor` was connected earlier

#### Scenario: Build error
- **WHEN** `app.scss` contains an error or imports a file that does not exist
- **THEN** the build finishes with a non-zero code and outputs the file and the reason of the error

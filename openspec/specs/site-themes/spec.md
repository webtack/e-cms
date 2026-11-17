# site-themes Specification

## Purpose

The system of public site themes: the theme catalog, the choice of the active theme, the cascade of template candidates from specific to general, the hooks that change data and candidates, and the check of theme completeness.

## Requirements

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

### Requirement: Choosing a template from a list of candidates
The system SHALL choose a template by an ordered list of candidates: for each candidate (from specific to general) the file is first looked for in the active theme, then in `default`, and the first one found is taken. If no candidate exists in any theme, the system SHALL throw a theme exception with the list of all names tried in the order of traversal. When `app.debug` is enabled, the rendered HTML SHALL contain a comment with the list of candidates and the chosen template; when disabled, it SHALL NOT contain it.

#### Scenario: A specific candidate outranks a general one
- **WHEN** the candidates are `pages/page-landing--5`, `pages/page-landing`, `pages/page`, and the files exist for `pages/page-landing` and `pages/page`
- **THEN** `pages/page-landing` is used

#### Scenario: The order of candidates matters more than the order of themes
- **WHEN** the active theme has `pages/page-landing`, and `default` has `pages/page-landing--5`
- **THEN** `pages/page-landing--5` from `default` is used, because the candidate determines the order, and then the theme

#### Scenario: No candidate at all
- **WHEN** no candidate exists in either the active theme or `default`
- **THEN** a theme exception is thrown, and its message contains all the names tried

#### Scenario: Candidate comment in debug
- **WHEN** `app.debug` is enabled and any public page is rendered
- **THEN** the HTML contains a comment with the list of candidates and the chosen template

#### Scenario: No debug comment without debug
- **WHEN** `app.debug` is disabled
- **THEN** the HTML contains no comments with the list of candidates

### Requirement: Theme hooks
A theme MAY have a `hooks.php` that returns a function with a registry: `preprocess(name, callback)` changes the template variables before rendering, `suggest(name, callback)` changes the list of candidates. The hook name SHALL be a dot path from general to specific (`field`, `field.youtube`); when called, the general hooks run first, then the specific ones. The hooks of `themes/default/hooks.php` SHALL always be loaded, then the hooks of the active theme. If `hooks.php` exists but does not return a function, the system SHALL throw a theme exception.

#### Scenario: Order of preprocess
- **WHEN** `preprocess('field')` and `preprocess('field.youtube')` are registered, and a field of type `youtube` is rendered
- **THEN** the hook `field` runs first, then `field.youtube`, and the template variables contain the changes of both

#### Scenario: Hooks of default and of the active theme
- **WHEN** `default` and the active theme register `preprocess('field')`
- **THEN** the hook of `default` runs first, then the hook of the active theme

#### Scenario: Suggest changes candidates
- **WHEN** the hook `suggest('page', fn)` adds the candidate `pages/page-special` to the beginning of the list, and such a file exists
- **THEN** `pages/page-special` is used

#### Scenario: Invalid hooks.php
- **WHEN** `hooks.php` returns something other than a function
- **THEN** a theme exception is thrown with an indication of the file

### Requirement: Theme resources
The resources of a theme (`assets/`) SHALL be built by a separate theme build, independently of the admin build, and the result SHALL lie in a separate theme directory with its own manifest. The template `html` of the active theme SHALL connect from the theme's manifest the files `vendor` (if any) and `app` (if any) in this order: `vendor` before `app`. The absence of resources in the active theme SHALL NOT break the rendering.

#### Scenario: Building the theme resources
- **WHEN** the theme `default` is built
- **THEN** the theme's manifest contains its entries `app`, and the build files lie in the directory of this theme, not in the directory of the admin build

#### Scenario: Order of vendor and app
- **WHEN** the theme has both `vendor` and `app`
- **THEN** in `<head>` the style sheet and the script of `vendor` are connected before `app`

#### Scenario: A theme without resources
- **WHEN** the active theme has no `assets` directory or no built theme manifest
- **THEN** the page is displayed without an error, without connecting the resources of this theme

### Requirement: Separate theme build
The system SHALL build the resources of each theme separately from the admin panel and from other themes. The build of one theme SHALL NOT require the build of the admin panel and SHALL NOT delete or change the build files of the admin panel or of other themes; the build of the admin panel SHALL NOT delete or change the files of the theme builds. The system SHALL provide a command to build one theme by name, a command to build all themes, and a command for the dev mode of one theme. The dev mode of a theme SHALL work simultaneously with the dev mode of the admin panel without conflict and SHALL apply only to public pages.

#### Scenario: Building one theme
- **WHEN** the theme `custom` is built
- **THEN** only the build files of the theme `custom` change, and the build files of the admin panel and of the theme `default` remain unchanged

#### Scenario: The admin build does not erase themes
- **WHEN** the admin panel is built after the build of the theme `default`
- **THEN** the build files of the theme `default` and its manifest remain in place

#### Scenario: Building all themes
- **WHEN** the command for building all themes is run
- **THEN** each theme with an `assets` directory has received its own build and its own manifest

#### Scenario: Dev mode of a theme together with the admin dev mode
- **WHEN** the dev mode of the theme and the dev mode of the admin panel are running simultaneously
- **THEN** the public pages take the theme's resources from the theme's dev server, and the admin pages — from the admin's dev server

### Requirement: Entry points of a theme by convention
The entry files of a theme build SHALL be only `assets/app` and `assets/vendor` (in each of the CSS, SCSS, and JS extensions that exist). All other files in `assets/` SHALL be considered parts (partials) that are connected by import and SHALL NOT become separate entry points. The compiled own styles and scripts of the theme SHALL go into the `app` files, and the styles and scripts of the libraries connected in `vendor` — into the `vendor` files.

#### Scenario: A partial is not an entry
- **WHEN** `assets/` contains the file `_variables.scss`, which is imported by `app.scss`
- **THEN** its content is included in the compiled `app`, and there is no separate build file for it

#### Scenario: Own code and libraries in different files
- **WHEN** `vendor.js` imports a library, and `app.js` contains the theme's code
- **THEN** the library code is in the compiled `vendor`, and the theme's code is in the compiled `app`

#### Scenario: Only app
- **WHEN** the theme has only `assets/app.css` and no `vendor`
- **THEN** the build succeeds, and the page connects only `app`

### Requirement: SCSS and libraries in a theme
A theme SHALL be able to write styles in SCSS and compile JavaScript. A theme SHALL be able to connect libraries both as packages from the project's `node_modules` and as files located inside the theme itself. A library connected in `vendor` SHALL be available to the `app` code through global variables that `vendor` itself sets. A failed build (an SCSS error, a missing import) SHALL end with a non-zero code and a message with the file and the reason.

#### Scenario: SCSS is compiled
- **WHEN** the theme has `assets/app.scss` with variables and nested rules
- **THEN** the compiled `app` is plain CSS, in which the variables are expanded and the rules are unnested

#### Scenario: A library from node_modules
- **WHEN** `vendor.scss` and `vendor.js` connect a package installed in `node_modules`
- **THEN** the styles and the code of the package are in the compiled `vendor`

#### Scenario: A library inside the theme
- **WHEN** `vendor.js` imports a file by a path inside the theme's `assets/`
- **THEN** the code of this file is in the compiled `vendor`

#### Scenario: A global variable from vendor
- **WHEN** `vendor.js` sets a library as a global variable, and `app.js` uses it
- **THEN** the page runs the code of `app` without an error, because `vendor` is connected earlier

#### Scenario: Build error
- **WHEN** `app.scss` contains an error or imports a file that does not exist
- **THEN** the build ends with a non-zero code and outputs the file and the reason of the error

### Requirement: Checking a theme with a command
The system SHALL have a command `theme:check` that checks the active theme: the presence of `theme.json` and of the theme `default`, the presence of the required templates (`html`, `layout`, `home`, `errors/404`, `pages/page`, `content/list`, `content/details`) in the theme or in `default`, and the validity of `hooks.php`. The command SHALL output each problem on a separate line and finish with a non-zero code if there are problems, and zero if there are none.

#### Scenario: A complete theme
- **WHEN** `theme:check` is run for a correct theme
- **THEN** success is reported, and the exit code is 0

#### Scenario: A required template is missing
- **WHEN** `views/layout.blade.php` is removed from `default`, and the active theme does not override it
- **THEN** the command outputs a line about the missing `layout`, and the exit code is non-zero

#### Scenario: Invalid hooks.php
- **WHEN** `hooks.php` of the active theme returns something other than a function
- **THEN** the command outputs the corresponding error, and the exit code is non-zero

### Requirement: Theme static files
A theme MAY keep static files (fonts, images) in `themes/<name>/assets/fonts/` and `themes/<name>/assets/images/`. Building the theme (and starting its dev server) SHALL copy these directories, with their structure, into the theme's static directory `public/app/<name>/`, so templates and the theme's CSS reference the files as `/app/<name>/fonts/<file>` and `/app/<name>/images/<file>`. The files SHALL still be there after a rebuild of the theme. Copying SHALL NOT touch anything outside `public/app/<name>/`. A theme without these directories SHALL build as before. While the theme's dev server runs, a request to the dev server for `/app/<name>/…` SHALL return the same file as the application does, so URLs in the theme's CSS resolve whether the stylesheet is loaded from the application or from the dev server.

#### Scenario: Files are copied on build
- **WHEN** `themes/dcculture/assets/images/logo1.png` and `themes/dcculture/assets/fonts/nunito.woff2` exist and the theme is built
- **THEN** `public/app/dcculture/images/logo1.png` and `public/app/dcculture/fonts/nunito.woff2` exist and are served at `/app/dcculture/images/logo1.png` and `/app/dcculture/fonts/nunito.woff2`

#### Scenario: Rebuild keeps the files
- **WHEN** the theme is built a second time
- **THEN** the copied files exist again after the build, and the built CSS is still available

#### Scenario: Theme without static files
- **WHEN** a theme has neither `assets/fonts/` nor `assets/images/` and is built
- **THEN** the build succeeds and no `public/app/<name>/` directory is created

#### Scenario: Static files through the dev server
- **WHEN** the theme's dev server runs and a stylesheet loaded from it contains `url(/app/dcculture/images/bg_symphony.png)`
- **THEN** the browser's request to the dev server for `/app/dcculture/images/bg_symphony.png` returns the image, the same as the application's `/app/dcculture/images/bg_symphony.png`

#### Scenario: Other dev server requests unaffected
- **WHEN** the theme's dev server runs and the browser requests a theme entry or `/@vite/client` from it
- **THEN** the dev server answers them itself and does not forward them to the application

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

### Requirement: Template copy on type cloning
Cloning a content type SHALL copy the type's templates only within the themes of the application, SHALL never write into the package's themes directory, and SHALL succeed when the application has no themes directory (nothing is copied).

#### Scenario: Application theme with type templates
- **WHEN** an application theme has `views/news/` and the type `news` is cloned as `press`
- **THEN** `views/press/` is created in that theme and nothing is written under the package

#### Scenario: Host without a themes directory
- **WHEN** the application has no `themes/` directory and a type is cloned
- **THEN** the clone succeeds and no template files are created

# Public site themes

A theme is a directory `themes/<name>/` that defines the look of the public site. The templates are regular Blade (without Inertia): the admin panel does not use them. The routes and template data: [public-site.md](public-site.md).

## Theme structure

```
themes/<name>/
├── theme.json        # {"name": "...", "version": "..."}; a required valid JSON with a name
├── hooks.php         # optional: the hook registry (see "Hooks")
├── assets/           # app.{css,scss,js}, vendor.{css,scss,js} + partials: built by Vite (see "Theme assets (Vite)")
└── views/            # Blade templates: html, layout, home, errors/404, pages/*, content/*, fields/*, blocks/*, menus/*
```

The theme `default` ships with the package, is required and always lies last in the list of paths: another theme contains only the templates it overrides. The required templates (in the theme or in `default`): `html`, `layout`, `home`, `errors/404`, `pages/page`, `content/list`, `content/details` (`ThemeManager::REQUIRED_VIEWS`).

## Active theme

The active theme is `APP_THEME` in `.env` (default `default`), the configuration `config/theme.php`:

| Key | Value |
| --- | --- |
| `active` | `env('APP_THEME', 'default')` |
| `path` | `base_path('themes')` (the application's themes directory; the package's own `themes/` is added by the provider, see [Theme roots](#theme-roots)) |
| `fallback` | `default` (does not change) |

`ThemeManager` (a singleton, `Webtack\Ecms\Theme\ThemeManager`) during `boot` only sets the Blade namespace `theme` to `[active/views, default/views]` (`theme::layout`) and does not touch the file system. The existence of the active theme and its `theme.json` is checked at the first `render()` of a public request: a broken `APP_THEME` throws `ThemeException` only on the public site, while the admin panel and the console work. A missing theme is not silently replaced with `default`.

## Theme roots

Themes are looked up in an ordered list of roots: the application's `themes/` (`config('theme.path')`) first, then the package's `themes/` (where `default` lives). `ThemeManager::directory($name)` returns the first root that holds `<name>/theme.json`, so:

- the theme `default` comes from the package when the application has none, and an application theme named `default` (with a `theme.json`) shadows the packaged one entirely;
- an application theme overrides single templates: with `themes/custom/views/layout.blade.php` the template `layout` comes from `custom` and every other one from `default`;
- a directory without `theme.json` does not shadow anything; when no root has the manifest, the first existing directory is used (so the error names a real path), and a theme found nowhere throws `ThemeException`.

`viewPaths()`, `registerViews()`, `hooks()` and `assets()` work per theme on the resolved directory. `php artisan theme:check` prints one line per theme naming the root it was found in (`Theme [default]: found in /…/themes`), then the problems or `Theme [x]: OK`.

## Candidate cascade

`ThemeManager::render(array $candidates, array $data, ?string $hook)` accepts a list of template names from the specific to the general:

```php
app(ThemeManager::class)->render(
    ['pages/page-landing--5', 'pages/page-landing', 'pages/page'],
    ['node' => $node],
    'page',
);
```

The order is "candidate → theme", as in Drupal 7: for each candidate, the file is first looked for in the active theme, then in `default`, and the first one found is taken. So `pages/page-landing--5` from `default` beats `pages/page-landing` of the active theme. If there is no candidate at all, `ThemeException` is thrown with the list of all names tried in the order of traversal.

In `app.debug` mode an HTML comment `<!-- theme: candidates [a, b] ; used: b -->` is added (the characters `<` and `>` are removed from the names, so the comment cannot be closed early); without debug there is no comment. The comment is added by `render()`, not by the templates, so a theme cannot forget it.

The candidates of the public site controllers: [public-site.md](public-site.md#template-candidates). The candidates of fields, blocks, and menus, and the directives `@field`, `@fields`, `@block`, `@menu`: [theme-templates.md](theme-templates.md).

## Cloning a type

Cloning a content type in the admin ([admin-content-types.md](admin-content-types.md#cloning)) also copies the type-level templates of the source key to the new key, in **every** theme under the application's `themes/` (not only the active one; the package's own themes are never written to, and an application without a `themes/` directory simply has nothing to copy, so the clone still succeeds), so the clone keeps the public look of the source. Per theme it copies only what exists:

- `views/{old}/` → `views/{new}/`, recursively, without the node-bound files whose names contain `--` and the node id (`preview--7.blade.php`);
- `views/blocks/block-{old}.blade.php` → `views/blocks/block-{new}.blade.php`, without `block-{old}--{slug}.blade.php`.

The block templates of the owner's type ([theme-templates.md](theme-templates.md#blocks): `views/{old}/block-{t}.blade.php`, `views/{old}/block-{t}--{slug}.blade.php`) are files of `views/{old}/`, so they are copied with it like any other file there (`block-{t}--{slug}` is not node-bound, its name has no numeric id).

Files are copied as they are: the content is not changed, so comments, class names and includes still mention the old key and are adjusted by hand. Nothing is overwritten: an existing target, or a file that cannot be written (the `themes/` directory must be writable by the web process), fails the whole clone and removes the files already created. Page templates `pages/page-{old}` and type keys written in `hooks.php` are not copied. The candidates the new files take part in: [theme-templates.md](theme-templates.md#pages-list-and-details).

## Hooks

`hooks.php` returns a function with the registry (`Webtack\Ecms\Theme\ThemeHooks`; before the package it was `App\Theme\ThemeHooks`, see [package-structure.md](package-structure.md#namespace-mapping)):

```php
<?php

use Webtack\Ecms\Theme\ThemeHooks;

return function (ThemeHooks $hooks): void {
    // Changes the template variables before rendering.
    $hooks->preprocess('field.youtube', function (array &$vars) {
        $vars['autoplay'] = false;
    });

    // Changes the list of candidates before the template is chosen.
    $hooks->suggest('page', function (array &$candidates) {
        array_unshift($candidates, 'pages/page-special');
    });
};
```

- The callback takes an argument by reference (`array &$vars`, `array &$candidates`).
- A `preprocess` callback may end the request by throwing `Illuminate\Http\Exceptions\HttpResponseException` with a response (for example a redirect); Laravel sends that response and the page is not rendered. The theme `dcculture` does this for a node with a single file or link ([theme-dcculture.md](theme-dcculture.md#redirect-to-a-single-file-or-link)).
- Besides template names, the core runs the `preprocess` hook `breadcrumbs` (the trail of the page, see [theme-templates.md](theme-templates.md#breadcrumbs)) when the shared page variables are built.
- The hook name is a dot path from general to specific: a call of `field.youtube` runs the hook `field`, then `field.youtube`. Within one name, the callbacks run in the order of registration.
- The files are loaded: `themes/default/hooks.php` first, then the active theme's; each file is loaded once (on first use).
- A `hooks.php` that does not return a function gives `ThemeException` with an indication of the file.
- The hook name is passed by `render()` as the third parameter; without it the last candidate is taken (`pages/page` → `pages.page`). Hooks are not bound to the view name (unlike View Composers), so they can change the choice of the template itself.

The example above is checked by `tests/Feature/Theme/ThemeHooksTest.php` (`test_the_documented_youtube_example_works`).

## Theme assets (Vite)

The theme is built separately from the admin panel: its own config `vite.theme.config.js`, without `tsc` and without React. The build of the admin panel (`npm run build`) writes only to `public/build`, the build of the theme — only to `public/themes/<name>/`, so they do not erase each other's files.

### Commands (on the host)

```bash
npm run theme:build              # all themes that have assets/ (sequentially)
npm run theme:build -- default   # one theme; a theme without assets/ is an error (code 1)
npm run theme:dev -- default     # the dev server with HMR; the name is required
```

The build result: `public/themes/<name>/manifest.json` and `public/themes/<name>/assets/*` (the directory is in `.gitignore`). After updating the code or changing the templates, build the theme again. Without a build the page renders without the theme's assets (without an error).

The theme's dev server listens on the port `THEME_DEV_PORT` (default `5174`, `strictPort`), writes `public/themes/<name>.hot`, and works together with the admin panel's `npm run dev` (port 5173, `public/hot`): the public pages take the assets from 5174, the admin panel — from 5173. After the server stops, the hot file disappears; if it remains after a crash, delete `public/themes/<name>.hot`. The dev server also forwards requests for `/app/…` to the application (`APP_URL` from `.env`), so the application must be running while you develop a theme (see [Static files](#static-files-fonts-images)).

### Entry points by convention

The entry points are only `assets/app.{css,scss,js}` (the theme's code) and `assets/vendor.{css,scss,js}` (the libraries). The rest of the files in `assets/` (`_variables.scss`, `lib/*`, `js/*.js`) are partials: they are connected by import and do not become separate build files. The keys of the manifest are paths from the project root (`themes/default/assets/app.css`).

- **vendor and app.** `vendor.*` lists the libraries itself, `app.*` contains the theme's code. The template `html` connects `vendor` before `app` (CSS and JS). Do not import a library in `app.js` if it is already in `vendor.js`.
- **Sources of libraries.** A package from `node_modules` (`import 'swiper'`, `@use 'pkg/style/file.css'`) or a file inside the theme (`import './lib/slider.js'`).
- **Global variables.** For plugins that need them, `vendor.js` assigns them itself (`window.$ = jQuery`); `app.js` uses them, because `vendor` is connected earlier.
- **SCSS.** Supported (the dependency `sass`). An SCSS error or a missing import ends the build with a non-zero code and the name of the file.

### Static files (fonts, images)

A theme may keep static files in `themes/<name>/assets/fonts/` and `themes/<name>/assets/images/`. Building the theme (and starting its dev server) copies both folders, with their structure, into `public/app/<name>/` (the plugin `copyThemeStatic` in `vite.theme.config.js`, run at `buildStart`). The theme's CSS uses plain URLs: `/app/<name>/fonts/<file>` and `/app/<name>/images/<file>`; Vite leaves absolute URLs untouched, so the files are not hashed. Templates use the versioned URL helper described below.

- Under `npm run theme:dev` the stylesheet is loaded from the dev server origin (`:5174`), so the bare CSS URLs resolve there. The dev server's `server.proxy` forwards `/app/…` to `APP_URL` (`vite.theme.config.js`, dev only; the build is unaffected). If fonts or images from CSS fail with 502/404, check that the application is running and that `APP_URL` matches the address it is reachable at from the host.
- The copy runs only when the folders exist; a theme without them builds as before and `public/app/<name>/` is not created.
- It writes only inside `public/app/<name>/`, which is outside the build output (`public/themes/<name>/`), so a rebuild does not erase it. Unchanged files (same size, not older) are skipped.
- `public/app/` is generated and is in `.gitignore`; the theme folder is the source of truth. After cloning, run `npm run theme:build -- <name>`.
- Files have no content hash, so a bare URL may keep serving an old file from the browser cache. In templates use `{{ theme_asset('images/logo.png') }}` (the global helper, also usable in PHP code and hooks): it returns `/app/<theme>/<path>?v=<mtime>`, where `<mtime>` is the modification time of the copied file in `public/app/<theme>/`, so the URL changes when the file is replaced and rebuilt.
- The path is relative to the theme's static directory. The lookup follows the cascade: the active theme first, then `default`; the URL names the theme where the file was found. A file found in neither gives `/app/<active>/<path>` without `?v` (a normal 404 for that image, no exception). A leading `/` is ignored; a path with a `..` segment is treated as missing.
- The helper reads only the copied files, so it does not need the theme to have JavaScript or a Vite manifest. URLs inside CSS (`@font-face`, ornaments) stay bare: a PHP helper cannot run there.
- The helper returns a raw string; print it with `{{ }}` so it is escaped and safe in attributes: `<img src="{{ theme_asset('images/logo1.png') }}" alt="">`.

### How it is connected

`ThemeManager::assets()` reads `public/themes/<active>/manifest.json` (under the theme's dev server — the files `assets/{vendor,app}.*` on disk) and returns the entry points in the order `vendor` → `app`; for a theme without `assets` or without a build — an empty list. `ThemeManager::assetTags()` builds the HTML of the tags through a separate `Vite` instance (the build directory `themes/<active>`, the theme's hot file), so the theme's dev server does not replace the admin panel's `public/hot`. `PublicData` passes `$assets` and `$assetTags` to the templates, and `html.blade.php` prints `{!! $assetTags !!}`.

### Tailwind in `default`

`default/assets/app.css` is a Tailwind build (Tailwind 4 through `@tailwindcss/vite`, without automatic source detection: `@import 'tailwindcss' source(none)`). The theme `default` stays on plain CSS without SCSS: Tailwind 4 is not designed for preprocessors (Sass, Less, Stylus), so the Tailwind entry does not use SCSS; SCSS is available to other themes. The directive `@source` covers the `views` of the theme `default` and the `views` of all themes (`../../*/views`), so the utilities from the templates of all themes get into the CSS. The element styles are utilities in the templates themselves; the file keeps the tokens `@theme` and the selectors `.richtext` for the editor's content. After changing the templates or CSS, `npm run theme:build` is needed.

## Isolation of admin styles from the theme

The admin panel and the theme are built into separate style sheets, and the theme's templates must not affect the look of the admin panel ([specification](../openspec/specs/default-theme-styling/spec.md)):

- `resources/css/app.css` (the admin panel) works with `@import 'tailwindcss' source(none)`: automatic scanning of the whole project is disabled, so classes from `themes/` do not get into the admin build.
- The admin sources are listed explicitly: `@source '../../resources'` (the pages, components, and Blade templates of the admin panel) and the pagination templates from `vendor`.
- We do not add a `@source` directive for `storage/framework/views`. Blade compiles the theme's templates into this directory, so the cache contains the theme's classes; the admin templates are already covered by `resources/`.
- Rule for development: a utility needed by the admin panel is written in `resources/` (in the admin's TSX or Blade). A utility that exists only in the theme's templates does not get into the admin panel, and this is the expected behavior.

Check after a change: `npm run build` and `npm run theme:build` on the host, then a search in `public/build/assets/app-*.css` (the name of the admin file from `public/build/manifest.json`). The class `.max-w-prose` should not be in the admin CSS, but it is in `public/themes/default/assets/app-*.css`.

## Theme check: `theme:check`

```
docker compose exec app php artisan theme:check
```

It checks `theme.json` of the active theme and of `default`, the presence of the required templates (in the active theme or in `default`), that `default` has `fields/field-{type}` for each registered field type ([theme-templates.md](theme-templates.md#default-templates)), and that each `hooks.php` returns a function. Each problem is output on a separate line; the exit code is 0 without problems and 1 otherwise.

```
Theme [default]: OK
Template [layout] is missing in the theme [custom] and in [default].
Template [fields/field-youtube] for the field type [youtube] is missing in the theme [default].
```

Tests: `tests/Feature/Theme/` (`ThemeManagerTest`, `CandidateCascadeTest`, `ThemeHooksTest`, `ThemeCheckCommandTest`; the fixture themes are built in a temporary directory by the trait `UsesFixtureThemes`, `themes/` is not changed).

## Languages in a theme

A theme gets the language data of a multilingual site for free: `PublicData` passes `$lang`, `$site` (the settings in the reading language), `$homeUrl`, `$languages`, `$alternates` and `$canonical` to every template, and the core prefixes the internal addresses it generates ([theme-templates.md](theme-templates.md#languages), [public-site.md](public-site.md#language-prefix)). A theme that overrides `html` must print `<html lang="{{ $lang }}">` and the `canonical` / `alternate` links, otherwise it silently drops the SEO signals; a theme that hard-codes internal links (`href="/"`) keeps them in the base language until it uses `$homeUrl` or `LocalizedUrl::path()`. The switcher markup is the theme's own: `default` ships `partials/language-switcher`.

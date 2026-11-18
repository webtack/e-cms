# Design

## Context

The motivation: see [proposal.md](proposal.md). The current state, from which the approach starts:

- `vite.config.js` (one config) through `themeEntries()` adds to the entries of the admin all files `themes/*/assets/{css,js}/*`. The output is shared: `public/build` + `manifest.json`; `npm run build` = `tsc --noEmit && vite build`.
- `ThemeManager::assets()` reads `public/build/manifest.json` (the keys with the prefix `themes/<active>/assets/`, only `isEntry`) or, if `public/hot` exists, lists the files of the theme on the disk. `html.blade.php` calls `@vite($assets)`.
- The Laravel `Vite` (`__invoke($entrypoints, $buildDirectory)`) takes the build directory as the second argument, and takes the hot file from its state (`useHotFile`, by default `public/hot`) and checks it before the manifest: in the hot mode `$buildDirectory` is ignored.
- Tailwind 4 is not designed for preprocessors (Sass, Less, Stylus); the documentation of Tailwind: «Think of Tailwind CSS itself as your preprocessor». `themes/default/assets/css/app.css` is the Tailwind entry (`@import 'tailwindcss' source(none)` + `@source`).
- `sass` is absent from `node_modules`; Vite 8 (`laravel-vite-plugin` 3.2) compiles `.scss` itself when the package `sass` is installed.

The components described: [docs/site-themes.md](../../../docs/site-themes.md) (the section «Ресурси теми (Vite)» — "Resources of the theme (Vite)"), [docs/e2e-testing.md](../../../docs/e2e-testing.md) (the prerequisites).

## Goals / Non-Goals

**Goals:**
- Work on one theme (the build, the dev server) without `tsc`, without the React admin and without an impact on `public/build`.
- SCSS, compiled JS, libraries from `node_modules` or from the files of the theme, the separation into `vendor.*` and `app.*`.
- A minimal change of the public code: the theme is still connected by one call in `html.blade.php`.

**Non-Goals:**
- SCSS in the theme `default` (it stays Tailwind in plain CSS).
- A separate `package.json` per theme / npm workspaces (the dependencies lie in the root `package.json`).
- The build of themes from PHP/artisan, an automatic build during deployment, the check of the resources in `theme:check`.
- The languages of the site, the regions of blocks, the change of the theme in the admin (outside this change).

## Decisions

### 1. A separate config `vite.theme.config.js` and the launch through `scripts/theme.mjs`

The theme is built by a separate config that reads `THEME=<name>`. The scripts:

```
"theme:dev":   "node scripts/theme.mjs dev"      # the name of the theme is mandatory
"theme:build": "node scripts/theme.mjs build"    # the name is optional
```

`scripts/theme.mjs` parses the argument, checks that `themes/<name>/assets` exists, and runs `vite -c vite.theme.config.js` (`build` — sequentially for each theme with `assets/`, when there is no name) with `THEME` in the environment. Names without `assets/` are skipped (all are built), and an explicitly named theme without `assets/` gives an error.

Why not `vite build` with an argument: Vite has no own parameters of this kind, and an environment variable in the config is the standard way. Why not one config with `--mode`: then `emptyOutDir` and the set of plugins of the admin (React, `tsc`) would remain shared, and the separation — the essence of the change — would not be.

Alternative: an array of configs (`defineConfig([...])`) in `vite.config.js` — Vite does not build several configs in one call, so it does not solve the problem of the independent launch.

### 2. The output `public/themes/<name>/` with its own manifest and hot file

`laravel({ input, buildDirectory: 'themes/<name>', hotFile: 'public/themes/<name>.hot' })`. Then:

```
public/build/               the admin (does not change)
public/hot                  the dev server of the admin
public/themes/<name>/       the build of the theme + manifest.json
public/themes/<name>.hot    the dev server of the theme
```

`/public/themes` is added to `.gitignore` (like `/public/build`). The dev server of the theme: the port from `THEME_DEV_PORT` (by default 5174), `strictPort: true`, so as not to silently replace the port of the admin.

### 3. Entries by the convention `assets/{app,vendor}.{css,scss,js}`

`vite.theme.config.js` adds to `input` only the existing `themes/<name>/assets/{vendor,app}.{css,scss,js}`. Everything else (`_*.scss`, `lib/`, `js/*.js`) — partials. The keys of the manifest remain the paths from the root of the project (`themes/default/assets/app.css`, `themes/x/assets/vendor.scss`).

Instead of the directories `assets/css` and `assets/js`, the entries lie in the root of `assets/`: the SCSS and JS of one bundle (`app.scss` + `app.js`) stand next to each other, and the subdirectories are free for partials.

Alternative: to keep the glob over `assets/{css,js}/*`. Rejected: any partial would become an entry, and vendor/app could not be told apart from the rest.

### 4. Vendor is explicit, not automatic

`vendor.scss`/`vendor.css` and `vendor.js` list the libraries themselves (`@use 'bootstrap/scss/bootstrap'`, `import 'swiper'`), `app.*` contains the code of the theme. The source of a library — a package from `node_modules` or a file in the theme (`assets/lib/...`, the import by a relative path): both are ordinary ES imports of Vite. For the plugins that need global variables, `vendor.js` assigns them itself (`window.$ = jQuery`); `app.js` does not import what is already in `vendor`.

Why not automatic (everything from `node_modules` → `vendor` through the groups of chunks): for JS it works, for CSS it does not (Sass inlines `@use`/`@import` into the file that imports them), so the rule would differ for JS and CSS, and the order of the cascade (`vendor` before `app`) would not be under the control of the author. The explicit `vendor.*` gives the same mechanism for CSS and JS.

If `app.js` nevertheless imports a module that also imports `vendor.js`, Rolldown moves it into a shared chunk (there is no duplicate, `@vite` adds `modulepreload`). This is permissible, but not the convention.

The files keep the hashes in their names (cache busting through the manifest); `vendor`/`app` are the keys of the manifest (`vendor.scss` → `vendor-<hash>.css`).

### 5. The connection: a separate instance of `Vite`, the order vendor → app

`ThemeManager`:

- `assets()` returns the keys of the entries of the active theme in the order `vendor`, then `app` (inside — as in the manifest). In prod it reads `public/themes/<name>/manifest.json` (no file — an empty list, as now without the manifest); in dev (the file `public/themes/<name>.hot` exists) it lists `assets/{vendor,app}.*` on the disk.
- A new method (for example `assetTags()`) returns the HTML of the tags: `(new Vite)->useHotFile(public_path('themes/<name>.hot'))->useBuildDirectory('themes/<name>')->__invoke($assets)`. A separate instance, not the global singleton, so the hot file of the theme does not replace `public/hot` of the admin and the tests do not depend on the state.

`html.blade.php` calls `{!! $assetTags !!}` (or `@themeAssets`) instead of `@vite($assets)`; `PublicData` passes the tag next to the list `$assets`.

Alternative: `@vite($assets, 'themes/x')` without a separate instance. Rejected: in the hot mode Laravel takes the global `public/hot`, so the dev server of the theme would never be used.

### 6. The admin does not know about the themes

`vite.config.js` loses `themeEntries()`, `fs` and `path` for the themes. `npm run build` remains `tsc --noEmit && vite build` and writes only to `public/build`. The rules of the isolation of the CSS of the admin ([default-theme-styling](../../specs/default-theme-styling/spec.md)) do not change.

### 7. Tailwind in `default`, SCSS in the other themes

`vite.theme.config.js` connects `tailwindcss()` for all themes (the plugin does not touch the files without Tailwind directives), `sass` is a dependency of the project. `default/assets/app.css` remains plain CSS; its `@source` are corrected for the new depth (`../views`, `../../*/views`, `../../../vendor/...`). A theme on SCSS does not use Tailwind directives in `.scss`.

### 8. Migration of the existing theme

`themes/default/assets/css/app.css` → `themes/default/assets/app.css` (`git mv`), the `@source` are corrected. The tests `ThemeManagerTest`/`DefaultThemeStylingTest` read `public/themes/default/manifest.json` and the key `themes/default/assets/app.css`; the test of the order `vendor` → `app` builds the manifest of a fixture theme in `public/themes/<unique-name>/` and removes it in `tearDown`.

## Risks / Trade-offs

- [The theme was forgotten to be built after the update: `public/themes/default` is absent, the page without styles] → `assets()` gives an empty list without an error (the requirement «Тема без ресурсів» — "A theme without resources"); in `docs/site-themes.md` and `docs/e2e-testing.md` add the command to the prerequisites; the e2e `public-site` will reveal it.
- [Rolldown duplicates or moves into a shared chunk a module imported by both `vendor.js` and `app.js`] → the convention «libraries only in `vendor`»; a check in the tasks: the build of `default` + a fixture theme with `vendor.js`, there is no duplicate in the output files.
- [The hot file of the theme is left after an emergency stop of the dev server: the pages look for a dead port] → the same risk as with `public/hot` of the admin; the plugin of Laravel removes the file on the stop; in the documentation — «remove `public/themes/<name>.hot`».
- [Two dev servers (the admin 5173, the theme 5174) and CORS] → the plugin of Laravel sets CORS for `APP_URL`, as for the admin; the port 5174 with `strictPort`, so that the conflict is an explicit error.
- [`sass` — a new dev dependency] → only `devDependencies`, does not get into the production build of the admin.
- [**BREAKING** for the third-party themes in `assets/css` and `assets/js`] → the project has one theme with resources (`default`); the migration is described in the tasks and in `docs/site-themes.md`.

## Migration Plan

1. `npm install --save-dev sass`.
2. Move `default/assets/css/app.css` → `default/assets/app.css`, fix the `@source`.
3. `npm run build` (the admin) and `npm run theme:build` (the themes); check `public/themes/default/manifest.json`.
4. Remove the old files of the themes from `public/build` (they will disappear by themselves after the repeated `npm run build`).
5. Rollback: return `themeEntries()` to `vite.config.js` and the file to `assets/css/`; remove `public/themes`.

## Open Questions

- Whether to add `theme:build` to the deployment script — the deployment is not described in this repository, it is decided during the deployment, it does not affect the behaviour.

# Proposal

## Why

The resources of the theme are now only additional entries of the build of the admin: `vite.config.js` builds together the admin (React) and `themes/*/assets/{css,js}/*`, and the result lands in the shared `public/build` and `manifest.json`. Therefore one cannot work on the theme separately: every edit drags in `tsc` and the build of the admin, the build of the admin erases the files of the themes, and the dev server of the theme conflicts with the dev server of the admin through one `public/hot`. In addition, there is no SCSS (the package `sass` is not in the project), there is no JS of the theme, and there is no separation into one's own code and the code of libraries: every file in `assets/css` or `assets/js` becomes a separate entry, so a partial cannot be told apart from a bundle.

## What Changes

- The theme is built separately from the admin: new commands `npm run theme:dev -- <name>` (the dev server with HMR) and `npm run theme:build [-- <name>]` (builds one theme or, without a name, all of them) with a separate config `vite.theme.config.js`, without `tsc` and without the admin.
- The output of the build of the theme lands in `public/themes/<name>/` with its own `manifest.json`; the dev server of the theme has its own hot file and its own port. The build of the admin (`npm run build`) and the build of the theme do not erase each other's files.
- The entries of the theme are set by the convention, not by a glob over all files: `assets/app.{css,scss,js}` (the own code of the theme) and `assets/vendor.{css,scss,js}` (libraries). The rest of the files in `assets/` are partials: they are imported, but do not become separate entries.
- SCSS is supported (the dependency `sass` is added). The libraries are connected by an import from `node_modules` or by a path inside the theme (`assets/lib/...`). The global variables needed by the plugins are set by `vendor.js` itself.
- The template `html` connects `vendor` before `app` from the manifest of the theme (`@vite($assets, 'themes/<name>')`); `ThemeManager::assets()` reads the manifest of the theme, and not `public/build/manifest.json`; in the dev mode the hot file of the theme is used.
- `vite.config.js` (the admin) no longer knows about the themes: `themeEntries()` is removed.
- **BREAKING**: the location of the entries of the theme changes. `themes/default/assets/css/app.css` moves to `themes/default/assets/app.css`, and the key of the manifest becomes `themes/default/assets/app.css`. The files from `assets/css/*` and `assets/js/*` that are not named `app`/`vendor` are no longer entries. After the update `npm run theme:build` is needed.
- The theme `default` stays on Tailwind in the ordinary `app.css`, without SCSS: Tailwind 4 is not designed for working with preprocessors (Sass, Less, Stylus), so the Tailwind entry stays plain CSS.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `site-themes`: the requirement «Ресурси теми» ("Resources of the theme") changes (a separate build, the `outDir` and manifest of the theme, the entries `app`/`vendor`, the connection from the manifest of the theme); requirements are added for the convention of entries, for SCSS and libraries, and for the independence of the builds of the theme and of the admin.
- `default-theme-styling`: the styles of the theme `default` are connected from `assets/app.css`, and not `assets/css/app.css`; the Tailwind entry stays an ordinary CSS.

## Impact

- Code: `vite.config.js` (`themeEntries()` is removed), a new `vite.theme.config.js`, `package.json` (the scripts `theme:dev`, `theme:build`, the dependency `sass`), `app/Theme/ThemeManager.php` (`assets()`, the hot file and the build directory of the theme), `themes/default/views/html.blade.php` (`@vite` with the build directory of the theme), `themes/default/assets/css/app.css` → `themes/default/assets/app.css` (the paths `@source` are corrected), `.gitignore` (`/public/themes`).
- Tests: `tests/Feature/Theme/ThemeManagerTest.php` and `DefaultThemeStylingTest.php` (the path of the manifest and the key), a check of the entries and of the order `vendor` → `app`.
- The build: separate `public/themes/<name>/`; `public/build` remains only the admin.
- Documentation: `docs/site-themes.md` (the section «Ресурси теми (Vite)»: the structure of `assets/`, the commands, vendor/app, SCSS, libraries, Tailwind and Sass), `docs/e2e-testing.md` (the prerequisite: besides `npm run build` the `npm run theme:build` is needed), `docs/README.md` (the description of the line about the themes, if it changes).
- E2E: `e2e/public-site.spec.js` and `e2e/smoke.spec.js` are run, because the public page changes the connection of the styles.

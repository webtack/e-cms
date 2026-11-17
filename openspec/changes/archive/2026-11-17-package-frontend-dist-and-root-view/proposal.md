# Proposal

## Why

The admin panel is React + Inertia + Tailwind built with Vite. A host that installs the package through Composer must not need Node, React or Vite, and the admin must not depend on the host's `app.blade.php`, `public/build` or Vite singleton. The package therefore has to ship a **pre-built** admin bundle and its own Inertia root view. This is step 4 of 7 (series in `package-scaffold-and-namespace`).

## What Changes

- Add a package build (`vite.config.js`, `package.json`, `tsconfig.json`) that compiles `resources/js/app.tsx` and `resources/css/app.css` into the committed directory `dist/` (`dist/build/manifest.json`, `dist/build/assets/*`, `fonts-manifest.json`). `npm run build:package` runs `tsc --noEmit` and the build; `npm run check:dist` rebuilds and fails if `dist/` differs from what is committed.
- Publish the bundle with the tag `ecms-assets` to `public/vendor/ecms/` of the host; the same tag publishes the built assets of the package theme `default` to `public/themes/default/` (where `ThemeManager` already looks for theme manifests).
- Publish the **reference theme** with the tag `ecms-theme`: the whole directory of the shipped theme `default` (`views/`, `assets/` sources, `hooks.php`, `theme.json`) is copied as is to `themes/default` of the host, so a developer can read it as the model for their own theme (which the developer creates by hand as a separate directory). The copy is refreshed with `vendor:publish --tag=ecms-theme --force` when the package is updated and is not meant to be edited.
- Publish the **theme build tooling** with the tag `ecms-theme-tooling`: `vite.theme.config.js` and `scripts/theme.mjs` go to the host root. They are the same files the package uses for its own `build:themes`; the package never edits the host's `package.json`, the docs give the `npm install -D …` line and the `theme:dev`/`theme:build` scripts. Static files of the built `default` theme (`assets/fonts`, `assets/images`) are built into `dist/app/default` and published to `public/app/default` by `ecms-assets` when that directory exists.
- Add the root view `ecms::app` (copy of `app.blade.php` with a dedicated Vite instance bound to `vendor/ecms/build`) and point `HandleInertiaRequests` at it. The host's `resources/views/app.blade.php`, `public/build` and global Vite configuration are never touched.
- Override `HandleInertiaRequests::version()` to hash the package manifest, because Inertia's default hashes `public/build/manifest.json` of the host.
- `ecms:install` (step 3) gets one more step that publishes `ecms-assets`; the docs recommend `@php artisan vendor:publish --tag=ecms-assets --force` in the host's `post-update-cmd` so an update of the package refreshes assets.
- A missing or stale bundle produces a clear message in the root view instead of a blank page.
- Dev workflow for working on the admin: the Vite dev server runs from the package directory on the host and writes its hot file into the demo's `public/vendor/ecms/hot`, so the demo can use HMR while the code lives in the package.

## Capabilities

### New Capabilities

- `package-frontend-assets`: how the admin frontend is delivered to a host — committed build, publishing, isolation from the host's Vite and views, asset versioning and the freshness check.

### Modified Capabilities

<!-- None: no requirement of `admin-shell` changes; the shell behaves the same, only its delivery changes (covered by `package-frontend-assets`). -->

## Impact

- New in the package: `vite.config.js`, `package.json`, `tsconfig.json`, `dist/`, `resources/views/app.blade.php` (as `ecms::app`), `scripts/`.
- Changed: `HandleInertiaRequests` (`$rootView`, `version()`), provider (views namespace, publish tag, view composer for the Vite instance), installer (assets step).
- Docs (package): `docs/package-frontend.md` (new), `docs/admin-shell.md`, `docs/package-installer.md`, `docs/README.md`.

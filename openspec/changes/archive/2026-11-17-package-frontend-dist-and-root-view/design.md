# Design

## Context

Observed: the admin entry is `resources/js/app.tsx` (Inertia, pages via `import.meta.glob('./Pages/**/*.tsx')`) plus `resources/css/app.css`; `vite.config.js` uses `laravel-vite-plugin` (v3, supports `publicDirectory`, `buildDirectory`, `hotFile`, `fonts: [bunny('Instrument Sans')]`), React, Tailwind 4 and an `@` alias. The build in the demo is 1.8 MB (`public/build`, with `fonts-manifest.json`). `resources/views/app.blade.php` uses `@fonts`, `@viteReactRefresh`, `@vite([...])`. `HandleInertiaRequests` has `$rootView = 'app'` and `version()` delegating to the parent, which hashes `public_path('build/manifest.json')` (verified in `vendor/inertiajs/inertia-laravel`). The framework `Vite` is a container singleton and `fonts()` reads the instance's build directory and hot file (verified), so configuring the singleton would change the host's own `@vite`. `ThemeManager` already builds a private `new Vite` bound to a theme's build directory (pattern to reuse). Theme assets are built to `public/themes/<name>/` with a `manifest.json` (`vite.theme.config.js`, `scripts/theme.mjs`). See proposal.md for motivation.

## Goals / Non-Goals

**Goals:** a host with no Node renders the admin; no interference with the host's Vite/views; repeatable publish; a way to detect a stale `dist/`.

**Non-Goals:** the admin's own theme editor or generator (a developer creates a custom theme by hand); SSR; changing the frontend code, page URLs or the admin prefix.

## Decisions

**Committed `dist/`.** Composer installs from VCS/Packagist cannot run a build, so the compiled bundle lives in the repository and is tagged with releases. Alternative: build in the host (`npm`) — rejected, contradicts "clean Laravel". Alternative: GitHub release artifacts downloaded by the installer — rejected, adds a network step and a failure mode.

**Output layout.** Package Vite config: `publicDirectory: 'dist'`, `buildDirectory: 'build'`, `hotFile: process.env.ECMS_HOT_FILE ?? 'dist/hot'`. Result: `dist/build/{manifest.json,fonts-manifest.json,assets/*}`. Publish map: `dist/build` → `public/vendor/ecms/build`; `dist/themes/default` → `public/themes/default`. Alternative: build straight into `public/vendor/ecms` — rejected, the package has no `public/`.

**Dedicated Vite instance.** A small `AdminAssets` service creates `(new Vite)->useBuildDirectory('vendor/ecms/build')->useHotFile(public_path('vendor/ecms/hot'))` and renders `fonts()`, `reactRefresh()` and the entries; it is passed to the view by a view composer for `ecms::app`. Alternative: `Vite::useBuildDirectory()` on the singleton — rejected (global side effects); alternative: `@vite([...], 'vendor/ecms/build')` — viable for tags but `@fonts` and the hot file are still singleton-bound, so the dedicated instance is the consistent choice.

**Inertia version.** `HandleInertiaRequests::version()` returns `hash_file('xxh128', public_path('vendor/ecms/build/manifest.json'))`, or `null` if missing. Without the override, Inertia would hash the host's manifest (or none) and not notice a CMS bundle change.

**Missing assets.** The composer catches the "manifest not found" case and the view prints a plain message with `php artisan vendor:publish --tag=ecms-assets` (and `ecms:install`). This is the most common first-run mistake.

**Freshness check.** `npm run check:dist` runs the build into a temporary directory and compares `manifest.json` file names (content hashes are part of the names) with the committed ones; CI and the release checklist run it. Alternative: a git pre-commit hook — rejected, hooks are not shared.

**Dev workflow.** `npm run dev` in the package with `ECMS_HOT_FILE=/path/to/demo/public/vendor/ecms/hot`; the host's root view reads that hot file, so the demo gets HMR. The `laravel-vite-plugin` writes the URL there. Runs on the host (Node never in Docker), as the project rules require. Docker mount for the package comes from step 1.

**Updating published assets.** Laravel's own `laravel-assets` tag is refreshed by `post-update-cmd`; the docs ask the host to add `@php artisan vendor:publish --tag=ecms-assets --force` the same way, and `ecms:install` repeats it. A stale bundle is also visible: the Inertia version changes only after publishing.

**Theme `default` assets.** `themes/default/assets` is built by the package (`npm run build:themes`, the existing `scripts/theme.mjs`, packaged output path `dist/themes/default`) and published to `public/themes/default`, matching the path `ThemeManager::assets()` reads. A host theme named `default` would collide; documented.

**Reference theme.** The tag `ecms-theme` copies the whole shipped theme `default` to `themes/default` of the host, as is: `views/`, `assets/` (sources), `hooks.php`, `theme.json`. It is optional and a model for the developer, who writes a custom theme by hand as a new directory and overrides only what they need (the cascade of step 2 supplies the rest from `default`). The copy is updated from the package by publishing with `--force`, like `ecms-assets` (the docs recommend it in `post-update-cmd`), so the host must not edit it. Consequences: by the lookup rule of step 2 an application directory `themes/default` shadows the package one, so a published copy that is not refreshed after a package update keeps serving the old templates (drift); the docs state this. The built assets of `ecms-assets` and a build made by the host from the published sources land in the same `public/themes/default`, with identical content, so there is no collision.

**Theme tooling.** `vite.theme.config.js` and `scripts/theme.mjs` have one source, the package, which uses them for `build:themes` (the built `default` in `dist/themes/default`) and publishes them with the tag `ecms-theme-tooling` to the host root. The host keeps its own `package.json`: the docs list the dependencies (`vite`, `laravel-vite-plugin`, `@tailwindcss/vite`, `tailwindcss`, `sass`) and the scripts `theme:dev` and `theme:build`; the installer may print them. Alternative: ship only a documented minimal config — rejected by the owner, a developer needs a working Vite configuration for frontend development.

**Static files of the package theme.** The package build of `default` copies `assets/fonts` and `assets/images` (the existing `copyThemeStatic()`) to `dist/app/default`. The publish map of `ecms-assets` adds `dist/app/default` → `public/app/default` only when that directory exists (registered by the provider after an `is_dir` check), because publishing a missing source path is an error in Laravel (to be confirmed by the task). Rejected: an unconditional entry and a rule "unsupported until `default` has static files".

**Fonts.** `bunny('Instrument Sans')` comes from the existing config and means the browser loads the font from the Bunny CDN. Kept as is (no behaviour change); making it self-hosted is a separate decision.

Docs: new `docs/package-frontend.md`, updates to `docs/admin-shell.md` (build/delivery sections) and `docs/package-installer.md`.

## Risks / Trade-offs

- [`dist/` goes stale after a source edit] → `check:dist` in CI/release checklist; documented in `docs/package-frontend.md`.
- [Repo grows with every dist commit (≈1.8 MB per build)] → Accepted; a release flow can commit `dist/` only on release commits.
- [Host forgets to re-publish after updating] → Message in the root view, installer step, `post-update-cmd` recommendation, plus Inertia version mismatch is not triggered until publish (documented).
- [`public/vendor/ecms` name could clash with a host path] → `vendor` is reserved in `PublicPaths` (step 2).
- [Dedicated Vite instance behaves differently with `@fonts` preloads in dev] → Verified manually in the demo (task 3.2).

## Open Questions

- Whether to also offer a symlink-based publish for local development. Does not affect specs or tasks.

### Audit 2026-11-17 (series deferred)

- **Theme static files.** The `site-themes` main spec requires a theme's `assets/fonts/` and `assets/images/` to be copied to `public/app/<name>/` (`vite.theme.config.js`), and `ThemeManager::assetUrl()` falls back to `public/app/<fallback>/`. The publish map here covers only `public/themes/default`. The package `default` has no static files today, but the package theme build and the `ecms-assets` tag must handle `public/app/default` once it has. `app` is now in `content.reserved_paths` (commit `33d9ccd`), so the public catch-all never shadows `public/app/<name>/`; the package `default` build must still copy its static files there. **Resolved (owner, 2026-11-17):** see "Static files of the package theme" and "Theme tooling" in Decisions; tasks 1.2, 2.4, 2.5.

#### Pre-start audit (2026-11-17, task 0.1)

Baseline commit: `45d2095`. `git diff --stat 124812e..45d2095` outside `openspec/` is empty: no code, config, route, middleware or docs changed since the security-hardening addendum, so the facts and counts of this series stay valid (532 files reference `App\` in `app/`, `config/`, `database/`, `routes/`, `themes/`, `resources/`, `tests/` and `bootstrap/` (564 with `docs/`); the next audit starts from this commit).

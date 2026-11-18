# Proposal

## Why

Steps 1–6 build `webtack/e-cms` next to the demo, which still carries its own copy of the CMS in `app/`. Two copies cannot both be the source of truth. This final step makes `e-cms-intertia` an ordinary Laravel application that **requires the package** (Composer path repository) and keeps only what is specific to this site, then deletes the duplicated code. It also proves, with the existing e2e suite and the real database, that the package is a faithful replacement. Step 7 of 7 (series in `package-scaffold-and-namespace`).

## What Changes

- Add to `composer.json`: a path repository `../e-cms-package` (symlink) and `"webtack/e-cms": "@dev"`; remove the now-package-owned dependencies from the demo `require` (`inertiajs/inertia-laravel`, `intervention/image`, `symfony/html-sanitizer` come through the package). The container sees the package through the mount added in step 1.
- Switch the application over:
  - `bootstrap/app.php` becomes the stock Laravel file (no CMS middleware, redirects or 404 renderer; the package registers them); `routes/web.php` has no CMS routes.
  - `app/` keeps only host code: `Models/User.php` (stock user + `HasEcmsAccess` + `EcmsUser`), the legacy import (`app/Legacy`, `LegacyImportCommand`, `LegacyImportMap`) with its namespaces renamed to `Webtack\Ecms\…` where it uses CMS classes, and `AppServiceProvider` (empty/stock). Everything else under `app/` is deleted.
  - `config/`: keep `app`, `auth`, `cache`, `database`, `filesystems`, `logging`, `mail`, `queue`, `services`, `session`, `legacy`; delete `content`, `files`, `theme`, `social_networks` (the package merges them); `APP_THEME=dcculture` comes from `.env`. In `config/app.php` remove the `available_locales` key (the package owns the list as `ecms.locales`).
  - `database/`: delete the CMS migrations, factories and seeders (the package loads them); keep Laravel's `users`/`cache`/`jobs` and `legacy_import_map` migrations, and a `DatabaseSeeder` that calls the package `AdminSeeder`. Verify `migrate:status` shows nothing pending.
  - Frontend: delete `resources/js`, `resources/css`, `resources/views/app.blade.php`, `tsconfig.json`, `vite.config.js`, `components.json` and the React/Tiptap/shadcn dependencies from `package.json`; keep the theme build (`vite.theme.config.js`, `scripts/theme.mjs`, Tailwind/Sass/Vite and Playwright; they are the demo's copies of the package tag `ecms-theme-tooling`) and `themes/dcculture`; delete the demo's own `themes/default` (the package owns the theme `default` and the demo does not publish it, otherwise the copy would shadow the package theme). Remove `public/build`; publish the package assets (`ecms-assets`) and add `/public/vendor/ecms` and `/public/themes/default` to `.gitignore`.
  - `lang/`: delete `{en,uk}.json` and `{en,uk}/{auth,validation}.php` (the package owns the admin translations); keep `{en,uk}/dcculture.php` and `{en,uk}/months.php` (read only by the `dcculture` theme).
  - `tests/`: delete CMS tests (they live in the package); keep `tests/Feature/Legacy` and a minimal `TestCase`.
  - `docs/`: delete the topic docs (they live in the package); keep `e2e-testing.md`, `legacy-import.md`, `theme-dcculture.md`, `deployment.md` and a `docs/README.md` that links to the package documentation.
  - `themes/dcculture`: rename the `App\…` imports in `hooks.php` and `hooks/*.php` and the template docblocks to `Webtack\Ecms\…` (for example `Webtack\Ecms\Theme\ThemeHooks`).
  - `e2e/`: rename the CMS class names inside the `tinker` code strings (22 files, 379 references of which about 327 are renamed, including `global-setup.js`) from `App\…` to `Webtack\Ecms\…`; the host classes `App\Models\User` and `App\Models\LegacyImportMap` stay. Without it the full e2e run cannot pass.
- `README.md`, `CLAUDE.md`, `AGENTS.md`: describe the demo as a consumer; PHP test rules point to the package for CMS tests; e2e rules stay.
- `openspec/`: keep `config.yaml` and the six demo specs (`e2e-testing`, `legacy-import`, `dcculture-theme`, `dcculture-accessibility`, `dcculture-events`, `demo-application`); remove the package capability specs after a final re-copy of `specs/` and `archive/` into the package (history complete).
- Acceptance: the full Playwright suite passes against the demo (justified: bootstrapping, auth and the shell change), the legacy tests pass, `theme:check` passes for `dcculture`.

## Capabilities

### New Capabilities

- `demo-application`: what the demo site contains and how it consumes the package — local development link, host-owned code, and the guarantee that site behaviour is unchanged.

### Modified Capabilities

- `legacy-import`: the command and its classes keep working against the packaged CMS classes (namespaces), with unchanged behaviour.
- `dcculture-theme`: the theme's hooks use the packaged `ThemeHooks` and the theme builds and renders as before.

## Impact

- Deleted from the demo: ~all of `app/` (except host code), CMS `config/`, `database/{factories,seeders}`, CMS migrations, `lang/`, `resources/js|css|views/app.blade.php`, CMS `tests/`, topic `docs/`. `lang/` loses only the admin files; the `dcculture` translations stay.
- Changed: `composer.json` (+`composer.lock`), `package.json`, `bootstrap/app.php`, `routes/web.php`, `docker-compose.yml` (no further change), `.gitignore`, `README.md`, `CLAUDE.md`, `AGENTS.md`.
- Docs: `docs/README.md`, `docs/legacy-import.md`, `docs/theme-dcculture.md`, `docs/e2e-testing.md` (paths to the package where relevant).
- Risk is concentrated here; rollback is `git` (nothing is committed until the owner asks).

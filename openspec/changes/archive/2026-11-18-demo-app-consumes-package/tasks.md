# Tasks

## 1. Safety net

- [x] 1.1 Ask the owner to commit or stash the pending `themes/dcculture` edits, then record the baseline: `docker compose exec app php artisan route:list --json > <scratch>/routes-before.json`, a MySQL dump of `ecms`, and `php artisan about`. Verify the three artifacts exist and are non-empty.
- [x] 1.2 Confirm the package is ready: its suite is green and `dist/` is fresh (`docker compose exec -w /var/www/e-cms-package app vendor/bin/phpunit`, `npm run check:dist` in the package on the host). Verify both pass; stop otherwise.

## 2. Package linked and host code prepared

- [x] 2.1 Add the path repository and `webtack/e-cms: @dev` to `composer.json`, add `vendor:publish --tag=ecms-assets --force` to `post-update-cmd`, and run `docker compose exec app composer update webtack/e-cms --no-scripts`. `--no-scripts` is required: the `post-autoload-dump` script runs `package:discover`, which boots the application while the old CMS in `app/` and the package would both be registered. The package classes are autoloaded, but the provider stays unregistered (`bootstrap/cache/packages.php` is not refreshed) until task 3.1. Do not publish the assets here; the application does not boot with both copies, so the publish runs in task 3.3. Verify `composer show webtack/e-cms` reports the path source and a symlink, and that `vendor/composer/installed.json` lists the package.
- [x] 2.2 Update `themes/dcculture/hooks.php` and template docblocks to `Webtack\Ecms\…`; verify with `grep` that no `App\` remains under `themes/dcculture`.
- [x] 2.3 Rename the CMS imports in `app/Legacy`, `LegacyImportCommand`, `LegacyImportMap` and `config/legacy.php`; make `User` use `HasEcmsAccess` and implement `EcmsUser`, and delete from `User` what the trait and contract now provide (`role()`, `favoriteTypes()`, `avatarUrl()` and the constant `MEDIA_OWNER`), so there are no duplicate definitions. Verify with `php -l` and a grep that every `use App\…` left refers to a class that stays in the demo. The application does not boot between this task and task 3.1; that is expected.
- [x] 2.4 Rename the CMS class names inside the `tinker` code strings of `e2e/` (22 files, 379 `App\…` references measured on 2026-11-18, of which 52 are the host classes that stay, so about 327 are renamed; including `global-setup.js`, which creates the e2e administrator through `App\Models\Role`) from `App\…` to `Webtack\Ecms\…`; `App\Models\User` and `App\Models\LegacyImportMap` stay, they are host classes. No e2e run happens between this task and the acceptance (task 5.2), so renaming before the switch is safe. Verify: `grep -rn 'App\\' e2e` returns only host classes and `npx playwright test --list` still lists every spec.

## 3. Switch over

- [x] 3.1 Replace `bootstrap/app.php` with the stock file, empty `routes/web.php` and `AppServiceProvider`, delete CMS code under `app/` (keep `Models/User.php`, `Legacy/`, `Console/Commands/LegacyImportCommand.php`, `Models/LegacyImportMap.php`, `Providers/AppServiceProvider.php`), delete the CMS config files, the `available_locales` key from `config/app.php` (the package owns it as `ecms.locales`) and the admin files of `lang/` (`{en,uk}.json`, `{en,uk}/auth.php`, `{en,uk}/validation.php`; keep `{en,uk}/dcculture.php` and `{en,uk}/months.php`). Then run `docker compose exec app composer dump-autoload` so `package:discover` registers the package provider. Verify `docker compose exec app php artisan about` boots, `route:list --json` matches `routes-before.json` by name and URI, and `trans('dcculture.header.title')`-style keys and `trans('months.full')` return translations, not keys (check one real key from `lang/uk/dcculture.php` in `tinker`).
- [x] 3.2 Delete CMS migrations, factories and seeders (keep Laravel defaults and `legacy_import_map`), point `DatabaseSeeder` at the package `AdminSeeder`. Verify `docker compose exec app php artisan migrate:status` shows nothing pending and no CMS migration is duplicated.
- [x] 3.3 Delete the admin frontend (`resources/js`, `resources/css`, `app.blade.php`, `tsconfig.json`, `vite.config.js`, `components.json`), trim `package.json` and the lockfile (`npm install --legacy-peer-deps` on the host), remove `public/build`, run `docker compose exec app php artisan vendor:publish --tag=ecms-assets --force`, update `.gitignore`. Verify `npm run theme:build -- dcculture` succeeds and `/admin/dashboard` loads its assets from `/vendor/ecms/build`. Delete the demo's own `themes/default` and verify the theme `default` now resolves from the package (`docker compose exec app php artisan theme:check` names the package root); verify `vite.theme.config.js` and `scripts/theme.mjs` are identical to the package's published tooling files (`diff`).

## 4. Documentation and rules

- [x] 4.1 Delete the topic docs from `docs/` (keep `e2e-testing.md`, `legacy-import.md`, `theme-dcculture.md`, `deployment.md`), rewrite `docs/README.md` and `README.md` for the consumer role, update `CLAUDE.md`/`AGENTS.md` and the `openspec/config.yaml` rules. Verify every relative link in the remaining docs resolves.
- [x] 4.2 Update `docs/legacy-import.md`, `docs/theme-dcculture.md` and `docs/e2e-testing.md` where they mention `App\` classes or moved paths. Verify with grep that no stale `App\` class reference to CMS classes remains.

## 5. Acceptance

- [x] 5.1 Run the demo PHP tests: `docker compose exec app php artisan test` (only Legacy tests remain; a full run is justified because bootstrapping, providers and config changed). Verify all pass.
- [x] 5.2 Run the full e2e suite on the host: `npm run test:e2e` (justified: authentication, shell and bootstrapping changed). Verify all specs pass; reset the user locale to `en` if the known `uk` locale failure appears (allowed by the project memory).
- [x] 5.3 Run `docker compose exec app php artisan theme:check` with `APP_THEME=dcculture` and open the public home page and an inner page in a browser to compare with the baseline. Verify no problems are reported and the pages render.

## 6. Freeze

- [x] 6.1 Lift the feature freeze: remove the freeze rule added by `package-scaffold-and-namespace` task 0.2 from `CLAUDE.md`. Verify: `grep -i freeze CLAUDE.md` finds nothing. The history re-copy into the package is not a task of this change: it needs this change to be archived first (see "Post-archive follow-up" in `design.md`).

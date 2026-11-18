# Tasks

## 1. Configuration and the skeleton of the directory of the theme

- [x] 1.1 Add `config/theme.php` (`active` from `APP_THEME`, `path`, `fallback = default`) and `APP_THEME=default` in `.env.example` (and the local `.env`), comments in English; check `docker compose exec app php artisan tinker --execute="echo config('theme.active');"` → `default`.
- [x] 1.2 Create `themes/default/theme.json`, `themes/default/hooks.php` (an empty function of the registry), `themes/default/assets/css/app.css` and the minimal Blade templates `views/{html,layout,home}.blade.php`, `views/errors/404.blade.php`, `views/pages/page.blade.php`, `views/content/list.blade.php`, `views/content/details.blade.php` (stubs; `layout` outputs the name of the site/logo/contacts from `$site`); remove `resources/views/welcome.blade.php`; check that the files exist (`ls themes/default/views`) and `welcome` is no longer mentioned anywhere (`grep -rn "welcome" app routes resources tests e2e`).

## 2. `ThemeManager`, candidates, exception

- [x] 2.1 Create `App\Theme\ThemeException` and `App\Theme\ThemeManager` (a singleton in `AppServiceProvider`): `active()`, `viewPaths()` (`[active/views, default/views]`), the registration of the Blade namespace `theme`, the deferred check of the active theme only on the public request (the admin and the console do not fall), PHPDoc in English with `@example`; check `tests/Feature/Theme/ThemeManagerTest.php` (the default theme, overriding part of the templates, a missing theme → an exception of the theme, the admin lives with a broken `APP_THEME`): `docker compose exec app php artisan test --compact --filter=ThemeManagerTest`. The tests use a fixture theme in a tmp directory and do not change `themes/`.
- [x] 2.2 Implement `ThemeManager::render(array $candidates, array $data)` (`view()->first()` over the namespace `theme`, the order «candidate → theme», `ThemeException` with the list of attempted names, the debug comment of the candidates under `app.debug`, without it no comment), PHPDoc with `@example`; check `tests/Feature/Theme/CandidateCascadeTest.php` (the more specific candidate wins; a candidate in `default` against a more general candidate of the active theme; the absence of all candidates; the debug comment is present/absent): `docker compose exec app php artisan test --compact --filter=CandidateCascadeTest`.
- [x] 2.3 Write the documentation `docs/site-themes.md` (the structure of the theme, `APP_THEME`, the cascade of candidates and the order «candidate → theme», `ThemeException`, the debug comment) in Ukrainian, add a line in `docs/README.md`; check that the relative references and the sections exist.

## 3. Hooks of the theme

- [x] 3.1 Create `App\Theme\ThemeHooks` (`preprocess`, `suggest`, the order by the dotted name from general to specific, first `default`, then the active theme, loading of `hooks.php` once, the exception `ThemeException` for a `hooks.php` that returns not a function), integrate into `ThemeManager::render()` (`suggest` before the resolution, `preprocess` before the rendering); PHPDoc with `@example`; check `tests/Feature/Theme/ThemeHooksTest.php` (the order `field` → `field.youtube`, `default` → the active theme, `suggest` adds a candidate, an invalid `hooks.php`): `docker compose exec app php artisan test --compact --filter=ThemeHooksTest`.
- [x] 3.2 Describe the hooks in `docs/site-themes.md` (the format of `hooks.php`, the names of the hooks, an example `preprocess('field.youtube')` and `suggest`); check that the example from the document works in the test `ThemeHooksTest`.

## 4. Public routes and the shell

- [x] 4.1 Create `App\Http\Controllers\Public\HomeController`, `PublicNodeController` (`show` for `/{first}`, `details` for `/{first}/{second}`), the 404 handler and the collector of the shared data of the template (`$site`, `$theme`, `$title`, `$node`/`$type`/`$paginator`, `$values` through `ContentValueReader`); the controllers form the full lists of candidates of the cascade from `design.md` (decision 6) and render through `ThemeManager::render()`; check that the controllers do not access the template directly (`grep -rn "view(" app/Http/Controllers/Public` is empty).
- [x] 4.2 Register the public routes at the end of `routes/web.php`: `/`, `/{first}`, `/{second}` with the restriction of the format of the segment (`ContentNode::SLUG_PATTERN` without anchors) and `Route::fallback` → `errors/404` with the status 404; remove the route `welcome`; the reserved paths in the DB (bypassing the validation) are not served as a page; check `docker compose exec app php artisan route:list --path=/` and `tests/Feature/Content/ReservedPathsGuardTest.php`: `docker compose exec app php artisan test --compact --filter=ReservedPathsGuardTest`.
- [x] 4.3 Add `tests/Feature/Public/PublicRoutesTest.php`: the home page 200 with `home` in `layout`; a visible page 200; disabled, scheduled, expired, non-page → 404; the list of a type with visible records; details 200; a record of another type and an invisible one → 404; the service paths (`/login`, `/admin/dashboard`) are not intercepted (including with a row-page `login` inserted bypassing the model); an unknown path 404 in `layout`; the block `/block-footer` → 404; a response without the root element of Inertia; `<title>` with the `title` of the record and the name of the site: `docker compose exec app php artisan test --compact --filter=PublicRoutesTest`.
- [x] 4.4 Update/replace the tests that depended on `welcome` or `/` (find with `grep -rn "'/'\|welcome" tests e2e`), and run the affected ones.
- [x] 4.5 Add `docs/public-site.md` (the routes, the priority of the service paths, visibility, 404, the shell, the data of the templates, the blocks are not routed), update `docs/README.md` and `docs/content-model.md` (a reference to the public routes next to the slug); check the relative references.
- [x] 4.6 The changes touch the provider, the configuration and `routes/web.php`, so run the full `docker compose exec app php artisan test --compact`; run `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 5. Vite and the resources of the theme

- [x] 5.1 Add to `vite.config.js` the entries `themes/*/assets/{css,js}` (a glob) and the output of the resources of the active theme from `html` (only when the files are in the manifest; a theme without `assets` does not break the rendering); check `npm run build` on the host (the build passes, the manifest contains `themes/default/assets/css/app.css`) and the scenario of a theme without `assets` in `ThemeManagerTest`.
- [x] 5.2 Describe the build of the resources of the themes in `docs/site-themes.md` and `docs/admin-shell.md` (Vite from `themes/*/assets`); check that the commands from the document match `package.json`.

## 6. `theme:check`

- [x] 6.1 Create `App\Console\Commands\ThemeCheckCommand` (`theme:check`): `theme.json`, `themes/default`, the required templates (`html`, `layout`, `home`, `errors/404`, `pages/page`, `content/list`, `content/details`), the correctness of `hooks.php`; the list of problems as separate lines, code 0/non-zero; PHPDoc with `@example`; check `tests/Feature/Theme/ThemeCheckCommandTest.php` (a complete theme, a missing `layout`, an invalid `hooks.php`): `docker compose exec app php artisan test --compact --filter=ThemeCheckCommandTest` and `docker compose exec app php artisan theme:check`.
- [x] 6.2 Describe the command in `docs/site-themes.md` (what it checks, the exit codes, an example of the output).

## 7. Browser checks

- [x] 7.1 Add `e2e/public-site.spec.js`: the home page, a visible page, the list and the details of a record `content`, 404 for an unknown path, `/login` is not intercepted; the data is created and cleaned up by the existing `cleanup` pattern; run `npm run test:e2e -- e2e/public-site.spec.js` (the containers are running).
- [x] 7.2 Update `docs/e2e-testing.md`: the row «публічний сайт → `e2e/public-site.spec.js`» ("public site") in the table «Область → спек»; in «Спільний код → спеки» add a row about the templates of the theme/routes; check that the tables match the existing specs.

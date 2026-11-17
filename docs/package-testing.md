# Package testing

The package has its own PHPUnit suite that runs from the package directory alone: no host application, no web server and no frontend tooling are needed. The suite uses [Orchestra Testbench](https://packages.tools/testbench) (a Laravel application skeleton) and PHPUnit 12 on in-memory SQLite.

## Running the tests

```sh
composer install
vendor/bin/phpunit                                   # the whole suite
vendor/bin/phpunit tests/Feature/Theme               # one directory
vendor/bin/phpunit --filter=ThemeManagerTest         # by name
composer test                                        # same as the first line
vendor/bin/pint --test                               # code style (composer lint)
```

In the demo application's Docker setup the package is mounted at `/var/www/e-cms-package`, so the commands run as `docker compose exec -w /var/www/e-cms-package app vendor/bin/phpunit ...`.

### Run policy

Run only the tests of the area you changed (a path or `--filter`). A full run is for changes that touch the whole package:

- migrations and the database schema;
- `tests/TestCase.php`, shared test helpers and fixtures, `phpunit.xml`;
- `composer.json` dependencies;
- global or `web` group middleware, service providers, configuration, authentication.

## Test harness

`tests/TestCase.php` extends Testbench's `TestCase` and:

- registers `Webtack\Ecms\EcmsServiceProvider` (`getPackageProviders()`);
- sets `auth.providers.users.model` to the fixture user and an application key (`defineEnvironment()`);
- adds the host's default migrations (`users`, which the package migrations extend) to the migrator path (`defineDatabaseMigrations()`); the package migrations are loaded by the provider, exactly as in a host;
- publishes the built assets of the theme `default` from `dist/themes/default` into Testbench's `public/themes/default` (refreshed when the manifest changes), as `vendor:publish --tag=ecms-assets` does in a host, so the theme tests need no frontend build;
- offers `packagePath($relative)` (absolute path of a package file; use it instead of `base_path()`) and `authorId()` (a user that authors nodes).

### Differences from a real host

The harness reproduces a host in the ways the tests rely on. Each of these is a Testbench difference, not package behaviour:

| Difference | What the harness does |
|------------|-----------------------|
| Testbench does not discover the providers of the package's own dependencies | `getPackageProviders()` lists `Inertia\ServiceProvider` next to the package provider. |
| Testbench resolves the HTTP kernel after the providers boot; a real host resolves it first, and the kernel copies its middleware groups to the router when it is built, which discards what a provider pushed earlier | `defineEnvironment()` resolves the kernel before the providers register. |
| SQLite foreign keys are off in Testbench's `testing` connection | `database.connections.testing.foreign_key_constraints` is `true`, as in a Laravel application. |
| `assertInertia()` looks for the page component in the skeleton's `resources/js/pages` | `inertia.pages.paths` points at the package's `resources/js/Pages`. |
| The skeleton has no `.env` | `phpunit.xml` sets `APP_LOCALE=uk` (the base language and interface locale most tests expect), `APP_THEME=default` and the in-memory SQLite database. |
| `route:cache` boots a separate skeleton application that has neither the package nor the test's routes | `RouteOrderTest` writes the cache file from the routes of the running application, boots the application again and loads that file the way a host's route service provider does. |
| The skeleton has no `config/ecms.php` and no `themes/` directory | This is what a fresh host looks like, and the tests rely on it. |

### Files a test writes into the skeleton

Tests that play the host (publishing files, adding `lang/` or `themes/`, running the installer) use the trait `Tests\Concerns\OverridesHostFiles`: `hostFile()` writes a file, `hideHostFile()` moves one aside, `forgetHostPath()` registers a path the test may create; all of them are restored or removed after the test, so the next test sees the skeleton as it was. A test that leaves files behind (for example after a fatal error) can break later ones: delete `vendor/orchestra/testbench-core/laravel/{themes,scripts,vite.theme.config.js,public/storage,public/vendor,public/app}` and any of the six config files of the package there.

`phpunit.xml` sets the same environment values the demo application used (`APP_THEME=default`, in-memory SQLite, array cache and session, sync queue).

## Fixtures

| File | Purpose |
|------|---------|
| `tests/Fixtures/User.php` | `Webtack\Ecms\Tests\Fixtures\User`: the user model of the suite. It implements `EcmsUser`, uses `HasEcmsAccess` and is bound to the package `UserFactory`, exactly as a host model would be. |
| `tests/Fixtures/word97.doc` | A Word 97 document used by the file storage tests. |

`UserFactory` builds the model returned by `UserModel::class()` (the configured user model), so the same factory serves a host.

## Layout

| Directory | Contents |
|-----------|----------|
| `tests/Unit`, `tests/Feature/*` | The behavioural tests of the CMS, grouped by area (Access, Admin, Auth, Content, Dashboard, Files, Locale, Menus, Navigation, Public, Settings, Taxonomy, Theme) |
| `tests/Feature/Package` | Tests of what only a package guarantees (see the table below) |

The legacy import is not part of the package and has no tests here.

### Package tests

| Test class | What it locks in |
|------------|------------------|
| `RegistrationTest` | automatic registration, merged configuration, gates, the `web` middleware group, redirects, the theme 404 page |
| `PublicSiteOffTest`, `AuthRoutesOffTest` | the `ecms.public_site` and `ecms.auth_routes` switches |
| `RouteOrderTest` | host routes before the public catch-all, also with cached routes |
| `ThemeRootsTest` | theme lookup: the application's `themes/` over the package's |
| `TranslationOverrideTest` | the override rule for interface translations |
| `LocalesTest` | `ecms.locales` in a host without `app.available_locales`, the locale of the public site |
| `LanguagePrefixTest`, `HostBeforeMigrationsTest` | the language prefix: protected host routes, no database access before the package migrations |
| `ThemeTemplateCopierPackageTest` | cloning type templates without and with an application `themes/` directory |
| `RolelessUserTest` | a user without a role: no rights, a legal target of the user actions |
| `UsersTableMigrationsTest`, `Access/RolesMigrationTest` | the migrations keep the rows of the host's `users` table and never assign a role |
| `AdminAssetsTest` | the missing-assets message, the Inertia version from the package manifest |
| `FactoryResolutionTest` | every model finds the package factory; the user factory builds the configured model |
| `InstallCommandTest` | `ecms:install`: first and repeated run, stock and customised user model, credentials, the integer-key precondition |
| `PublishTagsTest` | `ecms-theme`, `ecms-theme-tooling`, `ecms-assets` (with and without static files) |

## Continuous integration

`.github/workflows/tests.yml` runs on every push and pull request:

- `php` job, PHP 8.3 and 8.4 (`composer update --prefer-stable`): `vendor/bin/phpunit` and `vendor/bin/pint --test`;
- `dist` job, Node 22: `npm ci` and `npm run check:dist`, which fails when the sources changed and the committed `dist/build` was not rebuilt ([package-frontend.md](package-frontend.md)).

`composer.lock` is not committed (the package is a library), so CI resolves the newest dependencies each time.

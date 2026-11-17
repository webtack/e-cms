# Upgrading

## From an application that ran the CMS as its own code

Before the package existed the CMS lived in the application itself (`App\…`, its own `config/`, `routes/`, `lang/`, `resources/js` and `themes/default`). Moving such an application to `webtack/e-cms` needs these steps. The database does not change: the migrations of the package keep the file names of the application's migrations, so a database that already ran them does not run them again.

1. **Namespace.** Every class moves from `App\…` to `Webtack\Ecms\…` ([package-structure.md](docs/package-structure.md#namespace-mapping)). Remove the CMS code from `app/` and update the references in your own code (`App\Models\ContentNode` becomes `Webtack\Ecms\Models\ContentNode`, and so on). The user model stays in the application and gets the contract: `implements EcmsUser` and `use HasEcmsAccess` ([integration.md](docs/integration.md#the-user-model)).

2. **`ThemeHooks` import.** In every `hooks.php` of an application theme change

   ```php
   use App\Theme\ThemeHooks;
   ```

   to

   ```php
   use Webtack\Ecms\Theme\ThemeHooks;
   ```

   Class names written as strings (for example in `config/content.php` and `config/files.php`) change the same way.

3. **Interface languages.** The list moves from `config/app.php` `available_locales` to `ecms.locales` in `config/ecms.php` (code => the language's own name). Delete the old key; the package does not read it ([configuration.md](docs/configuration.md#ecmsphp)).

4. **Admin translation files.** The package now ships `lang/{en,uk}.json`, `lang/{en,uk}/auth.php` and `lang/{en,uk}/validation.php`. Delete your copies unless you changed them. Your `lang/*.json` still overrides the package's JSON dictionary key by key; a key that the package defines in `auth.php` or `validation.php` now overrides the same key of your file ([integration.md](docs/integration.md#translations)).

5. **Language-prefix middleware.** `ResolvePublicLanguage` is registered by the service provider. Remove it, and the other CMS middleware, from `bootstrap/app.php`, and remove the CMS routes from `routes/web.php`: the provider registers them, the public ones after every route of yours.

6. **Configuration.** Delete the CMS config files from `config/` or publish the package's (`php artisan vendor:publish --tag=ecms-config`) and re-apply your changes. A key that you do not define keeps the package default.

7. **Frontend.** Remove the admin sources (`resources/js`, `resources/css`), the admin Vite and TypeScript configuration and the admin dependencies from `package.json`. Publish the compiled admin: `php artisan vendor:publish --tag=ecms-assets --force`. The application's own `public/build` and `@vite` are not touched by the CMS.

8. **Themes.** `themes/default` is shipped by the package. If you kept a copy, delete it, or refresh it from the package (`vendor:publish --tag=ecms-theme --force`) and do not edit it; put your changes in a theme of your own ([theming.md](docs/theming.md#refreshing-the-published-default)). To build a theme in the application, publish the build tooling with `--tag=ecms-theme-tooling`.

9. **Run the installer** once to check the result: `php artisan ecms:install`. Every step reports `already done` where the application already has it.

## Per version

### 0.1.0

First release. Nothing to upgrade from; see the section above if the application ran the CMS as its own code.

From `0.x` to `1.0` the public API (the contracts, the config keys, the template variables, the publish tags) may change in a minor version; each change is listed here before the release.

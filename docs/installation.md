# Installation

How to add `webtack/e-cms` to a Laravel application, what the installer does, how to update and how to remove the package. The architecture is in [package-structure.md](package-structure.md); the integration with an existing application (users, routes, login) is in [integration.md](integration.md).

## Requirements

| Requirement | Detail |
|-------------|--------|
| PHP | 8.3 or newer |
| Laravel | 13.17 or newer (the package requires `laravel/framework: ^13.17`) |
| Database | A database supported by Laravel; the migrations use plain schema builder calls. The package suite runs on SQLite, the demo application on MySQL. |
| PHP extension for images | `imagick` (the default, `FILES_IMAGE_DRIVER=imagick`) or `gd` (`FILES_IMAGE_DRIVER=gd`) for uploads, thumbnails and image styles. |
| Users | The `users` table with an auto-incrementing integer key ([limits](integration.md#limits)). |
| Sessions | The `web` middleware group with sessions (the admin panel signs users in through a session guard). |
| Node and npm | **Not needed.** The admin frontend ships compiled in the package ([package-frontend.md](package-frontend.md)). Node is needed only to build your own theme ([theming.md](theming.md#building-the-assets)). |

Composer installs `inertiajs/inertia-laravel`, `intervention/image` and `symfony/html-sanitizer` with the package. The host does not need an Inertia frontend of its own: the package has a private root view and a private Vite instance, and never reads the host's `app.blade.php`, `public/build` or `@vite`.

## Install

1. Require the package:

   ```bash
   composer require webtack/e-cms
   ```

   Until the package is published on a registry, add its repository first (a VCS repository, or `path` for a local checkout) and require the released version. A path repository takes the version from the git tag of the checkout (`v0.1.0`); a checkout past the tag, or without tags, has only `dev-main`, which needs `webtack/e-cms:@dev` under the default `minimum-stability`:

   ```bash
   composer config repositories.ecms path ../e-cms-package
   composer require "webtack/e-cms:^0.1"
   ```

2. Configure the database and the application in `.env` as usual (`APP_URL`, `DB_*`, `APP_KEY`). Mail is needed only if you use email verification.

3. Run the installer:

   ```bash
   php artisan ecms:install
   ```

   It asks for the email and the password of the first administrator. For a non-interactive run (CI, provisioning):

   ```bash
   php artisan ecms:install --admin-email=owner@example.com --admin-password='a-long-secret' --no-interaction
   ```

   The email and the password are never defaulted. They are read from `--admin-email` / `--admin-password`, then from `ADMIN_EMAIL` / `ADMIN_PASSWORD`, then prompted.

4. Open `/login`, sign in with that account and you land on `/admin/dashboard`.

## What the installer does

`ecms:install` is idempotent: each step reports `done` or `already done`, and a second run changes nothing. The steps and their order are in [package-installer.md](package-installer.md#ecmsinstall). In short it:

1. checks that the user model uses the `users` table with an integer key;
2. publishes `config/{content,files,theme,social_networks,limits,ecms}.php` (skipped when `config/ecms.php` exists, unless `--force`);
3. runs `php artisan migrate` (the package loads its own migrations);
4. makes the user model implement `EcmsUser` and use `HasEcmsAccess` (only for the default Laravel `User`; for any other model it prints the lines to add);
5. creates the administrator, or promotes an existing account after confirmation (`--force` skips the question);
6. runs `storage:link`;
7. publishes the compiled admin and the built `default` theme (`vendor:publish --tag=ecms-assets --force`).

Options:

| Option | Meaning |
|--------|---------|
| `--admin-email=` | Email of the administrator. |
| `--admin-password=` | Password of a new administrator. |
| `--force` | Overwrite published files, run migrations in production, promote an existing account without asking. |
| `--no-interaction` | Never prompt; a missing value fails the step naming it. |

The step order can be run by hand: `vendor:publish --tag=ecms-config`, `migrate`, the two lines in the user model, `db:seed --class='Webtack\Ecms\Database\Seeders\AdminSeeder'`, `storage:link`, `vendor:publish --tag=ecms-assets --force`.

Publish tags:

| Tag | Publishes |
|-----|-----------|
| `ecms-config` | The six configuration files ([configuration.md](configuration.md)). |
| `ecms-assets` | The compiled admin and the built `default` theme into `public/`. |
| `ecms-lang` | The translation files into `lang/` ([integration.md](integration.md#translations)). |
| `ecms-views` | The root view into `resources/views/vendor/ecms`. |
| `ecms-theme` | The source of the `default` theme into `themes/default`, as a model ([theming.md](theming.md)). |
| `ecms-theme-tooling` | `vite.theme.config.js` and `scripts/theme.mjs` for building a theme in the host. |

## Update

```bash
composer update webtack/e-cms
php artisan migrate
php artisan vendor:publish --tag=ecms-assets --force
```

The package delivers new migrations through the provider, so `migrate` is enough for the schema. The assets must be published again, otherwise the browser keeps the old admin bundle. Add the publish to the host's `composer.json` to do it on every update:

```json
"scripts": {
    "post-update-cmd": [
        "@php artisan vendor:publish --tag=ecms-assets --force"
    ]
}
```

If you published the theme `default` with `ecms-theme`, refresh it too ([theming.md](theming.md#refreshing-the-published-default)). Read [../UPGRADING.md](../UPGRADING.md) and [../CHANGELOG.md](../CHANGELOG.md) before a new minor version: the package is `0.x` and the API may change.

## Production notes

The package relies on a few settings that belong to the host. Without them it works, but not as intended.

- **Trusted proxies.** The per-IP request limits (public search, failed logins from one address, `LIMIT_*` in [configuration.md](configuration.md#request-limits-limitsphp)) read the client address from the request. Behind a reverse proxy, a load balancer or a CDN the host must declare its trusted proxies (`trustProxies()` in `bootstrap/app.php`), otherwise every visitor shares the address of the proxy and the limits fire for everyone at once.
- **Secure session cookie.** Over HTTPS set `SESSION_SECURE_COOKIE=true` so the session cookie is never sent over plain HTTP.
- **Request limits.** `LIMIT_SEARCH_PER_MINUTE` (60), `LIMIT_UPLOAD_PER_MINUTE` (120), `LIMIT_LOOKUP_PER_MINUTE` (120), `LIMIT_YOUTUBE_PER_MINUTE` (20), `LIMIT_LOGIN_IP_ATTEMPTS` (20) and `LIMIT_LOGIN_IP_WINDOW` (300 seconds) tune the limiters. The defaults are in `config/limits.php`; the installer does not write them into `.env`.
- **Upload limits.** Raise `upload_max_filesize` and `post_max_size` in PHP and `client_max_body_size` in nginx above the largest upload profile (30 MB by default, [file-storage.md](file-storage.md)).
- **Cache for view counters.** With `CONTENT_VIEWS_TTL` above zero the visit deduplication lives in the cache store, which must persist between requests (not `array`).
- **Route cache.** `php artisan route:cache` works; the package routes are cached in the same order ([package-bootstrap.md](package-bootstrap.md#routes-and-their-order)).
- **Storage.** Files go to the `public` disk by default, so `public/storage` must exist (`storage:link`) and be served by the web server. Missing web server rules for image styles are described in [image-styles.md](image-styles.md).

## Removing the package

There is no uninstall command: the content tables hold the site's data, and dropping them is a decision of the host.

1. Remove the `EcmsUser` interface and the `HasEcmsAccess` trait from the user model.
2. Roll back or drop the tables of the package by hand. The migration names in `database/migrations` of the package tell which tables belong to it (`content_*`, `media*`, `app_*`, taxonomy, menu, role and favorite tables, plus the columns `locale`, `role_id`, `is_active` and `avatar` of `users`).
3. `composer remove webtack/e-cms`.
4. Delete the files published into the host: `config/{content,files,theme,social_networks,limits,ecms}.php`, `public/vendor/ecms`, `public/themes/default`, `public/app/default`, and `themes/default`, `vite.theme.config.js`, `scripts/theme.mjs` if you published them.

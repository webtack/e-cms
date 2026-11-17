# Integration with a host application

How `webtack/e-cms` fits into an existing Laravel application: the user model, existing users, routes, login, translations and the limits of the package. Installation steps: [installation.md](installation.md). The registration details: [package-bootstrap.md](package-bootstrap.md).

## The user model

The package does not own a user class. It uses the model of `auth.providers.users.model` and requires it to:

- implement `Webtack\Ecms\Contracts\EcmsUser`;
- use the trait `Webtack\Ecms\Concerns\HasEcmsAccess` (it implements `role()`, `favoriteTypes()`, `avatarUrl()` and casts `is_active`);
- live in the table `users` with an auto-incrementing integer key.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Webtack\Ecms\Concerns\HasEcmsAccess;
use Webtack\Ecms\Contracts\EcmsUser;

class User extends Authenticatable implements EcmsUser
{
    use HasEcmsAccess;
}
```

`ecms:install` makes this edit for a default Laravel `User`. For any other shape it prints the lines to add, and the model keeps working only after you add them (a model without the contract fails with a type error on the first admin request). Details of the contract: [package-structure.md](package-structure.md#the-user-model-contract).

The package adds four columns to `users` ([package-installer.md](package-installer.md#migrations)): `locale`, `role_id`, `is_active` and `avatar`. The admin also writes `email_verified_at` for users it creates, so keep that column (the Laravel default). Email verification is not required: the `verified` check of the admin applies only when your model implements `Illuminate\Contracts\Auth\MustVerifyEmail`, and the package sends no verification mail.

## Existing users and roles

Migrations never assign a role. After the installation every existing user has `role_id = null`, which means **no rights in the CMS**: such a user is signed in normally in your application but cannot open `/admin` ([access-control.md](access-control.md#users-without-a-role)).

To give an existing account the administrator role:

- run the installer with its email: `php artisan ecms:install --admin-email=existing@example.com` (it asks for confirmation; `--force` skips the question). The account keeps its password unless `--admin-password` is given, and its email is marked verified;
- or run the seeder with `ADMIN_EMAIL` and `ADMIN_PASSWORD` set: `php artisan db:seed --class='Webtack\Ecms\Database\Seeders\AdminSeeder'`.

An administrator then assigns the other roles in the admin panel ([users](admin-users.md), [roles](admin-roles.md)). The role `admin` is created by the migration, cannot be changed or deleted, and the last active administrator cannot be deactivated or deleted.

## Routes

The provider registers the routes itself: you edit no `bootstrap/app.php` list and no `routes/web.php`. The order is what matters:

- The service routes (`/admin/*`, `/login`, `/logout`, `/locale`, the email verification notice, the image styles) are registered when the provider boots.
- The public routes (`/`, `/search`, `/sitemap`, `/sitemap.xml`, and the catch-all `/{path}`) are registered after **every** other route, including your `routes/web.php`.

So your own routes win over the catch-all: `GET /contact` of the host is served by the host, and `/contact` is never looked up among the CMS pages ([package-bootstrap.md](package-bootstrap.md#routes-and-their-order)).

Two cases need action:

- A host route with exactly the same method and URI as a public route (`GET /`, `GET /search`, `GET /sitemap`, `GET /sitemap.xml`) is replaced by the CMS one. The stock `Route::get('/', ...)` welcome route of a new Laravel application is such a route: remove it.
- The first segment of a CMS page or type address must not be a reserved path (`content.reserved_paths`, [content-model.md](content-model.md#reserved-paths)). The CMS refuses such slugs; if the host owns a first segment the CMS does not know, such as `/shop`, nothing breaks as long as no page uses the slug `shop`, because the host route is matched first.

To use only the admin panel and keep the whole public side in the host, set `ECMS_PUBLIC_SITE=false`: none of the public routes, the language prefix or the theme 404 page is registered.

### Language codes are reserved

An active additional language (for example `en`) prefixes the public addresses: `/en/about-us`. The language code therefore becomes a reserved first segment of the application. When a language is added in the admin, a code equal to the first segment of a route of the host is refused: a host route such as `/en/contact` conflicts with the language `en`, and the admin reports it ([package-bootstrap.md](package-bootstrap.md#language-prefix-of-public-addresses)). Rename the host route or use another code. The routes of the package itself are not counted.

## Login

By default the package registers `/login`, `/logout` and the verification notice and sends guests to `/login` and signed-in users to `/admin/dashboard`. The login page is the package's React page.

If the host has its own authentication (Breeze, Fortify, a custom login), set `ECMS_AUTH_ROUTES=false`. Then:

- the package registers none of those routes and none of the redirects, so the host's `login` route and `redirectGuestsTo()` / `redirectUsersTo()` stay in effect;
- the host's login must be a route named `login` that authenticates a user of the configured model, because the admin routes use the standard `auth` middleware (the default guard);
- the package's `/admin/*` routes stay unchanged. A deactivated user is signed out through the `web` guard, so a host with a session guard under another name is not covered by this check (not tested).

With `auth_routes` on, the redirect callbacks of the package are process-wide and replace a host's own `redirectGuestsTo()`.

## Mail, queue and storage

- **Mail.** The package sends no mail. Password reset and email verification are not part of it ([admin-auth.md](admin-auth.md)).
- **Queue.** The package dispatches no jobs.
- **Storage.** Uploads go to the `public` disk and need `php artisan storage:link` (the installer runs it). Image styles are generated lazily by a route, so the web server must pass non-existent files under `storage/styles` to Laravel ([image-styles.md](image-styles.md)).
- **Cache.** The view counter deduplication (`CONTENT_VIEWS_TTL` above zero) uses the cache store.
- **Sessions.** The admin relies on the `web` middleware group, and the provider adds `AuthenticateSession` (it ends the other sessions of a user whose password changed) unless the host already has it in the group.

## Translations

The package ships the admin in two languages, `en` and `uk`: `lang/en.json`, `lang/uk.json`, `lang/{en,uk}/auth.php` and `lang/{en,uk}/validation.php`. The interface languages come from `ecms.locales` ([configuration.md](configuration.md#ecmsphp)); the host does not need an `available_locales` key in `config/app.php`.

- **JSON dictionary** (`lang/{code}.json`): the package file is read first and the host's `lang/{code}.json` on top. A key of the host always wins, and a key the package lacks is added.
- **PHP groups** (`auth.php`, `validation.php`): a key that the package defines **overrides** the same key in the host's file. Keys the package does not define stay with the host. This is a known limit: on an overlapping key the package text wins.

### Adding an interface language

1. Add it to `ecms.locales` in the host's `config/ecms.php`, for example `'pl' => 'Polski'`.
2. Copy the package's `lang/en.json` (publish it with `php artisan vendor:publish --tag=ecms-lang`) to the host's `lang/pl.json` and translate the values. Do not change the keys.
3. Optionally add `lang/pl/auth.php` and `lang/pl/validation.php`; without them the fallback to `en` applies.

No code change is needed: the language switcher is built from `ecms.locales`. More: [admin-i18n.md](admin-i18n.md#override-rule). The interface languages are separate from the content languages of the site, which are managed in the admin ([content-languages.md](content-languages.md)).

## Limits

- The admin prefix is fixed: `admin`. A host that already has a `/admin` route is not supported.
- The user table is `users` with an integer primary key. UUID and string keys are not supported.
- The package assumes it owns `/login` and `/logout` unless `ECMS_AUTH_ROUTES=false`.
- A host served from a sub-directory is not supported: the compiled admin uses absolute asset paths.
- The package is `0.x`: the public API may change between minor versions ([../UPGRADING.md](../UPGRADING.md)).
- A host theme named `default` shadows the shipped one entirely ([theming.md](theming.md#overriding-default)).

## Conflicts at a glance

| The host has | Result |
|--------------|--------|
| its own login | set `ECMS_AUTH_ROUTES=false` |
| `GET /`, `/search`, `/sitemap`, `/sitemap.xml` | replaced by the CMS; remove it or set `ECMS_PUBLIC_SITE=false` |
| another route such as `/contact` | the host route wins over `/{path}` |
| a route `/en/...` | the language `en` cannot be added |
| a `/admin` prefix | not supported |
| its own `redirectGuestsTo()` / `redirectUsersTo()` | replaced while `auth_routes` is on |
| no `available_locales` in `config/app.php` | nothing to do |

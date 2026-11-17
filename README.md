# E-CMS

`webtack/e-cms` is a multilingual content management system for Laravel. It adds an admin panel (Inertia, React, Tailwind), a flexible content model, a media library, menus, taxonomy, role-based access and a theme system for the public site, to an existing Laravel application. The admin ships compiled, so the host needs **no Node and no build** to use it.

## Features

- **Content model** — content types of three kinds (content, pages, blocks) with 15 field types, translations per field, node queries and a public search.
- **Admin panel** — types and fields, nodes, menus, taxonomy, media library, users and roles, dashboard, site settings; the interface is available in English and Ukrainian and extends to other languages.
- **Access control** — roles with per-section rights and `none` / `own` / `any` levels for content and media.
- **Media** — upload profiles, image downscaling, image styles (`thumb`, `300x250`) generated on demand, usage tracking.
- **Public site** — a catch-all router with a language prefix, a sitemap, SEO metadata and a theme system with a template cascade, hooks and a Vite build; a default theme is included.
- **Installer** — `php artisan ecms:install` migrates, prepares the user model and creates the first administrator.

## Requirements

- PHP 8.3 or newer and Laravel 13.17 or newer
- A database supported by Laravel
- The `imagick` or `gd` PHP extension
- A `users` table with an auto-incrementing integer key

## Quick start

In a fresh Laravel application with a configured database (`.env`):

```bash
composer require webtack/e-cms
php artisan ecms:install
```

`ecms:install` asks for the email and the password of the first administrator, publishes the configuration, runs the migrations, adds the CMS interface and trait to the `User` model, creates the storage link and publishes the compiled admin. For a non-interactive run:

```bash
php artisan ecms:install --admin-email=owner@example.com --admin-password='a-long-secret' --no-interaction
```

Start the application (`php artisan serve`), open `/login`, sign in with that account and you land on `/admin/dashboard`.

Before the package is published on a registry, add its repository to the application's `composer.json` first and require the released version (a path repository takes the version from the git tag the checkout is on):

```bash
composer config repositories.ecms path ../e-cms-package
composer require "webtack/e-cms:^0.1"
php artisan ecms:install
```

The stock `GET /` welcome route of a new Laravel application is replaced by the CMS home page: remove it from `routes/web.php`, or set `ECMS_PUBLIC_SITE=false` to keep the public site in the host ([integration](docs/integration.md#routes)).

## Documentation

- [Installation](docs/installation.md), [integration with an existing application](docs/integration.md), [configuration](docs/configuration.md) and [theming](docs/theming.md)
- [The documentation index](docs/README.md) lists the reference page of every component
- [Upgrading](UPGRADING.md) and [changelog](CHANGELOG.md)
- [Development history](docs/HISTORY.md) — every change is recorded as an OpenSpec proposal, design and spec
- [Contributing](CONTRIBUTING.md)

## License

MIT, see [LICENSE](LICENSE).

# Package structure

The CMS ships as the Composer package `webtack/e-cms`. This page describes what the package contains, how its code is namespaced, what stays in a host application, and how a host user model takes part in the CMS.

## Layout

| Path | Contents |
|------|----------|
| `composer.json` | Identity `webtack/e-cms`, runtime dependencies, PSR-4 autoloading |
| `src/` | All PHP code, namespace `Webtack\Ecms\` (same sub-directories as the demo's former `app/`: `Models`, `Http/Controllers/Admin`, `Files`, `Theme`, …) and `helpers.php` (global helpers such as `image_style()`, loaded by Composer `files`) |
| `config/` | `content.php`, `files.php`, `theme.php`, `social_networks.php`, `limits.php`, `ecms.php` |
| `database/migrations` | Schema of the CMS (without the Laravel defaults, see below) |
| `database/factories`, `database/seeders` | `Webtack\Ecms\Database\Factories\`, `Webtack\Ecms\Database\Seeders\` |
| `lang/` | `{en,uk}.json` (admin UI), `{en,uk}/auth.php`, `{en,uk}/validation.php` |
| `resources/` | Admin frontend sources (`js`, `css`) and the root view |
| `routes/` | `service.php` (always loaded) and `public.php` (public site, loaded last), see [package-bootstrap.md](package-bootstrap.md) |
| `themes/default/` | The default theme (`theme.json`, `views/`, `assets/`, `hooks.php`) |
| `tests/` | The package test suite (introduced in the change `package-tests-on-testbench`) |
| `docs/` | Topic documentation |
| `openspec/` | Specifications and change history |

## Namespace mapping

| Demo application | Package |
|------------------|---------|
| `App\…` | `Webtack\Ecms\…` |
| `Database\Factories\…` | `Webtack\Ecms\Database\Factories\…` |
| `Database\Seeders\…` | `Webtack\Ecms\Database\Seeders\…` |
| `Tests\…` | `Webtack\Ecms\Tests\…` |

Rules:

- No file of the package declares or references a class in the host's `App\` namespace.
- Class names written as strings (`config/content.php`, `config/files.php`), theme `hooks.php` files and docblocks in templates use the package namespace too.
- A theme's `hooks.php` must import `Webtack\Ecms\Theme\ThemeHooks` (it was `App\Theme\ThemeHooks`); see [site-themes.md](site-themes.md) and [theme-templates.md](theme-templates.md).
- Config keys (`content.*`, `files.*`, `theme.*`) keep their names.

## What stays out of the package

The package takes what belongs to the admin panel and the theme `default`. Everything else stays in the host (the demo application):

| Not packaged | Why |
|--------------|-----|
| `Models/User.php` | The host owns its user model, see [the user model contract](#the-user-model-contract) |
| Laravel-default migrations for `users`, `cache`, `jobs` | The host already has them |
| The legacy import (`Legacy/`, `LegacyImportCommand`, `LegacyImportMap`, its migration, `config/legacy.php`) | One-off code of the owner's old site |
| `themes/dcculture` and `lang/{en,uk}/{dcculture,months}.php` | Application theme and its translations |
| `e2e/` | Browser tests of the demo |
| `docs/{e2e-testing,legacy-import,theme-dcculture,deployment}.md` | Demo-only documentation |

## The user model contract

The package does not name a concrete user class.

- `Webtack\Ecms\Support\UserModel::class()` returns the class configured in `auth.providers.users.model`; `UserModel::query()` is a query on it. Relations from nodes and files to the author and from a role to its users use it.
- `Webtack\Ecms\Contracts\EcmsUser` extends `Illuminate\Contracts\Auth\Authenticatable` and declares `role()`, `favoriteTypes()`, `avatarUrl()` and the constant `MEDIA_OWNER`. Package code that works with a CMS user type-hints it. An interface cannot declare attributes, so the columns `role_id`, `is_active`, `locale` and `avatar` are documented as `@property` in its docblock.
- `Webtack\Ecms\Concerns\HasEcmsAccess` implements the three methods and casts `is_active` to boolean.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Webtack\Ecms\Concerns\HasEcmsAccess;
use Webtack\Ecms\Contracts\EcmsUser;

class User extends Authenticatable implements EcmsUser
{
    use HasEcmsAccess;
}
```

Notes:

- `Webtack\Ecms\Database\Factories\UserFactory` builds the configured user model; the host model binds it with `#[UseFactory(UserFactory::class)]`. Other factories that need an author call `UserModel::class()::factory()`.
- A route parameter typed `EcmsUser` (for example `{user}` in the admin user routes) is not resolved by implicit binding, because an interface is not a model. The service provider registers an explicit binding with `Route::model('user', UserModel::class())` (wired in the change `package-service-provider-and-routing`).

## Factories

Models with `HasFactory` bind their factory explicitly with `#[UseFactory(XFactory::class)]`, so factories resolve without relying on the `Database\Factories\` naming convention.

## Copy, not move

The package was created by copying the code from the demo application and renaming namespaces with a script; the demo keeps its `app/` until the last change of the package series (`demo-app-consumes-package`) switches it to the package. Until then the shared code is frozen in the demo (see the demo's `CLAUDE.md`): a fix made there is re-applied to the package by re-running the idempotent rename script on the changed files. The package repository starts without the demo's git history; the history of decisions is kept in `openspec/`.

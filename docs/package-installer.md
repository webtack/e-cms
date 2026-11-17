# Package installer

How the database schema of `webtack/e-cms` reaches a host application and how the first administrator is set up. Auto-registration of the package: [package-bootstrap.md](package-bootstrap.md).

## Migrations

The package loads its migrations itself (`loadMigrationsFrom()` in `EcmsServiceProvider`), so the host's `php artisan migrate` runs them and an update of the package delivers new migrations without a publish step. The file names are the same as in the demo application, so a database that already ran them does not run them again.

The package does **not** create the host's `users`, `cache`, `jobs`, `sessions` or `password_reset_tokens` tables. It extends the host `users` table:

| Column | Migration | Meaning |
| --- | --- | --- |
| `locale` | `2026_10_28_190000_add_locale_to_users_table` | Interface language (null = not chosen). |
| `role_id` | `2026_11_03_100000_create_roles_and_permissions` | Nullable role reference. |
| `is_active` | the same | Activity flag, default active. |
| `avatar` | `2026_11_16_110000_add_avatar_to_users_table` | Optional avatar reference. |

Existing rows keep their data: they stay active and get no role and no avatar. **The migrations never assign a role.** A user without a role has no rights in the CMS and cannot open the admin panel ([access control](access-control.md#users-without-a-role)). The role `admin` is created by the migration; the installer gives it explicitly.

The migration order relies on the Laravel skeleton: its users migration is dated `0001_01_01_000000`, so it runs before the dated package migrations.

## `ecms:install`

```
php artisan ecms:install [--admin-email=…] [--admin-password=…] [--force] [--no-interaction]
```

Run it once after `composer require webtack/e-cms`. It is safe to run again: each step reports `done` (it changed something) or `already done`, and a repeated run changes nothing.

| Step | What it does |
| --- | --- |
| User model | Checks that the user model of the `users` provider uses the table `users` with an auto-incrementing integer key. Stops before anything is created otherwise. Reports `ok`. |
| Configuration | `vendor:publish --tag=ecms-config`. Skipped when `config/ecms.php` exists, unless `--force`. |
| Migrations | `php artisan migrate` (the host's and the package's). Skipped when nothing is pending. |
| User model preparation | Makes the model implement `EcmsUser` and use `HasEcmsAccess` (see below). |
| Administrator | Creates or promotes the first administrator (see below). |
| Storage link | Creates `public/storage` when missing. |
| Admin assets | `vendor:publish --tag=ecms-assets --force`: the compiled admin and the built assets of the theme `default` (see [package-frontend.md](package-frontend.md)). Reports `done` on every run, because the files are refreshed from the package. |

The command exits with a failure status when a step failed; the other steps still run (except after a failed precondition or failed migrations, which stop it).

### User model preparation

A default Laravel `User` (the line `use HasFactory, Notifiable;` and `class User extends Authenticatable`) is edited: the two imports are added, `implements EcmsUser` is appended and `HasEcmsAccess` is put into the trait list. The step is idempotent (it checks for the trait name). Any other shape is **not** touched: the installer prints the exact lines to add and exits with a failure status after the other steps.

```php
use Webtack\Ecms\Concerns\HasEcmsAccess;
use Webtack\Ecms\Contracts\EcmsUser;

class User extends Authenticatable implements EcmsUser
{
    use HasEcmsAccess;
}
```

The trait makes `role_id` and `is_active` mass-assignable and casts `is_active` to boolean; `locale` stays non-mass-assignable. The installer does not run Pint on host code, so check the diff.

### Administrator

The email and the password come from, in this order: `--admin-email` / `--admin-password`, the variables `ADMIN_EMAIL` / `ADMIN_PASSWORD`, a prompt. **There are no defaults in any environment.** With `--no-interaction` and a missing value the step fails naming it and creates nothing.

- A new account is created with a verified email and the role `admin`.
- An **existing account** with that email (the usual case in a host that already has users) is promoted only after confirmation, or with `--force`; without either the step fails and changes nothing. A promoted account keeps its password unless `--admin-password` is given, and its email is marked verified.
- When an administrator already exists and no email was given, the step reports `already done` and does not prompt.
- Other existing users stay without a role; give them roles in the admin panel ([users](admin-users.md)).

```
php artisan ecms:install --admin-email=owner@example.com --admin-password='…' --no-interaction
php artisan ecms:install --admin-email=existing@example.com --force      # promote an existing account
```

The seeder `Webtack\Ecms\Database\Seeders\AdminSeeder` holds this logic. `php artisan db:seed --class='Webtack\Ecms\Database\Seeders\AdminSeeder'` runs it with `ADMIN_EMAIL` / `ADMIN_PASSWORD` only (both required; see [admin-auth.md](admin-auth.md#initial-administrator-seeder)).

## Limitations

- The user table is named `users` and has an integer primary key (UUID and string keys are not supported; the content tables reference `users.id` as a big integer).
- The installer does not write the host's `.env`; limits have defaults in `config/limits.php`.
- After updating the package, publish the assets again (`ecms-assets`); see [package-frontend.md](package-frontend.md#publishing).

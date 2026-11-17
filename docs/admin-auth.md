# Admin authentication

Custom minimal authentication (without Breeze) built on Laravel's standard mechanisms.

## Login and logout

- `GET /login` — the login page (`Pages/Auth/Login.tsx`), for guests only. An authenticated user is redirected to `/admin/dashboard`. In the top right corner of the page there is a `LocaleSwitcher` (the guest's language is stored in the `locale` cookie, see [multilingual admin](admin-i18n.md)); login errors (`auth.failed`, `auth.throttle`) are shown in the language of the request.
  Look: the page has the full-page background `resources/images/auth-bg.png` (bundled by Vite); the form is a `bg-card` block with a border, padding, and shadow, with the logo (`BrandLogo`, the white variant in the dark theme) and the site name (shared prop `site.name`, see [admin shell](admin-shell.md#routes-and-shared-props)) at its top. Errors are shown inside the block.
- `POST /login` — `Auth\LoginController@store` with `LoginRequest`: `Auth::attempt`, `session()->regenerate()`, redirect to the previously requested address or `/admin/dashboard`.
- `POST /logout` — `Auth\LogoutController`: ends the session, invalidates it, and redirects to `/login`. On the frontend it is called via `router.post('/logout')`.

## Protecting `/admin`

All `/admin/*` routes have the `auth` + `verified` middleware:

- a guest → redirect to `/login` (set by the package provider, see [package-bootstrap.md](package-bootstrap.md#redirects-and-the-404-page); with `ecms.auth_routes` off the host's own redirect applies);
- a user without a verified email → redirect to `/email/verify` (`verification.notice`, the page `Pages/Auth/VerifyEmail.tsx` has only a logout button; sending verification emails is out of scope).

`User` implements `MustVerifyEmail`.

## Deactivated user

`users.is_active = false` prevents login: `LoginRequest` adds `is_active => true` to the credentials of `Auth::attempt`, so a deactivated user gets the same message as for a wrong password (and is counted in rate limiting). An already open session is terminated by the `EnsureUserIsActive` middleware (pushed to the `web` group by the package provider, [package-bootstrap.md](package-bootstrap.md#middleware-of-the-web-group)): on the next request it logs the user out, invalidates the session, and redirects to `/login`. Reactivation restores access. See [access control by roles](access-control.md).

## Sessions after a password change

The `web` group runs Laravel's `AuthenticateSession`, which the package provider pushes to the group (a host that already called `authenticateSessions()` keeps its own entry; the class is never added twice). It keeps a keyed hash of the user's password in the session (`password_hash_web`). When the password changes, whether the user changed it on the [profile page](admin-profile.md) or an administrator reset it ([users](admin-users.md)), every other session of that user, and any remember-me login, is logged out at its next request and redirected to `/login`. The session that changed its own password stays signed in, because the middleware stores the new hash in it after the response.

- The login form has no "remember me" checkbox, but `LoginRequest` honours a `remember` field, so a crafted login can create a remember-me cookie; it is evicted by a password change like any session.
- Changing name, email, role or activity does not end sessions (a deactivated user is still ended by `EnsureUserIsActive`).
- A session created before the middleware was deployed has no stored hash; the hash is stored on its first request, so nobody is logged out at deployment.
- Tests: `tests/Feature/Auth/SessionInvalidationTest.php`.

## Rate limiting

`LoginRequest` limits login attempts: 5 failed attempts per email + IP pair, then an error in the `email` field (`auth.throttle`) until the lockout ends. A second counter keyed by the IP address alone counts failures across all emails (`limits.login_ip_attempts`, default 20, within `limits.login_ip_window` seconds, default 300; `.env` keys `LIMIT_LOGIN_IP_ATTEMPTS`, `LIMIT_LOGIN_IP_WINDOW`, see `config/limits.php`). When either counter is exhausted, further attempts are refused with the same message, even with correct credentials. Every failure hits both counters. A successful login resets only the email + IP pair counter, never the per-IP one, so an attacker who owns one valid account cannot reset it.

**Deployment note.** Both counters use the client address (`$request->ip()`). The application configures no trusted proxies, so behind a reverse proxy, load balancer or CDN every visitor looks like one IP and the per-IP limits would throttle everyone together. Configure trusted proxies before relying on them (and raise the numbers through `.env` meanwhile). The search and helper-endpoint limits ([site search](public-site.md#site-search), [helper endpoints](access-control.md#helper-endpoints)) depend on the same address.

## Initial administrator (seeder)

The installer ([`ecms:install`](package-installer.md#administrator)) and the seeder `AdminSeeder` (called by `DatabaseSeeder`) create a user with a verified email and the administrator role, or promote the existing account with that email (its password is kept unless a different one is given). The credentials are taken only from explicit values: the installer options `--admin-email` / `--admin-password`, a prompt of the installer, or the environment variables (see `.env.example`):

| Variable | Default |
| --- | --- |
| `ADMIN_EMAIL` | none (required) |
| `ADMIN_PASSWORD` | none (required) |

There are no defaults in any environment. When either value is missing or empty, the seeder throws a `RuntimeException` naming it before any write, so no user is created or changed (the installer without interaction fails the same way). A re-seed therefore can never recreate a weak account.

> ⚠️ An account such as `admin@example.com` / `password` (created by older versions of the seeder) must not exist on any installation that is reachable by other people or whose database can be dumped elsewhere.

### Neutralising a seeded `admin@example.com` account on an existing installation

Older versions of the seeder created `admin@example.com` / `password`. On an installation that still has it, run these steps in `php artisan tinker` inside one `DB::transaction`:

1. Pick a real administrator (the new author) and check that it exists and has the administrator role.
2. Move the authorship: `content_nodes.user_id` (`restrictOnDelete`, so the account cannot be deleted before this) and `media.user_id` from the old user to the real one.
3. Change the old account's email and set a strong password (read it from an environment variable, do not type it into the command), or delete the account once it owns nothing.
4. Delete its rows from `sessions`, so no old session survives.
5. Check that no node or media file references the old user, that no user has the email `admin@example.com`, and that `Hash::check('password', $user->password)` is false.

```bash
docker compose exec app php artisan db:seed
```

For a clean database from scratch (⚠️ deletes all tables): `docker compose exec app php artisan migrate:fresh --seed`.

## Tests

```bash
docker compose exec app php artisan test
```

Feature tests: `tests/Feature/Auth` (login, logout, throttling, unverified email, seeder) and `tests/Feature/Admin`.

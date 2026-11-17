## Why

Two findings of the auth audit remain, both about what happens after an account or a deployment is already in a risky state, and the owner also decided how to treat a third one:

- **F7 (sessions after a password change).** Changing a password does not end the user's other sessions. The `web` group has no `AuthenticateSession`, and a session in Laravel is renewed by use, so a stolen or forgotten session outlives the owner's reaction: they change the password and the other session keeps working. The same happens when an administrator resets a user's password. A "remember me" cookie is not offered by the login form (it has no checkbox), but the login request still honours a `remember` field, so a crafted login can create one that would also outlive a password change. Deactivation already works (the next request logs out).
- **F6 (production settings).** `.env.example` ships `APP_ENV=local` and `APP_DEBUG=true`, and the session cookie is not marked `Secure` unless `SESSION_SECURE_COOKIE` is set. The site will run without Docker behind nginx and PHP-FPM; if these values are copied to the server as they are, an error page shows a stack trace with environment values, and the session cookie can travel over plain HTTP. There is no deployment documentation that says what to set.
- **F4 (reference lookups).** The owner decided to keep `lookup/*` as they are (the staff are trusted and reference fields need them) and to record this as an accepted decision in the documentation.

## What Changes

- Every session of a user, and any remember-me login, ends at its next request when that user's password changes, whether the user changed it or an administrator reset it. The session in which the user changed their own password stays signed in. Users who are signed in at deployment time are not logged out.
- A new deployment page `docs/deployment.md` lists what a production server must set (`APP_ENV`, `APP_DEBUG`, `APP_URL` over HTTPS, `SESSION_SECURE_COOKIE`, writable `storage/` and `bootstrap/cache`, the administrator credentials, trusted proxies when a proxy is put in front), and `.env.example` gets comments on the dangerous defaults. No default changes: the file stays a development example.
- `docs/access-control.md` records the accepted behaviour of `lookup/*` (any signed-in user gets titles of all nodes, drafts included, and names of all types) with the reason and when to revisit.
- `docs/admin-profile.md` and `docs/admin-auth.md` replace the note that other sessions stay open with the new behaviour.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `admin-auth`: adds the requirement that the other sessions and remember-me logins of a user end when the user's password changes.

## Impact

- Code: `bootstrap/app.php` (`$middleware->authenticateSessions()`); no controller change is expected, because the middleware keeps the current session signed in after the user's own password change.
- Documentation (English): new `docs/deployment.md` and its link in `docs/README.md`; `docs/admin-auth.md`, `docs/admin-profile.md` (remove the "other sessions stay open" note), `docs/access-control.md` (F4), `.env.example` comments.
- Tests: new `tests/Feature/Auth/SessionInvalidationTest.php`; `tests/Feature/Admin/ProfileTest.php` and `UserAdminTest.php` for the profile and the admin reset.
- A global middleware and authentication change, so the full PHP test run and the full e2e run are part of the plan. No migrations, no new dependencies.

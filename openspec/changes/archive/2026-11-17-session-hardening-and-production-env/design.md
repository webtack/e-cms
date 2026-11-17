## Context

See proposal.md - Why. Current state, read from the code and the framework source:

- The `web` group appends `EnsureUserIsActive`, `SetLocale` and `HandleInertiaRequests` (`bootstrap/app.php`); there is no `AuthenticateSession`. Laravel 13 offers `$middleware->authenticateSessions()` for this.
- `AuthenticateSession` keeps a keyed hash of the user's password in the session (`password_hash_web`). It stores the hash when it is absent (so existing sessions are not logged out), logs the request out when the stored hash no longer matches, checks the hash inside the remember-me cookie in the same way, and after every response stores the current hash again. That last step is why the session that just changed its own password stays signed in: after the controller updates the password, the middleware writes the new hash into that session, while the other sessions keep the old one.
- `ProfileController::updatePassword` and `UserController::update` set `password` through the model's `hashed` cast; neither touches sessions. `UserController::update` can change another user's password; the target's sessions are other sessions by definition.
- The login form has no "remember me" checkbox (the page state has `remember: false`), but `LoginRequest` passes `$this->boolean('remember')`, so a crafted request can create a remember-me cookie.
- `docs/admin-profile.md` documents that other sessions stay open after a password change and that adding `AuthenticateSession` is a separate decision; this change is that decision.
- `.env.example` is a development example (`APP_ENV=local`, `APP_DEBUG=true`); `config/session.php` reads `SESSION_SECURE_COOKIE` (default `null`) and `SESSION_ENCRYPT` (default `false`). The seeder requires `ADMIN_EMAIL` and `ADMIN_PASSWORD`. The project runs in Docker for development; the production server runs nginx and PHP-FPM without Docker (owner).

Documentation describing these components: `docs/admin-auth.md`, `docs/admin-profile.md`, `docs/access-control.md`.

## Goals / Non-Goals

**Goals:**
- A password change evicts a session the user no longer controls, without logging out the person who changed it.
- A person deploying the site finds what to set in one place.

**Non-Goals:**
- A list of the user's active sessions or a "sign out everywhere" button.
- Changing the session lifetime, the session driver, or making the session cookie `Secure` by default (that would break an HTTP-only server; the deployment page says to set it).
- Changing `lookup/*` (recorded as accepted).
- Forcing `APP_DEBUG=false` in code.

## Decisions

**1. Use the framework's `AuthenticateSession`, enabled globally.**
One line in `bootstrap/app.php`: `$middleware->authenticateSessions()`. It covers the profile change, the administrator reset and any future place that changes a password, and it handles the remember-me cookie. No controller needs to call `logoutOtherDevices`. Alternative: call `Auth::logoutOtherDevices()` in the two controllers (rejected: it still needs the middleware to evict the other sessions, covers only the places we remember to change, and the administrator reset would need a separate way to end another user's sessions; with the database session driver that would mean deleting rows from `sessions` by hand).

**2. No new stored data and no migration.**
The hash lives in each session. Existing sessions get it on their next request, so deployment does not log anyone out.

**3. Verify the "stays signed in" behaviour by test, not by reading.**
The reading of the middleware says the current session survives its own password change; a feature test with two real sessions is the proof, and the first task fails the plan if the test shows otherwise (then `ProfileController::updatePassword` gets the explicit call).

**4. Deployment page, no code default changes.**
`docs/deployment.md` is a checklist with the reason for each item, and `.env.example` gets comments next to `APP_ENV`, `APP_DEBUG` and the session keys, with `SESSION_SECURE_COOKIE` listed as a commented key. Defaults stay as they are because the file serves development and a `Secure` default would lock out an HTTP-only setup. The list contains only what is grounded in this repository (environment values, writable directories, seeder credentials, trusted proxies note, link to the throttle configuration if it exists); it is not a generic hosting guide.

**5. F4 is a documentation decision.**
`docs/access-control.md` states what `lookup/*` return, that it is accepted because reference fields need it and the staff are trusted, and the condition to revisit (a role for contributors who must not see unpublished titles). The route coverage test already treats `lookup/*` as open to every user, so no test changes.

## Risks / Trade-offs

- [A hashing change (cost or algorithm rehash on login) alters the stored password hash and signs the user's other sessions out once] → Rare and harmless: the user logs in again.
- [A password changed by an administrator signs the target out in the middle of their work] → That is the purpose; the profile page and the users page docs say so.
- [The middleware adds a session write on every response] → The hash is rewritten only as a normal session save, which already happens per request with the database driver; no extra query.
- [Tests using `actingAs` without a session] → The middleware does nothing without a session and stores the hash when absent; the full PHP run in the plan checks it.
- [Docs can drift from the server] → The deployment page is short and each item names the configuration key it refers to.

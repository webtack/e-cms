# Design

## Context

- `GET /admin/profile` (`admin.profile`) is an invokable `ProfileController` rendering `Pages/Admin/Profile.tsx`, a stub. It already sits in the `auth` + `verified` admin group with no `can:` gate, which is the access the spec requires (see proposal.md and `specs/admin-profile`).
- Editing users exists only in `UserController::update` / `UpdateUserRequest` (right `users`, role and activity included, `LastAdminGuard`). That path must not be reused for self-service: it would expose role and activity.
- The interface language is saved by `POST /locale` (`LocaleController`, `users.locale`) and switched by `LocaleSwitcher`. The theme is `ThemeProvider` state mirrored in `localStorage` (`light` | `dark` | `system`); `ThemeToggle` only flips light/dark.
- `User::$fillable` is `name, email, password, role_id, is_active`; `password` has the `hashed` cast.
- `bootstrap/app.php` appends only `EnsureUserIsActive`, `SetLocale` and `HandleInertiaRequests` to the `web` group; there is no `AuthenticateSession`. `SESSION_DRIVER=database`.
- Components to reuse: `PageTitle`, `FormSection`, `FormActionBar`, `Input`, `Label`, `useTranslation`; error rendering follows `UserForm`. Docs: `docs/admin-users.md`, `docs/admin-shell.md`, `docs/admin-auth.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- A self-service page that cannot touch role or activity by construction (separate requests that validate only the fields they own).
- Reuse the existing language and theme mechanisms instead of duplicating them.

**Non-Goals:**
- Avatar (follow-up change `user-avatar`).
- Persisting the theme in the database or touching the inline theme script in `app.blade.php`.
- Email verification mail, "forgot password" and ending the user's other sessions after a password change (see Decisions).
- Changing `UserController`, `UpdateUserRequest` or `LastAdminGuard`.

## Decisions

1. **Two write endpoints, two FormRequests.** `PUT /admin/profile` (`UpdateProfileRequest`: `name`, `email`) and `PUT /admin/profile/password` (`UpdateProfilePasswordRequest`: `current_password`, `password`, `password_confirmation`). Both redirect `back()` with a `success` flash. *Alternative:* one endpoint with an optional password block, like `UserForm`. Rejected: the password needs the current-password check and its own errors, and separate requests keep validation bags and the front-end forms independent.
2. **Only validated fields are written.** The controller uses `$request->safe()->only(['name', 'email'])`; `role_id` and `is_active` are never validated, so they cannot be mass-assigned even if sent. Email uniqueness ignores the user's own id. `email_verified_at` is not touched (spec: no reset).
3. **Password rules.** `current_password` (Laravel's rule against the authenticated user), `min:` from `StoreUserRequest::MIN_PASSWORD`, `confirmed`. The new password is assigned to the model and the `hashed` cast hashes it. The route gets `throttle:6,1`: otherwise a stolen session could use the current-password check as a guessing oracle.
4. **Other sessions are NOT ended after a password change.** Verified: the `web` group has no `AuthenticateSession`, and the sessions live in the database. `Auth::logoutOtherDevices()` is documented to depend on that middleware; I have not verified its behaviour without it, so this change does not call it. Adding `AuthenticateSession` is a global middleware change (full PHP and e2e runs, login/logout paths) and is a separate decision; the limitation is stated in `docs/admin-profile.md`. *Alternative:* add it here. Rejected as scope creep with a wide blast radius.
5. **Language and theme reuse the existing mechanisms.** The "Settings" section posts the language to `POST /locale` exactly as `LocaleSwitcher` does (a select over `locales` from `useTranslation`, `preserveScroll` and `preserveState`) and sets the theme with `useTheme().setTheme` (a three-way select light / dark / system, since `ThemeToggle` offers only two). No new endpoint, no migration. *Alternative:* embed `LocaleSwitcher` and `ThemeToggle` as they are. Rejected: they are icon buttons with dropdown/toggle semantics that read poorly as form settings and cannot offer `system`.
6. **Page props.** `ProfileController::show` passes `profile: {name, email, role}` (role name, read-only). It does not pass anything from the `users` section. The shared `auth.user` prop (name, email in the header) refreshes after a save because Inertia reloads shared props on the redirect.
7. **Structure.** `Pages/Admin/Profile.tsx` composes three components in `Components/Admin/Profile/`: `ProfileDataForm`, `ProfilePasswordForm`, `ProfileSettings`, each in a `FormSection`. After a successful password change the password fields are cleared (as `UserForm` does).
8. **Tests.** PHP Feature test `tests/Feature/Admin/ProfileTest.php` (access, save, duplicate email, role/activity ignored, password success/failure, verification kept). One e2e spec `e2e/admin-profile.spec.js`. Because the e2e specs share the seeded administrator, the password test must restore the seeded password in `afterEach`/`afterAll` (via `tinker`), and the data test must restore name and email.

## Risks / Trade-offs

- [Password change leaves other sessions alive] → Stated in docs; `AuthenticateSession` is a deliberate follow-up decision.
- [An e2e test that changes the seeded administrator's password or email could break every later spec] → Restore in `finally`/`afterEach` through `tinker`; alternatively seed a separate throw-away user and log in as them (preferred in the spec: the stored admin session stays valid).
- [User changes their email to an address they do not control] → Accepted: no verification mail exists in the project; consistent with the administrator-vouches model.
- [`current_password` throttling is per route, not per account] → Acceptable for an authenticated-only endpoint.

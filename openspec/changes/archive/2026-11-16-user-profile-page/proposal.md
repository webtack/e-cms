# Proposal

## Why

`/admin/profile` is a placeholder that only says "Profile editing is not available yet." A signed-in user can reach it from the user menu, but cannot change their own name, email or password: only an administrator with the `users` right can do that on `/admin/users/{id}/edit`. Every user should be able to maintain their own account without bothering an administrator.

## What Changes

- Replace the profile stub with a real page for any authenticated, verified user (no `can:` gate, as today).
- Section "Personal data": edit `name` and `email` (email stays unique). Changing the email does **not** reset `email_verified_at`: the project sends no verification mail and the administrator vouches for addresses, so resetting it would lock the user out under the `verified` middleware.
- Section "Password": change the password by entering the current password and the new one (minimum length shared with `StoreUserRequest::MIN_PASSWORD`, confirmation field). A wrong current password is a validation error.
- Section "Settings": show and change the interface language (the existing `users.locale` through `POST /locale`) and the colour theme (the existing `ThemeToggle`, still stored only in `localStorage`; no `users.theme` column and no change to the inline theme script).
- Show the user's read-only role next to the page header. Role and activity stay editable only in the users section.
- New routes `PUT /admin/profile` (personal data) and `PUT /admin/profile/password` (password), each with its own FormRequest, both inside the existing `auth` + `verified` admin group.
- Update the "User menu" requirement: the profile link opens a working page, not a stub.
- **Not changed:** other sessions of the user stay open after a password change (see design). Avatar upload is the separate follow-up change `user-avatar`.

## Capabilities

### New Capabilities
- `admin-profile`: the profile page of the signed-in user: personal data, password change and interface settings.

### Modified Capabilities
- `admin-shell`: the "User menu" requirement no longer describes the profile page as a stub.

## Impact

- **PHP:** `App\Http\Controllers\Admin\ProfileController` (from invokable to `show`/`update`/`updatePassword`), new `UpdateProfileRequest` and `UpdateProfilePasswordRequest` in `app/Http/Requests/Admin`, routes in `routes/web.php`.
- **Frontend:** `resources/js/Pages/Admin/Profile.tsx` and new components under `resources/js/Components/Admin/Profile/`; new translation keys in `lang/en.json` and `lang/uk.json`; removal of the key "Profile editing is not available yet.".
- **Tests:** a PHP Feature test for the profile (data, password, validation, access) and a new e2e spec `e2e/admin-profile.spec.js`.
- **Docs:** new `docs/admin-profile.md`; update `docs/admin-shell.md` (route table, user menu), `docs/admin-users.md` (cross-link: self-service lives in the profile), `docs/e2e-testing.md` ("area → spec" and "shared code → specs" tables) and the index in `docs/README.md`.
- **No database migration, no new dependencies.**

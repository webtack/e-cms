## 1. Backend

- [x] 1.1 Create `UpdateProfileRequest` in `app/Http/Requests/Admin` (authorize any authenticated user; rules `name`, `email` unique ignoring the own id) with an English docblock and `@example`. Verify with Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`.
- [x] 1.2 Create `UpdateProfilePasswordRequest` (`current_password`, `password` with `min:StoreUserRequest::MIN_PASSWORD` and `confirmed`) with an English docblock. Verify with Pint.
- [x] 1.3 Rework `ProfileController` into `show` (props `profile: {name, email, role}`), `update` (writes only `name` and `email`, `back()` with `success`) and `updatePassword` (assigns the new password, `back()` with `success`); add English docblocks. Update the three routes in `routes/web.php` (`GET`, `PUT /admin/profile`, `PUT /admin/profile/password` with `throttle:6,1`) inside the existing `auth` + `verified` group, keeping the name `admin.profile` for `GET`.
- [x] 1.4 Add the translation keys used by the backend messages to `lang/en.json` and `lang/uk.json` ("Profile updated.", "Password changed.") and remove "Profile editing is not available yet.".
- [x] 1.5 Write `tests/Feature/Admin/ProfileTest.php`: access by a user without rights and redirect of a guest, name and email saved, own email resubmitted, duplicate email, `role_id`/`is_active` ignored, `email_verified_at` kept, password changed (old fails, new works), wrong current password, too short and unconfirmed password. Run `docker compose exec app php artisan test --compact --filter=ProfileTest` (area only; no migrations, middleware or config changes, so no full run).

## 2. Frontend

- [x] 2.1 Create `Components/Admin/Profile/ProfileDataForm.tsx` (name, email, errors next to fields, `FormActionBar`-style save) with JSDoc and `@example`.
- [x] 2.2 Create `Components/Admin/Profile/ProfilePasswordForm.tsx` (current, new, confirmation; clear the fields on success; errors next to fields) with JSDoc and `@example`.
- [x] 2.3 Create `Components/Admin/Profile/ProfileSettings.tsx` (language select posting to `/locale`, theme select via `useTheme().setTheme` with light/dark/system) with JSDoc and `@example`.
- [x] 2.4 Rewrite `Pages/Admin/Profile.tsx` to show the heading, the read-only role and the three sections; add all new UI strings to `lang/en.json` and `lang/uk.json`. Build on the host: `npm run build`.

## 3. E2E

- [x] 3.1 Add `e2e/admin-profile.spec.js` with a throw-away user created through `tinker` (logging in as that user, so the seeded administrator and the saved session are untouched): open the profile from the user menu, save name and email, wrong current password error, successful password change and re-login, language and theme change. Clean the user up in `afterAll`. Run `npm run test:e2e -- e2e/admin-profile.spec.js`.
- [x] 3.2 Add the "Profile" row to the "area → spec" table in `docs/e2e-testing.md` and add `admin-profile` to the "shared code → specs" rows for i18n (`LocaleSwitcher`, `useTranslation`) and the translation files. Also run `npm run test:e2e -- e2e/admin-shell.spec.js -g "<user menu / profile test>"` if a test of the profile link exists (find it with `npx playwright test --list e2e/admin-shell.spec.js`).

## 4. Documentation

- [x] 4.1 Create `docs/admin-profile.md` (routes, sections, validation, what is not editable, theme in `localStorage`, email verification not reset, other sessions not ended after a password change and why, tests) and link it from `docs/README.md`.
- [x] 4.2 Update `docs/admin-shell.md` (route table row `Admin/Profile` is no longer a stub; user menu text) and add a cross-link from `docs/admin-users.md` (self-service lives in the profile).
- [x] 4.3 Run `openspec validate user-profile-page --strict` and fix any findings.

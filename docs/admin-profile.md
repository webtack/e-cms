# Profile of the signed-in user

The `/admin/profile` page lets every signed-in user maintain their own account: view the role, upload an avatar, edit the name and email, change the password and choose the interface language and colour theme. Access is `auth` + `verified` only; there is **no** `can:` gate, so a user without any role right can open it. A guest is redirected to `/login`. Managing other users (role, activity) stays in [users](admin-users.md).

## Routes

| Method | Address | Name | Action |
| --- | --- | --- | --- |
| GET | `/admin/profile` | `admin.profile` | the page |
| PUT | `/admin/profile` | `admin.profile.update` | save name and email |
| PUT | `/admin/profile/avatar` | `admin.profile.avatar` | set, replace or remove the avatar |
| PUT | `/admin/profile/password` | `admin.profile.password` | change the password (`throttle:6,1`) |

The controller is `Webtack\Ecms\Http\Controllers\Admin\ProfileController` (`show`, `update`, `updateAvatar`, `updatePassword`); the requests are `UpdateProfileRequest`, `UpdateProfileAvatarRequest` and `UpdateProfilePasswordRequest` in `app/Http/Requests/Admin`. The page is `Pages/Admin/Profile.tsx`, composed of `Components/Admin/Profile/{ProfileAvatar,ProfileDataForm,ProfilePasswordForm,ProfileSettings}.tsx`. The page props are `profile: {name, email, role, avatar}`; the role is the role name, read-only; `avatar` is the picture URL or `null`.

## Sections

- **Avatar.** <a id="avatar"></a>A large round preview (the picture, or the initials when there is none), a `FileUpload` of the `avatar` [upload profile](file-storage.md#profiles) (JPEG, PNG, WebP; 2 MB; larger than 512 px is downscaled proportionally, no crop) and "Remove avatar". A picked file or a removal is only pending until "Save", which sends `PUT /admin/profile/avatar` with `avatar` = the file `uuid` (empty removes). `UpdateProfileAvatarRequest` validates it with `FileOfProfile('avatar')`, so a file of another profile or an unknown `uuid` is an error under the field and nothing changes. The controller builds `users.avatar` (`{uuid}.{ext}`, the term-image convention) from the `media` row, never from the input, and in one transaction writes the column and `FileRelations::sync('user', id, 'avatar', 'default', …)`: the [media library](media-library.md) then lists the file as used by the user (and cannot delete it), and a replaced or removed file is released but stays in the storage. `avatar` is not mass assignable (not in `User`'s `#[Fillable]`). The picture URL comes from `User::avatarUrl()`; it is shown in the header menu (`auth.user.avatar`), the [users list](admin-users.md), the media library "Uploaded by" cell and the author cell and "Author" row of [nodes](admin-content-nodes.md), always through `UserAvatar`, with the initials as the fallback. Deleting a user releases the relation.
- **Personal data.** `name` (required, up to 255 characters) and `email` (required, valid, up to 255 characters, unique; the user's own address is not a duplicate). The controller writes `$request->safe()->only(['name', 'email'])`, so `role_id` and `is_active` can never be changed from here, even if a request carries them. After a save the user stays on the page (`back()`) with a `success` message; the shared `auth.user` prop (the header) refreshes.
- **Password.** `current_password` (checked with Laravel's `current_password` rule), `password` (at least `StoreUserRequest::MIN_PASSWORD` characters, `confirmed`). A wrong current password is an error next to the current password field; a short or unconfirmed new password is an error next to the new password field. The model's `hashed` cast hashes the new value. After a success the fields are cleared; after a rejected attempt the new password fields are cleared.
- **Settings.** The language select posts to `POST /locale` like the header `LocaleSwitcher` (saved in `users.locale`, see [i18n](admin-i18n.md)). The theme select (light / dark / system) calls `useTheme().setTheme`; the choice lives only in the browser's `localStorage`, as with the header `ThemeToggle`. There is no `users.theme` column.

## Decisions and limits

- **Email verification is not reset** when the email changes: the project sends no verification mail and the administrator vouches for addresses, so a reset would lock the user out under the `verified` middleware.
- **Other sessions end after a password change.** The `web` group runs `AuthenticateSession` (`$middleware->authenticateSessions()` in `bootstrap/app.php`): every other session and remember-me login of the user is redirected to `/login` on its next request, while the session that changed the password stays signed in. See [sessions after a password change](admin-auth.md#sessions-after-a-password-change).
- **Throttling** of `PUT /admin/profile/password` is per route (6 attempts per minute), not per account: without it a stolen session could use the current-password check as a guessing oracle.
- Role and activity are editable only by an administrator with the `users` right.

## Tests

```bash
docker compose exec app php artisan test --compact --filter=ProfileTest
npm run test:e2e -- e2e/admin-profile.spec.js
```

Feature tests: `tests/Feature/Admin/ProfileTest.php` (access, save, duplicate email, role and activity ignored, verification kept, password success and failures) and `tests/Feature/Admin/ProfileAvatarTest.php` (set, replace, remove, wrong profile, relation sync, shared prop). e2e: `e2e/admin-profile.spec.js` logs in as a throw-away user created through `tinker` (the avatar tests use `seedAvatar` and `avatarCleanup` from `e2e/support.js`), so the seeded administrator and the saved session stay untouched.

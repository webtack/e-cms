## 1. Storage and database

- [x] 1.1 Add the `avatar` profile to `config/files.php` (JPEG, PNG, WebP; `max_size` and `max_width`/`max_height` from `FILES_AVATAR_*` variables with defaults 2048 KB and 512 px) and the variables with comments to `.env.example`. Update the profiles table in `docs/file-storage.md`. Verify with `docker compose exec app php artisan test --compact --filter=FileProfilesTest`.
- [x] 1.2 Add a migration for the nullable string column `users.avatar` (reversible). Add `User::MEDIA_OWNER = 'user'` and the `avatar` property to the model docblock, without adding it to `#[Fillable]`. This changes the schema, so run the full `docker compose exec app php artisan test --compact` once.

## 2. Backend

- [x] 2.1 Create `App\Files\Owners\UserOwner` (label = user name, url = admin edit page) with an English docblock and `@example`; register `'user' => UserOwner::class` in `config/files.php` owners; document it in `docs/media-library.md`. Verify with `--filter=MediaLibraryTest`.
- [x] 2.2 Create the request and the `PUT /admin/profile/avatar` action (`FileOfProfile('avatar')`, nullable; builds `{uuid}.{ext}` from the `media` row; writes `users.avatar` and calls `FileRelations::sync('user', …)` in one transaction); add the route in the `auth` + `verified` group.
- [x] 2.3 Share `auth.user.avatar` (URL or null) in `HandleInertiaRequests` and add `avatar` to the rows of `UserController::index`; add `uploader_avatar` to the rows of `MediaController` (eager load `uploader:id,name,avatar`); make `UserController::destroy` call `FileRelations::forgetOwner('user', $id)` in the deletion transaction.
- [x] 2.4 Write `tests/Feature/Admin/ProfileAvatarTest.php` (set, replace, remove, a file of another profile rejected, relation synced and released, shared prop) and extend `UserAdminTest` (list row avatar, deleting a user with an avatar releases the relation). Extend `MediaLibraryTest` (uploader avatar in the row, none for an unknown uploader). Run `--filter=ProfileAvatarTest`, `--filter=UserAdminTest` and `--filter=MediaLibraryTest`.

- [x] 2.5 Add `avatar` to `author` in the rows of `ContentNodeController::index` and in `node.author` of the create and edit forms (eager load `author:id,name,avatar`); extend `ContentNodeIndexTest` and the form test that covers `node.author`. Run `--filter=ContentNodeIndexTest` and the form test file.

## 3. Frontend

- [x] 3.1 Extend `UserAvatar.tsx` with an optional `src` (round `object-cover` image, still `aria-hidden`, initials as the fallback) with JSDoc and `@example`; switch `UserMenu.tsx` to `UserAvatar` and remove its private initials helper; pass `avatar` in `Pages/Admin/Users/Index.tsx` and render the uploader cell of `Pages/Admin/Media/Index.tsx` with `UserAvatar` and the name.
- [x] 3.3 Pass `src` from `author.avatar` to `UserAvatar` in the author cell of `Pages/Admin/ContentNodes/Index.tsx` and show the avatar in the "Author" row of `NodeServicePanel.tsx` (`data-testid="node-author"` keeps the name).
- [x] 3.2 Create `Components/Admin/Profile/ProfileAvatar.tsx` (large avatar preview, `FileUpload` with `profile="avatar"`, remove, save, error under the field) with JSDoc and `@example`, and add it to `Pages/Admin/Profile.tsx`; add translation keys to `lang/en.json` and `lang/uk.json`. Build on the host: `npm run build`.

## 4. E2E

- [x] 4.1 Extend `e2e/admin-profile.spec.js` (upload, replace and remove an avatar, wrong file rejected, picture visible in the header menu) and `e2e/admin-users.spec.js` (avatar in the list row, deleting such a user) `e2e/admin-media.spec.js` (avatar in the uploader cell) and `e2e/admin-content-nodes.spec.js` (avatar in the author cell and in the form's author row). Run the three specs with `npm run test:e2e -- e2e/<spec>.spec.js`, using `-g` to limit each to the changed tests.
- [x] 4.2 Update the `UserAvatar` row of the "shared code → specs" table in `docs/e2e-testing.md` (consumers: `Pages/Admin/Users/Index.tsx`, `Pages/Admin/Media/Index.tsx`, `Pages/Admin/ContentNodes/Index.tsx`, `NodeServicePanel`, `UserMenu`, `ProfileAvatar`; specs: `admin-users`, `admin-media`, `admin-content-nodes`, `admin-profile`, `admin-shell` for the header), and the "area → spec" row for the profile. Since `UserMenu` is in the shell, also run `npm run test:e2e -- e2e/admin-shell.spec.js -g "<user menu test>"` (find it with `npx playwright test --list e2e/admin-shell.spec.js`).

## 5. Documentation

- [x] 5.1 Update `docs/admin-profile.md` (avatar block, endpoint, profile limits), `docs/admin-users.md` (avatar in the name cell, release on deletion) and `docs/admin-shell.md` (user menu shows the avatar) `docs/media-library.md` (uploader cell) and `docs/admin-content-nodes.md` (author avatar in the list and form).
- [x] 5.2 Run `openspec validate user-avatar --strict` and fix any findings.

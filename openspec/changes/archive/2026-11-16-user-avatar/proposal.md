# Proposal

## Why

Users are represented everywhere in the admin panel by two-letter initials. The file storage was designed with avatars as an intended consumer, and the profile page (change `user-profile-page`) now gives every user a place to manage their own account, so an uploaded picture can finally replace the initials.

## What Changes

- New upload profile `avatar` in `config/files.php` (JPEG, PNG, WebP; small size limit; images larger than 512 px are downscaled proportionally by the existing resizer).
- New nullable column `users.avatar` holding the stored `{uuid}.{ext}` value, the same convention as the term image.
- The profile page gets an "Avatar" block: upload, replace and remove. The picture is bound to the user in the media library (owner type `user`), so the media library shows where the file is used and a used file cannot be deleted from it.
- The avatar is shown instead of the initials in the header user menu, on the profile page and in the name column of the users list and in the "Uploaded by" column of the media library table; the initials remain the fallback when there is no avatar.
- Deleting a user releases their avatar relation.
- The author's avatar also appears in the "Author" column of the node list and in the "Author" row of the node edit form.
- **Depends on** `user-profile-page` (the avatar block lives on that page) and `node-list-author-column` (the author column it extends); apply those first.
- **Out of scope:** cropping or an editor (the picture is shown with `object-cover` in a circle), administrators uploading an avatar for another user, avatars on the public site.

## Capabilities

### New Capabilities
<!-- None: the avatar extends existing capabilities. -->

### Modified Capabilities
- `admin-profile`: adds the avatar requirement (created by `user-profile-page`).
- `admin-users`: the users list shows the avatar; deleting a user releases the avatar.
- `admin-shell`: the user menu shows the avatar when there is one.
- `media-library`: the uploader cell of the media table shows the avatar.
- `admin-content-nodes`: the author column of the node list and the "Author" row of the node form show the avatar.
- `file-storage`: adds the `avatar` upload profile.

## Impact

- **Database:** one migration adding `users.avatar` (nullable string); the full PHP test suite runs once for it, because of the schema change.
- **PHP:** `config/files.php` (profile `avatar`, owner `user` => `UserOwner`), new `App\Files\Owners\UserOwner`, `User` model (`avatar` attribute, `MEDIA_OWNER`), a small `ProfileAvatarController` (or `ProfileController` action) with its FormRequest using `FileOfProfile('avatar')`, `UserController` (list rows carry the avatar URL; destroy forgets the relation), `MediaController` (rows carry the uploader's avatar URL; the eager load selects `avatar`), `ContentNodeController` (`author.avatar` in list rows and in the form's `node.author`; the author eager load selects `avatar`), `HandleInertiaRequests` (`auth.user.avatar` URL).
- **Frontend:** `UserAvatar.tsx` (optional image), `UserMenu.tsx` (reuses `UserAvatar` instead of its own initials helper), `Components/Admin/Profile/ProfileAvatar.tsx`, `Pages/Admin/Users/Index.tsx`, `Pages/Admin/Media/Index.tsx` (uploader cell), `Pages/Admin/ContentNodes/Index.tsx` (author cell), `NodeServicePanel.tsx` (author row), translation files.
- **Tests:** PHP Feature test (avatar set, replace, remove, wrong-profile file rejected, relation sync, user deletion) and additions to `e2e/admin-profile.spec.js` and `e2e/admin-users.spec.js` and `e2e/admin-media.spec.js` and `e2e/admin-content-nodes.spec.js`.
- **Docs:** update `docs/file-storage.md` (profiles table), `docs/media-library.md` (owner registry, uploader cell), `docs/admin-profile.md`, `docs/admin-users.md`, `docs/admin-shell.md`, `docs/e2e-testing.md` (tables: `UserAvatar` consumers, area → spec).

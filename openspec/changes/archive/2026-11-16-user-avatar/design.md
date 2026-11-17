# Design

## Context

- File storage already has everything an avatar needs: profiles in `config/files.php`, `FileStorage` (`urlForValue`), `FileUpload` widget, the `FileOfProfile`/`FileValue` rules and `FileRelations` with the owner registry (`config('files.owners')`). The term image is the template: `taxonomy_terms.image` stores `{uuid}.{ext}`, `TermWriter::syncImage` binds it with `FileRelations::sync(Term::MEDIA_OWNER, id, 'image', default_lang, [uuid])`, and `TermOwner` labels it in the media library.
- `User` has no picture; `UserMenu.tsx` has its own `initials()` helper and shadcn `Avatar`, while `UserAvatar.tsx` (initials, `aria-hidden`) serves the users list. `HandleInertiaRequests` shares `auth.user` as an allow-list (`id`, `name`, `email`, `can`).
- This change builds on the profile page of `user-profile-page` (see its design: separate write endpoints per concern, the controller never mass-assigns role or activity).

## Goals / Non-Goals

**Goals:**
- Reuse the term-image pattern end to end so the avatar behaves like every other stored file (relation, media library label, delete protection).
- One avatar component for all three places.

**Non-Goals:**
- Cropping/editing, uploading for other users, public-site avatars, a separate thumbnail style (see Decisions).

## Decisions

1. **Column `users.avatar` (`{uuid}.{ext}`), not `avatar_uuid`.** The explored plan said `avatar_uuid`; the codebase convention for a single stored image is the `{uuid}.{ext}` value (`taxonomy_terms.image`, content fields), consumed by `FileStorage::urlForValue`. Following it keeps one way to turn a stored value into a URL. `avatar` is not added to `User::$fillable`: the profile controller sets it explicitly, so administrators' mass assignment in `UserController` can never carry it.
2. **Dedicated profile `avatar`** (`image/jpeg`, `image/png`, `image/webp`; `max_size` 2048 KB; `max_width` and `max_height` 512, from `.env` variables like the other profiles). Not reusing `image`: its 30 MB / 1920 px limits and GIF support are wrong for an avatar, and `FileOfProfile('avatar')` then prevents any other file kind from being bound. Downscaling is proportional (no crop), which the existing `ImageResizer` already does.
3. **Owner type `user`.** `User::MEDIA_OWNER = 'user'`, role `avatar`, lang `default`-language like terms; `UserOwner` (label = user name, url = `/admin/users/{id}/edit`, a plain admin URL guarded by `can:users` on its own). Registered in `config/files.php` `owners`. The release path: `ProfileAvatar` update calls `FileRelations::sync('user', $user->id, 'avatar', lang, $uuids)` in the same transaction as the column write, and `UserController::destroy` calls `forgetOwner('user', $id)` (the relation has no FK to the owner, so it would otherwise dangle).
4. **Endpoint.** `PUT /admin/profile/avatar` with `avatar` = file `uuid` (or empty to remove), validated by `FileOfProfile('avatar')`, handled by an action on `ProfileController` (or a small invokable controller if the class grows), inside the existing `auth` + `verified` group. The value stored is built from the `media` row (`{uuid}.{ext}` from `path`), never trusted from input.
5. **One display component.** `UserAvatar` gets an optional `src` prop and renders `<img class="object-cover">` in the same round box, still `aria-hidden` with empty `alt` (the name is always next to it). `UserMenu` drops its private `initials()`/`Avatar` and uses `UserAvatar`. Size comes through `className` (`size-7` for the header, larger on the profile).
6. **Data to the client.** `auth.user.avatar` is the URL or `null`, computed in `HandleInertiaRequests` with `FileStorage::urlForValue('avatar', …)`. The users list rows carry `avatar` the same way (one disk URL build per row, no extra query, since `avatar` is a column). The node list does the same: the author column's `author` object (from `node-list-author-column`) gets `avatar`, the page's authors are loaded with `author:id,name,avatar`, and the form's `node.author` gets `avatar` too; `NodeServicePanel` and the author cell render `UserAvatar`. The media table does the same: `MediaController` eager-loads `uploader:id,name,avatar` and each row carries `uploader_avatar` next to the existing `uploader` name; the uploader cell renders `UserAvatar` plus the name, and nothing when the uploader is unknown.
7. **Original file is served as is.** After the 512 px downscale the original is small enough; no `thumb` style is used (its two-sided style fills margins with a blurred background, which is wrong for a face in a circle).

## Risks / Trade-offs

- [Media library "where used" link for owner `user` points to `/admin/users/{id}/edit`, which needs the `users` right] → Same situation as other owners with guarded pages; the label is still shown.
- [A migration on `users`] → One nullable column, no data change; the full PHP run is required by project rules for schema changes.
- [Non-square pictures are cropped by `object-cover`] → Accepted; an editor is out of scope.
- [The media library cannot delete a used avatar] → Intended: the relation `RESTRICT` protects it; removing the avatar from the profile releases it.

## Open Questions

- None that change the specs: the exact size limit (2 MB) and 512 px bound are tunable in `.env` without code changes.

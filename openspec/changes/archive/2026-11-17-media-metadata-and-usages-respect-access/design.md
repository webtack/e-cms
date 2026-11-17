## Context

See proposal.md - Why. Current state, read from the code:

- The node form has no picker of existing library files (`docs/admin-content-nodes.md`: "no file choice from the media library"); new files get in by upload (`POST /admin/files`), which records `files.user_id` for every upload, with or without a media right. The server nevertheless accepts any existing file uuid of the right profile in a node's values (`FileOfProfile` checks only the profile), so a crafted request can attach a foreign file.
- Every file-bearing value (`image`/`file` fields, YouTube screenshots, images in rich text) goes through `ContentValueWriter::replace()`, which stores the value and calls `FileRelations::sync()` for fields implementing `TracksFiles`, in each language. `media_relations` therefore always holds the node's referenced files per field (`role`) and language. `ContentNodeController` calls the writer inside a `DB::transaction` for `store`, `update`, and (through `TranslationSaver::save`) `saveTranslation`; a `ValidationException` thrown inside rolls everything back and is mapped to `values.{key}` by `forForm`.
- `ContentNodeController::applyFileMeta` writes `original_name` and `description` only for files in the node's saved values, and `TranslationSaver::saveFileDescriptions` accepts only uuids already in the node's (or type's) values. So both depend on what may get into the values, not on a check of their own.
- `MediaPolicy::update(User, File)` expresses "may this user change this file" (media level `any`, or `own` and `files.user_id` equals the user).
- `MediaController::show` returns `usages` built by `FileRelations::usages` and `describeOwners`; the registry of owners (`ContentNodeOwner`, `UserOwner`, `TermOwner`, `MenuItemOwner`, `ContentTypeOwner`, `SiteSettingOwner`) returns labels and edit URLs with no access check. The panel (`MediaDetails.tsx`) renders `label`, `url`, `role`, `lang`.
- Existing tests assert the full usage shape for an administrator (`MediaLibraryTest`), which must stay valid.

Documentation describing these components: `docs/media-library.md`, `docs/admin-content-nodes.md`, `docs/access-control.md`.

## Goals / Non-Goals

**Goals:**
- A user can attach to a node only a file they may attach, closing the root cause of the file-data leak on every path that writes node values.
- The right to edit a node keeps meaning the right to change the data of its files.
- A viewer sees only the places of use they could open anyway.

**Non-Goals:**
- Changing the node form or adding a library picker.
- Changing how `file_meta` and the translation of file data work; they only ever touch files already in the node.
- Other places that accept a file by uuid: type values, site logos, terms, menu items sit behind their own system rights, and the profile avatar affects only the user's own account.
- Changing the media library list, the delete rules, or the usage count.
- Per-owner access rules beyond those listed in the spec (no new rights).

## Decisions

**1. A guard on new files, run after the write, inside the existing transaction.**
A new service `App\Files\AttachedFileGuard` with two calls: `snapshot(ValueOwner)` returns the set of file uuids in `media_relations` of the node (all roles and languages), and `assertAllowed(User, ValueOwner, array $before)` reads the set again, takes the difference (the files that are new), and for each new file that exists in `files` requires `files.user_id` equals the user or `MediaPolicy::update` allows. Otherwise it throws a `ValidationException` keyed by the relation's `role` (the field key), which `forForm` shows as `values.{key}`. The controller takes the snapshot before the write (empty on create) and calls `assertAllowed` after it, in the same transaction, so a refusal rolls back the values, the relations, the slug change, and `file_meta`.
Why after the write and by relations: the writer already normalizes values and tracks the files of every type (`TracksFiles`: fields, YouTube screenshots, rich text) per language, so the guard needs no per-type parsing of raw input and cannot miss a type that is added later and tracks files. Alternative: a validation rule per file field (rejected: rich text and YouTube need their own parsing and the rule has no access to the actor and the saved values); a check in `ContentValueWriter` (rejected: it also serves legacy import, type values and seeders, which have no actor).
`ContentNodeController` calls it in `store`, `update`, and `saveTranslation`; the translation mode can add a file to a translatable field in another language.

**2. File data follows the node edit right, with no actor filter.**
`applyFileMeta` and `TranslationSaver::saveFileDescriptions` stay as they are. Whoever may edit a node (`ContentNodePolicy::update`) may rename and describe the files in its values: with decision 1 those files are either the user's own, ones the media access allows, or ones already in the node before. A colleague's file in a node an `any` editor opens stays renameable, which keeps the form feature intact.
Alternative considered and dropped: filtering `file_meta` by file ownership (it would stop an `any` editor without media access from renaming a colleague's upload in a node they may edit, and would treat the symptom, not the cause).

**3. Translation and type-values paths: probe, test, no code change expected.**
A probe confirms that `saveFileDescriptions` for the node translation action and for the type-values translation action accepts only files already in the values, so with decision 1 there is no new way in. The task adds regression tests; code changes only if the probe finds a path where a new file can still enter without passing the guard.

**4. Usage visibility in a new service, not in the owner classes.**
`App\Files\UsageAccess` takes the viewer and the usage collection and returns which usages are visible and which have a link. It groups by `owner_type`, makes one query for node types (`content_nodes.id` to `content_type` and `user_id`) and decides by `AccessResolver` (`nodeLevel`, `canEditNode`, `allows`). The owner classes stay free of access logic and keep their one-pass `describe()`; the controller calls `describe()` only for visible owners, so hidden titles are never even read.
Alternative: add an access method to the `MediaOwner` interface (rejected: seven classes change and the registry is meant to describe, not authorize).

**5. Shape of a hidden usage.**
Hidden entries keep the array position and the total count, and carry `restricted: true`, `label: "Used elsewhere"`, `url: null`, and empty `owner_type`, `owner_id`, `role`, `lang`. The panel renders only the label for such entries and builds a distinct list key from the index. An unknown owner type is hidden for everyone, administrators included: it is never described.
Today an unknown owner type is shown as `type #id`; this becomes the neutral entry. Alternative: drop hidden entries from the list (rejected: the count and the "file is in use" explanation would disagree with the list).

**6. Node links follow the edit right.**
A visible node usage gets a URL only when `canEditNode` allows it (the rule the dashboard and the sidebar already use); otherwise the title is shown without a link.

## Risks / Trade-offs

- [A request that carries another user's file the node already holds is accepted] → Intended: those files are in the node's saved values, which is how an `any` editor keeps a colleague's upload.
- [A node that already holds a foreign file (an old crafted save, legacy import) keeps it] → The guard checks only what is new; existing data is not rewritten. A file with `files.user_id = null` can be newly attached only by media `any` or an administrator, the same as the media rule.
- [The guard needs `media_relations` to be in sync with the values] → `ContentValueWriter::replace()` writes both in the same transaction for every `TracksFiles` field; a regression test covers the relation of a file field, a YouTube screenshot and a rich text image.
- [Two reads of `media_relations` per node save] → One indexed query by owner before and one after; the save already runs many queries.
- [Extra queries on the details endpoint] → One query for node types; the details endpoint is a single-file request, not a table row.

# Proposal

## Why

The file storage (`file-storage`) already accepts and stores files, but it does not show what exactly lies in the storage, and it does not know who uses the file and where. The item "Media Library" in the sidebar remains a layout. There is also no first consumer: a field of type `file` that writes a file into content. Now, to delete unnecessary files, one would have to go through the database by hand; and the automatic `files:prune` through `config('files.references')` requires every consumer to register itself in the configuration, and it can delete a file that is in use if someone forgot about it.

## What Changes

- **The media library** `/admin/media` instead of the layout: a table (on the existing `DataTable`) with all files in the storage, both used and not. Columns: preview, name, type, size, who uploaded it, date, **the number of uses** (0 for unused ones). The file details show where it is used. An unused file can be deleted manually; the server refuses if the file has relations. There are no roles in the project, so the page is available to every authenticated admin (intended as a superadmin page, when roles appear).
- **The table of relations `media_relations`**: "a file is used by an owner in a role". The relation to a file is by `uuid`, the foreign key `RESTRICT` does not allow deleting a used file. The relations service synchronizes the set of files of the owner in one transaction with the writing of the value.
- **Two field types, `image` and `file`**: both store the value in `content_values_string` as `{uuid}.{extension}`; the type defines the upload profile, which remains the **ceiling** (`image` → profile `image`, `file` → profile `document`), and the field itself narrows it with its settings: the allowed file types (a subset of the profile's types) and the maximum size (not more than the profile's limit). Without settings the whole profile applies. The URL is built from the string and the disk of the profile without additional DB queries. The content core (reading, query, registry) does not change: type classes, lines in `config/content.php` and an optional hook interface that `ContentValueWriter` calls after writing, to synchronize `media_relations`, are added. The relations are removed when the record and the field are deleted.
- **The table `files` is renamed to `media`** (a `Schema::rename` migration); the model `File` stays and points to `$table = 'media'`. The media library is a view of this table; there is no separate entity.
- **Uploading by field**: `POST /admin/files` accepts either a profile or a field (`content_type`, `field_key`); for a field the server takes the profile of the type and narrows it with the settings of the field. Saving a record checks the file against the settings of the field once more (authoritatively). A change of settings does not make the already stored values invalid.
- **No directories**: the upload profiles no longer have `directory`; the file lies in the root of the profile's disk as `{uuid}.{extension}`; the location is set by the standard setting of the disk. The path depends only on the `uuid`, so an edit of the configuration does not break the stored values.
- **The file identifier without hyphens**: `media.uuid` becomes `char(32)` (uuid v7 without hyphens), the file name on the disk — `{uuid}.{extension}`. The `uuid` becomes the single external identifier: the response of `POST /admin/files`, the widget `FileUpload` and the rule `FileOfProfile` work with it instead of the numeric `id`.
- **The owner of a file**: `media.user_id` (nullable, a foreign key to `users`, `nullOnDelete`); `POST /admin/files` records the authenticated user. The media library shows everyone.
- **BREAKING (for consumers that do not exist yet)**: `files:prune`, its schedule, `prune_after_hours`, `config('files.references')` and the key `directory` of the profiles are removed; the consumer's value is the `uuid`, not the `id`; `media.uuid` changes its format.

Not included: a file picker in forms (the fields `image` and `file` use the widget `FileUpload` directly); roles and permissions; thumbnails; folders and tags; the reconciliation command `media:reconcile`; private files.

## Capabilities

### New Capabilities
- `media-library`: the media library page with the list of all files, the number of uses and manual deletion of unused ones; the table of relations `media_relations` and its synchronization.

### Modified Capabilities
- `file-storage`: uploading by field (narrowing the profile rules with the settings of the field); `uuid` without hyphens as the single external identifier and the file name on the disk; the owner of a file (`user_id`); the rule `FileOfProfile` and the widget work with `uuid`; the requirement "Cleanup of unused files" is removed; the requirement "Protection of files in use" is rewritten from the FK of the consumer to `media_relations`.
- `content-values`: new field types `image` and `file` (the value `{uuid}.{extension}` in `content_values_string`, the field settings `mimes` and `max_size` within the profile of the type, a check on writing).
- `admin-content-fields`: for the fields `image` and `file` the form of the field lets one choose the allowed file types and the maximum size; the server checks the limits of the profile.
- `admin-content-nodes`: the record form shows for the field `image` a widget with an image preview, for `file` — a widget with the file name; the list shows the file name.
- `admin-shell`: the item Media Library is no longer a layout, it opens `/admin/media`.

## Impact

- **Code (PHP):** migrations (renaming `files` → `media`, `media_relations`; `media.uuid` → `char(32)` and `media.user_id`); the models `File` (uuid without hyphens, `user`), `MediaRelation`; the relations service in `app/Files/`; `ImageType`, `FileType` and the hook interface in `app/Content/FieldTypes/`, the call of the hook in `ContentValueWriter`; the cleanup of relations when `ContentNode` and `ContentField` are deleted; `FileStorage` (the file name from the uuid without hyphens, `user_id`), `FileController`/`StoreFileRequest`, `FileOfProfile`; `MediaController` (list, details, deletion) and routes; removal of `PruneFiles`, the entry in `routes/console.php`, `references` and `prune_after_hours` in `config/files.php`.
- **Code (React):** the page `Pages/Admin/Media/Index.tsx`; `FileUpload` moves to `uuid`; the elements `image` and `file` in `NodeFieldInput.tsx` and the settings of types/size in the form of the field (`ContentFields/*`); `navigation.ts` (Media Library without `mock`); translations `lang/en.json`, `lang/uk.json`.
- **Infrastructure:** no changes (the PHP limits are already raised).
- **Dependencies:** none.
- **Tests:** Feature/Unit: relations, `ImageType`, `FileType`, the write hook, the cleanup of relations, `MediaController`, the updated tests of `FileStorage`/endpoint/`FileOfProfile`; `PruneFilesTest` is removed; the e2e spec of the media library (`e2e/admin-media.spec.js`) and the update of `admin-file-upload.spec.js`; the rows in the tables "Area → spec" and "Shared code → specs" in `docs/e2e-testing.md`.
- **Documentation (`docs/`, in Ukrainian):** the new `docs/media-library.md` (the page, `media_relations`, how a consumer registers relations); updates to `docs/file-storage.md` (uuid without hyphens, `user_id`, the removed `prune` and `references`), `docs/content-values.md` (the types `image` and `file`), the documents of the forms of the field and of the record (settings, widgets `image` and `file`), `docs/admin-shell.md` (the item Media Library), `docs/e2e-testing.md`; the index `docs/README.md`.

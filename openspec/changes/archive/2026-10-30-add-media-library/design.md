# Design

## Context

The motivation and scope are in [proposal.md](proposal.md), the requirements are in `specs/`. What is in the code (checked):

- `files` (see `docs/file-storage.md`; this change renames the table to `media`): `uuid` — `char(36)` UUID v7 with hyphens, generated in `File::booted` and in `FileStorage::store` (`Str::uuid7()`); `path` = `{directory}/{uuid}.{ext}`; `disk` is recorded in the row. There is no user column. There are no consumers, `config('files.references')` is empty.
- `FileOfProfile` accepts a numeric `id`; `POST /admin/files` returns `id` and `uuid`; the widget `FileUpload` passes `id` to the form.
- Content core: `FieldTypeRegistry::tables()` and `ContentValueReader`/`ContentNodeQuery`/`ContentFieldController` group by `table()`, so two type keys can share one table. `ContentValueWriter::replace` deletes and inserts rows directly through `DB::table`. Changing a field type with stored values is forbidden only by the table of the original type (`ContentField::hasStoredValues`).
- The form of a field has no type settings (min/max are given only in data); the record form maps a type to a widget in one place — `NodeFieldInput.tsx`.
- Deleting a content type is forbidden if it has records (`ContentTypeController::destroy`), so values disappear only together with the record (`ContentNode`) or the field (`ContentField`).
- `navigation.ts` has `Media Library` as a `mock` at `/admin/media`.

## Goals / Non-Goals

**Goals:**
- The media library shows all files and the number of uses of each; an unused file is deleted manually.
- All links "file → owner" are gathered in one table, from which both the counter and "where it is used" are taken.
- The types `image` and `file` fit into the existing EAV without a new values table and without changes in reading/selection.
- Displaying a field value (URL) makes no DB queries.

**Non-Goals:**
- A file picker, roles and permissions, thumbnails, folders and tags, `media:reconcile`, private files.
- Automatic cleanup.

## Decisions

### D1. The table `files` → `media` and `media.uuid` — `char(32)` without hyphens

A separate migration `Schema::rename('files', 'media')` (the archived migration `create_files_table` is not edited); the model `File` stays and sets `protected $table = 'media'`, and `FileFactory` and the rest of the code (`FileStorage`, `config/files.php`, `POST /admin/files`, `FileOfProfile`, `FileUpload`, the field type `file`) are not renamed. The column `media_relations.file_uuid` keeps its name. The migration changes the column `uuid` to `char(32)`, converts the existing values (removes the hyphens) and for each row renames the file on the disk from `{uuid-with-hyphens}.{ext}` to `{uuid32}.{ext}`, updating `path`. The new `uuid` is `str_replace('-', '', (string) Str::uuid7())`, one helper used by `File::booted` and `FileStorage::store` (now a duplicate).

Alternatives: keep the hyphens in the table and remove them at the boundaries (rejected: two forms of the identifier and a conversion in each rule); the content hash as the name (rejected: deduplication, which contradicts "each row has its own file and independent deletion"). uuid v7 is chosen for the ordering of the indexes.

There is no directory: the file lies in the root of the disk, and the root is set by the standard disk setting in `config/filesystems.php`. The migration moves the existing files from the profile directories (`images/`, `documents/`) to the root of the disk. Invariant: `path === {uuid}.{ext}`. D6 rests on it.

### D2. The table `media_relations`

| Column | Type | Purpose |
| --- | --- | --- |
| `id` | bigint | — |
| `file_uuid` | char(32) | FK to `media.uuid`, `restrictOnDelete` (there is a unique index on `uuid`) |
| `owner_type` | string(64) | The key of the owner's type (`content_node`) |
| `owner_id` | unsigned bigint | The identifier of the owner |
| `role` | string(64) | The key of the field (for content) |
| `lang` | string(16), binary comparison | The language of the value (`default` or a code), as in `content_values_*` |
| `timestamps` | — | — |

Uniqueness: (`file_uuid`, `owner_type`, `owner_id`, `role`, `lang`). An index (`owner_type`, `owner_id`) for synchronization and cleanup. The owner is polymorphic, so there is no FK to it; the integrity is kept by the hooks (D4).

The link is by `uuid`, not by the numeric `id` — by the owner's decision. We do not store a copy of `file_id`: two keys would need a composite FK for consistency.

### D3. The service `FileRelations`

`sync(string $ownerType, int $ownerId, string $role, string $lang, array $fileUuids): void` compares the current rows of the owner in this role and language with the given set, deletes the extra ones and inserts the new ones. It does not open its own transaction: it works in the transaction of the caller. `forgetOwner(ownerType, ownerId)` and `forgetRole(ownerType, ownerId?, role)` for cleanup (D4). `usageCounts`/`usages` — reads for the media library.

### D4. The write hook and cleanup

An optional interface `App\Content\FieldTypes\TracksFiles` with the method `fileUuids(mixed $storedValue): array`. `ContentValueWriter::replace`, after the insert, calls — if the type implements it — `FileRelations::sync('content_node', $node->id, $field->key, $lang, $type->fileUuids($value))`; `replace` already runs in the transaction of `write()`. The rest of the core does not change.

Cleanup: the model events `ContentNode::deleted` (all relations of the record) and `ContentField::deleted` (the relations of this field). A type without records has no values, so deleting it leaves no relations. A record and a field must be deleted through the model, not through raw SQL; this rule is written into the documentation.

Alternative: keep the relation directly in `content_values_*` (a column `file_uuid`), which would give a cascade. Rejected: it touches the shared contract of the value tables and breaks "the value of `image`/`file` lies in `content_values_string`".

### D5. The types `image` and `file`

The abstract `AbstractFileFieldType extends AbstractFieldType implements TracksFiles` sets the common parts: `table()` = `content_values_string`, the operation `eq`, does not take part in the text search, the value rule. The concrete types only name the profile: `ImageType` (`key()` = `image`, profile `image`) and `FileType` (`key()` = `file`, profile `document`); the ceiling profile is defined by the type, and the field narrows it with its settings (D12). Both are registered in `config/content.php`. `rules()` returns a rule that (a) requires the format `^[0-9a-f]{32}\.[a-z0-9]{1,8}$`, (b) calls `FileOfProfile` with the profile of the type, (c) checks that the extension matches the extension of `media.path`, (d) checks that `media.mime` is among the allowed types of the field, and that `media.size` does not exceed `max_size` of the field (D12). `prepare`/`cast` — unchanged.

The type of a field cannot be changed between `string`, `image` and `file` when there are values: the check in `ContentField` compares only the tables of the original type, and this one is shared. So `ContentField` compares the type keys when the tables are the same, and rejects a change between these three types for a field with values. This is the only edit in the core of the field model.

### D6. URL without DB queries

`FileStorage::urlForValue(string $profile, string $value): string` builds `Storage::disk(profile.disk)->url($value)`. The profile is defined by the type of the field (`image` → `image`, `file` → `document`) and is needed only for the disk. There are no queries to `media`.

Consequence: only the `disk` of the profile depends on the configuration; its change already means moving files between storages, so it is a deliberate operation. The directory from the profiles is removed (the key `directory` in `config/files.php` is deleted), so the paths cannot be broken accidentally by editing the configuration. The location is set only by the root of the disk in `config/filesystems.php`. We record this in the documentation and with a test `media.path === {uuid}.{ext}` for each saved file.

### D7. File names in the admin

The URL is needed everywhere, but the original name (`original_name`) exists only in `media`. The admin pages (the edit form of a record, the table of records) load the names for the values that are now on the screen with **one** `whereIn('uuid', …)` per page on `media`. The public reading of content does not make this query. If the file is missing (deleted outside the service), the stored value is shown.

### D8. The media library

`MediaController`: `index` (Inertia, a `DataTable` with server pagination, search by name, sorting; following `ContentNodeController` and `docs/admin-data-table.md`), `show` (JSON: metadata and the list of uses), `destroy`. The `index` query: `File::query()->withCount('relations')->with('uploader')`. The columns from the specification; the number of uses 0 is highlighted.

`destroy`: in a transaction it recounts the relations with a lock (`lockForUpdate` on the file); if there are some — it returns an error without deleting; otherwise `FileStorage::delete`. The FK `RESTRICT` remains the second protection against a race with writing a value.

### D9. The owner registry

`config/files.php`, the key `owners`: `owner_type => class implementing MediaOwner`. `MediaOwner::describe(array $ids): array` returns for each id a label and an edit link (or `null`). For `content_node` — the title (`title`, language `default`) and the address `/admin/content-types/{type}/nodes/{uuid}/edit`. An unknown owner type does not break the page: `owner_type #id` is shown without a link.

### D10. The owner of a file

A migration adds `media.user_id` (nullable unsigned bigint, FK `users`, `nullOnDelete`); `FileStorage::store` accepts an optional user, `FileController` passes `$request->user()`. In the media library there is a column "Uploaded by" (the user's name or empty).

### D11. Cleanup `prune`

Removed: `app/Console/Commands/PruneFiles.php`, `tests/Feature/Files/PruneFilesTest.php`, the entry in `routes/console.php`, the keys `references` and `prune_after_hours` in `config/files.php`, the section "Lifecycle and cleanup" in `docs/file-storage.md` (replaced by a link to `docs/media-library.md`). The accumulation of files is a deliberate compromise; they are deleted manually in the media library.

### D12. The settings of a field: types and size

The settings are stored in `content_fields.settings` (JSON): `mimes` — a list of allowed mimes (a subset of `profile.mimes`), `max_size` — the maximum size in KB (not more than `profile.max_size`, as in the profile). Missing settings mean the whole profile. The narrowing rules live in one place — `App\Files\UploadRules::forField(ContentField): UploadRules` (the intersection of types, the minimum of sizes) and `forProfile(string)`; they are used by the endpoint, `FileType` and the form of the field (for the list of available types).

- **Endpoint.** `POST /admin/files` accepts `profile` **or** the pair `content_type` + `field_key` (both at once — a validation error). For a field the server checks that the field exists and has the type `image`/`file`, takes the profile of the type and applies the narrowing. `FileStorage::store` receives an optional parameter with the narrowed rules; without it the profile applies. The narrowed rules never widen the profile: the intersection with `profile.mimes` and the minimum with `profile.max_size` guarantee this even with corrupted data in `settings`.
- **Saving a record.** `FileType::rules` checks `media.mime` and `media.size` against the settings of the field. This is the authoritative check: the same file can be tried for insertion into another field with narrower rules.
- **Changing the settings.** It does not change or delete the stored values; the new rules apply at the next write of a value. The profile of the field does not become locked, because there is none (it is defined by the type).
- **Widget.** `FileUpload` receives `accept` and `maxSize` from the settings of the field only as a hint and for a fast rejection; the server checks independently.

Alternatives: rules in the body of the request from the client (rejected, cannot be trusted); rules only when saving a record (rejected: the file already lies on the disk, and the user learns about the error late); a complete rejection of profiles in favour of field settings (rejected: consumers without a field — an avatar, a logo — would be left without a place for rules).

### D13. The widget and the endpoint on `uuid`

The response of `POST /admin/files` does not contain `id` (only `uuid`, `profile`, `name`, `mime`, `size`, `width`, `height`, `url`). `FileUpload` passes `uuid` in `onChange`; the elements `image` and `file` in `NodeFieldInput` store in the value `{uuid}.{ext}` (the extension is taken from the response as part of `url`/`name` — the endpoint additionally returns `value`). The profile for the widget is defined by the type of the field, and `accept`/`maxSize` — by the settings of the field (D12). The element `image` shows an image preview, `file` — an icon and the file name; on the site the types are output differently (an image with dimensions, a link to the file), which is the business of the client code.

## Risks / Trade-offs

- **The counter can diverge from reality** (a value written bypassing `ContentValueWriter`, a record deleted by raw SQL) → an unused file looks used (safe, it cannot be deleted) or the reverse, a used file looks unused (dangerous: a manual deletion removes a file that is still used on a page). Mitigation: a single write path in a transaction, the rule "delete through the model" in the documentation, the deletion dialog names the file. The reconciliation command `media:reconcile` is deliberately postponed; if discrepancies are found, it is added in a separate change.
- **Changing the `disk` of a profile breaks values** (D6) → a rule in the documentation: a change of disk is accompanied by moving the files; a test of the path correspondence. Files of different profiles lie in the same root of the disk and can be told apart only through `media`.
- **The migration renames files on the disk** (D1): a failure in the middle of the migration leaves some files renamed → the migration goes row by row, the renaming and the update of `path` for each row together; a file missing on the disk does not stop the migration; on SQLite in tests the disk is `Storage::fake`.
- **Narrowed rules diverge from the stored values** (the admin narrowed the types after files appeared in records) → the values are not touched, the new rules apply at the next write; this is recorded in the specification.
- **Accumulation of files without automatic cleanup** → accepted; it is visible in the media library.
- **A superadmin page without roles is available to every admin** → recorded in the specification and the documentation, roles are added in a separate change.
- **`char(32)` + FK on `uuid`**: MySQL requires a unique index on the referenced column (it exists); in the SQLite tests FKs are enabled.

## Migration Plan

1. Migrations: `media.uuid` → `char(32)` with the renaming of files; `media.user_id`; `media_relations`. Rollback: the reverse conversion of `uuid` does not restore the hyphens, so `down` for `uuid` throws an exception ("irreversible"); `user_id` and `media_relations` are rolled back with `drop`.
2. Deploy the code, remove the schedule `files:prune` from the cron wrapper, if there was one.
3. The profiles `image`/`document` do not change.

## Documentation

- the new `docs/media-library.md` (the page, `media_relations`, the registry of owners, the rules for a consumer: synchronization, deletion through the model);
- `docs/file-storage.md` (uuid without hyphens, `user_id`, the removed `prune` and `references`), `docs/content-values.md` (the types `image` and `file`), `docs/admin-content-fields.md` (the settings of the type and size), `docs/admin-content-nodes.md` (the widgets), `docs/admin-shell.md`, `docs/e2e-testing.md`, the index `docs/README.md`.

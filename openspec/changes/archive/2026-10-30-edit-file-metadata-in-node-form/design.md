# Design

## Context

The record form is `ContentNodeForm.tsx`; the file fields are drawn by `NodeFieldInput` (single) and `NodeFieldList` (multiple) through the widget `FileUpload`. The `Edit` page receives the prop `files` (`ContentNodeController::fileInfo()`: name, mime, size, url by the value `{uuid}.{ext}`); the form remembers them in the state `known`. Saving — `PUT …/nodes/{node}` (`UpdateContentNodeRequest` → `ContentNodeController::update()` → `ContentValueWriter::write()` in a transaction). The file name lives in `media.original_name`; there is no description. `ContentField` also has no description; the fields are passed to the record form through `ContentNodeController::formFields()`, and the field forms — `ContentFieldForm.tsx` (the rules are shared in `StoreContentFieldRequest::sharedRules()`). A file in `media` is shared by all owners (`media_relations`). Described in [`docs/admin-content-nodes.md`](../../../docs/admin-content-nodes.md), [`docs/file-storage.md`](../../../docs/file-storage.md), [`docs/media-library.md`](../../../docs/media-library.md), [`docs/admin-content-fields.md`](../../../docs/admin-content-fields.md).

## Goals / Non-Goals

**Goals:**
- The name and the description of a file are edited in the record edit form and are saved atomically together with the record.
- Remove "Add item" from the file multiple fields and the × of the file block in the row of a multiple field.

**Non-Goals:**
- Editing the metadata in the media library itself; formatting (markdown/HTML) in the description of the field.
- Changing the extension, the path or the content of the file; renaming the file on the disk.
- Access rights: while there are no roles, any administrator who edits a record changes the metadata of the file.

## Decisions

1. **The metadata travels in the same save request (`POST` on create or `PUT` on update), not a separate one.** The form adds the key `file_meta`: `{ [uuid]: { name, description } }` for the files that are in the values of the form. `update()` after `writer->write()` in the same `DB::transaction`, and `store()` — wraps `ContentNodeCreator::create()` and the application of the metadata in its own `DB::transaction` (the nested transaction of the creator becomes a savepoint), and they update `media.original_name` / `description`. Decision: one "Save" button, one toast, and a validation error of the record does not leave partially saved metadata (and vice versa). *Alternative:* `PATCH /admin/media/{uuid}` on blur — rejected: a partial state (the file renamed, the record not saved) and new routes.
2. **The server changes only the files that are really in the values of the record.** The application is the same for `store()` and `update()` (a shared method). The server takes `fileUuids()` from the stored values of the file fields (`TracksFiles`) and applies the metadata only to the intersection; foreign uuids are silently ignored. Otherwise anyone could rename an arbitrary file knowing its uuid. A file just uploaded in this form is also in the values, so the intersection covers it.
3. **Validation in `StoreContentNodeRequest` and `UpdateContentNodeRequest` (shared rules):** `file_meta` — `sometimes|array`; the keys — 32 characters `0-9a-f`; `file_meta.*.name` — `required|string|max:255`; `file_meta.*.description` — `nullable|string|max:2000`. The name is cleaned the same way as on upload (`basename`, without `\`, truncation to 255). An empty description (`''`/spaces) → `NULL`. Errors are addressed by the key `file_meta.{uuid}.name`; the form shows them next to the corresponding field. A row with an error `values.*` and `file_meta.*` comes together, as now.
4. **The column `media.description`** — `text`, `nullable`, the migration `add_description_to_media_table`. `File` gets `description` in `Fillable` and in the PHPDoc. `fileInfo()` and `MediaController::show()` return `description`; the row of the media table does not show the description.
5. **The widget `FileUpload` stays universal:** new optional props `removable` (by default `true`; `false` hides the × "Remove file") and `details` (ReactNode, drawn inside the block of the selected file). The demo page and other consumers do not change. The fields "Name"/"Description" are built by `NodeFieldInput` and passed into `details`; they are present both in the creation and in the editing.
6. **The state of the metadata in `ContentNodeForm`:** `useForm` receives `file_meta`, initialized from the prop `files` (name/description; empty on creation) for the files that are already in the values; for a just-uploaded file the entry is added at `rememberFile`. Only the uuids that remained in the values of the form are sent (replacing/removing a file removes its metadata from the request). `known` does not need to change — it stays the source of the display, `file_meta` — the source of the editing.
7. **"Add item"** is hidden in `NodeFieldList` for `image`/`file`; the new items are only the multiple upload zone. The × of the row ("Remove item") stays, the × of the file block in the row — `removable={false}`; in a single field `removable` stays `true`.
8. **The description of a field — `content_fields.description`** (`text`, `nullable`, the migration `add_description_to_content_fields_table`). The rule `description` (`nullable|string|max:1000`) is added to `StoreContentFieldRequest::sharedRules()`, so the edit form inherits it too; an empty string → `NULL` (`ConvertEmptyStringsToNull` already converts, spaces are trimmed by `TrimStrings`). System fields may change the description (it is not in `SYSTEM_LOCKED`). `ContentFieldController` adds `description` to the `only([...])` of the response and to the form; `formFields()` adds `description` to `NodeField`. `ContentNodeForm` shows it right under the legend (`text-sm text-muted-foreground`) for single, multiple and boolean fields; without a description nothing is drawn. The description is plain text, output as React text (without HTML).

## Risks / Trade-offs

- [The shared metadata: renaming in one record changes the name everywhere the file is used] → accepted deliberately (the name and the description are properties of the file), recorded in the specification and the documentation.
- [Two administrators edit one file at the same time — the last save wins] → acceptable, there are no locks on the other fields.
- [The request becomes bigger with the number of files in the form] → the metadata is small; the multiple fields are limited by reasonable numbers.
- [Without "Add item" an empty item cannot be added in a file field] → not needed: an empty widget made no sense, the upload adds the items itself.

## Migration Plan

`docker compose exec app php artisan migrate` adds nullable columns; the existing files have `description = NULL`. Rollback — `migrate:rollback` (the columns are deleted together with the descriptions). The data of `original_name` does not change.

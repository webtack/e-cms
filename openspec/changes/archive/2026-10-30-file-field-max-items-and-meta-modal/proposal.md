# Proposal

## Why

A multiple field currently has no upper limit on the number of items: the editor can add as many values as they like, and the administrator cannot limit, for example, a gallery to five images. Separately: the "Name" and "Description" fields under each file (added in `edit-file-metadata-in-node-form`) bloat the block of one file too much, especially in a multiple field with many rows.

## What Changes

- A new field setting **"Maximum number of items"** (`max_items`) for multiple fields of any type: an integer ≥ 0, by default `0` — no limit. It is set in the create and edit field forms (only when "Multiple" is enabled); for a non-multiple field it is ignored and not stored (reset to `0`).
- The server rejects a record in which a multiple field has more items than `max_items` (an error next to the field), regardless of the form.
- The record form respects the limit: when the limit is reached, "Add item" is hidden/disabled, and the zone of multiple file upload is unavailable and shows a hint; the files of a batch beyond the free places are not uploaded, and the reason is shown. Next to the field a counter "n / max" is visible if a limit is set.
- The fields "Name" and "Description" of a file move from the file block to a **modal window**: in the file block, in place of the former inner × (in a row of a multiple field) a button "Edit file data" appears; in a single field it stands next to the button for removing the file. The window has the buttons "Done" (applies the changes to the state of the form — they are saved together with the record, as before) and "Cancel" (discards). The saving contract (`file_meta`) does not change.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-fields`: the setting "Maximum number of items" in the field forms and in the response of `index`/the form.
- `content-values`: the validation of a multiple field takes `max_items` into account.
- `admin-content-nodes`: the limit in the record form (the list, multiple upload); the name and the description of a file are edited in the modal window.

## Impact

- Code: the migration `add_max_items_to_content_fields_table`; `ContentField`, `StoreContentFieldRequest`/`UpdateContentFieldRequest`, `ContentFieldController`, `ContentValueWriter`, `ContentNodeController::formFields()`; frontend — `ContentFieldForm.tsx`, `ContentNodeForm.tsx`, `NodeFieldList.tsx`, `NodeFieldInput.tsx`, `types.ts`, the new `ui/dialog.tsx`, translations `lang/uk.json` / `lang/en.json`.
- Documentation (updates): `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/e2e-testing.md`; if needed `docs/file-storage.md` (if `FileUpload` changes).
- Tests: Feature (fields, records), e2e `admin-content-fields.spec.js`, `admin-content-nodes.spec.js`.

## Assumptions

- The limit is stored in a separate column `content_fields.max_items`, not in `settings`: it is a general property of multiplicity (like `is_multiple`), not a specific setting of a value type. `settings` remains the place of specific settings of any field; the fact that currently only the file types accept it is the current state, not a restriction of the design, and this change does not fix it.
- If the limit is reduced below the number of already stored items, the existing records do not change, but they cannot be saved until the number of items is within the limit.
- System fields are not multiple, so blocking `max_items` for them is not needed.

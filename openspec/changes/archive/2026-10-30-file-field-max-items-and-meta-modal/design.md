# Design

## Context

The field is the model `ContentField` (`content_fields`, `is_multiple`, `settings` json, which for non-file types is forbidden by the rule `settings => prohibited`). The rules of the field forms are `StoreContentFieldRequest::sharedRules()` (inherited by `UpdateContentFieldRequest`). The values are written and checked by `ContentValueWriter::validate()`: for a multiple field the rules are `[$presence, 'array']` + `{key}.*`. The record form is `ContentNodeForm.tsx` → `NodeFieldList` (multiple) / `NodeFieldInput` (single); the block "Name"/"Description" of a file is `FileMetaFields` in `NodeFieldInput`, passed into `details` of the widget `FileUpload`. The file metadata travels under the key `file_meta` (see the archived change `edit-file-metadata-in-node-form`); this contract does not change. The description of the components: [`docs/admin-content-fields.md`](../../../docs/admin-content-fields.md), [`docs/admin-content-nodes.md`](../../../docs/admin-content-nodes.md), [`docs/file-storage.md`](../../../docs/file-storage.md), [`docs/e2e-testing.md`](../../../docs/e2e-testing.md).

## Goals / Non-Goals

**Goals:**
- A limit of items for any multiple field, which is respected by both the form and the server.
- A compact file block: the metadata is edited in a modal window.

**Non-Goals:**
- A minimum number of items; a limit for non-multiple fields; a change of the `file_meta` contract; automatic trimming of already stored lists when the limit is reduced.

## Decisions

1. **A separate column `content_fields.max_items`** — `unsignedSmallInteger`, `default 0`, the migration `add_max_items_to_content_fields_table`. *Alternative:* `settings.max_items` — rejected not because `settings` is "for file types" (it is meant as specific settings of any field, and the `prohibited` rule for the other types is only the current state, which this change neither touches nor fixes), but because the limit is a general property of multiplicity alongside `is_multiple`, not a setting of a particular value type; a separate column also does not require extending the validation and the form of `settings` to all types. It is not in `SYSTEM_LOCKED` (system fields are not multiple).
2. **Rules:** `max_items` — `nullable|integer|between:0,65535` in `sharedRules()`; `null`/absent → `0`. For `is_multiple = false` the value is forcibly reset to `0` before saving (in the controller, like the rest of the normalization; the model remains the last barrier). `ContentFieldController` adds `max_items` to the `only([...])` of the form and the response; `formFields()` returns `max_items` in `NodeField`.
3. **Server:** in `ContentValueWriter::validate()` for a multiple field with `max_items > 0`, `max:{max_items}` is added to `[$presence, 'array']`. `normalize()` already drops the empty items, so only the non-empty ones are counted. The message — the standard `max.array`, the error key — `values.{key}` (the form shows it next to the field).
4. **The field form (`ContentFieldForm.tsx`):** an `Input type=number` (`min=0`, `max=65535`) "Maximum number of items" with the hint "0 — no limit" is shown under the checkboxes, only when "Multiple" is enabled; when it is disabled the value is reset to `0`.
5. **The record form:** `NodeFieldList` receives `max = field.max_items` and counts `full = max > 0 && value.length >= max`. "Add item" — `disabled={full}`. For file fields the `FileUpload` (multiple) receives `disabled` according to `full` and a hint; in the `addFiles` path the batch is trimmed to `max - value.length` **before** uploading (a new option of the widget/wrapper — the list of allowed files: those that do not fit are not uploaded, and their names go into the message "Limit: :max"). The counter "n / max" — next to the label of the field in `ContentNodeForm` (only for `max > 0`). *Alternative:* upload everything and trim — rejected: the extra files would remain in the storage unused.
6. **The modal window:** a new `Components/ui/dialog.tsx` (a wrapper over the `radix-ui` `Dialog`, in the style of the existing `alert-dialog.tsx`). `FileMetaFields` is replaced by `FileMetaDialog`: a button (a pencil icon, `aria-label` "Edit file data") opens a window with a draft (`name`, `description`), "Done" calls `editor.onChange(uuid, draft)` and closes, "Cancel"/Escape/an outside click close without changes. The draft is initialized on opening from the state of the form. *Alternative:* edit the form state directly in the window — rejected: "Cancel" would then require a separate rollback.
7. **Placement of the button:** the button belongs to the file block. `FileUpload` receives an optional slot `actions` (ReactNode) in the row of the buttons of the block, next to "Remove file" (`removable`). In the row of a multiple field `removable={false}`, so `actions` takes the place of the former ×; in a single one it stands next to "Remove file". `details` stays (for compatibility/other consumers), but the record form no longer uses it. The demo page does not change.
8. **Errors of the metadata:** since the fields are hidden in the window, `NodeFieldInput` shows the errors `file_meta.{uuid}.*` under the file block as text (`role="alert"`), and in the window they are also shown next to the corresponding fields; the button receives `aria-invalid`.

## Risks / Trade-offs

- [The limit is reduced below the number of existing items → the record cannot be saved until the extra ones are removed] → accepted deliberately; the form shows the counter above the limit and the server error; the data is not trimmed silently.
- [Hidden state in the modal window: the user pressed "Done", but not "Save"] → the changes live only in the form, as the other fields do; this same state keeps the current behaviour of the guard of unsaved changes, if there is one.
- [Trimming the batch to the free places: part of the selected files is not uploaded] → the message names the skipped files and the limit.
- [Two places of truth about the limit (the form and the server)] → the server is the final barrier; the form is only a convenience.

## Migration Plan

`docker compose exec app php artisan migrate` adds a column with `0` for the existing fields (the behaviour does not change). Rollback — `migrate:rollback` (the limits disappear).

# Tasks

## 1. Backend: `max_items`

- [x] 1.1 The migration `add_max_items_to_content_fields_table` (`unsignedSmallInteger`, `default 0`); `ContentField`: `max_items` in `Fillable`, the cast `integer` and PHPDoc (in English, with `@example`); check: `docker compose exec app php artisan migrate` passes, `migrate:rollback --step=1` and a repeated `migrate` too.
- [x] 1.2 `StoreContentFieldRequest::sharedRules()` (`max_items`: `nullable|integer|between:0,65535`) with PHPDoc; `ContentFieldController` returns and saves `max_items` (the response and the form), resets it to `0` for a non-multiple field; check: Feature tests (`docker compose exec app php artisan test --filter=ContentField`) — saving the limit on creation and editing, the reset when "Multiple" is disabled, errors for `-1`, `65536`, `abc`.
- [x] 1.3 `ContentValueWriter::validate()`: `max:{max_items}` for a multiple field with `max_items > 0`; `ContentNodeController::formFields()` returns `max_items`; check: Feature tests — within the limit, above the limit (the error `values.{key}`), without a limit, empty items are not counted; `formFields` in the response of create/edit contains `max_items`.
- [x] 1.4 Documentation: `docs/admin-content-fields.md` (the setting, the rules, the form) and `docs/admin-content-nodes.md` (`formFields()`, the server check of the limit); check: the cross-references work, `docs/README.md` unchanged.

## 2. Field form: "Maximum number of items"

- [x] 2.1 `ContentFieldForm.tsx`: a numeric field "Maximum number of items" (only when "Multiple" is enabled, the hint "0 — no limit", reset to `0` when disabled, an error next to the field, the entered text is not lost); labels in `lang/uk.json` and `lang/en.json`; JSDoc; check: `npm run build` without type errors.
- [x] 2.2 E2E `admin-content-fields.spec.js`: the field is visible only for a multiple one, saving and display in editing, an error for an invalid value; run `npm run test:e2e -- e2e/admin-content-fields.spec.js` (the field forms are shared code: add the dependent specs from the row of the table "Shared code → specs", if shared components are changed); check: the specs pass.
- [x] 2.3 Documentation: `docs/admin-content-fields.md` (the section "Form"), `docs/e2e-testing.md` (the scenarios `admin-content-fields`); check: the links work.

## 3. Record form: item limit

- [x] 3.1 `types.ts` (`NodeField.max_items`), `NodeFieldList.tsx`: "Add item" is disabled by `full`; the multiple upload zone is disabled with a hint; the batch is trimmed to the free places before uploading, the skipped files are named in the limit message; JSDoc; new labels in `lang/uk.json` and `lang/en.json`; check: `npm run build`.
- [x] 3.2 `ContentNodeForm.tsx`: the counter "n / max" next to the label of a multiple field with a limit; the server error `values.{key}` is shown next to the field; JSDoc; check: `npm run build`, a manual check on `http://localhost:8080` — a row and an `image` field with a limit of 2–3.
- [x] 3.3 E2E `admin-content-nodes.spec.js`: the counter, disabled adding when the limit is reached (a row and a file field), trimming of the batch, removal frees the place, a field without a limit has no counter; run `npm run test:e2e -- e2e/admin-content-nodes.spec.js` (plus the dependent specs per the table for `ContentNodes/*`); check: the specs pass.
- [x] 3.4 Documentation: `docs/admin-content-nodes.md` (the sections "Multiple fields" and "Fields `image` and `file`"), `docs/e2e-testing.md`; check: the links work.

## 4. Modal window for file data

- [x] 4.1 `Components/ui/dialog.tsx` (a wrapper over the `radix-ui` `Dialog` in the style of `alert-dialog.tsx`); `FileUpload.tsx`: an optional slot `actions` in the row of the buttons of the file block, JSDoc with `@example`; check: `npm run build`, `npm run test:e2e -- e2e/admin-file-upload.spec.js` passes without changes to the spec (the default behaviour has not changed).
- [x] 4.2 `NodeFieldInput.tsx`: `FileMetaFields` → `FileMetaDialog` (a pencil button "Edit file data" in `actions`, a draft "Name"/"Description", "Done" applies, "Cancel"/Escape discards, the errors `file_meta.{uuid}.*` under the block and in the window); `NodeFieldList.tsx` — the contract is unchanged (`removable={false}` in the row); labels in `lang/uk.json` and `lang/en.json`; JSDoc; check: `npm run build`, a manual check of creating and editing a record on `http://localhost:8080`.
- [x] 4.3 E2E `admin-content-nodes.spec.js` and `admin-media.spec.js`: adapt the existing scenarios of changing the name/description to the window (open → change → "Done" → save), add cancellation, a check of the absence of fields in the block, the button in place of the × in a row of a multiple field, and the error of an empty name; run `npm run test:e2e -- e2e/admin-content-nodes.spec.js e2e/admin-media.spec.js e2e/admin-file-upload.spec.js` (the dependent specs for `FileUpload` and `ContentNodes/*`); check: the specs pass.
- [x] 4.4 Documentation: `docs/admin-content-nodes.md` (the section "Fields `image` and `file`": the window instead of fields in the block), `docs/file-storage.md` (the slot `actions`), `docs/e2e-testing.md` (the scenarios; there are no new specs — check the tables "Area → spec" and "Shared code → specs"); check: `docs/README.md` without broken links.

## 5. Completion

- [x] 5.1 `docker compose exec app vendor/bin/pint --dirty --format agent` and `docker compose exec app php artisan test` pass; the report names the run and skipped e2e specs with the reason.

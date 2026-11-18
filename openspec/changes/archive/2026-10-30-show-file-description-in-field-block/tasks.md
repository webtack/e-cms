# Tasks

## 1. Widget and field

- [x] 1.1 `FileUpload.tsx`: an optional prop `description` (single mode) — a `<p>` between the name and the size with `line-clamp-2`, `data-testid={`${root}-description`}`; JSDoc with `@example`; check: `npm run build` without type errors.
- [x] 1.2 `NodeFieldInput.tsx`: for the fields `image`/`file` take the name and the description from `fileMeta.values[uuid]` (fallback — `files`) and pass `description` to `FileUpload`; update the JSDoc of the table of widgets; check: `npm run build`, a manual check on `http://localhost:8080` (creating and editing a record, single and multiple field): «Готово» immediately changes the name and the description in the block, «Скасувати» does not.

- [x] 1.3 `ContentNodeForm.tsx`: the `legend` of a multiple field with a limit — `flex items-center gap-2`, the counter in one line with the label (and with the required mark); `npm run build`, a manual check of a field with a limit (string and `image`, required and optional).

## 2. Tests

- [x] 2.1 e2e `admin-content-nodes.spec.js`: the description above the size in a saved file, the update of the block after «Готово», disappearance when cleared, no change after «Скасувати», the row of a multiple field, no description line without a description, the counter and the label in one line (the same vertical position, `boundingBox`); run `npm run test:e2e -- e2e/admin-content-nodes.spec.js` and `e2e/admin-file-upload.spec.js` (the shared `FileUpload` changed, the dependent specs per the table "Shared code → specs"); check: the specs pass. There are no PHP changes, so Feature tests are not needed.

## 3. Documentation

- [x] 3.1 `docs/admin-content-nodes.md` (the section «Поля `image` і `file`»: the block shows the name, the description and the size from the state of the form), `docs/file-storage.md` (the prop `description`; the section on the limit counter in `docs/admin-content-nodes.md`), `docs/e2e-testing.md` (the scenarios `admin-content-nodes`; check the tables "Область → спек" and "Спільний код → спеки" — there are no new specs); check: the links work.

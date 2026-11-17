## 1. Backend

- [x] 1.1 Add `author` to `ContentType::LIST_SERVICE_KEYS` (not to `LIST_SORT_SERVICE_KEYS`), to `NodeListSettings::SERVICE_COLUMNS` (`['Author', 'text']`) and to `NodeListSettings::DEFAULT_SERVICE_COLUMNS` (last); add `author` to `ContentField::RESERVED_KEYS`; update the docblocks. Add the translation key "Author" check in `lang/en.json` and `lang/uk.json` (it exists for the form; reuse it).
- [x] 1.2 In `ContentNodeController`, return `author: {name}` or `null` in `row()` and load the page's authors with one query only when `author` is a visible column (`load('author:id,name')`); keep the column non-sortable in `column()`/`tableColumns()`. Add JSDoc-style docblocks in English.
- [x] 1.3 Update the PHP tests: `ContentNodeIndexTest` (author in the default columns, author column with name, empty cell for a missing author, not sortable, one query for authors), the list settings tests (key accepted in `visible`, default composition, `formOptions` offers it) and the reserved key tests (`author` rejected for a new field). Run `docker compose exec app php artisan test --compact --filter=ContentNodeIndexTest`, then `--filter=NodeListSettings` and `--filter=ContentFieldCreateTest` (and `--filter=ContentTypeEditTest` if it covers list settings).

- [x] 1.4 Add the data migration `append_author_to_list_visible` that appends `author` to every non-empty own `visible` list that has more than `title` and no `author`, with `AppendAuthorToListVisibleMigrationTest`. Document it in `docs/content-model.md`.

## 2. Frontend

- [x] 2.1 In `Pages/Admin/ContentNodes/Index.tsx` render the service column `author` with `UserAvatar` and the name (nothing for `null`); extend the row type and JSDoc (`@example`). Confirm the "Node list" section of the type form lists "Author" from `listOptions` with no code change; adjust only if it does not. Build on the host: `npm run build`.

## 3. E2E

- [x] 3.1 Update `e2e/admin-content-nodes.spec.js` where the default columns are asserted (add the author column; add a test that the author's name is shown and that "Columns" can hide it) and `e2e/admin-content-types.spec.js` (the "Author" option in the "Node list" section). Find tests with `npx playwright test --list`. Run both specs with `-g` limited to the changed tests: `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "<fragment>"`.

## 4. Documentation

- [x] 4.1 Update `docs/admin-content-nodes.md` (the column list, default composition, rendering, not sortable), `docs/admin-content-types.md` (the "Node list" section's column list and the default), `docs/content-querying.md` (default list settings and `LIST_SERVICE_KEYS`) and `docs/content-model.md` and `docs/admin-content-fields.md` (the reserved key `author`).
- [x] 4.2 Run `openspec validate node-list-author-column --strict` and fix findings.

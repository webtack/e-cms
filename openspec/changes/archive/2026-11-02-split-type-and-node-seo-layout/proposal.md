## Why

The panels «Розділ» ("Section") and «SEO» stand in a long column under the settings form of a content type, and the «SEO» of a record takes the main column of the form together with the required fields. They are secondary and distract from the main thing: it is better to separate them into their own tabs of the type and to collapse them in the right column of the record.

## What Changes

- The panel «Розділ» of a content type moves from the settings page to its own tab of the menu of the type («Розділ»), with its own form and save button.
- The panel «SEO» of a content type likewise gets its own tab («SEO»).
- The tabs «Розділ» and «SEO» exist only for the types of the kind `content`; for `page` and `block` the menu stays as it was («Налаштування», «Поля», «Записи»). The settings page of a type no longer contains panels of values.
- In the form of a record «SEO» leaves the main column and moves to the right column under «Деталі» ("Details"), as an accordion: each section («Деталі», «SEO») is a separate element in its own frame, and the whole accordion stays in the shared frame of the side column; only one section is open at a time (opening another one automatically closes the current one), initially «Деталі» is open. The block «Деталі» remains available as before.
- The fields of the panels (`meta_*`, `section_*`) disappear from the list of fields of the type (the tab «Поля», all kinds): the system configures them, and the values are edited only in the tabs «Розділ»/«SEO» and in the section «SEO» of the record. The field `title` remains. Editing, saving and deleting such a field respond with 404; the change of order accepts only the shown fields.
- On mobile (below `lg`) the content of the right column likewise opens in the bottom sheet, with the same accordion.
- A validation error in the collapsed «SEO» expands it and closes the open section «Деталі», so that the error is visible; the indicator of errors on the button of the sheet also takes the fields «SEO» into account.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-types`: the requirement about the panels «Розділ» and «SEO» on the settings page is replaced by the requirement about separate tabs of the menu of the type; the composition of the menu of the type changes.
- `admin-content-fields`: the list of fields of the type does not show the fields of the panels; their edit/save/delete pages — 404; the change of order works only with the shown fields.
- `admin-content-nodes`: the requirement about the panel «SEO» in the form of a record changes — the panel is in the right column (the accordion «Деталі» / «SEO»), not among the main fields; the behaviour of the sheet on mobile changes.

## Impact

- Frontend: `resources/js/Components/Admin/ContentTypes/ContentTypeNav.tsx`, `TypeValuesPanels.tsx` (the form of one panel), `Pages/Admin/ContentTypes/Edit.tsx`, two new pages (`Section.tsx`, `Seo.tsx`), `Components/Admin/ContentNodes/ContentNodeForm.tsx`, `NodeServicePanel.tsx`, `NodeFieldsPanel.tsx`; a new `Components/ui/accordion.tsx` (on `radix-ui`, already in the dependencies).
- Backend: `ContentTypeController` (two new GET actions, `edit` no longer gives the props of the panels), `routes/web.php` (two routes only for `content-types`), `ContentTypeValuesController` (saving the values of one panel), `ContentFieldController` (`index`, `edit`, `update`, `destroy`, `reorder`), `ReorderContentFieldsRequest`.
- Tests: `tests/Feature/Admin/ContentTypeValuesTest.php`, `ContentTypeEditTest.php`, the tests of the fields of a type (`tests/Feature/Admin/ContentField*`), `e2e/admin-content-fields.spec.js`, `e2e/admin-content-types.spec.js`, `e2e/admin-content-nodes.spec.js`.
- Documentation (to update): `docs/admin-content-types.md`, `docs/admin-content-nodes.md`, `docs/admin-content-fields.md`, `docs/e2e-testing.md` (if needed).
- There are no changes in the data and the model of the content.

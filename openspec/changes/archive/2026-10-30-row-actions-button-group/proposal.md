## Why

To open the edit form of a content type or a field, right now you need to double-click: open the menu «⋮» and select "Edit". The most frequent action should be moved to a visible button in the row, and the menu left for the rest of the actions.

## What Changes

- The row actions of the content types table — a `ButtonGroup`: the main button «Налаштування» (a link to the edit form of the type) and next to it the button «⋮», which opens the drop-down menu without changes («Редагувати», «Поля», «Видалити»).
- The row actions of the fields table — by analogy: the main button «Редагувати» (a link to the edit form of the field) and «⋮» with the menu without changes («Редагувати», «Видалити»).
- The UI primitive `ButtonGroup` (shadcn) is added, which `Components/ui` does not have yet.
- The accessible name of the menu button «Actions» does not change.

Assumption (recorded, because the request does not imply otherwise): the menu stays "as it is", so "Редагувати" duplicates the main button; the label of the main button of types is «Налаштування» (as in the section menu of the type), of fields — «Редагувати».

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-types`: the requirement «Меню дій рядка» — the main button «Налаштування» is added.
- `admin-content-fields`: the requirement «Меню дій рядка поля» — the main button «Редагувати» is added.

## Impact

- Code: `resources/js/Components/ui/button-group.tsx` (new), `Components/Admin/ContentTypes/ContentTypeRowActions.tsx`, `Components/Admin/ContentFields/ContentFieldRowActions.tsx`.
- Translations: `lang/uk.json` — the key «Settings» already exists; there are no new ones.
- e2e: `e2e/admin-content-types.spec.js`, `e2e/admin-content-fields.spec.js` — the scenarios that click «Actions» must pass without changes; a check of the main button is added.
- Documentation: update `docs/admin-content-types.md`, `docs/admin-content-fields.md` and the mention of the menu «⋮» in `docs/admin-data-table.md`.

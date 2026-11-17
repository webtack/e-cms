## Why

Types of the same shape (for example several news-like or institution-like types) are built from the same set of fields today by re-creating every field by hand. A clone function lets an editor copy an existing type together with its field definitions and only then adjust the differences.

## What Changes

- Add a "Clone" action to the row menu of the types table in all three sections (content, page, block).
- Cloning opens a dialog with three settings of the new type: `name`, `key` and `slug` (`slug` only for content types; page and block types have none). The dialog also has a parent type select, pre-filled with the parent of the source (it can be changed or cleared). `name`, `key` and `slug` start empty. The dialog is validated by the same rules as the create form.
- The new type has the same kind as the source (the kind cannot be changed). Its description is not copied. Its fields are copied from the source type: key, name, description, value type, multiplicity, `max_items`, requiredness, translatability, label visibility, order and field `settings`. System fields are created by the model as for any new type, and their editable attributes are copied as well.
- The type `settings` are copied too: the node list settings and the public display settings (they reference fields by key, and the fields keep their keys).
- Not copied: nodes, field values, the type's own values (SEO tab), description, role levels, favorites.
- After success the user is taken to the fields page of the new type with a `success` message.

## Capabilities

### New Capabilities

### Modified Capabilities

- `admin-content-types`: the row menu gets a "Clone" item and a new requirement describes the clone dialog, its validation and the copy result.

## Impact

- Backend: new route `POST /admin/{section}/{key}/clone` (`{section}` = `content-types`, `page-types`, `block-types`) behind `can:types`, a new request class, a cloning service and a controller action.
- Frontend: `ContentTypeRowActions.tsx`, a new clone dialog component, translations (en/uk).
- Tests: PHP Feature tests of cloning; e2e `admin-content-types` (and the page/block type specs if their row menus are asserted).
- Docs: `docs/admin-content-types.md` (row actions, new "Cloning" section), `docs/admin-page-types.md` and `docs/admin-block-types.md` (menu item), `docs/e2e-testing.md` only if a new spec is added.

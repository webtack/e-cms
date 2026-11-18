# Proposal

## Why

The management of a content type is spread across several pages (the type edit page, the list of fields, the creation/editing of a field), and moving between them is possible only through the list of types or separate links. The administrator needs a single horizontal section menu to switch quickly between the pages of one type.

## What Changes

- A new shared horizontal section menu panel for a content type with the items "Settings" (the type edit form) and "Fields" (the list of fields).
- The menu is shown on the pages: type edit, list of fields, field creation, field editing. The active item is highlighted; on the field creation and edit pages the active item is "Fields".
- The creation page of a type has no menu (the type does not exist yet).
- The link "← Content types" from the fields page is moved to a common block above the menu on all pages of the section.

## Capabilities

### New Capabilities

### Modified Capabilities
- `admin-content-types`: a requirement on the horizontal section menu of a type is added to the pages for managing the type.

## Impact

- Frontend: a new component in `resources/js/Components/Admin/ContentTypes/`, changes to the pages `Pages/Admin/ContentTypes/Edit.tsx`, `Pages/Admin/ContentFields/{Index,Create,Edit}.tsx`; the backend does not change (all pages already receive `type`/key).
- Translations `lang/uk.json`, `lang/en.json` (new rows "Settings"/"Fields", if missing).
- e2e: `e2e/admin-content-types.spec.js`, `e2e/admin-content-fields.spec.js`.
- Docs: `docs/admin-content-types.md`, `docs/admin-content-fields.md`.

# Proposal

## Why

The content type model (`ContentType`) already exists, but in the admin the "Content Types" item is only a layout without a page. The administrator cannot see which types exist in the system, their kinds, the hierarchy and the volume of data. The data table (`DataTable`) is ready, but it cannot show actions on a row or buttons in the toolbar, and these are exactly what the content type management section needs. We build a full server-side table of types with a skeleton of actions, so that the next changes (the create/edit form, the fields page, deletion) only connect the behaviour.

## What Changes

- A new page `/admin/content-types` with a `DataTable` in **server mode**: search, filters, sorting, pagination, column visibility and the state in the address — everything is performed by the server.
- Columns: name, key, kind (`kind`), parent type, number of fields, number of nodes, description (hidden by default).
- `DataTable` gets two optional extensions: a **row action column** (the "⋮" menu) and a **slot for actions in the toolbar** (a button above the table).
- On the page: a "Create" button and the row "⋮" menu with the items "Edit", "Fields", "Delete". **UI skeleton only:** the button and the items are displayed but inactive (there are no forms, no fields page and no deletion); separate changes connect them.
- The "Content Types" item in the sidebar navigation stops being a layout item and leads to the new page.
- New translation rows uk/en.

## Capabilities

### New Capabilities
- `admin-content-types`: the content types table page — columns, server-side search/filters/sorting/pagination, the "Create" button and the row action menu (skeleton), the empty state.

### Modified Capabilities
- `admin-data-table`: support for optional row actions (a menu) and a slot for actions in the toolbar.
- `admin-shell`: the "Content Types" item becomes working (it opens the page), not a layout; the other items remain layouts.

## Impact

- Code: a new `App\Http\Controllers\Admin\ContentTypeController` (index), the route `admin.content-types.index` in `routes/web.php`; the page `resources/js/pages/Admin/ContentTypes/Index.tsx`; changes in `resources/js/components/Admin/DataTable/` (`DataTable.tsx`, `DataTableRow.tsx`, `DataTableToolbar.tsx`, `types.ts`) and a new row menu component; `navigation.ts` (remove `mock`); `lang/uk.json`, `lang/en.json`.
- Tests: a Feature test of the controller (`tests/Feature/Admin/`), e2e `e2e/admin-content-types.spec.js`, an extension of the e2e of the data table (the demo page with actions), a check of the existing shell e2e.
- Docs: create `docs/admin-content-types.md`; update `docs/admin-data-table.md` (row actions, the toolbar slot), `docs/admin-shell.md` (the item is no longer a layout), the index `docs/README.md`.
- Dependencies and migrations: no changes.

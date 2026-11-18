# Proposal

## Why

Content types are already created, edited and deleted from the admin, but the fields of a type (`content_fields`) can be added only in code. Without field management a content type in the admin is empty: the "Fields" item in the type's row menu remains an inactive skeleton, and nodes with their own data cannot be created.

## What Changes

- A new page `/admin/content-types/{key}/fields` with the list of the type's fields in the client `DataTable` (all fields are passed to the page, search/sorting/pagination in the browser, **without filters**), ordered by `weight`.
- Creating a field (`/fields/create`), editing (`/fields/{field}/edit`) and deleting with confirmation in a dialog — for types of the `content` kind.
- A field form: key (immutable), name, value type from the registry, the flags "multiple", "required", "translatable", weight. The rules of the model (`ContentField`) are mirrored in the `FormRequest`s: errors are shown next to the fields.
- Changing the order of fields by dragging the table rows: a new optional `reorder` capability in `DataTable` (a handle in the first cell, keyboard accessibility) and saving the order on the server (the weights are rewritten sequentially). Dragging is available only when the whole list is visible in its original order.
- Protection of the system field `title`: it is not deleted, and its type, multiplicity and requiredness are blocked in the form.
- The value type of a field that already has values is blocked in the form (a rule of the model).
- **A field that has values cannot be deleted** (like a type with nodes): the dialog shows the reason without a confirmation, and the server checks the ban itself.
- The "Fields" item in the content type's row menu becomes an active link to the fields page of this type.

## Capabilities

### New Capabilities
- `admin-content-fields`: the list page of the fields of a content type (a client table without filters), the create/edit forms, deletion with confirmation, changing the order by dragging, the limits of the system field and of fields with values.

### Modified Capabilities
- `admin-content-types`: the "Fields" item of the type's row menu becomes active and leads to the fields page of the type (previously an inactive skeleton).
- `admin-data-table`: the table gets an optional capability of dragging rows to change the order (`reorder`); without it the behaviour does not change.

## Impact

- **Backend:** a new `App\Http\Controllers\Admin\ContentFieldController` (including the reorder action), `StoreContentFieldRequest`/`UpdateContentFieldRequest`/`ReorderContentFieldsRequest`, routes in `routes/web.php` (nested under `content-types/{contentType}`, field binding by the pair type + key). There are no migrations or model changes.
- **Frontend:** pages `Pages/Admin/ContentFields/{Index,Create,Edit}.tsx`, `Components/Admin/ContentFields/{ContentFieldForm,ContentFieldRowActions}.tsx`, the `reorder` prop in `Components/Admin/DataTable/*`, activation of the item in `ContentTypeRowActions.tsx`, breadcrumbs in `navigation.ts`, translations in `lang/uk.json` and `lang/en.json`.
- **Dependencies:** `@dnd-kit/core` and `@dnd-kit/sortable` (npm, on the host).
- **Tests:** Pest feature tests of the controller (list, create, edit, delete, order, limits), Playwright e2e in `e2e/admin-content-fields.spec.js`, client checks of `reorder` in the e2e of the demo table.
- **Documentation (`docs/`):** create `docs/admin-content-fields.md`; update `docs/admin-data-table.md` (the `reorder` prop, `rowHandle`), `docs/admin-content-types.md` (the row menu), `docs/content-model.md` (a link to the admin page of fields), `docs/README.md` (index).

## Non-Goals

- Editing the `settings` of a field (JSON).
- Fields for types of the `page` and `block` kinds.
- Changing the `ContentField` model or the registry of field types.
- Dragging in server mode of `DataTable` and between pagination pages.

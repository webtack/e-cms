## Why

The "Content Types" page already shows a table, but types can be created, changed and deleted only in code: the "Create" button and the row menu items are an inactive skeleton. In addition, a type of the `content` kind must open on the site as a separate public page with a list of its nodes, and for that the type has no public path.

## What Changes

- A create form for a type `/admin/content-types/create` (name, key, slug, parent type, description) and saving via `POST /admin/content-types`.
- An edit form `/admin/content-types/{key}/edit` and saving via `PUT /admin/content-types/{key}`: the name, slug, parent type and description change; the key is shown, but not edited (it is immutable).
- Deletion of a type `DELETE /admin/content-types/{key}` with a confirmation dialog. Deletion is forbidden if the type has nodes or child types.
- A new type field `slug` — the public path of the site that opens the list of nodes of this type. Optional, unique among types (case-insensitive), the format as for the node slug; **available only for the `content` kind** (for `page` and `block` it must be empty). A new nullable column `content_types.slug`.
- The "Content Types" page becomes a page of the `content` kind only: the list shows only such types, the kind column and the "Kind" filter are removed, the forms have no kind field (the type is always `content`). The `page` and `block` types will get their own pages in separate changes.
- A table without filters: the filter button disappears, filters in the address are ignored. Search is performed by name, description, slug and key; sorting remains.
- A `slug` column appears in the table; the description is no longer a separate column, but is shown on a second line under the name in the "Name" column.
- The "Create" button and the row menu items "Edit" and "Delete" become working; "Fields" remains an inactive skeleton.
- The parent type list in the forms contains only types of the `content` kind; in the edit form the type itself and its descendants are excluded from it.

Out of scope: the fields page, the public site route by `slug` itself (only storage of the path), forms for `page`/`block`, reservation of service paths (`admin`, `login`, etc.), changing `key` and `kind` of an existing type.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-types`: the page is limited to the `content` kind, filters are removed (the requirement "Search, filters and sorting" is replaced by "Search and sorting"), the kind and description columns (the description is under the name), a `slug` column is added, the "Create" button and the row menu become working, the create and edit forms and deletion appear.
- `content-model`: a public `slug` of a content type is added (format, uniqueness, only for the `content` kind).

## Impact

- Code: the migration `content_types.slug`; `App\Models\ContentType` (fillable, slug validation); `Admin\ContentTypeController` (`index` with the kind filter, `create`, `store`, `edit`, `update`, `destroy`), `StoreContentTypeRequest`, `UpdateContentTypeRequest`; `routes/web.php`; `ContentTypeFactory`; pages `Admin/ContentTypes/Index.tsx`, `Create.tsx`, `Edit.tsx`, the shared form, `ContentTypeRowActions.tsx`, the deletion confirmation dialog (shadcn `alert-dialog`); `navigation.ts`; `Components/Admin/DataTable` (the table prop `filterable`, disabled by default, enables the filter button; the demo tables pass it explicitly); `lang/uk.json`, `lang/en.json`.
- Tests: Feature (update `ContentTypeIndexTest`, new tests of creation, editing, deletion), model tests of the slug, `e2e/admin-content-types.spec.js` (update and extend).
- Docs: update `docs/admin-content-types.md` (the page is `content` only, forms, deletion, slug, description under the name), `docs/content-model.md` (a section on the type slug), `docs/admin-data-table.md` (the table prop `filterable`), `docs/e2e-testing.md` if needed; there are no new files in docs, the index `docs/README.md` does not change.
- BREAKING (internal): the `kind` and `description` columns disappear from the `index` response and from the table; the `page`/`block` types are no longer shown on this page.

# Design

## Context

The motivation and scope are in [proposal.md](proposal.md), the requirements are in `specs/`. What already exists and what we rely on (all read in the code):

- The backend of nodes is ready: `ContentNodeCreator::create()`, `ContentValueWriter::write()/validate()`, `ContentValueReader::load()`, `ContentNodeQuery::paginate()`.
- The section of types is served by the route loop in `routes/web.php`; `kind` is taken from `defaults('kind')` of the route (the trait `ResolvesTypeSection`), `{contentType}` is bound by `key` within the kind, `{field}` — by the pair (type, `key`).
- The server `DataTable` is fed by `TableRequest::from()` + `TablePage::make($rows, $total, $table)`: the table state is parsed from the address, and the rows the controller takes from its own source. The columns `Column<Row>` are declared on the client (React), the server `TableColumn` — for validating `sort` and `q`.
- `ContentNodeQuery::paginate()` paginates itself and throws `ValidationException` for unknown fields, and a page number out of range returns an empty result.
- The settings of a field (`settings`: `min`, `max`) are not edited in the field forms (they are not in `sharedRules`), so they are set only in code.
- Deleting a field does not clean `settings.list` of the type (`ContentField` blocks only the deletion of a system field), so keys of non-existent fields may remain in `settings.list.visible`.
- Documentation: [content-values.md](../../../docs/content-values.md), [content-querying.md](../../../docs/content-querying.md), [admin-data-table.md](../../../docs/admin-data-table.md), [admin-content-types.md](../../../docs/admin-content-types.md), [admin-content-fields.md](../../../docs/admin-content-fields.md), [e2e-testing.md](../../../docs/e2e-testing.md).

## Goals / Non-Goals

**Goals:**
- One controller and one pair of queries for nodes of `content`, without duplication of the value logic: all validation and writing go through the existing `ContentNodeCreator` / `ContentValueWriter`.
- The form is built from data (the fields of the type), without hard-coded fields.
- The controller does not depend on the kind always being `content` more than necessary: the kind is taken from the route, so that the sections `page`/`block` can be connected by a separate change.

**Non-Goals:**
- Languages, translations, the multilingualism switch (a separate change). Everything is read and written with `lang = default`; the language is not chosen in the form.
- Filters by field values in the node table.
- Editing the `settings` of a field or the `settings.list` of a type in the admin.

## Decisions

### 1. Addresses and binding of a node

The node routes are declared separately from the loop of types in `routes/web.php`, only for `content-types` (`->defaults('kind', 'content')`):

```
GET    /admin/content-types/{contentType}/nodes                  nodes.index
GET    /admin/content-types/{contentType}/nodes/create           nodes.create
POST   /admin/content-types/{contentType}/nodes                  nodes.store
GET    /admin/content-types/{contentType}/nodes/{node}/edit      nodes.edit
PUT    /admin/content-types/{contentType}/nodes/{node}           nodes.update
DELETE /admin/content-types/{contentType}/nodes/{node}           nodes.destroy
```

`Route::bind('node', …)` looks up `ContentNode` by the pair (`content_type = contentType.key`, `uuid`), like `{field}` by the pair (type, `key`): an unknown `uuid` or the `uuid` of a node of another type gives 404. The table row carries the `uuid` as the row identifier (`getRowId`) and for the edit/delete addresses.

`slug` is the public identifier for the frontend of the site (the owner's decision); it is not used in the admin addresses, so its change does not break open forms and links. This purpose of `slug` was not recorded anywhere before, so it is fixed in `content-model` (the delta) and in `docs/content-model.md`.

*Alternative:* addressing by `slug` — more readable, but the slug is changeable (an open second tab would get 404 after the change), and the `uuid` was created precisely as the stable identifier of a node.

### 2. List: `ContentNodeQuery` through `TableRequest`

The controller builds `TableRequest::from($request, $columns)`, where the columns are a server description of the same set that goes to the client:

| Column | Table type | sortable | searchable |
| --- | --- | --- | --- |
| `title` and the visible fields | by the field type (`string`/`text` → `text`, `integer`/`decimal` → `number`, `boolean` → `boolean`, `date`/`datetime` → `date`) | single-valued fields | the decision by `ContentNodeQuery` |
| `slug` | `text` | no | no |

`TableRequest` provides `search`, `sort`/`dir`, `page`, `perPage` without throwing errors. Then the controller calls `ContentNodeQuery::paginate($type, new NodeQueryParams(fields: $visible, search: …, sort: …, page: …, perPage: …))` and wraps the result in `TablePage::make($rows, $total, $table)`.

A page number out of range: `paginate()` returns an empty page, so after the first call the controller compares `page` with `lastPage()` and if needed repeats the query for the last page (`TablePage::make` itself takes `withTotal`, but the rows must match). One extra query only in a rare case.

`$visible` = `settings.list.visible` ∩ the existing fields of the type, with the mandatory `title` in the first place (see Risks). The table row: `uuid` (the row id), `slug`, the key → value for each visible field (multiple — a row with commas, date/time — a string, boolean — a boolean), plus the service `title` for the deletion dialog (this is the value of the field `title`).

*Alternative:* `TablePage::fromCollection` over all nodes — rejected: it does not scale and duplicates the search/sorting that already exist in `ContentNodeQuery`.

### 3. Form: the field data goes to the client, the widget is chosen by the client

The controller passes `fields` (by weight): `key`, `name`, `type`, `is_multiple`, `is_required`, `settings` (only `min`/`max` for the hints of the attributes `min`/`max`/`maxLength`; the server remains the arbiter) and `values` (key → value, multiple — a list). The values are formatted into strings for the HTML elements: `date` → `Y-m-d`, `datetime` → `Y-m-d\TH:i` (the format `datetime-local`), `decimal` is already a string, `boolean` — a boolean. The map "field type → widget" lives on the client in one place (`NodeFieldInput`): `string` → `Input`, `text` → `Textarea`, `integer` → `Input type=number step=1`, `decimal` → `Input inputMode=decimal` (text, so as not to lose precision), `boolean` → `Checkbox`, `date` → `Input type=date`, `datetime` → `Input type=datetime-local`.

A multiple field — `NodeFieldList` on top of the same widget: the buttons "Add" and "Remove item", and reordering of the items. The order of the stored values = `weight` = the index in the list (`ContentValueWriter` writes the list by the index, `ContentValueReader` reads by `weight`), so the backend does not change: the form sends the list in the new order. Reordering is done by dragging the handle via `@dnd-kit/core` + `@dnd-kit/sortable` (already in the project for `DataTable`) with keyboard support (space, arrows), as in the dragging of the table rows. The items in the form state have client identifiers (a stable `id` per item), because the values may repeat and are not suitable as keys; the identifiers are not sent to the server.

A new field type in the registry that is not in the widget map is shown as a plain text field (a fallback), so that an extension of the registry does not break the form.

### 4. Form contract and errors

The form sends `{ slug, values: { <key>: <value|list> } }`. The values are under a separate key `values`, because a field key may coincide with `slug` (the format of the key allows this).

`StoreContentNodeRequest` / `UpdateContentNodeRequest` check only `slug` (optional on creation; the format; uniqueness through `Rule::unique('content_nodes', 'slug')`, on change — with `ignore($node)`, because uniqueness in the model is delegated to the DB index, and a `UniqueConstraintViolationException` on an update would give a 500) and that `values` is an array. The rest of the values are checked by `ContentValueWriter::validate()` (the single source of the rules).

The controller calls `ContentNodeCreator::create()` / `ContentValueWriter::write()` in a `try`; the `ValidationException` from them has keys without a prefix (`title`, `scores.1`), so the controller rethrows it with the prefix `values.` (`slug` stays as is), so that the keys match the nesting in `useForm` and are displayed next to the right fields (`values.scores.1`). The entered data stays in the form, because Inertia returns it back.

*Alternative:* flat fields without `values` — rejected because of the collision with `slug`; a separate validation of each type in a FormRequest — rejected as a duplicate of the registry rules.

### 5. Update

`update` in a transaction: if the `slug` changed — `$node->update(['slug' => …])` (the model checks the format/prefix), then `ContentValueWriter::write($node, $allFieldValues, 'default')` with the values of **all** fields of the form, so that a cleared field loses its value (`''`/`[]` clear). An unchanged slug is not passed to `update`. `uuid` and `content_type` are not touched (the model blocks them anyway). Other languages are not affected, because `write()` replaces only the specified language.

A field that the form did not show (for example, added between opening the form and saving) is not sent and is not changed: only the keys from `values` are updated.

### 6. Deletion

`$node->delete()` in the controller, without a new service: the values are removed by the DB cascade ([content-model.md](../../../docs/content-model.md)). A redirect to the list of the type with `success`. The name of the node in the dialog — from the table row (`title`), the fallback — `slug`.

### 7. Navigation and components

- `ContentTypeNav`: `active` is extended with the value `'nodes'`; the item "Nodes" is added only when `section.kind === 'content'`.
- `ContentTypeRowActions`: in the "⋮" menu the item "Nodes" between "Fields" and "Delete" only for `content`; the column `nodes_count` in `Pages/Admin/ContentTypes/Index.tsx` gets a `cell` with a `Link` only for `content`.
- `navigation.ts`: `patternTitles` for `/admin/content-types/{key}/nodes` ("Nodes"), `/nodes/create` ("Create node"), `/nodes/{uuid}/edit` ("Edit node"); the first segment `content-types` gives an intermediate item "Content Types" (by `isActive`).
- New files: `Pages/Admin/ContentNodes/{Index,Create,Edit}.tsx`, `Components/Admin/ContentNodes/{ContentNodeForm,NodeFieldInput,NodeFieldList,ContentNodeRowActions}.tsx`.
- Translations: rows in `lang/uk.json` and `lang/en.json`.

## Risks / Trade-offs

- [Stale keys in `settings.list.visible` after deleting a field → `ContentNodeQuery` throws `ValidationException`] → the controller intersects `visible`/`search`/`sort` with the existing fields, and always adds `title`; cover with a Feature test.
- [The node name for the dialog is the value of `title`, which is not necessarily among the visible fields] → `title` is always included in `$visible` (the first column).
- [A change of slug breaks external links to it] → in the specification it is an allowed explicit action; the `uuid` remains stable. No warning in the UI is added (deferred).
- [A boolean field without a value cannot be distinguished from "No" in a form with a checkbox] → an uncleared checkbox is stored as "No". Acceptable for the first version; the need for three states is a separate decision.
- [Date and time: time zones] → `datetime` is stored as `Y-m-d H:i:s` without a zone ([DateTimeType]); the form passes the value "as is" without converting zones. The behaviour matches the backend, the UI does not change it.
- [A page number out of range → an extra query] → only in the extreme case.

## Migration Plan

There are no DB migrations: no schema changes are needed. The deployment is a regular frontend build (`npm run build`). Rollback — remove the routes and pages; the data is not affected.

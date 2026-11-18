# Proposal

## Why

The backend of content nodes already exists (`ContentNodeCreator`, `ContentValueWriter`, `ContentValueReader`, `ContentNodeQuery`, 7 field types), but the admin has no page for working with them: types and fields can be created, but the records themselves (`content_nodes`) cannot. Without this, types of `kind = content` cannot be filled with content.

## What Changes

The admin for nodes of types with the kind `content` only in the language `default`. Nodes live under their type within the "Content Types" section (the owner's decision: option A).

- Routes `/admin/content-types/{contentType}/nodes` (the list), `/create`, `/{node}/edit`, `PUT`, `DELETE`. A node is addressed by `uuid` (an immutable identifier); `slug` is the public identifier for the frontend of the site and is not used in the admin addresses; a node of another type or of another kind → 404 (as for `{field}`).
- `ContentNodeController` + `StoreContentNodeRequest` / `UpdateContentNodeRequest`: the rules of the values are built by `FieldTypeRegistry` from `content_fields`, writing — `ContentNodeCreator::create()` and `ContentValueWriter::write()`.
- The list: a DataTable in server mode. The columns — from `settings.list.visible` of the type (by default `title`), search and sorting through `ContentNodeQuery` / `NodeQueryParams`. An adapter between `TableRequest` and `NodeQueryParams` is needed, because `TablePage::fromCollection` works with an in-memory collection.
- The node form: dynamic widgets by field type (`string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`), a list editor for `is_multiple`, the requiredness and the `settings` (min/max) of the field; validation errors by keys (`tags.2`).
- Slug: optional on creation (empty → automatic generation from `title`, explicit → checking the format and uniqueness). The slug is shown and edited in the edit form (the default decision; a change of slug does not change the `uuid`).
- Deleting a node with a confirmation dialog.
- The link with types: the action "Nodes" in the type row menu, the third item "Nodes" in `ContentTypeNav` (only for `kind = content`), the counter `nodes_count` becomes a link to the list; breadcrumbs and uk translations.
- Documentation, Feature tests, a new e2e spec.

## Non-goals

- **Translations** (language tabs, the switch `content.multilingual`, blocking of non-translatable fields) — the next separate change. Here only `default`.
- Nodes of the kinds `page` and `block` (their sections are separate; the controller is designed so that `kind` does not get in the way later).
- Filters by field values in the table (the operators `between`, `contains`, etc.): the backend supports them, the UI — later.
- The public site (`content_types.slug` as a public path).

## Capabilities

### New Capabilities
- `admin-content-nodes`: the list, creation, editing and deletion of nodes of the type `content` in the admin (language `default`).

### Modified Capabilities
- `content-model`: the purpose of the node `slug` is recorded (the public/frontend identifier), and that internal and admin references to a node go through `uuid`; previously this was not recorded anywhere.
- `admin-content-types`: the row menu and the horizontal section menu of a type get the item "Nodes"; the node counter in the table becomes a link.

## Impact

- Code: `routes/web.php` (the route bind `{node}` and the node routes), `app/Http/Controllers/Admin/ContentNodeController.php`, `app/Http/Requests/Admin/*ContentNodeRequest.php`, the table adapter, `resources/js/Pages/Admin/ContentNodes/{Index,Create,Edit}.tsx`, `Components/Admin/ContentNodes/*` (the form, the field widgets, row actions), `ContentTypeNav.tsx`, `ContentTypeRowActions.tsx`, `navigation.ts`, uk translations.
- Docs: create `docs/admin-content-nodes.md`; update `docs/README.md` (the index), `docs/content-model.md` (the purpose of `slug` and `uuid`), `docs/admin-content-types.md` (the item "Nodes"), `docs/e2e-testing.md` (the spec in "Area → spec" and "Shared code → specs").
- e2e: a new `e2e/admin-content-nodes.spec.js`; the shared code `ContentTypeNav` and row actions are touched → also `admin-content-types`.

## Research notes (for design / tasks)

- The existing backend: `ContentNodeCreator::create($type, $values, ?$slug, $lang, ?$uuid)`; `ContentValueWriter::write($node, $values, $lang)` replaces only the passed fields; `ContentNodeQuery::paginate()` takes the visible fields from `settings.list`; `ContentValueReader::load()` — one query per value table.
- There is no separate path for deleting a node (only the DB cascade) — `$node->delete()` in the controller is enough; decide whether a service method is needed.
- Changing the slug of an existing node is allowed by the model (`ContentNode::assertValidSlug`); uniqueness on update is delegated to the DB index, so the FormRequest checks it itself.
- In `ContentTypeController::destroy` a type with nodes is not deleted — after this change the message "delete the nodes first" becomes actionable.
- Established: the server `DataTable` receives the columns from the props of the page, the rows — through `TablePage::make()`; see design.md.
- The next change (translations): language tabs, the switch `content.multilingual` in the UI (now only `AppSettings`), `ContentLanguages::isMultilingual()`.

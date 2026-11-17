# Block types in the admin panel

The `/admin/block-types` section manages content types of kind `block` and their fields. Like the page types section, it has no own controllers or React pages: these are the same `ContentTypeController`, `ContentFieldController`, form requests, and pages `Pages/Admin/ContentTypes/*`, `ContentFields/*`, and the route defines the kind. The kind mechanism (the `kind` value in the route, `Route::bind`, the `ResolvesTypeSection` trait, the `section` prop) is described in [admin-page-types.md](admin-page-types.md#the-route-defines-the-kind); the type and field pages — in [admin-content-types.md](admin-content-types.md) and [admin-content-fields.md](admin-content-fields.md); the model — in [content-model.md](content-model.md). Access — `auth` + `verified` and the role right `types`; a guest is redirected to `/login`. See [access control by roles](access-control.md).

## Routes

The names are `admin.block-types.*`; the parameter `{contentType}` is the type's `key`. After a successful `update` of a type or a field, the user stays on the form (`back()`) with a `success` message, as in [page types](admin-page-types.md). The set is identical to `admin.page-types.*` (the table is in [admin-page-types.md](admin-page-types.md#routes)), with the prefix `/admin/block-types`: `index`, `create`, `store`, `edit`, `update`, `destroy`, `fields.index`, `fields.create`, `fields.store`, `fields.reorder`, `fields.edit`, `fields.update`, `fields.destroy`.

The nodes of blocks live under the type: `/admin/block-types/{key}/nodes…`, names `admin.block-types.nodes.*` — the same six routes as for content types; in detail in [admin-content-nodes.md](admin-content-nodes.md). A type key of another kind in `/admin/block-types/**` (including `…/nodes**`), as well as a key of a type `block` in `/admin/content-types/**` and `/admin/page-types/**`, gives 404 on all routes.

## Differences from content types

- There is no slug: neither a table column nor a form field (the model forbids a slug for `block`).
- The create form rejects a reserved key (`content`, `pages`, `blocks`, `fields`, `menus`, `errors`) with an error next to the key field, as for content types: [content-model.md](content-model.md#reserved-type-keys).
- The parent type can only be a type of kind `block`.
- `kind` is always `block`; the system field `title` is added by the model.
- There are no "Section" and "SEO" panels either in the type settings or in the node form: a block type has only the system field `title`. The "Display" tab (`/admin/block-types/{key}/display`) is also here — with the single "Public display" block (the page fields), without section values ([admin-content-types.md](admin-content-types.md#display-tab-all-kinds)).
- Deletion, fields, and the section menu "Settings" / "Display" / "Fields" / "Nodes" work the same way as for `page`. Cloning: [admin-content-types.md](admin-content-types.md#cloning). The row menu has "Edit", "Display", "Fields", "Nodes", "Clone", "Delete" (no "SEO", since a block type has no SEO tab).

Placing blocks on pages is not covered by this section. Block nodes have slugs with the prefix `block-`: an empty slug is generated automatically, and the form shows the prefix as a separate read-only addon ([admin-content-nodes.md](admin-content-nodes.md#slug-by-kind-and-the-block-prefix)).

## Frontend

`sectionLabels('block')` (`Components/Admin/ContentTypes/section.ts`) returns the translation keys "Block Types", "Create block type", "Edit block type", "Delete block type", "Block type sections", and the deletion confirmation; the translations are in `lang/*.json`. The table has the `tableId` `block-types` (`data-testid="data-table-block-types"`). The type pages have no title of their own: the heading is the name of the type, shown by `ContentTypeNav` above the tabs, and there is no "Preview" link or row-menu item (`typePublicUrl` is only for kind `content`). The section menu of the type and the row menu have the "Nodes" item, and the node count in the table is a link to their list; the breadcrumbs of nodes are "Admin → Block Types → Nodes / Create node / Edit node". The item "Block Types" (icon `Blocks`) is placed in `navigation.ts` after "Page Types" ([admin-shell.md](admin-shell.md)).

## Tests

`tests/Feature/Admin/BlockTypeTest.php` and `e2e/admin-block-types.spec.js` (together with nodes: the menus, generation of `block-…`, the slug prefix in the form); block nodes — also `ContentNodeIndexTest` and `ContentNodeWriteTest`. Run:

```bash
docker compose exec app php artisan test --filter=BlockType
npm run test:e2e -- e2e/admin-block-types.spec.js
```

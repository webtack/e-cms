# Page types in the admin panel

The `/admin/page-types` section manages content types of kind `page` and their fields. It has no separate controllers or React pages: these are the same `ContentTypeController`, `ContentFieldController`, form requests, and pages `Pages/Admin/ContentTypes/*`, `ContentFields/*` as for kind `content` ([admin-content-types.md](admin-content-types.md), [admin-content-fields.md](admin-content-fields.md)); the route defines the kind. The model is described in [content-model.md](content-model.md). Access — `auth` + `verified` and the role right `types`; a guest is redirected to `/login`. See [access control by roles](access-control.md).

## Routes

The names are `admin.page-types.*`; the parameter `{contentType}` is the type's `key`. The set is identical to `admin.content-types.*`:

| Method and address | Name |
| --- | --- |
| `GET /admin/page-types` | `index` |
| `GET /admin/page-types/create`, `POST /admin/page-types` | `create`, `store` |
| `GET /admin/page-types/{key}/edit`, `PUT /admin/page-types/{key}` | `edit`, `update` |
| `DELETE /admin/page-types/{key}` | `destroy` |
| `GET /admin/page-types/{key}/fields`, `…/fields/create`, `POST …/fields` | `fields.index`, `fields.create`, `fields.store` |
| `PUT …/fields/order` | `fields.reorder` |
| `GET …/fields/{field}/edit`, `PUT`/`DELETE …/fields/{field}` | `fields.edit`, `fields.update`, `fields.destroy` |

The nodes of pages live under the type: `/admin/page-types/{key}/nodes…`, names `admin.page-types.nodes.*` — the same six routes as for content types; in detail in [admin-content-nodes.md](admin-content-nodes.md). The placeholder item "Pages" (`/admin/pages`) is not related to this section.

## The route defines the kind

`routes/web.php` registers all sets in one loop `['content-types' => 'content', 'page-types' => 'page', 'block-types' => 'block']`; each route gets a default `kind` value. Further:

- `Route::bind('contentType')` selects `ContentType::ofKind($route->defaults['kind'])`, so a type key of another kind (in particular `content` or `block` in `/admin/page-types/**` and `page` in `/admin/content-types/**`; the block types section works the same way, see [admin-block-types.md](admin-block-types.md)) gives 404 on all routes, including fields.
- The controllers take the kind through the trait `Concerns\ResolvesTypeSection`: `kind($request)` for querying, creation (`'kind' => $kind`), and `parentOptions`; `routeName($request, 'index')` for redirects into their own section; `section($request)` for the Inertia prop `section` = `{ kind, basePath }`.
- `StoreContentTypeRequest::sharedRules($type, $kind)` filters `parent_type` by kind and adds the `slug` rule only for `content`; so for `page`, `slug` and `kind` from the request are dropped from `validated()`. `UpdateContentTypeRequest` takes the kind from the type of the route.

## Differences from content types

- After a successful `update` (of a type and of a type field), the user stays on the edit form (`back()`) with a `success` message, as for content types; creation and deletion lead to the section's list.

- There is no slug: neither a table column nor a form field (the model forbids a slug for `page`).
- The slugs of pages in this section cannot match the slug of a `content` type or a reserved path: an error next to the slug field in the node form ([admin-content-nodes.md](admin-content-nodes.md#slug-by-kind-and-the-block-prefix)).
- The create form rejects a reserved key (`content`, `pages`, `blocks`, `fields`, `menus`, `errors`) with an error next to the key field, as for content types: [content-model.md](content-model.md#reserved-type-keys).
- The parent type can only be a type of kind `page`; the type itself and its descendants are not offered when editing.
- `kind` is always `page`; the system field `title` is added by the model, as for `content`.
- The settings page has neither public settings nor type values. The "Display" tab (`/admin/page-types/{key}/display`) contains only the "Public display" block with the page fields ([admin-content-types.md](admin-content-types.md#display-tab-all-kinds)). The "SEO" tab (`/admin/page-types/{key}/seo`) is the same as for `content`: the values of `meta_title`, `meta_description`, `meta_image` **of the type itself** (`PUT /admin/page-types/{key}/values`); `section_*` values are not created for a page type and do not belong to it. The system fields `meta_title`, `meta_description`, `meta_image` of the type are created by the model together with `title`; the owner of their values is both the node and the type. On the "SEO" tab, below the values, there is also the "Meta data" block (saved by the same `PUT /admin/page-types/{key}/values` and the single "Save" button of the tab): [admin-content-types.md](admin-content-types.md#meta-data-block-on-the-seo-tab).
- The "Sitemap" tab (`/admin/page-types/{key}/sitemap`) holds the sitemap settings of the type's pages: only the "Content" checkboxes (a page type has no list page), see [admin-content-types.md](admin-content-types.md#sitemap-tab-content-and-page) and [type-sitemap.md](type-sitemap.md).
- The behavior of deletion (prohibited with nodes and child types), of fields (the system `title`, a field with values, drag-and-drop ordering), and the section menu "Settings" / "Display" / "SEO" / "Sitemap" / "Fields" / "Nodes" is the same as for `content`.

## Frontend

The prop `section` is received by the pages of types and fields and by the components `ContentTypeForm`, `ContentTypeNav`, `ContentTypeRowActions`, `ContentFieldForm`, `ContentFieldRowActions`; addresses are built from `section.basePath`, and the slug is shown only when `section.kind === 'content'`. The type pages (edit, Display, SEO, fields, nodes) have no title of their own: the heading is the name of the type, shown by `ContentTypeNav` above the tabs; the page section never has the "Preview" link, because `typePublicUrl` returns an address only for kind `content`. The type `TypeSection` and `sectionLabels(kind)` are in `Components/Admin/ContentTypes/section.ts`; `sectionLabels` returns translation keys ("Page Types", "Create page type", "Edit page type", "Delete page type", "Page type sections", the deletion confirmation) — the translations are in `lang/*.json`. The server messages (`Content type created.` and so on) are shared by both kinds. The table has the `tableId` `page-types` (`data-testid="data-table-page-types"`).

The section menu of the type and the row menu share one list of destinations (`typeSectionItems`): the row menu has "Edit", "Display", "SEO", "Sitemap", "Fields", "Nodes", "Clone", "Delete" (cloning: [admin-content-types.md](admin-content-types.md#cloning)). Both have the "Nodes" item (`…/nodes`), and the node count in the table is a link to their list, the same as in the content types section; the breadcrumbs of nodes are "Admin → Page Types → Nodes / Create node / Edit node".

The item "Page Types" (`FileStack`) is placed in `navigation.ts` after "Content Types"; the breadcrumb templates for fields are generalized to `(content|page|block)-types` ([admin-shell.md](admin-shell.md)).

## Tests

`tests/Feature/Admin/PageTypeTest.php` (a guest, only `page` in the list without a slug, creation with kind `page`, a parent of another kind, an update without changing `key`/`kind`, a descendant as the parent, 404 between sections, deletion, fields: list, creation, prohibition of deleting `title`, order, a foreign key) and `e2e/admin-page-types.spec.js` (together with nodes: the "Nodes" item in both menus, the counter as a link, creation with a slug from the title, editing, breadcrumbs, 404 between sections). Page nodes: `ContentNodeIndexTest`, `ContentNodeWriteTest`. Run:

```bash
docker compose exec app php artisan test --filter=PageType
npm run test:e2e -- admin-page-types
```

# Design

## Context

See proposal.md for motivation and specs/admin-content-types/spec.md for requirements.

Current state (read from the code):

- `ContentTypeController::index` loads all types of the kind with `withCount(['fields','nodes','children'])`, maps them to rows and hands them to `TablePage::fromCollection()` (in-memory search, sort and paging; sort compares the row value under the column key). `parent_type` is a column with the raw key; `children_count` is a service row value used only by the delete dialog.
- Routes for the three kinds come from one `$typeRoutes` list in `routes/web.php`; each route gets its `kind` default and, except `index`, the `can:types` middleware. The `{contentType}` binding is kind-bound (404 for another kind).
- `ContentTypeNav` builds tabs from `typeSectionItems(kind)`; the row menu (`ContentTypeRowActions`) renders from the same list. Eleven controller actions (types edit/display/seo, fields index/create/edit, nodes index/create/edit) pass `type` as `$contentType->only(['key','name','slug'])`.
- `ContentTypeForm` already takes `parentOptions` and shows the slug-prefix hint for the selected parent; `create` passes `parentOptions` of the kind.
- Direct children are `ContentType::children()` (`hasMany` on `parent_type` → `key`); parent and children always have the same kind (checked on save).
- Documentation: `docs/admin-content-types.md` (table, row menu, section menu, forms), `docs/content-model.md` (hierarchy), `docs/e2e-testing.md` (spec map).

## Goals / Non-Goals

**Goals:**

- Show the hierarchy where administrators already work (types table, type pages) with the existing table and row menus, and no new UI pattern.
- One implementation for the three kinds.

**Non-Goals:**

- No tree rendering, no drag-and-drop re-parenting, no change to where "Create" redirects after saving (it still goes to the types list), no change to the model, migrations or public site.

## Decisions

**1. The "Child types" page reuses `Pages/Admin/ContentTypes/Index.tsx` with an optional `parent` prop.**
A new `children` action of `ContentTypeController` renders the same page with `parent: {key, name, slug}` (and the usual nav `type`). When `parent` is set, `Index` shows `ContentTypeNav` (active `children`, which supplies the heading) instead of the list title, passes `tableId` `{kind}-type-children` and a "Create" link with `?parent_type=`. Row building, columns, row actions, favorites and the clone dialog are shared, so every row menu works with no copy.
*Alternative:* a separate `Children.tsx` page — duplicates columns and wiring and would drift. *Alternative:* a block on the Settings page — rejected earlier with the owner in favor of a tab.

**2. Rows come from one private builder used by `index` and `children`.**
The builder takes a collection of types (already `withCount`) plus the kind's id→name map for the parent name, so there is no N+1. `children` queries `$contentType->children()->withCount(...)`; the sort fallback (by name) and access handling stay in the builder. `children` sits behind `can:types` through the shared `$typeRoutes` loop (name `children`), so `canManage` is always true there.

**3. Parent column is `parent_name`, rows keep `parent_type`.**
`TablePage` sorts by the value under the column key, so to sort by the displayed name the column key becomes `parent_name` (a text, sortable, non-searchable column) while each row still carries `parent_type` (the key, used for the link). The old `sort=parent_type` address stops being a valid sort and falls into the table's existing "ignored" handling. The name is resolved from the same loaded collection of the kind (the full kind is already loaded in `index`; in `children` it is the parent itself). Existing tests that assert `table.rows.N.parent_type` keep passing; the column list in tests changes.
*Alternative:* keep `parent_type` and sort by key — the visible order would not match the displayed names.

**4. `children_count` becomes the numeric column `children_count`.**
Visible by default, sortable, `searchable: false` (search keeps its current fields). The cell links to `{basePath}/{key}/children`. Without the `types` right the server omits the column (`columns($kind, $manage)`), and the parent name is plain text, because the link target needs `types`.

**5. One helper builds the nav `type` prop.**
`ContentType::navProps()` returns `key`, `name`, `slug`, `children_count` and `ancestors` (`[{key,name}]`, root first, built by walking `parent_type` with a cycle guard, at most the depth of the tree). The eleven actions replace `only([...])` with it (the edit action merges its extra fields). This is one extra count query and up to depth-many lookups per page, which is negligible next to the page's own queries; it keeps the nav data identical everywhere. The TypeScript `type` shape is declared once (`NavType` exported from `ContentTypeNav.tsx`) and the pages import it instead of repeating the inline object.
*Alternative:* a lazy shared Inertia prop keyed by the route — hides the dependency and is harder to test.

**6. Tab and breadcrumbs in `ContentTypeNav`.**
`typeSectionItems` gets a `children` item (`path: 'children'`, `icon: Network`) after `nodes` for all kinds; the row menu would then also get a "Child types" entry, which is useful and keeps menu and tabs equal (spec: both render from one list). The nav renders its label as `Child types (N)` using the translated base label plus the count from `type.children_count`. The tab is filtered out without `types` like the other management tabs. The back button becomes a breadcrumb (`nav aria-label`) listing the list link and `type.ancestors`, each linking to `{basePath}/{key}/edit`; it is hidden without `types` as today. `data-testid`s: `type-breadcrumbs`, `child-types-tab`.

**7. Preselecting the parent on create.**
The `Create` button links to `{basePath}/create?parent_type={key}`. `create` reads `parent_type` from the query and passes `defaultParent` only if the key is among `parentOptions` (same kind), otherwise `null`. `Create.tsx` passes it to `ContentTypeForm` as the initial `parent_type`; the form logic (hint, validation) is unchanged. Store is unchanged and redirects to the list.

**8. Row menu "Child types" item.**
Because the menu renders from `typeSectionItems`, the new item appears in the menu of every row, linking to its children tab. This is intended (quick drill-down) and needs no extra code; it is listed in the docs.

## Risks / Trade-offs

- [Eleven controller actions change] → covered by one helper and by the existing Feature tests of those pages plus a new test that asserts `children_count` and `ancestors` in the prop.
- [Renaming the sortable column key `parent_type` → `parent_name`] → only affects hand-written addresses; e2e and Feature tests that sort by the parent column are updated in the same change.
- [The existing main spec says the node counter is a plain number in the page and block sections, while the code and `docs/admin-content-types.md` link it in all sections] → not touched here; the delta copies the existing sentence unchanged. Worth a separate fix.
- [Ancestor walk on corrupted data (cycles)] → the helper stops when a key repeats.
- [Tab count on every type page adds a `count` query] → one indexed query on `content_types.parent_type`; acceptable.

## Migration Plan

No migrations or data changes. Deploy the code and rebuild the frontend (`npm run build`). Rollback is reverting the change.

## Documentation

`docs/admin-content-types.md` (columns, the new tab and route, row menu entry, create preselect, breadcrumbs, the nav `type` prop) and `docs/e2e-testing.md` (area → spec rows). A one-line cross-link from `docs/content-model.md` (hierarchy of types) to the new admin tab.

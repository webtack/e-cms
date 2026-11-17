# Proposal

## Why

Types form a tree through `content_types.parent_type`, but the admin hardly shows it: the types table has only a plain-text column with the parent's raw key, `children_count` is used solely by the delete dialog, and neither the type pages nor the section menu say anything about child types. To see or manage the children of a type the administrator has to scan the flat list and match keys by eye, and to add a child has to open the create form and pick the parent by hand.

## What Changes

- **Types table (content, page and block sections, shared controller and `Index` page):**
  - The parent column shows the parent type's **name** as a link to the parent's node list (users without the `types` right see the plain name).
  - `children_count` becomes a regular, visible, sortable column; the number links to the type's new "Child types" tab (only for users with the `types` right).
- **New "Child types" tab in the type section menu** (`ContentTypeNav` / `typeSectionItems`), placed between "Nodes" and "Preview" and labelled with the count, e.g. "Child types (2)":
  - Always shown, even with 0 children, because it is where the first child is created.
  - Route `GET {section}/{type}/children` (`admin.{section}.children`), for all three kinds, behind the `types` right.
  - It renders the same table (same columns, search, sorting, column visibility and the full row menu: Settings, Display, SEO, Fields, Nodes, Preview, Clone, Delete, favorites star) restricted to the **direct** children of the type.
  - Its "Create" button opens the create form with the parent type preselected (`?parent_type={key}`); the existing slug-prefix hint then applies.
- **Navigation up:** the "back to the list" link above the tabs becomes a breadcrumb trail `Content Types / Ancestor / Parent` (links to each ancestor's settings page), shown on every page that renders the section menu.
- Every controller that feeds `ContentTypeNav` sends the same `type` prop shape through one helper, which now also carries `children_count` and the ancestor chain.

Not in scope: a tree mode of the main types table, a type tree in the sidebar, showing child types' nodes in the parent's node list, changing where "Create" redirects after saving.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-types`: the "Columns of the table" requirement changes (named, linked parent column; visible child-types column), and new requirements describe the "Child types" tab and its page, the preselected parent on create, and the breadcrumb trail above the section tabs. The page-type and block-type sections share the same controller and React pages, so they follow the same behavior without their own delta.

## Impact

- Backend: `app/Http/Controllers/Admin/ContentTypeController.php` (shared row builder, `children` action, `parent_name` row value, `create` accepts `parent_type`), `routes/web.php` (new `children` route in `$typeRoutes`), `app/Models/ContentType.php` (helper for the nav `type` prop), `ContentFieldController` and `ContentNodeController` (use the helper).
- Frontend: `Pages/Admin/ContentTypes/Index.tsx` (columns, optional `parent` scope), `Components/Admin/ContentTypes/ContentTypeNav.tsx`, `typeSectionItems.ts`, `ContentTypeForm.tsx` / `Create.tsx` (preselected parent), the pages that pass `type` to the nav; translations in `lang/uk.json` for the new strings.
- Tests: PHP Feature tests in `tests/Feature/Admin/ContentTypeIndexTest.php` (and a new child-types test), e2e in `e2e/admin-content-types.spec.js`, plus small checks in `admin-page-types` / `admin-block-types`.
- Docs: `docs/admin-content-types.md` (columns, new tab and route, create preselect, breadcrumb), `docs/e2e-testing.md` (area → spec rows), cross-link from `docs/content-model.md` if the hierarchy section mentions the admin.

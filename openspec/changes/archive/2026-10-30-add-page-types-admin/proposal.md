# Proposal

## Why

The admin already manages content types of the kind `content` (list, creation, editing, deletion, fields), but types of the kind `page` are not available in it: the routes `/admin/content-types/**` for them respond 404, and there is no section in the navigation. To describe the structure of the site's pages (sets of page fields), the administrator needs the same pages for `kind=page`.

## What Changes

- A new section "Page Types" at the address `/admin/page-types` with the same pages as for content types: the list (a server-side table), creation, editing, deletion of a type, as well as the list, creation, editing, deletion and ordering of the fields of a type (`/admin/page-types/{key}/fields/**`).
- The differences from content types: the form and the table have no `slug` (the model forbids a slug for `page`); the parent type can be chosen only from types of the kind `page`; `kind` is always `page`.
- The horizontal section menu ("Settings" / "Fields") works on the pages of a page type too; the link back leads to the list of page types.
- The item "Page Types" in the sidebar navigation and the corresponding breadcrumbs. The existing layout item "Pages" (the future list of the node pages themselves) remains unchanged.
- The pages `/admin/content-types/**` continue to respond 404 for types `page` and `block`; types `block` remain outside this change.

## Capabilities

### New Capabilities
- `admin-page-types`: the admin pages for managing types of the kind `page` and their fields.

### Modified Capabilities
- `admin-shell`: a working item "Page Types" appears in the sidebar navigation.

## Impact

- Backend: the routes in `routes/web.php`; `ContentTypeController`, `ContentFieldController` and the form requests become dependent on the kind (the kind is taken from the route), without duplicating the controllers.
- Frontend: the pages `Pages/Admin/ContentTypes/*`, `Pages/Admin/ContentFields/*` and the components `Components/Admin/ContentTypes/*`, `ContentFields/*` accept the base address of the section and the kind; `navigation.ts` (the menu item, breadcrumbs).
- Translations `lang/uk.json`, `lang/en.json` ("Page Types", the headers/dialogs for pages).
- Tests: `tests/Feature/Admin/*` (the pages of page types), e2e `e2e/admin-page-types.spec.js`.
- Docs: a new `docs/admin-page-types.md`; update `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`, `docs/README.md` (the index).

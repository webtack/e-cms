# Proposal

## Why

The admin manages content types of the kinds `content` and `page`, but types of the kind `block` are not available in it: the routes `/admin/content-types/**` and `/admin/page-types/**` for them return 404, and there is no section in the navigation. To describe the structure of blocks (reusable sets of fields), the administrator needs the same pages for `kind=block`.

## What Changes

- A new section "Block Types" at the address `/admin/block-types` with the same pages as for page types: the list (a server-side table), creation, editing, deletion of a type, as well as the list, creation, editing, deletion and ordering of the fields of a type (`/admin/block-types/{key}/fields/**`).
- The differences from content types are the same as for `page`: the form and the table have no `slug` (the model forbids a slug for `block`); the parent type can be chosen only from types of the kind `block`; `kind` is always `block`.
- The horizontal section menu ("Settings" / "Fields") also works on the pages of a block type; the link back leads to the list of block types.
- The item "Block Types" in the sidebar navigation (after "Page Types") and the corresponding breadcrumbs.
- The pages `/admin/content-types/**` and `/admin/page-types/**` continue to respond 404 for types `block`; the block nodes (the slug prefix `block-`, placement) remain outside this change.

## Capabilities

### New Capabilities
- `admin-block-types`: the admin pages for managing types of the kind `block` and their fields.

### Modified Capabilities
- `admin-shell`: a working item "Block Types" appears in the sidebar navigation.

## Impact

- Backend: `routes/web.php` (the third pair `block-types` => `block` in the registration loop); the trait `Concerns\ResolvesTypeSection` (the section segment for `block`); the controllers and form requests already depend on the kind from the route, so no logic changes are needed (check the comments/PHPDoc that mention only `content`/`page`).
- Frontend: `Components/Admin/ContentTypes/section.ts` (the type `TypeSection`, `sectionLabels('block')`); `navigation.ts` (the menu item, `extraTitles`/`patternTitles`); a check that the components and pages do not assume only `content`/`page`.
- Translations `lang/uk.json`, `lang/en.json` ("Block Types", "Create block type", "Edit block type", "Delete block type", "Block type sections", the deletion confirmation).
- Tests: `tests/Feature/Admin/BlockTypeTest.php`, e2e `e2e/admin-block-types.spec.js`.
- Docs: a new `docs/admin-block-types.md`; update `docs/admin-page-types.md` (a mention of the common mechanism), `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`, `docs/e2e-testing.md` (a table row "area → spec") and the index `docs/README.md`.

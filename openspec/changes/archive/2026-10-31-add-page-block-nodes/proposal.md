# Proposal

## Why

Page types and block types can already be created and filled with fields, but it is impossible to create records (pages and blocks) from them: the form, the list and the saving of records exist only in the section «Content Types». Without this, the types of the kinds `page` and `block` remain empty descriptions of a structure.

## What Changes

- The sections `/admin/page-types/{key}/nodes…` and `/admin/block-types/{key}/nodes…` get the same six record routes (list, create, store, edit, update, delete) as `/admin/content-types/{key}/nodes…`. The controller, the React pages, the forms and the field widgets remain shared; the kind determines the route.
- Requests for records of a type of another kind (for example, the key of a type `block` in `/admin/page-types/**/nodes`) still respond with 404.
- The horizontal section menu of the type (`ContentTypeNav`) and the row action menu of the type (`ContentTypeRowActions`) show the item «Записи» for all three kinds; in the table of page types and block types the number of records becomes a link to their list, as for content types.
- The breadcrumbs of the record pages of the sections «Page Types» and «Block Types» lead through the corresponding sidebar item.
- The link to a record in the media library (`ContentNodeOwner`) is built for a record of any kind, not only `content`.
- The slug rules on the server remain as in the model: for page records the slug is taken from the title, for blocks it is generated automatically with the prefix `block-`, and an explicit slug of a block without the prefix is rejected.
- The record form of a block shows the prefix `block-` as an unchangeable part of the slug field (the field takes only the rest), and always sends the full slug with the prefix, unless the user specified the prefix explicitly; an empty field, as before, gives a generated slug.
- Tests: Feature tests of the record routes for `page` and `block` (instead of the current expectations of 404), e2e scenarios in `admin-page-types` and `admin-block-types`.

**Not included:** the page tree, public routing and rendering, placement of blocks on pages, an update of the mock item «Pages» in the sidebar.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: the section of records is no longer only for the kind `content`: records are available in the sections of content types, pages and blocks (the requirement about 404 for `page`/`block` is replaced by the requirement about 404 only for a type of another kind); the list of records, the section menu and the breadcrumbs are described for the three kinds; the link to a record in the media library works for all kinds.
- `admin-page-types`: the section menu of the type and the row action menu get the item «Записи», the number of records in the table — a link.
- `admin-block-types`: the same for block types.

## Impact

- **Code:** `routes/web.php` (the loop of record routes over the three sections), `app/Http/Controllers/Admin/ContentNodeController.php` (the class comment; a check that the redirects and `section` are taken from the route), `app/Files/Owners/ContentNodeOwner.php` (the URL by kind), `resources/js/Components/Admin/ContentNodes/NodeServicePanel.tsx` and `ContentNodeForm.tsx` (the slug prefix), `resources/js/Components/Admin/ContentTypes/ContentTypeNav.tsx`, `ContentTypeRowActions.tsx`, `resources/js/Pages/Admin/ContentTypes/Index.tsx`, `resources/js/Components/Admin/navigation.ts` (breadcrumb templates), if needed translations in `lang/en.json` and `lang/uk.json`.
- **Tests:** `tests/Feature/Admin/ContentNodeIndexTest.php`, `ContentNodeWriteTest.php`, `PageTypeTest.php`, `BlockTypeTest.php`, `MediaLibraryTest.php`; `e2e/admin-page-types.spec.js`, `e2e/admin-block-types.spec.js`.
- **Documentation (docs/, in Ukrainian):** update `admin-content-nodes.md`, `admin-page-types.md`, `admin-block-types.md`, `admin-content-types.md`, `admin-shell.md` (breadcrumbs), `media-library.md` (the owner's link), `content-model.md` (a mention that the behaviour of the kinds is "future"), `e2e-testing.md` (the tables "Область → спек" and "Спільний код → спеки"); the index `docs/README.md` without new files remains valid.
- No DB migrations and no new dependencies.

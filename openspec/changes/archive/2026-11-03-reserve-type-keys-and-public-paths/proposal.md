# Proposal

## Why

A public frontend with themes (`themes/<name>/views`) and the routes `/{slug}` (a page), `/{type.slug}` (a list of the type `content`), `/{type.slug}/{node.slug}` (details) will appear soon. Now the data does not protect this:

- the key of a type will become the name of a folder of the templates in `views/`, but the key `content`, `pages`, `blocks`, `fields`, `menus` or `errors` would coincide with a service folder of the theme (the prohibition exists only for the keys of **fields**, `ContentField::RESERVED_KEYS`);
- the slug of a page, the slug of a type and the service paths (`admin`, `login`, `storage` …) share one space of the first URL segment, but are checked only for uniqueness within their own table. A page `news` and a type with the slug `news` both want `/news`, and a page `admin` hides behind the service route. The automatic generation can also give `admin` from the heading «Admin».

The error must be shown in the form on saving, while the correct paths can still be fixed, and before the public routing appears.

## What Changes

- The key of a new type cannot be from the list of reserved ones (`content`, `pages`, `blocks`, `fields`, `menus`, `errors`). The single place of the list is a constant in `ContentType`. It is checked on creation (the key is immutable), in the model and in `StoreContentTypeRequest` (an error next to the field of the key). The existing types are not touched.
- The slug of a **page** (a record of the kind `page`) cannot match the slug of any type `content` or a reserved path of the site (`config('content.reserved_paths')`: `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`).
- The slug of a **type `content`** cannot match the slug of any page or a reserved path.
- The automatic generation of the slug of a page (`SlugGenerator`) considers such values occupied and adds a numeric suffix (`admin` → `admin-2`).
- The check works on two levels: the rules of the forms (an error next to the field `slug`) and the model (the last boundary for code and import). It is checked only on creation and on a change of the slug, so an existing match does not block other edits of a record or a type.
- Guard test: the first segment of each non-public route of the application is included in `content.reserved_paths`, so a new service route will not remain without protection.
- Language prefixes (`en`, `uk`) are **not** included here: they will be added by the separate change about the languages of the site.
- The slugs of records `content` and `block` are not checked: they are not on the first segment of the URL.
- **BREAKING** (only for the code and the tests): it is no longer possible to create a type with a reserved key. The tests and fixtures that use the key `pages` and similar ones move to neutral keys.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-model`: the keys of types (reserved); the public slug of a type (it stops being independent from the slugs of pages and reserved paths); the slug of a record (a new rule for pages and the generation rule without matches).
- `admin-content-types`: the form of creating and editing a type shows the errors of a reserved key and of a slug collision next to the corresponding fields.
- `admin-content-nodes`: the form of a page record shows the error of a slug collision next to the field slug (creation and editing).

## Impact

- Code: `app/Models/ContentType.php`, `app/Models/ContentNode.php`, a new service of checking public paths in `app/Content/` (next to `SlugGenerator`), `app/Content/SlugGenerator.php`, `app/Content/ContentNodeCreator.php` (the error message), `app/Http/Requests/Admin/StoreContentTypeRequest.php`, `StoreContentNodeRequest.php`, `UpdateContentNodeRequest.php`, `config/content.php`, `lang/en.json`, `lang/uk.json`.
- Tests: `tests/Feature/Content` (`ContentTypeTest`, `ContentNodeTest`, `SlugTest`, a new test of collisions and the guard of routes), `tests/Feature/Admin` (`ContentTypeCreateTest`, `ContentTypeEditTest`, `PageTypeTest`, `ContentNodeWriteTest`), replacement of the reserved keys in the fixtures.
- e2e: the check of the errors in the forms in `e2e/admin-content-types.spec.js` and `e2e/admin-page-types.spec.js`.
- There are no database and no migrations: this is only validation. In the working DB there are currently no collisions (types `ddd`, `dsfsdf`, `news`; the slug of the type `news`; the page `rtrtyrty`).
- Docs: update `docs/content-model.md` (the keys of types, the slug of a type and of a record, the reserved paths), `docs/admin-content-types.md`, `docs/admin-content-nodes.md`, `docs/admin-page-types.md` (where the differences and the slug errors are listed) and, if needed, `docs/e2e-testing.md`.

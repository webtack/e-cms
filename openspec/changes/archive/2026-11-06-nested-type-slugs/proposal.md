# Proposal

## Why

The legacy site (kultura-poltava) serves sections of content types at nested addresses such as `/common/institutions` and `/collectives/ckd`, and their records at `/common/institutions/<slug>`. These public addresses are important and must survive the migration (the later `import-legacy-site` change). The new CMS allows only one segment in the type slug (`/{type.slug}` and `/{type.slug}/{node.slug}`, anything deeper is 404), so a type nested in another cannot keep its address. All 11 nested legacy types have a path that starts with the path of their parent type, so the rule "a child slug starts with the parent slug" matches the existing data.

## What Changes

- The `slug` of a type of kind `content` may have several segments separated by `/` (for example `common/institutions`); each segment follows the existing slug format, the whole slug stays up to 190 characters, unique case-insensitively.
- **Hierarchy rule.** A type that has a parent type and a slug must have a slug that starts with the parent's slug and `/`, followed by a non-empty tail of one or more segments; the parent must have a slug. A child without a slug is still allowed. A root type may have any slug of one or more segments.
- Changing `parent_type` of an existing type re-checks the rule.
- **Blocked parent changes.** Changing or clearing the slug of a type is rejected while any of its direct children has a slug (the message says to change the children first). There is no cascade.
- **Collisions.** A reserved path is checked against the first segment of a type slug; a type slug may not equal a page slug. A type slug may not equal the address of a content node (`parent slug` + `/` + node slug), and a content node slug may not make its address equal to the slug of a type.
- **Public routing.** `/{first}` and `/{first}/{second}` become one route with any number of segments. An address is resolved as: a page (one segment), else a type list (the whole address is the type slug), else a node (the last segment is the node slug, the rest is the type slug), else 404. Service paths are still checked by the first segment.
- Admin type form: the slug accepts `/`; the hint explains the format; a type with a parent shows the required prefix.
- Places that build a node address from the type slug keep working with a multi-segment slug.
- **Out of scope**: redirects from old addresses, deep-nesting of page slugs, language prefixes, any change of `parent_type` semantics for `page` and `block` kinds (no slug there), migrating existing types (the database is cleared before the import).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-model`: "Public slug of a content type" (multiple segments), "Top-level public paths without collisions" (reserved check by the first segment); new requirements for the hierarchy of type slugs and for a node address that must not hide a type list.
- `public-site`: "List of nodes of a content type" and "Details of a node of the content type" work for multi-segment type slugs; a new requirement for resolving addresses of any depth.
- `admin-content-types`: a new requirement for the slug of a nested type in the create and edit forms (hint, prefix, errors, blocked change).
- `admin-content-nodes`: a new requirement for the error when a node slug would hide a type list.

## Impact

- Code: `App\Models\ContentType` (slug pattern, hierarchy and blocked-change checks), `App\Content\PublicPaths` (first-segment reserved check, address collisions), `App\Models\ContentNode` (the address check), `StoreContentTypeRequest` / `UpdateContentTypeRequest` and a new rule class, `routes/web.php` (one multi-segment route), `PublicNodeController`, `ContentTypeForm.tsx` and the controller prop that feeds parent options with their slugs, `lang/uk.json` / `lang/en.json`.
- Unchanged by design: `PresentContext`, `PreviewRenderer`, `ContentNodeController::publicUrl` already join `'/'.$type->slug.'/'.$node->slug`, so they work with a multi-segment slug (covered by tests).
- Tests: `tests/Feature/Content/ContentTypeTest.php`, `tests/Feature/Admin/ContentTypeCreateTest.php`, `ContentTypeEditTest.php`, `tests/Feature/Public/PublicRoutesTest.php`, `tests/Feature/Content/ReservedPathsGuardTest.php` (the guard reads the route list), node tests; e2e: `e2e/admin-content-types.spec.js` and `e2e/public-site.spec.js`.
- Docs: `docs/content-model.md` (Type slug, Reserved paths), `docs/public-site.md` (Routes), `docs/admin-content-types.md` (fields, editing), `docs/e2e-testing.md` if a scenario row is added.
- No new dependencies and no data migration.

# Design

## Context

Observed state (see proposal.md - Why for the motivation):

- `ContentType::SLUG_PATTERN` is the single-segment slug pattern (`^[a-z0-9]+(-[a-z0-9]+)*$`); the model validates it in `assertValid()` together with uniqueness and `PublicPaths::typeSlugConflict()` (reserved path or a page with the same slug). `StoreContentTypeRequest::sharedRules()` duplicates these rules for the form (`regex`, `unique`, `AvailablePublicPath`). `parent_type` is organizational only (same kind, no cycles), described in [docs/content-model.md](../../../docs/content-model.md#type-hierarchy).
- `routes/web.php` registers two public routes after all service routes: `/{first}` (`public.show`) and `/{first}/{second}` (`public.details`). The segment regex is derived from `ContentNode::SLUG_PATTERN`, and the first segment has a negative lookahead for `PublicPaths::reserved()`. Nothing else uses the route names (checked by grep). `PublicNodeController` has `show()` (page, else type list) and `details()` (type by `first`, node by `second`).
- `ReservedPathsGuardTest` walks the route list and requires the first segment of every route to be reserved, except for routes that start with a parameter, so one route `/{path}` stays compatible.
- `PresentContext`, `PreviewRenderer` and `ContentNodeController` already build a node address as `'/'.$type->slug.'/'.$node->slug`, which is correct for a multi-segment type slug.
- The admin type form passes `parentOptions` (`key`, `name`) and has a single hint for the slug field ([docs/admin-content-types.md](../../../docs/admin-content-types.md)).

Constraint from the owner: the legacy addresses (`/common/institutions`, `/collectives/ckd`, and their records) must work, and a child slug must start with its parent's slug.

## Goals / Non-Goals

**Goals:**

- Type slugs of any depth, validated in the model (last barrier), in the form requests (field errors) and explained in the form.
- One public route that resolves addresses of any depth without changing the priority of service routes, pages and 404.
- No silent address changes: the slug of a parent cannot be changed under its children, and no node or type can shadow another.

**Non-Goals:**

- Redirects from old addresses, page slugs with several segments, language prefixes.
- Cascading renames of child slugs.
- Backfilling or migrating existing data (the database is cleared before the import); the checks run only when a slug or a parent changes, so older rows never block other edits.

## Decisions

1. **One slug pattern for types, with `/` as the separator.** `ContentType::SLUG_PATTERN` becomes `^[a-z0-9]+(-[a-z0-9]+)*(/[a-z0-9]+(-[a-z0-9]+)*)*$`. `ContentNode::SLUG_PATTERN` (a node slug never contains `/`) and `Term::SLUG_PATTERN` stay as they are, so the node route regex reuses the node pattern per segment. The 190-character limit applies to the whole slug (the column and its unique index do not change). Alternative considered: a separate `path` column or a table of segments; rejected as an unneeded schema change, because the unique `slug` column already identifies the type and `LOWER(slug)` comparisons are in use.

2. **A small service `App\Content\TypeSlugs` owns the hierarchy rules; the model and the form rule both call it.** The model stays the last barrier (like `PublicPaths`), and the form request adds `App\Rules\TypeSlugHierarchy` (a `ValidationRule` with `DataAwareRule` to read the submitted `parent_type`). The service answers: *does the slug start with the parent's slug and `/`?* (case-insensitive, whole segments, non-empty tail), *does the parent have a slug?*, and *does the type being changed have a child with a slug?* (direct children only). Direct children are enough: a grandchild with a slug requires a child with a slug (the parent-must-have-a-slug rule), so the first level always shows the block. Alternative considered: inlining in `ContentType::assertValid()` only; rejected because the form needs the same messages as field errors, as it already does for `AvailablePublicPath`.

3. **When the checks run.** Hierarchy: on creation and when `slug` or `parent_type` is dirty. Blocked parent change: when `slug` is dirty on an existing type whose original slug was non-null and which has a child with a slug. Same pattern as the existing "only when new or changed" collision checks, so legacy rows never block unrelated edits. Errors go under `slug` (for a parent change too, because the slug is what becomes invalid).

4. **Reserved paths by the first segment; pages by equality.** `PublicPaths::typeSlugConflict()` compares the first segment with the reserved list and the whole slug with page slugs (a page slug has one segment, so only a single-segment type slug can match one). `pageSlugConflict()` keeps comparing the page slug with whole type slugs: a page `common` next to a type `common/institutions` is not a conflict, as they are different addresses.

5. **Address collisions between nodes and types.** With the longer slugs two things can mean the same address: the type list `common/institutions` and the node `institutions` of the type `common`. The list wins (decision 6), so the node would be unreachable. `PublicPaths` gets the checks (only on creation and slug change, same as other collisions): a `content` node whose address `type.slug/node.slug` equals a type slug is rejected (`ContentNode::assertValidSlug`, skipped for types without a slug, `page` and `block`); a type slug that equals the address of an existing node is rejected (the type's slug minus its last segment finds the candidate parent type, its nodes are searched for the last segment); a type whose slug changes is also checked against the types one segment deeper (`LIKE 'new/%'` without another `/`) and its own nodes. All comparisons are case-insensitive. Legacy rows that already collide stay readable; the route resolves them deterministically.

6. **One route with a multi-segment parameter.** `Route::get('/{path}', [PublicNodeController::class, 'show'])->where('path', $regex)->name('public.show')` replaces both routes (`public.details` disappears; nothing refers to it). `$regex` is `(?!(?:reserved)(?:/|$))` followed by `slug(?:/slug)*` where `slug` is the node pattern body, as today. Service routes are registered earlier, so they keep priority. The pattern rejects empty segments, uppercase, underscores and trailing slashes, which fall through to the themed 404 as before.

7. **Resolution order in the controller.** `show()` splits the path on `/`: (1) one segment: a visible page; (2) a `content` type whose slug equals the whole path: the list; (3) two or more segments: the type whose slug equals the path without its last segment, then the visible node of this type with the last segment as the slug; (4) `notFound()`. Reading the type list before the node is the rule the owner approved. `details()` and `page()`/list rendering code are extracted from the current methods, not rewritten, so templates and data are unchanged. Alternative considered: trying the node first and falling back to the list; rejected because the list is the established meaning of an address that equals a type slug and the collision rules make the other case impossible for new data.

8. **Form.** `parentOptions` gets `slug`; `ContentTypeForm.tsx` shows, under the slug field, the prefix (`common/`) for the selected parent that has a slug, or a notice for a parent without a slug, and the general hint now mentions `/`. The field stays a plain input (no auto-prefix, to keep typing and pasting predictable). New texts are added to `lang/uk.json` and `lang/en.json`.

The components are described in [docs/content-model.md](../../../docs/content-model.md#type-slug), [docs/public-site.md](../../../docs/public-site.md#routes), [docs/admin-content-types.md](../../../docs/admin-content-types.md) and [docs/admin-content-nodes.md](../../../docs/admin-content-nodes.md); the tasks update them.

## Risks / Trade-offs

- [A type list hides a node with the same address] → The collision checks prevent new cases; the resolution order makes legacy cases deterministic (list wins).
- [Changing the parent's slug is blocked, so renaming a branch needs several saves] → Intentional (owner's decision): addresses never change silently; the message tells which step to do first. A bulk rename can be a later feature.
- [The route accepts deep paths, so more requests reach the controller and run up to two lookups] → One extra indexed query per miss at most (unique `slug` index for types, unique node `slug`); unknown paths still end in the themed 404.
- [`LOWER(slug)` comparisons cannot use the unique index] → The existing code already does this for type and page slugs; the tables are small.
- [Dropping the route name `public.details`] → No consumers found; the task re-checks with `grep` before removal.

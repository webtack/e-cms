## Why

The `views` counter of a node (added by `add-node-views`, filled by `import-legacy-site` with the old site's data) is only useful if it keeps growing after the move. Right now nothing records a visit, and editors and themes cannot see or order by the number. This change counts visits on the public site and exposes the counter in the admin list, in templates, and in sorting ("popular" lists).

## What Changes

- The public site counts a view for each successful `GET` of a node page (`content` details and `page`), with rules: no bots or empty User-Agent, no authenticated users, one count per visitor per node per 24 hours (atomic cache key, no cookies), no lists/blocks/404/invisible nodes. Write is one synchronous atomic `UPDATE` that does not touch `updated_at`; failures are only logged. A switch and the settings live in `config/content.php` (`views.enabled`, `views.ttl`, `views.bot_agents`).
- `node.views` is available to theme templates (details, page, list cards).
- Admin: service column "Views" in the node table (selectable in the type's list settings, sortable, numeric order, ties by title then id); "Views" as a sort option in the type form and for the public list (`settings.public.sort`).
- Index on `content_nodes.views` for sorting.
- **BREAKING (narrow)**: `views` becomes a reserved field key. A new field named `views` cannot be created; existing ones stay valid but are not offered as list columns. Test fixtures and spec examples that use a `views` field are renamed to `visits`.

Not included: analytics, charts, views per period, block counters, resetting the counter, IP-range bot detection, cookie dedup, queued writes, showing views on the node edit form.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `public-site`: counting views, `views` in templates, public sorting by `views` (all ADDED).
- `content-model`: `views` becomes a reserved field key.
- `content-querying`: sorting by service attribute `views`; `views` allowed in type list settings.
- `content-values`, `admin-content-fields`: examples renamed (`views` → `visits`); `admin-content-fields` rejects the reserved key.
- `admin-content-nodes`: "Views" column and sorting.
- `admin-content-types`: "Views" as a column and sort option in the type form.

## Impact

- Code: `PublicNodeController` (hook after building the response), a small `App\Content\ViewCounter` service, `config/content.php`, a migration (index), `NodeListSettings`, `ContentType` (`LIST_SERVICE_KEYS`, `LIST_SORT_SERVICE_KEYS`), `ContentNodeQuery`, `ContentField::RESERVED_KEYS`, `PublicDisplaySettings`, `ContentTypeForm.tsx` and the nodes table, `lang/*.json`.
- Tests: new feature tests for the counter, admin column and sorting, reserved key; about 26 test files rename the `views` field fixture.
- Docs: `public-site`, `content-model`, `content-querying`, `content-values`, `admin-content-*`.
- Depends on `add-node-views` (column, `incrementViews()`) and `nested-type-slugs` (single `/{path}` route). Apply after both.

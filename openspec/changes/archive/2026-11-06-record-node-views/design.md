## Context

`add-node-views` adds `content_nodes.views` and `ContentNode::incrementViews()`; `nested-type-slugs` makes one route `/{path}` with `PublicNodeController` resolving a page, a type list, or a node details page. This change records visits there and exposes the counter. Templates already receive the `ContentNode` model, so `views` is an attribute of `$node`. Lists get nodes from `ContentNodeQuery`, which already supports service-attribute sorting.

## Goals / Non-Goals

**Goals:** a cheap, honest counter (humans only, one per visitor per day), visible in the admin list and templates, usable as a sort key; no change to page responses on failure.

**Non-Goals:** analytics, per-period stats, block counters, reset from the admin, queue, cookies, IP-range bot lists, views on the node edit form.

## Decisions

1. **What counts.** A 200 response to `GET` of a content-node details page or a page node. Lists, blocks, 404, invisible nodes, `HEAD`/`OPTIONS` and non-GET do not count. The hook sits in the controller after the response is built (`details()` and `page()`), so a page that failed to render never counts.
2. **Service `App\Content\ViewCounter`** with `record(Request, ContentNode): void`. Order of checks, cheapest first: enabled → method is `GET` → not authenticated → User-Agent not empty and not a bot → dedup key → `incrementViews()`. The controller only calls it; the whole call is wrapped in `try/catch (Throwable)` that logs at `warning` and never rethrows.
3. **Bots.** Case-insensitive substring match against `config('content.views.bot_agents')` (defaults: `bot`, `crawl`, `spider`, `slurp`, `facebookexternalhit`, `preview`, `headless`, `curl`, `wget`, `python-requests`, `monitor`). Empty User-Agent is a bot. Simple and configurable; sophisticated detection is out of scope. Trade-off: false positives for agents containing e.g. "bot" (accepted).
4. **Dedup.** `Cache::add("node-view:{id}:" . sha1(ip . '|' . userAgent), 1, ttl)`; `add` is atomic, so only the request that creates the key counts. TTL from `content.views.ttl` (seconds, default 86400). No cookies (no consent banner needed, works for cookie-less clients). Trade-off: visitors behind one NAT with the same browser count as one; the cache driver in use (check `config/cache.php`) must persist between requests — with the `array` driver (tests) the key lives per process.
5. **Failure of the cache** → the view is **not** counted and the page is served (an undercount is better than an inflated counter or an error). Failure of the update → logged only.
6. **Write.** `ContentNode::incrementViews()` is one atomic `UPDATE ... SET views = views + 1` on the base query, without Eloquent timestamps, so `updated_at` stays. Synchronous: one cheap indexed-by-primary-key update per unique visit; no queue.
7. **Switch.** `config('content.views.enabled')` from `CONTENT_VIEWS_ENABLED` (default true). The test environment sets it to `false` in `phpunit.xml`-independent fashion: counter tests turn it on explicitly with `config()`, so other public tests do not change counters unexpectedly. Local development may switch it off in `.env`.
8. **Templates.** No new plumbing: `node.views` is a model attribute. The value is the one read before the current visit, which is fine for display. A guard test checks that card nodes of a list have an integer `views` (the query selects all columns).
9. **Admin column and sorting.** `views` joins `NodeListSettings::SERVICE_COLUMNS` (type `number`, sortable), `ContentType::LIST_SERVICE_KEYS`, `LIST_SORT_SERVICE_KEYS`, `ContentNodeQuery::SORTABLE_SERVICE_KEYS`. `applyServiceSort` handles it as an integer column (no string compare), ties by `title`, then `id`, like `published_at`. The public sort accepts it through the same `LIST_SORT_SERVICE_KEYS` check in `PublicDisplaySettings`. The type form's column/sort options are built from the same constants, so the frontend only needs the label "Views" in the translations.
10. **Index.** A plain index on `content_nodes.views` (migration) because the public "popular" list sorts by it over all nodes of a type. This migration is separate from the `add-node-views` column migration so the two changes stay independent.
11. **Reserved key.** `views` is added to `ContentField::RESERVED_KEYS`. Existing fields named `views` stay valid in data but `NodeListSettings::isServiceKey` makes the service column win (documented behavior for the other service keys). Fixtures and spec examples that used a `views` field are renamed to `visits`; the list is found by `grep -rlw views tests e2e docs`.
12. **No conflicts with other open changes.** The behavior lives in ADDED requirements of `public-site`; MODIFIED blocks touch only requirements that the other open changes (`add-node-views`, `nested-type-slugs`, `import-legacy-site`) do not modify. `import-legacy-site` imports legacy field `views`? It maps the legacy counter to the node column, not to a field, so the reservation does not conflict; if the legacy type defines a field with key `views`, the importer already reports reserved keys.

## Risks / Trade-offs

- Caching layers (CDN/full-page cache) in front of the app would hide visits → not used now; documented.
- Heavy traffic → one update per unique visitor per day per node; acceptable. A queue or batched writes can be added later without changing the contract.
- Renaming about 20 test files is mechanical but wide → run the touched classes with `--filter`, and the whole suite once because `ContentField::RESERVED_KEYS` and a migration change.

## Migration Plan

Add the index migration; no data change. Roll back by dropping the index. The switch `CONTENT_VIEWS_ENABLED=false` disables counting instantly.

## Open Questions

None; defaults (bots, 24 h, authenticated excluded, synchronous write, reserved key) were approved by the owner.

## Context

See proposal.md. Current state: `ViewCounter::shouldCount()` returns false when `$request->user() !== null`, then claims an atomic `Cache::add` key `node-view:{id}:{sha1(ip|ua)}` with `content.views.ttl`; `ContentNodeCreator::create()` has `int $views = 0` and assigns it to the model next to `user_id`; the `content_nodes.views` column has database default `0`. A manual check on the running site confirmed that a guest request with a browser User-Agent raises the counter (0 → 1), so the report comes from the signed-in exclusion (and, on repeated tests, from the dedup).

## Goals / Non-Goals

**Goals:** by default count every non-bot `GET` of a node page, signed-in or not, with no per-visitor limit; both rules switchable in `.env`; new nodes start at 1.

**Non-Goals:** excluding admin previews or roles; changing the bot list; changing the column default; backfilling existing nodes; changing the legacy import's counters; rate limiting.

## Decisions

1. **Configurable, defaults count everything.** The owner rejected deduplication and the signed-in exclusion as defaults (a tester would see +1 only once a day) but wants to switch them from `.env`. `config/content.php` `views`: `ttl` = `(int) env('CONTENT_VIEWS_TTL', 0)` and new `count_authenticated` = `(bool) env('CONTENT_VIEWS_COUNT_AUTHENTICATED', true)`. `shouldCount()` order: enabled, method `GET`, authenticated user while `count_authenticated` is false, User-Agent/bot, then — only when `ttl > 0` — the atomic `Cache::add` dedup key (unchanged). With defaults the service does not touch the cache. The cache-failure path stays (it exists only when `ttl > 0`); `record()` keeps its `try/catch`. Both variables are added to `.env.example`. Assumption: the owner's "налаштовуваним" covers these two behaviors (dedup period and signed-in counting); `0` is the "off" value of `ttl` rather than a separate boolean.
2. **Initial `1` in the creator, not in the migration.** Changing `ContentNodeCreator::create()`'s default to `1` affects every creation path that does not pass a value (admin forms, code), and keeps the import explicit. Alternative: change the column default and migrate — rejected: it would also change existing semantics (`0` for old nodes, spec "Migration gives existing nodes a zero counter") and factories/tests inserting raw rows. Assumption recorded: "додавати 1 при створенні" is read as the creation counting as the first view, i.e. the initial value is 1, not 0.
3. **Admin previews** that open the public address as a signed-in user are counted like any visit (not verified whether previews use a separate route); excluding them is out of scope.

## Risks / Trade-offs

- [Page reloads, editors checking their own pages, and simple scripted clients with a browser-like User-Agent inflate counters by default] → accepted by the owner's decision; `CONTENT_VIEWS_TTL` and `CONTENT_VIEWS_COUNT_AUTHENTICATED` restore the stricter rules per environment.
- [One `UPDATE` per page load instead of per unique visitor per day] → still a single indexed-by-primary-key update; a queue can be added later without changing the contract.
- [Tests that assert `views` equals `0` after creation through the creator, or the old defaults, fail] → update them in the same task; fixtures created by factories use the column default `0` and are unaffected.

# Design

## Context

`content_nodes` holds the service attributes of a node as real columns next to the EAV value tables (`published_at`, `unpublished_at`, `is_active`, `user_id`; see [docs/content-model.md](../../../docs/content-model.md#node-service-attributes)). They are filled by `App\Content\ContentNodeCreator` (the author and the initial dates are not mass-assignable, `ContentNode`'s `#[Fillable]` lists only `uuid`, `content_type`, `slug`, `published_at`, `unpublished_at`, `is_active`), and the model validates them in its `saving` hook. The latest additions of this kind are small migrations (`add_unpublished_at_to_content_nodes_table`, `add_service_attributes_to_content_nodes_table`).

The legacy `node.views` (and the same column in `block` and `page`) is an integer counter that must be copied by the later import change. A future change will record views on the public site and may sort by them. See proposal.md - Why.

Observed constraints:

- `Eloquent` `increment()` updates `updated_at` as well (the builder adds the timestamp), which would make a view look like an edit and reorder lists sorted by "Update date". A counter must not do that.
- The key `views` is used as an ordinary integer field key in more than 20 test files and in main specs (`content-model`, `admin-content-fields`). Reserving it, or listing it among the service keys that win over fields in sorting and in the list settings (`NodeListSettings::SERVICE_COLUMNS`, `ContentNodeQuery::SORTABLE_SERVICE_KEYS`), would shadow such fields and force a rename of those fixtures.

## Goals / Non-Goals

**Goals:**

- A durable per-node counter that an import can seed and a later change can increase atomically.
- No effect on `updated_at`, visibility, the admin forms, queries or the public site.

**Non-Goals:**

- Recording views, anti-bot rules, deduplication, or a public/queued write path (a separate change).
- Showing, sorting or filtering by `views` in the admin list or in `ContentNodeQuery`; an index on `views`.
- Reserving the field key `views`. This is deferred together with the list/sorting change, which can rename the fixtures in one place.
- Per-language or per-day counters; the counter is one number per node.

## Decisions

1. **A column on `content_nodes`, not an EAV field.** An EAV integer value would need a field in every type (including blocks and pages), a row per node, and a read-modify-write that races. A column gives a single-statement `UPDATE ... SET views = views + n`. Alternative considered: an `integer` system field created with every type; rejected because it multiplies rows, cannot be incremented atomically through the value writer, and would need a backfill for every type.

2. **Type `unsignedBigInteger`, `NOT NULL`, default `0`, placed after `unpublished_at`.** `BIGINT` leaves headroom for a busy site; the default means the migration needs no backfill and the creator needs no argument. SQLite (tests) does not enforce `unsigned`, so the non-negative rule is also checked in the model (Decision 4).

3. **Not mass-assignable, set explicitly.** `views` stays out of `#[Fillable]`, like `user_id`. `ContentNodeCreator::create()` gets an optional named argument `int $views = 0` and assigns it on the model before `save()`, so an import (and tests) can seed it while HTTP requests cannot: `StoreContentNodeRequest` / `UpdateContentNodeRequest` have no `views` rule, and the controllers pass only validated data. Nothing in the node update path rewrites `views`, because it is not among the values nor the slug/date attributes that the update writes.

4. **Validation in the model.** `ContentNode::assertValid()` rejects `views < 0` with a `ValidationException` under the key `views`. The creator argument is validated by this same hook, so there is one place for the rule.

5. **An increment helper that does not touch timestamps.** `ContentNode::incrementViews(int $by = 1): void` runs `static::query()->whereKey($this->getKey())->toBase()->increment('views', $by)`: the base query builder bypasses the Eloquent `updated_at` bump and the model events, and the statement is atomic in the database. It also syncs the in-memory attribute (`$this->views` plus `$by`, original synced) so the model object is not dirty afterwards. A negative `$by` is rejected (`InvalidArgumentException`; this is a programming error, not user input). Alternative considered: `incrementQuietly()`; rejected because it only silences events and still writes `updated_at`.

6. **Migration.** `2026_11_06_100000_add_views_to_content_nodes_table` adds the column; `down()` drops it (the counters are lost, as for the other service columns). No backfill and no `ADMIN_EMAIL` dependency.

The components are described in [docs/content-model.md](../../../docs/content-model.md#node-service-attributes) (the section is extended in the implementation tasks).

## Risks / Trade-offs

- [Unindexed column] → Nothing filters or sorts by `views` yet; the later list/sorting change adds an index together with its queries.
- [A hot row] → Many concurrent increments of one popular node serialize on its row lock. Acceptable for this site; the tracking change may batch writes (cache or queue) without changing the column.
- [The key `views` stays free for user fields] → A user can still create a type field `views`; it lives in the value tables and does not clash with the column. The clash appears only when the list/sorting change starts treating `views` as a service key, and that change must then reserve the key and handle existing fields (the existing mechanism for earlier reserved keys applies).
- [SQLite does not enforce `unsigned`] → The model check covers the non-negative rule in tests and in code paths; direct SQL is outside the contract.

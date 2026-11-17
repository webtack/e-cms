# Design

## Context

`ContentNodeQuery::applySearch()` (`app/Content/ContentNodeQuery.php`) builds an OR of `EXISTS` sub-queries on the value tables, one per search field, with `LIKE … ESCAPE`. The slug is a column of `content_nodes`, so it never takes part. The admin node list (`ContentNodeController::paginate()`) passes only `search`, never `searchFields`; the public site does not search through this class (`SiteSearch` is separate). Described in [content-querying.md](../../../docs/content-querying.md#search) and [admin-content-nodes.md](../../../docs/admin-content-nodes.md#search-sorting-pagination).

## Goals / Non-Goals

**Goals:**
- Search in the node table also matches `content_nodes.slug`.
- No settings, form, or data migration changes.

**Non-Goals:**
- `node_ref` autocomplete, public site search, other admin tables.
- A "Slug" checkbox in the type's "Node list" section.

## Decisions

1. **Slug is added inside `applySearch()` as one more OR branch** (`orWhere('slug', 'like', $term)` with the existing escape), in the same group as the field conditions. Alternative: pass the slug through `settings.list.search` as a service key; rejected because it needs changes in `NodeListSettings`, the form, validation and existing data, for no gain: the slug is a node attribute, not a type field.
2. **Only the default path adds the slug.** When the caller passes `searchFields` explicitly, the set is exactly what was asked (the existing `searchFields` validation and tests stay as they are). The admin never passes it, so the table gets the slug. Alternative: always add it; rejected because it would silently widen an explicit programmatic query.
3. **No more "empty keys means nothing matches" for the default path.** With the slug always searched, a type without text fields is searched by the slug alone. For explicit `searchFields: []` the result stays empty (`1 = 0`).
4. **Same escaping and collation** as for values (`likePattern()`, `LIKE_ESCAPE`), so `%` and `_` are literal and the match is case-insensitive.

## Risks / Trade-offs

- [Type settings `search: ["title"]` no longer mean "title only"] → documented in `admin-content-types.md` and the spec; intended per the owner's decision.
- [`LIKE '%x%'` on the slug column does not use its index] → the node count per type is moderate and values are already searched the same way; full-text search stays out of scope.

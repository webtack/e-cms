# Proposal

## Why

The search box of the admin node list only looks into field values (`title` and the other text fields), so an editor who knows a node's `slug` (from a public URL, a link or a log) cannot find it by typing the slug. The slug is stored on the node itself (`content_nodes.slug`), outside the EAV value tables that the search walks, which is why it is missed. The type list already searches by slug; the node list should too.

## What Changes

- The search (`q`) of the node table (`/admin/content-types|page-types|block-types/{key}/nodes`) also matches the node's `slug`, case-insensitively, with `%` and `_` treated literally like for field values.
- The slug is always part of the search: it is added to the fields from `settings.list.search`, or to all text fields of the type when the setting is empty. It is not a checkbox in the "Node list" section of the type form, and no settings are migrated.
- A caller that passes `searchFields` explicitly to `ContentNodeQuery` keeps the exact set it asked for (no implicit slug), so the programmatic API stays predictable. The admin controller does not pass `searchFields`.
- A type without text fields stays searchable by slug instead of returning nothing for a default search.
- Out of scope: the `node_ref` autocomplete (`ReferenceLookupController`), the public site search (`SiteSearch`), and the other admin tables.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `admin-content-nodes`: the requirement "Search and sorting of nodes" now says the table search also matches the node slug, in addition to the type's search fields.

## Impact

- Code: `app/Content/ContentNodeQuery.php` (`applySearch()`); no change to `NodeQueryParams` or to the admin controller.
- Tests: `tests/Feature/Admin/ContentNodeIndexTest.php` and the `ContentNodeQuery` search tests; e2e `e2e/admin-content-nodes.spec.js` (search scenario), run only with `-g` for search.
- Docs updated: `docs/admin-content-nodes.md` (Search, sorting, pagination), `docs/content-querying.md` (Search), `docs/admin-content-types.md` (the "Search" bullet of the node list section, to say the slug is always searched).

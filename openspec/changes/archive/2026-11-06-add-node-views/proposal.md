# Proposal

## Why

The legacy site (kultura-poltava) keeps a view counter per node (`node.views`), and the owner treats these numbers as important data that must survive the migration. The content model has no place for a counter: `content_nodes` carries only the service attributes `published_at`, `unpublished_at`, `is_active` and `user_id`. The counter must exist before the legacy import (a separate, later change) can carry the numbers over, and before view tracking on the public site (also a separate change) can write to it.

## What Changes

- Add the service column `content_nodes.views`: an unsigned big integer, `NOT NULL`, default `0`. Existing nodes get `0`.
- `views` is a service attribute of the node (like `published_at`), not a type field: it belongs to nodes of all kinds (`content`, `page`, `block`), has no translations, and does not appear among the type's fields.
- `views` is not mass-assignable and never comes from an HTTP request (the create and update node forms ignore it), the same as `user_id`.
- Provide a way to bump the counter atomically without touching `updated_at` (a single `UPDATE ... SET views = views + n`).
- Let the code that creates nodes set the initial value, so an import can carry the legacy number over (`ContentNodeCreator::create(..., views: n)`).
- Reject a negative value at the model level.
- **Out of scope** (separate changes): recording views on the public site; showing, sorting or filtering by `views` in the admin list and in public querying; reserving the field key `views`.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-model`: the requirement "Service attributes of a node" gains `views` (default, non-negative, not accepted from requests, atomic increment keeps `updated_at`, migration gives existing nodes `0`).

## Impact

- Code: a new migration for `content_nodes`; `App\Models\ContentNode` (cast, validation, increment helper, doc block); `App\Content\ContentNodeCreator` (optional initial `views`); `ContentNodeFactory` (no change needed, default comes from the column).
- Tests: `tests/Feature/Content/ContentNodeTest.php` and `ContentNodeCreator` coverage, a request test that `views` is ignored in the admin create/update node requests (`tests/Feature/Admin/ContentNodeWriteTest.php`).
- Docs: `docs/content-model.md` (the "Node service attributes" section: the table row, the migration paragraph, the increment helper).
- No UI changes, so no e2e specs are affected.
- No dependencies, no config changes.

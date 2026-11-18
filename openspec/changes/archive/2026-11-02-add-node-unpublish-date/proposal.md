# Proposal

## Why

A content record can be postponed (`published_at` in the future), but it cannot be removed from the site by schedule: for temporary materials (promotions, announcements, notices) the editor has to disable the record by hand at the right moment. The menu already has a display interval (`published_at` + `unpublished_at`, the status «Завершено»), and this approach is proven; the records should be brought to the same model.

## What Changes

- A record gets an optional unpublish date `unpublished_at` (UTC, to the minute, as `published_at`). It must be later than `published_at`.
- Visibility of records: `is_active` and `published_at <= now` and (`unpublished_at` empty or `unpublished_at > now`). The interval is half-open: at the moment `unpublished_at` the record is already invisible. Computed on read, without cron and queues.
- The derived state of a record gets a fourth value «Завершено» (`expired`) by the order of checks: disabled, scheduled, expired, published.
- The form of the record (the block «Деталі»): a new field «Показувати до» (`unpublished_at`, a date and time selection, may be empty); the badge of the state in the form takes the new date into account. The label of the existing field «Дата публікації» does not change.
- The table of records shows the state «Завершено» (a badge); columns and sorting by `unpublished_at` are not added.
- The mode «only visible» of the selection, `ContentNode::scopeVisible()` and the search by slug for the public consumer take the unpublish date into account.
- `published_at` remains required, its behaviour (empty on creation = now, clearing is rejected) does not change.
- Ukrainian translations of the new labels (the key «Expired» already exists).

Not included in this change: an unpublish date for the content types themselves (`content_types`), the reserved key `unpublished_at` for user fields, time zone conversions, notifications and an automatic action when the date arrives, bulk operations over dates.

## Capabilities

### New Capabilities

None.

### Modified Capabilities
- `content-model`: the service attributes of a record are extended with `unpublished_at` (optional, later than `published_at`).
- `content-querying`: the visibility of records for the public selection takes `unpublished_at` into account.
- `admin-content-nodes`: the state «Завершено» in the table and in the badge of the form, the field «Показувати до» in the block «Деталі», creation and editing accept and check `unpublished_at`.

## Impact

- Code: a new migration (`content_nodes.unpublished_at` nullable datetime); `ContentNode` (cast, `Fillable`, the check of the interval in `saving`, `status()`, `scopeVisible()`); `ContentNodeCreator` (the parameter `unpublishedAt`); `StoreContentNodeRequest`/`UpdateContentNodeRequest` and `ContentNodeController` (rules, writing, passing to the page, the status); `ContentNodeFactory`; React: `status.ts`, `NodeServicePanel.tsx`, `ContentNodeForm.tsx`, `types.ts`, `Pages/Admin/ContentNodes/Index.tsx` (the badge «Завершено»); `lang/uk.json`, `lang/en.json`.
- Dependencies and environment variables do not change; the existing records get `unpublished_at = NULL` and behave as before.
- Documentation (`docs/`): update `docs/content-model.md` (service attributes, visibility), `docs/content-querying.md` (the section «Видимість запису»), `docs/admin-content-nodes.md` (the row of the table, the state, the contract of the form, the block «Деталі»); `docs/menus.md` — only a cross-reference to the shared visibility model, if appropriate.
- Tests: the migration changes the DB schema, so the task of the migration calls for a full run of the PHP tests; further only the areas `Content`, `ContentNode*` in `tests/Feature/Admin`; e2e — `e2e/admin-content-nodes.spec.js` (the form and the table of records) and, since the block «Деталі» is shared by the three kinds, the dependent specs of the pages and blocks by the table «Спільний код → спеки».

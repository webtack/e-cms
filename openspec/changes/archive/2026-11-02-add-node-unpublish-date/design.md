# Design

## Context

Records (`content_nodes`) have `published_at` (NOT NULL), `is_active` and `ContentNode::scopeVisible()`: `is_active AND published_at <= now()`. The menu has already extended this model with the action «unpublish by schedule»: `menu_items.unpublished_at`, visibility computed on read, the status `expired` and the check of the interval in the model and in the request (`MenuItem::status()`, `scopeVisible()`, `StoreMenuItemRequest::withValidator`). The motivation is in [proposal.md](proposal.md), the requirements — in [specs/](specs/).

Where the logic of the state of a record lives today: `ContentNodeController::status()` (PHP, for the table), `nodeStatus()` in `resources/js/Components/Admin/ContentNodes/status.ts` (the client, for the badge of the form before saving) and `NodeStatusBadge` in `NodeServicePanel.tsx`. The form and the panel «Деталі» are shared by the records of content, pages and blocks. The components described: `docs/content-model.md` (service attributes), `docs/content-querying.md` (visibility), `docs/admin-content-nodes.md` (the table, the contract of the form, the block «Деталі»), `docs/menus.md` (the first application of the model).

## Goals / Non-Goals

**Goals:**
- The same visibility model as in the menu: the interval `[published_at, unpublished_at)`, computed on read, without cron, queues and events.
- Zero changes for the existing records and for the code that does not know about the new date: `unpublished_at = NULL` means «no limit».
- One state calculator on the server (instead of a copy in the controller) and a mirror in the client for the badge before saving.

**Non-Goals:**
- An unpublish date for the content types themselves (`content_types`): by the owner's clarification the change concerns records.
- The reserved key `unpublished_at` for user fields: it is not a column of the table of records in the list, so `ContentField::RESERVED_KEYS` is not touched.
- A column and sorting by `unpublished_at` in the table; a filter by state.
- Making `published_at` optional (as in the menu): it would change the existing behaviour of records and is not needed for the unpublishing.
- A shared abstraction «visibility schedule» for `ContentNode` and `MenuItem`: two uses do not justify a trait.

## Decisions

### 1. The column `content_nodes.unpublished_at`, nullable, without an index

A new migration adds `dateTime('unpublished_at')->nullable()` after `published_at`; there is no backfill (the existing records remain without an unpublish date), the rollback drops the column. The index `(content_type, published_at)` remains: the condition on `unpublished_at` is `IS NULL OR > now`, it cuts few rows and works on top of the already selected range, so an additional index is not needed while there is no measurable problem.

**Alternatives:** a separate table of schedules (even for many intervals) — excessive for one pair of dates; a `status` column switched by a scheduler — the state diverges from the schedule on failures and erases the intent of the editor (the same rejected for the menu).

### 2. Visibility and state — on read, modeled on `MenuItem`

```
visible(node) = is_active
                AND published_at <= now
                AND (unpublished_at IS NULL OR unpublished_at > now)

status(node)  = disabled   if !is_active
                scheduled  if published_at > now
                expired    if unpublished_at IS NOT NULL AND unpublished_at <= now
                published  otherwise
```

`ContentNode::scopeVisible()` adds the third condition (an `orWhere` group, as in `MenuItem::scopeVisible()`), so `ContentNodeQuery` (the mode «only visible», the counter), `ContentNode::findBySlug(onlyVisible: true)` and the other consumers of the scope get the new behaviour without changes of their own code. The boundary: exactly at the moment `unpublished_at` the record is invisible (`> now`, strict), and at the moment `published_at` it is already visible (`<= now`): the interval is half-open and has no overlap. `ContentNode::status(?CarbonInterface $now = null)` is added, which returns `disabled | scheduled | expired | published`; the parameter `now` makes the method deterministic in tests, as in `MenuItem`. `ContentNodeController::status()` becomes a thin wrapper over it (or is removed, and the controller calls the model): the rule of the state lives in one place in PHP.

### 3. The interval is checked in three places, the arbiter is the model

- `ContentNode::assertValid()` (the hook `saving`, the last boundary for the code and the import): when both dates are set, `unpublished_at` must be later than `published_at`, otherwise a `ValidationException` for the key `unpublished_at`.
- `StoreContentNodeRequest` and `UpdateContentNodeRequest`: `unpublished_at` — `nullable|date_format:Y-m-d\TH:i` (the same `PUBLISHED_AT_FORMAT`, UTC without conversion) and the check of the order in `withValidator` (after the basic rules), so that the error stands next to the field. For creation an empty `published_at` means «now»: the comparison is made with the current minute, so an unpublish date in the past without a publication date is rejected (the scenario in the spec).
- `ContentNodeCreator::create()` receives the parameter `?CarbonInterface $unpublishedAt = null` (passed into `new ContentNode([...])`); the check is performed by the model on `save()`.

The comparison is made by the minute (`startOfMinute()`), as in `StoreMenuItemRequest`, because the form works to the minute.

**Alternative:** only the rule `after:published_at` in the request — it does not protect the code and the import and does not work when the publication date is substituted by the server; rejected.

### 4. The contract of the form and the controller

`ContentNodeController` adds `unpublished_at` to `SERVICE_KEYS`, to the props `node` of the pages `Create` (`null`) and `Edit` (`Y-m-d\TH:i` or `null`), to `store()` (`unpublishedAt: $this->publishedAt($request->validated('unpublished_at'))`; the existing `publishedAt()` already returns `null` for an empty value) and to `update()`. In `update()` an empty value means «clear» (`null`), and an absent key leaves the value as it is (`$request->has('unpublished_at')`): so the form (which always sends the key) can clear the date, while the code/test that does not know about the field does not erase it. The errors of the service attributes are already returned without the prefix `values.`, so the key `unpublished_at` gets next to the field without changes in `forForm()`.

### 5. The client: a mirror of the state and the new field

- `status.ts`: `NodeStatus` gets `'expired'`; `nodeStatus(isActive, publishedAt, unpublishedAt, now)` keeps the order of the server (disabled, scheduled, expired, published); an incorrect or empty `unpublishedAt` is counted as «no date», and an empty `publishedAt` — as «now» (as now). The comparison is by text `YYYY-MM-DDTHH:mm`, as in the existing function.
- `NodeServicePanel.tsx`: a row «Показувати до» (`datetime-local`, `id="unpublished_at"`, the label — the existing translation key `Show until`) right after «Дата публікації»; `STATUS_LABEL` adds `expired: 'Expired'` (the key is already in `lang/*.json`); the badge `expired` is rendered as `outline`, the difference — `data-status="expired"`. The error is shown by the same `message('unpublished_at')`.
- `ContentNodeForm.tsx` and `types.ts`: `unpublished_at` in `useForm`, in the props `node` and in the list of the keys of errors that light up the red dot of the button «Деталі» on narrow screens.
- `Pages/Admin/ContentNodes/Index.tsx`: there are no new columns; `NodeStatus` is extended, so the badge «Завершено» appears by itself. No changes in `DataTable`.

No new translation is needed: the labels `Show until` and `Expired` already exist in `lang/en.json` and `lang/uk.json`. This is checked by the task, not assumed.

### 6. Documentation and e2e

The service attributes, the visibility and the contract of the form are described in `docs/content-model.md`, `docs/content-querying.md`, `docs/admin-content-nodes.md`; `docs/menus.md` remains the source of the description of the menu (a link to it is not needed, only a short note that the logic matches, in `content-model.md`). The shared form of records covers three sections, so, apart from `admin-content-nodes`, the specs `admin-page-types` and `admin-block-types` are checked by the table «Спільний код → спеки» (`docs/e2e-testing.md`, the row about the form of records); no new specs and no new rows of the tables are needed, only the update of the description of the block «службова інформація» in the spec of the records.

## Risks / Trade-offs

- [A record is invisible for the site until the editor notices that the unpublish date has passed] → the state «Завершено» is visible in the table of records and in the badge of the form; the field «Показувати до» stands next to the publication date.
- [The change of `scopeVisible()` affects all consumers of the scope] → the condition is added only as a cut (`unpublished_at` NULL or in the future), so the existing records with `NULL` are visible as before; covered by the tests `ContentNodeTest` and `ContentNodeQueryTest`, including the boundary at the moment `unpublished_at`.
- [A discrepancy of the server and client computation of the state] → both in the same order of checks; the server is the arbiter, the client shows only a preliminary badge before saving (as now).
- [Time without a zone: UTC, as `published_at`] → the behaviour matches the existing one; the editor sees the time in the same form.
- [Duplication of the schedule logic with `MenuItem`] → deliberate (see Non-Goals); a third use will be the basis for a shared abstraction.

## Migration Plan

1. The new migration adds a nullable column; there is no data to transfer, `php artisan migrate` is fast (adding a nullable column).
2. Deployment: `migrate`, the usual front-end build (`npm run build`). There are no new environment variables or dependencies.
3. Rollback: `migrate:rollback` drops the column together with the unpublish dates; the records remain and are visible again by `is_active` and `published_at`.

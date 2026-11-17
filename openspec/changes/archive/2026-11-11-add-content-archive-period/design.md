# Design

## Context

- The public list of a `content` type is built in `PublicNodeController::list()`: it reads `PublicDisplaySettings::resolve()` (through `DisplayFields::settings()`), runs `ContentNodeQuery::paginate()` with `onlyVisible: true`, and renders `content/list`. The theme cannot change that query (hooks run after it); `home-news` re-queries on its own.
- `ContentNodeQuery` filters only by fields of the type, and a filter on a multiple field matches when **any** value matches. Sorting by a multiple field uses the **first** value (the begin of an event). Service dates are supported only in sorting.
- Dates are stored and compared in UTC; the `events` type has a multiple `datetime` field `date` (begin, optional end).
- `settings.public` is validated in three places that must agree: `UpdateContentTypeDisplayRequest` (shape), `ContentType::assertValidPublicSettings()` (keys and references), `PublicDisplaySettings::clean()` (stale references on read). Its form is `PublicDisplaySettingsSection` (built from `formOptions()`), which already groups controls with `FieldGroup` (`fieldset` + `legend`).
- Documentation: `docs/content-querying.md`, `docs/public-site.md`, `docs/admin-content-types.md`, `docs/theme-dcculture.md`, `docs/theme-templates.md`.

## Goals / Non-Goals

**Goals:**

- One reusable "not past / past" selection, used by the list page, the archive page and the home block, with exact complement semantics.
- Archive field and archive sort chosen per type in the admin, with no hidden rules between the two sorts.
- Behavior that does not depend on the `events` type: any `content` type with a date field can use it.

**Non-Goals:**

- Filters, tabs, or an "Archive" view in the admin nodes table.
- A generic filter UI or generic service-date filters in `filters`; the selection is a dedicated parameter.
- Automatic hiding of past nodes (`unpublished_at` is not written by this change); no cron, queue, or migration.
- Per-language boundaries or time zones other than UTC.

## Decisions

### D1. A dedicated `period` parameter in `NodeQueryParams`, not generic filters

`NodeQueryParams` gets `period: ?array{field: string, part: 'current'|'past', since: CarbonInterface}`. `ContentNodeQuery` applies it next to the filters as one condition for both the page and the counter.

- Field (`date` / `datetime`): `EXISTS (value >= since)` for the current part and `NOT EXISTS (value >= since)` for the past part. With a multiple field this is "any value is on or after the boundary", which keeps an event that has begun but not ended. A node without a value is past. For a `date` field `since` is compared as a date.
- `created_at`: `>= since` / `< since`.
- `unpublished_at`: `IS NULL OR >= since` for the current part, the exact negation (`IS NOT NULL AND < since`) for the past part.

Alternatives: (a) generic `filters` entries with service dates. Rejected: the filter semantics cannot express "empty value means current" and "no value means past", and the two lists would not be exact complements. (b) A scope on `ContentNode`. Rejected: the field case needs the value tables, which only `ContentNodeQuery` knows.

An invalid field is a `ValidationException` under `period.field`, like unknown filter fields today.

### D2. The boundary is the start of the current UTC day

The controller computes `since = now()->startOfDay()` (UTC is the application time zone) and passes it in. This keeps an event in the main list during its whole day, also events stored with `00:00` time (a day without a time). The boundary is a parameter, not read inside the query, so tests can fix it and the home block uses the same value.

### D3. Settings: `settings.public.archive = {field, sort}`

- `field`: an added `date` / `datetime` field, `created_at`, or `unpublished_at`. `sort`: the same shape as the main `sort`; the options of the admin select are `sortOptions` (already computed by `formOptions()`).
- `clean()` drops the whole `archive` when `field` is stale or its type changed, and drops only `sort` when that is stale. `resolve()` adds `archive: {field, sort}` for kind `content`, where an absent `sort` becomes the effective main sort.
- `LIST_KEYS` gets `archive`, so the existing check "a page or a block cannot have list keys" covers it, and `assertValidPublicSettings()` validates the field (offered fields only), the sort, and unknown keys. `UpdateContentTypeDisplayRequest` allows `archive` with `array:field,sort` and the same sort rules as the main sort.
- The main `sort` is unchanged and keeps its meaning. The archive has its own sort; there is no implicit "ascending for current, descending for archive" rule.

Alternatives: a single `sort` plus a derived direction (rejected by the owner: both sorts must be set separately); storing the archive in `settings.list` (rejected: it is a public, not an admin table setting).

### D4. The list page chooses the period from `?period=`

`PublicNodeController::list()`: when the resolved settings have an archive, `period=archive` selects `part = past` with the archive sort, anything else selects `part = current` with the main sort; with no archive the query is as before and the parameter is ignored. The paginator is created with `->appends(['period' => 'archive'])` in the archive case, so page links keep it. The view gets `period` (`current` / `archive`) and `hasArchive`. The theme builds the archive link from these, so the core keeps no markup.

### D5. The home "Afisha" block reuses the selection

A new theme hook (`hooks/home-afisha.php`, registered in `hooks.php` next to `home-news`) resolves the type `events`, reads its settings through `DisplayFields`, and runs the query with `part = current`, the main sort, and a small count, exactly like `home-news`. It adds `homeAfisha` to the home template variables. The block hard-codes the type key `events`, as `home-news` hard-codes `news`.

### D6. Admin form

`formOptions()` returns, for kind `content`, `archiveOptions` (the empty choice is a client concern; options are `unpublished_at`, `created_at` as service entries and the added `date` / `datetime` fields) and `current.archive` (`{field, sort}` or empty). `PublicDisplaySettingsSection` renders a new `FieldGroup` with the legend "Archive": the field select, and, only while a field is chosen, the sort field and direction selects (the same markup as the main "Default sort" group). `toPublicSettings()` sends `archive` only when a field is chosen. Service labels use translation keys (`Unpublishing date`, `Creation date`, `Do not use`, `Archive`, `Archive sort`) in `lang/en.json` and `lang/uk.json`.

### D7. Data for `events`

A data step (not a schema migration) sets `settings.public.sort = {date, asc}`, `settings.public.archive = {field: date, sort: {date, desc}}` and adds `date` to `settings.list.visible`. It is described in the tasks and performed through the admin form or a one-off command; the main `sort` must be changed too, otherwise the existing `date desc` would show the farthest event first.

## Risks / Trade-offs

- [A record dated far in the future stays in the main list for a long time] → accepted: that is the meaning of "not past"; sort `asc` puts it last.
- [`created_at` / `unpublished_at` are offered although `date` fields are the usual choice] → they are explicit options of the owner; their semantics are tested.
- [A multiple field with more than two values or values in any order] → the rule "any value on or after the boundary" does not depend on the order; the sort still uses the first value, as everywhere else.
- [Two sorts to keep in sync] → each is independent by design; a type without an archive sort falls back to the main sort so nothing breaks.
- [The home block hard-codes `events`] → same trade-off as `home-news`; a different key is a theme edit.
- [Existing `events` setting `date desc`] → D7 changes it as part of the change; without that step the page order would be wrong though the behavior is correct.

## Migration Plan

No schema migration. Deploy the code, then apply the `events` data step (D7). Rollback: remove `settings.public.archive` from the type; the list returns to its previous behavior (restore the old main sort if needed).

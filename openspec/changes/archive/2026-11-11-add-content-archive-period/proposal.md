# Proposal

## Why

The public list of a `content` type has one sort and no notion of "past". For the `events` type (736 active records, 732 of them already past) this means the list cannot be ordered "nearest first" without showing the past at the top: the current setting is `date desc`, so the farthest future event comes first and the archive fills 70+ pages behind it. The owner wants the nearest upcoming events first and the past ones moved to a separate archive page, configurable per type in the admin panel.

## What Changes

- A type of kind `content` gets an optional **archive** in its public display settings: `settings.public.archive = {field, sort}`. `field` is the date the list is split by: `created_at`, `unpublished_at`, or an added field of type `date` / `datetime`. `sort` is the sort of the archive list (`{field, direction}`), set independently of the main `sort`.
- The public list `/{type slug}` shows only records that are **not past** by the archive field; `/{type slug}?period=archive` shows only the past ones, in the archive sort. Both keep the type's `per_page` and the paginator, and the paginator links keep `?period=archive`.
- "Past" is decided against the **start of the current day (UTC)**, so a record stays in the main list during its whole day. For a multiple field (`date` holds the begin and the optional end) a record is not past while **any** of its values is not past, so an event that has begun but not ended stays in the main list.
- A record without a value in the archive field goes to the archive, except for `unpublished_at`, where an empty value means "no end of display" and the record stays in the main list.
- `ContentNodeQuery` gets a dedicated `period` parameter (not-past or past records by a date, with a boundary moment). It is not a generic filter: it must treat an empty value and a multiple field in a fixed way, and it supports the service dates `created_at` and `unpublished_at`, which the generic filters (fields of the type only) do not.
- The "Display" tab of the type form gets an "Archive" block (the archive field and the archive sort), wrapped in `fieldset` / `legend` like the other groups of the tab (`FieldGroup`). Without a chosen field there is no archive: `?period=archive` is ignored and the list is unchanged.
- The `dcculture` theme shows one link "Archive" after the list of a type that has an archive, and the home page block "Afisha" reads the same not-past query. The `events` type is configured by data: main sort `date asc`, archive field `date`, archive sort `date desc`.
- No admin tab "Archive" for the nodes table: the table has no filters or tabs, and the editor already sorts by a visible `date` column (adding the column to `settings.list.visible` of `events` is a data setting, not code).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-querying`: the `period` selection (not-past and past records by a date field or a service date); reading the public settings of a type includes `archive` and the effective archive sort.
- `public-site`: the list of a content type honors `?period=archive` (the not-past list and the archive list, paginator keeps the parameter).
- `admin-content-types`: the "Archive" block (archive field and archive sort) on the "Display" tab, validated and saved with `settings.public`.
- `dcculture-theme`: an archive link after the list of a type with an archive and the home "Afisha" block reading the not-past records.

## Impact

- Code: `app/Content/ContentNodeQuery.php` and `app/Content/NodeQueryParams.php` (the `period` parameter), `app/Content/PublicDisplaySettings.php` (`archive` key: `clean()`, `resolve()`, `formOptions()`), `app/Models/ContentType.php` (`assertValidPublicSettings()`), `app/Http/Requests/Admin/UpdateContentTypeDisplayRequest.php`, `app/Http/Controllers/Public/PublicNodeController.php`, `resources/js/Components/Admin/ContentTypes/PublicDisplaySettingsSection.tsx` (and `toPublicSettings`), translations (uk/en), `themes/dcculture` (list template, home "Afisha" block hook and template, translations). Theme code has no tests by project rule.
- Data: `events` settings (`public.sort`, `public.archive`, `list.visible`) are set by a data step, not by a migration of the schema.
- Docs to update: `docs/content-querying.md`, `docs/public-site.md`, `docs/admin-content-types.md`, `docs/theme-dcculture.md`, `docs/theme-templates.md` (the new template variables), `docs/e2e-testing.md` (only if a new spec appears; the existing `admin-content-types` spec covers the tab).
- Tests: PHP Feature tests for the query, the settings and the public controller; e2e `admin-content-types` for the "Archive" block.

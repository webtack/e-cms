# Proposal

## Why

The public part of the site does not exist yet, and the templates will need to know which added fields of a type to show in the card of the list and on the page of the record, how to sort the list, how many records to show and whether a paginator is needed. Right now `content_types.settings` has only `list` (the admin list), so these decisions would have to be hard-coded into the templates. The settings must be stored in the data of the type, so that the template only reads and resolves them.

## What Changes

- A new key `settings.public` in the settings of the type with the fields:
  - `list_fields` — the added fields that are shown in the card (teaser) of the record in the list; by default empty (only `title`);
  - `page_fields` — the added fields that are shown on the page of the record; the key **absent** means «all added fields» (dynamically: a new field appears automatically), after an explicit choice — a list;
  - `sort` — `{field, direction}` for the public list; by default `published_at` descending;
  - `per_page` — the number of records per page, `0` (by default) — all;
  - `pagination` — whether to show the paginator (yes/no); ignored when `per_page = 0`.
- The «added» fields are the user fields (`is_system = false`) that hold the values of records. `title`, the service attributes of the record, the meta fields (`meta_*`) and the values of the section of the type (`section_*`) are always available to the template and are not configured.
- `list_fields`, `sort`, `per_page`, `pagination` — only for the kind `content` (the public list exists only for it); for `page` and `block` only `page_fields` is configured.
- The section «Публічне відображення» in the form of the type: a switch «Усі додані поля» with checkboxes for the page, checkboxes for the card, the sorting, the number per page, the paginator.
- The selection of records can have the mode «all» (`per_page = 0`) with a separate ceiling `content.max_all` (separate from `max_per_page`).
- Saving the form of the type updates only the key `public`, without touching `list` and the other keys of `settings`; references to deleted or non-sortable fields are discarded on reading.
- The public routes and templates themselves are **outside** the scope of this change: it only stores and returns the settings.

## Capabilities

### New Capabilities

None.

### Modified Capabilities
- `content-model`: the key `settings.public` (the structure, the default values, the restrictions by kind, the check on saving).
- `content-querying`: the mode «all records» for `per_page = 0` with a ceiling, and reading of the public settings (the effective set of fields, the sorting).
- `admin-content-types`: the section «Публічне відображення» in the form of the type, its behaviour by kinds and saving.

## Impact

- Code: `app/Content/` (a new class of reading/cleaning of the public settings by the pattern of `NodeListSettings`), `app/Content/ContentNodeQuery.php`, `app/Content/NodeQueryParams.php`, `app/Models/ContentType.php` (the check of `settings.public`), `ContentTypeController`, `StoreContentTypeRequest`/`UpdateContentTypeRequest`, `config/content.php` (`max_all`), the pages `resources/js/Pages/Admin/ContentTypes/` (Create/Edit), `lang/uk.json`, `lang/en.json`.
- Tests: Feature tests of the settings of the type and of the selection; e2e `e2e/admin-content-types.spec.js`.
- Docs: update `docs/content-model.md` (the section «Налаштування типу»), `docs/content-querying.md`, `docs/admin-content-types.md`, `docs/e2e-testing.md` (the description of the spec), `docs/README.md` if needed.
- There are no DB migrations: `settings` is an existing JSON column.

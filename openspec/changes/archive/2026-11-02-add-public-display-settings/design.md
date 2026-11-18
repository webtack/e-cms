# Design

## Context

The settings of a type live in the JSON column `content_types.settings`. The key `list` (the admin list) is read and cleaned by `App\Content\NodeListSettings`; `ContentTypeController::mergeListSettings()` replaces only it on saving, and `ContentType::assertValidListSettings()` checks the keys against the fields of the type. The section «Список записів» in the form (`NodeListSettingsSection`) is shown only in the mode `edit`. `ContentNodeQuery::paginate()` limits `perPage` to the values `[1, max_per_page]`; there is no mode «all». There are no public routes and templates yet (the motivation is in proposal.md).

## Goals / Non-Goals

**Goals:**
- Store the public settings next to `list`, by the same pattern (checking on saving, cleaning on reading, a separate key in the merge).
- Give the template one «effective» result (the fields of the card and the page, the sorting, `per_page`, `pagination`) without the need to know about the default values.

**Non-Goals:**
- Public routes, templates, rendering of the fields, caching.
- The choice of meta fields and service attributes (they are always available).
- The settings of search, filters or the choice of sorting by the visitor.

## Decisions

**1. The key `settings.public`, not a separate table and not an extension of `list`.** `list` describes the admin, `public` — the site; different default values (the sorting `created_at asc` versus `published_at desc`). No migration is needed. The alternative «shared settings» was rejected: it would force the admin table and the site to have the same columns.

**2. `page_fields` absent = «all added» (dynamic).** The key is not written while the administrator does not switch off «Усі додані поля»; switching it back on removes the key. This gives the behaviour «a new field appears automatically» without migrations and without walking through all types when a field is added. The alternative — an explicit list from the moment the type is created — would need the list to be extended with every new field. `list_fields` has no such mode: empty = only `title`.

**3. The admissible fields — only the added ones.** `is_system = false`, the field holds the values of records (`ValueOwner::forNodesOfType`), the key is not reserved. `title`, `meta_*`, `section_*` and the service attributes are always available to the template, so they are not stored in the settings.

**4. The class `PublicDisplaySettings` modeled on `NodeListSettings`** (`clean()`, `formOptions()`, plus `resolve()` — the effective result for the template). For sorting it uses the same rules as the admin list (`NodeListSettings::sortable()`, `ContentType::LIST_SORT_SERVICE_KEYS`), but the default value is `published_at desc`. The check on saving — `ContentType::assertValidPublicSettings()` next to `assertValidListSettings()`; reading silently discards stale references.

**5. Restrictions by kind in the model.** `list_fields`, `sort`, `per_page`, `pagination` for `page`/`block` are rejected with a `ValidationException` (as the `slug` of a type); the form does not show them.

**6. `per_page = 0` is a branch in `ContentNodeQuery`, not a «large per_page».** For `0` the query takes `limit(content.max_all)` and returns a `LengthAwarePaginator` on one page, so that the shape of the result stays the same. `max_per_page` (100) applies only to `per_page > 0`. `NodeQueryParams::$perPage` now accepts `null` (by default) and a number; `0` was previously raised to 1, but no call in `app/` passes it, so the change of semantics is safe. `max_all` by default is 1000. The upper bound of the saved `per_page` is `max_per_page`.

**7. The effective `pagination` with `per_page = 0` is «no».** The saved value does not change (returning `per_page > 0` restores the choice), but `resolve()` gives `false`; in the form the switch is disabled. By default `pagination = true` (for `per_page > 0` the usual behaviour).

**8. The form.** A new section «Публічне відображення» next to `NodeListSettingsSection` in `ContentTypeForm` (only `edit`); `ContentTypeController::edit()` adds `publicOptions`; `mergeListSettings()` is generalized to the replacement of one key, so that `list` and `public` do not erase each other. `StoreContentTypeRequest` extends `array:list` to `array:list,public` and adds the rules of the form.

**9. Default values are not stored.** The form does not write to `settings.public` the values that coincide with the typical ones: an empty `list_fields`, the sorting `published_at desc`, `per_page = 0`, `pagination = true`; an empty `public` is removed (as `list`). Reading (`resolve()`) substitutes the typical values, so the result is the same. The consequence: a change of the typical values in the code affects the types without explicitly saved values. The exception — the kinds without a list (`page`, `block`): the sent keys of the list are not discarded as typical, so that the model rejects them.

The components described: [content-model.md](../../../docs/content-model.md), [content-querying.md](../../../docs/content-querying.md), [admin-content-types.md](../../../docs/admin-content-types.md).

## Risks / Trade-offs

- [`per_page = 0` on a large type returns up to `max_all` records] → a separate ceiling `content.max_all`; the ordering is kept, and an excess is silently cut (the template can show the paginator by hand when needed).
- [The dynamic «all» means that a new field is immediately public] → this is a deliberate decision; a field can be hidden by an explicit list.
- [Two sets of `*_fields` complicate the form] → `list_fields` is a simple checkbox list, «Усі» exists only for the page.
- [A deleted field remains in the saved JSON] → reading discards it; it is cleaned at the next saving of the form.

## Migration Plan

There are no migrations: the JSON column `settings` already exists. The types without the key `public` work with the typical values (`resolve()`); the first saving of the form writes the key only if a value differs from the typical ones. Rollback: the key `public` in `settings` is ignored by the code of the previous version and can be left as it is.

# Design

## Context

See `proposal.md`. The current state (read in the code):

- `settings.public.meta` (`title`, `description`, `image`) is edited on the tab «Відображення» ("Display"): `PublicDisplaySettings::formOptions()` gives `metaFields` and `current.meta`, `TypeDisplayForm`/`PublicDisplaySettingsSection` show the sub-block, `ContentTypeDisplayController` saves it together with the values of the section, `UpdateContentTypeDisplayRequest` accepts `meta`.
- The tab «SEO» exists only for `content`: the routes `content-types/{type}/seo` and `…/values` stand outside the loop over the kinds with `defaults('kind', 'content')`; `ContentTypeController::typeValuesProps()` gives the fields of the panel only for `kind === 'content'`.
- The owners of the values are set by `SystemFields::DEFINITIONS`: `meta_*` have `owners`: `content => [node, type]`, `page => [node]`. `ValueOwner::forType($type)->fields()` and `ContentValueWriter` filter the fields by `owns()`. The fields `meta_*` in the types `page` already exist (created together with the type).
- `App\Theme\PageMeta::make($values, $type)` takes the `meta_*` of the record → the chosen field of the type → the settings of the site. `PublicData` passes `$extra['type']` both for the list of the type (without `values`) and for the record.
- `ContentValueReader::loadForType($type, $keys, $lang)` reads the values of the type itself.

## Goals / Non-Goals

**Goals:**
- The block «Мета-дані» ("Meta data") on the tab «SEO» (`content` and `page`) as a separate section with a border, without its own button: it is saved together with the values `meta_*` of the type by the single button of the tab.
- The tab «SEO» and the values `meta_*` of the type for `page`.
- The chain: the record → the chosen fields → (for the heading) the `title` of the record → `meta_*` of the type → the settings of the site; `<title>` without the suffix of the name of the site.

**Non-Goals:**
- New fields of metadata, `og:title`/`og:description`, the translation of the values of the type (the tab works with the base language, as now).
- SEO for the types `block`.
- A change of the format of `settings.public.meta`.
- The suffix « — {назва сайту}» ("— {name of the site}") in `<title>`: if it is needed, the hooks or the templates of the theme add it.

## Decisions

1. **`page` becomes an owner of `meta_*` of the type through `SystemFields`.** In `DEFINITIONS` for `meta_title`, `meta_description`, `meta_image` it becomes `'page' => [node, type]`. No migration is needed: the fields already exist in the types `page`, and the owner is defined by the definition, not by a column. The type `block` does not change. The alternative (a separate table or a bypass of `owns()`) was rejected: the rule of the owner is the single source of truth.
2. **The tab «SEO» for `page`.** The routes `seo` and `values.update` are moved into the loop over the kinds `content`/`page` (the names `content-types.seo`, `page-types.seo`, `content-types.values.update`, `page-types.values.update`); for `block` they do not exist (404). `typeValuesProps()` and `ContentTypeValuesController` define the fields through `ValueOwner::forType()` and the panel (`seo`/`section`), and not through `kind === 'content'`; for `page` the panel `section` is empty, because the `section_*` of the type do not belong to it. `ContentTypeNav` shows «SEO» for `content` and `page`.
3. **The block «Мета-дані» lives on the tab «SEO» and is saved together with the values `meta_*` in one request.** The tab is one form with one sticky panel «Скасувати / Зберегти» ("Cancel / Save"); the block is rendered in a `fieldset` with a border (`rounded-lg border p-4`) below the fields `meta_*`, without its own button. As with the tab «Відображення», `PUT /{section}/{key}/values` (`ContentTypeValuesController::update`, the names `content-types.values.update` / `page-types.values.update`; for `block` there are none) in one transaction writes the values `meta_*` of the type and, when the request contains `settings.public.meta`, merges only this key into `settings.public` (the check through `ContentType::assertValidPublicSettings()`; it does not touch the other keys of `public` and `settings`; an empty `meta` is removed, an empty `public` is not saved). An error in any part saves nothing; the errors of the values are shown next to the fields, the errors of `meta` — next to the block. The response is `back()` with `success`. The separate route `…/seo/meta`, `updateMeta()` and `UpdateContentTypeMetaRequest` are removed. The alternative — a separate button and a separate request — was rejected: a second button breaks the sticky action panel and splits one save of the tab into two.
4. **The meta is removed from the tab «Відображення».** `PublicDisplaySettings::formOptions()` stops giving `metaFields` and `current.meta` (the new `metaOptions()` gives them for the tab «SEO»); `PublicDisplaySettingsSection` and `TypeDisplayForm` — without the sub-block; `toPublicSettings()` does not add `meta`. `UpdateContentTypeDisplayRequest` stops accepting `settings.public.meta`, and `ContentTypeDisplayController::mergePublicSettings()` **keeps the existing `meta`** when saving the rest of `public` (otherwise «Відображення» would wipe the meta settings). A request with `meta` on the tab «Відображення» is rejected with a validation error.
5. **The chain in `PageMeta`.** For the description and the image: `meta_*` of the record → the chosen field → the value of the type (`ContentValueReader::loadForType($type, ['meta_title','meta_description','meta_image'])`, the base language) → the settings of the site. For the heading: `meta_title` of the record → the chosen field (a field of the type «рядок» — "string") → the `title` of the record (`$values['title']`) → `meta_title` of the type → the name of the site. The value of the type is cleaned by the same methods as the field: the heading — `text()`, the description — `text()` + the shortening `shorten()` to `meta_description_max`, the image — `imageUrl()`. On the page of the list (without `values`) the steps of the record, the fields and the `title` of the record are empty. The loading of the chosen fields and of the values of the type is done only for the types that have `meta`/`meta_*`, so as not to add extra requests. There is no service item `title` in the choice «Заголовок» ("Heading"): the `title` of the record is already taken by default.
6. **`<title>` without the suffix of the name of the site.** `PublicData` no longer assembles «{title of the record} — {name of the site}»: the heading of the document is the result of the chain as it is; the name of the site only when there is no value at all (the home page, 404), and without it — the name of the application. The addition of the suffix is the job of the hooks or of the templates of the theme.
7. **The contract of `$meta` does not change** (three keys). `docs/theme-templates.md` only clarifies the sources.

## Risks / Trade-offs

- [A change of `<title>`: the suffix « — {назва сайту}» disappears] → an explicit decision of the owner; the theme can add it through a hook or in the template; update the docs, the tests and the e2e.
- [Saving «Відображення» would wipe `meta`] → `mergePublicSettings()` keeps `meta`; a separate test.
- [The values `meta_*` of the type `page` could not exist before] → there are no new values, no migration is needed; the backfill migration remains a snapshot and does not change.
- [Two sources of errors in one request] → a transaction; the errors of the values and of `meta` are returned in one response and shown next to their fields (check in the e2e).
- [Access] → new routes under `can:types`, as the rest of the tabs of the type.

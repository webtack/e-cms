# Proposal

## Why

After the previous change (`stay-on-edit-preview-home-meta`), the choice of meta fields sits on the tab «Відображення» ("Display"), where it does not belong: it is an SEO setting. Types `page` do not have a tab «SEO» with the values of the type itself. The site also does not take metadata written on the tab «SEO» of a type: the metadata chain has no such source.

## What Changes

- The block «Мета-дані» ("Meta data": the choice of fields for the heading, the description and the image, `settings.public.meta`) **moves** from the tab «Відображення» to the tab «SEO» of the type. It is a separate section with a border under the values `meta_*` of the type and has no button of its own: the tab «SEO» is one form with one sticky panel «Зберегти» ("Save"), which saves the values `meta_*` of the type and `settings.public.meta` in one atomic request, without touching the rest of `settings`. The block disappears from the tab «Відображення»; saving that tab does not wipe the saved `meta`.
- Types `page` get a tab «SEO» (`/admin/page-types/{key}/seo`) in the same way as `content`: the values `meta_title`, `meta_description`, `meta_image` **of the type itself** and the block «Мета-дані». For this, the type `page` becomes an owner of the values of these three system fields (the fields already exist in page types; no migration is needed). The type `block` has no tab.
- The metadata chain of a public page gets a new source. The order for each value separately: `meta_*` of the record → the field chosen in the block «Мета-дані» → (only for the heading) the `title` of the record → `meta_*` of the type itself (the tab «SEO» of the type) → the settings of the site. A page without a record (the list of records of the type) takes `meta_*` of the type, then the settings of the site; the home page and 404 take only the settings of the site.
- The document heading `<title>` no longer appends « — {назва сайту}» ("— {name of the site}"): the value from the chain is used as is (for a record without a meta heading — the `title` of the record). If the suffix is needed, the hooks or the templates of the theme add it.
- **BREAKING (behaviour):** the `<title>` of pages that previously looked like «{title} — {назва сайту}» now equals «{title}».

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-model`: «Системні поля метаданих і розділу» ("System metadata and section fields") and «Власники значень поля» ("Owners of field values") — the fields `meta_*` of the type `page` belong both to records and to the type itself.
- `admin-content-types`: «Вкладки «Відображення» і «SEO» типу» ("Tabs «Display» and «SEO» of the type") (the tab «SEO» is in `content` and `page`), «Секція «Публічне відображення»…» ("Section «Public display»…") (without the sub-block «Мета-дані»); a new requirement about the block «Мета-дані» on the tab «SEO».
- `admin-page-types`: «Меню розділу типу сторінки» ("Section menu of a page type") — the item «SEO».
- `public-site`: «Мета-дані сторінки…» ("Page metadata…") — a new source (`meta_*` of the type) and the choice of `title`.

## Impact

- Backend: `App\Content\SystemFields` (owners of `meta_*` for `page`); `ContentTypeController` (`seoTab()`, `typeValuesProps()` are no longer limited to the kind `content`; the props of the block «Мета-дані» for the tab «SEO»; with `displayTab()` they are removed); `ContentTypeValuesController` (and the routes `…/values` for `page-types`; `update` atomically saves the values and `settings.public.meta`, only `content` and `page`); `routes/web.php`; `PublicDisplaySettings` (`formOptions()` without the meta part, a separate method of options for SEO), `ContentType::assertValidPublicMeta()`, `UpdateContentTypeDisplayRequest` and `ContentTypeDisplayController::mergePublicSettings()` (no longer accept `meta` and do not wipe it); `App\Theme\PageMeta` (the chain with `meta_*` of the type through `ContentValueReader::loadForType()`), `App\Theme\PublicData` (the heading without the suffix with the name of the site).
- Frontend: `PublicDisplaySettingsSection.tsx`/`TypeDisplayForm.tsx` (without the sub-block), `Pages/Admin/ContentTypes/Seo.tsx` and a new component of the block «Мета-дані» with a border, `ContentTypeNav.tsx` (the item «SEO» for `page`), translations.
- Tests: Feature (`ContentTypeDisplayTest`, `ContentTypeValuesTest`, `PageTypeTest`, `SystemFieldsTest`, `TypeValuesTest`, Theme/Public), e2e (`admin-content-types`, `admin-page-types`, `public-site`).
- Docs: `docs/admin-content-types.md`, `docs/admin-page-types.md`, `docs/content-model.md`, `docs/content-values.md`, `docs/public-site.md`, `docs/theme-templates.md`, `docs/e2e-testing.md`.

## Assumptions

- The tab «SEO» of the type `page` has the same three value fields of the type as in `content` (the owner's decision).
- The block «Мета-дані» has no button of its own: the values `meta_*` of the type and `settings.public.meta` are saved in one atomic request (as on the tab «Відображення»), so that there is one sticky action panel.
- For the heading, the `title` of the record comes after its own `meta_title` and the chosen field, but **before** `meta_title` of the type: `meta_title` of the type takes effect only where the record has no `title` (the list page of the type). There is no separate item «Заголовок запису» ("Record heading") in the choice, because `title` is used by default.
- The name of the site in `<title>` is used only where there is neither a record nor a meta heading (the home page, 404); there is no suffix added to the values of the record and the type.
- Existing values of `settings.public.meta` remain valid (the format is the same).

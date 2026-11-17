# Tasks

## 1. Readers with fallback

- [x] 1.1 Add the fallback mode to `ContentValueReader` (`load`, `loadForType`) with per-field and whole-list/whole-composed fallback and a constant query count. Verify with `docker compose exec app php artisan test --compact --filter=ContentValueReader` for the `content-values` delta scenarios; English docblocks with `@example`.
- [x] 1.2 Add the fallback to `ContentNodeQuery` sorting (`COALESCE` over translation and base) and to `MenuTree`, the term reads, `FileDescriptions`, field labels and a `SiteSettings::forLang()` view. Verify with `--filter=ContentNodeQuery`, `--filter=MenuTree`, `--filter=Term`, `--filter=SiteSettings` and `--filter=FileDescription` for the `content-querying`, `menus` and `taxonomy` deltas.
- [x] 1.3 Give `PresentContext` the reading language and fallback flag and make field types' `present()` (terms, files, node and type references, labels) use them, and make `FieldRenderer` pass each field template a detached copy of the field whose `name` is the label in the reading language (never saved). Verify with `--filter=Present`, `--filter=FieldRenderer` (label in the reading language, fallback to the base name, stored name unchanged) and `--filter=NodeRef`/`--filter=TypeRef` (the `theme-rendering` delta); `grep -rn '\$field->name' themes` shows only reads of `name` (no other label source); update `docs/content-values.md`, `docs/content-querying.md`, `docs/taxonomy.md`, `docs/menus.md`, `docs/media-library.md`.

## 2. Routing

- [x] 2.1 Reserve language codes in the path namespace (`PublicPaths`, page and type slug validation, language add validation) and update `docs/content-model.md`. Verify with `--filter=PublicPaths`, `--filter=ContentLanguageAdmin` and the page/type slug tests for the `content-model` delta.
- [x] 2.2 Add `ResolvePublicLanguage` (strip, record, 301 for the base prefix, service path guard, cached active codes with invalidation), the scoped `LocalizedUrl`, `SetLocale` changes for public requests, and `<html lang>`. Verify with new `--filter=PublicLanguageRouting` tests for every scenario of `public-languages` (prefix, home, record, inactive, off, base redirect with query, service paths) and then the full `docker compose exec app php artisan test --compact` (global middleware and providers).
- [x] 2.3 Update `docs/public-site.md`, `docs/content-languages.md` (routing, reading language, fallback). Verify the documented examples.

## 3. Presentation

- [x] 3.1 Prefix internal links: menus, breadcrumbs, cards and lists, references, `url` values, search form and results, the sitemap page, the paginator path, and the rich text DOM pass. Verify with `--filter=PublicLinks` including relative, absolute, anchor, service and file links, pagination under the prefix, and `route:cache` on and off.
- [x] 3.2 Make `SiteSearch` look through all languages (excerpt from the matching row, title with fallback). Verify with `--filter=SiteSearch` for the `public-site` delta.
- [x] 3.3 `PublicData`: languages list, `translated` flags, alternates and canonical; print them in the core `html` shell; add the switcher partial to the default theme. Verify with `--filter=PublicHead` (alternates, `x-default`, canonical for untranslated pages, absent on single-language sites), `php artisan theme:check`, and a visual check of the default theme in two languages.
- [x] 3.4 Update `docs/theme-templates.md`, `docs/site-themes.md`, `docs/README.md`; run `npm run test:e2e -- e2e/public-site.spec.js` and `e2e/smoke.spec.js` (global middleware touches every request). Verify the docs against the implemented variables.

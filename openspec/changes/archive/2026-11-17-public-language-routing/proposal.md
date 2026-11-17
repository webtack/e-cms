# Proposal

## Why

Translations can now be entered in the admin panel, but the public site still reads only the base language, fixes its interface language to the application locale, and has no way to address a page in another language. Search engines and visitors need real language addresses, consistent fallback to the base language, and links that stay in the chosen language.

## What Changes

- **Addresses:** `/en/...` prefix for every active additional language (home, pages, records, search, sitemap page); the base language stays unprefixed; `/{base}/...` redirects (301) to the unprefixed address; service paths are never prefixed. The slug of a node is one for all languages.
- **Reading language and fallback:** the reading language comes only from the address. Values, term names, menu items, field labels, site settings, file descriptions, type section and meta values are read with **per-field fallback to the base language**; a multiple field or composed value falls back as a whole. A visible node is always served in every language. Admin, writers and APIs keep the strict, no-fallback reading.
- **Interface strings and `<html lang>`** follow the reading language when interface translations exist.
- **Links stay in the language:** menus, breadcrumbs, cards, pagination, search, sitemap page, references, `url` fields and rich text links get the prefix.
- **Language switcher** data for every theme and default-theme markup.
- **SEO signals:** `hreflang` alternates with `x-default`, and canonical rules (a page without a translation is canonical to the base-language address).
- **Search** looks through all languages of a node; titles are shown in the reading language.
- **Reserved codes:** language codes are reserved first segments (no page or type slug may equal one, and vice versa).
- `sitemap.xml` is unchanged (base-language addresses only).

## Capabilities

### New Capabilities
- `public-languages`: language prefix routing, reading language, whole-page fallback, document language, link prefixing, switcher data, alternate and canonical links.

### Modified Capabilities
- `public-site`: the search covers all languages of a node.
- `theme-rendering`: referenced node content reads with fallback; presented values (term names, file descriptions, labels) use the reading fallback.
- `content-values`: an explicit fallback reading mode.
- `content-querying`: sorting by a translated value with fallback.
- `taxonomy`: reading a term with fallback.
- `menus`: menu tree with fallback.
- `content-model`: language codes are reserved first segments of public addresses.

## Impact

- Code: a global middleware that resolves and strips the language prefix (and redirects the base prefix), a scoped `LocalizedUrl` service, fallback modes in `ContentValueReader`, `ContentNodeQuery`, `PresentContext`, `MenuTree`, the term reader, `FileDescriptions`, labels and `SiteSettings` (reading language passed explicitly), `SetLocale` for public routes, `PublicData` (languages, alternates, canonical), the `html` shell, link prefixing in `MenuRenderer`, `Breadcrumbs`, `FieldRenderer`, pagination, search and a rich text link pass, `SiteSearch`, slug validation of pages and types and language codes; the language switcher partial in the default theme.
- Database: no migrations.
- Docs: update `docs/public-site.md`, `docs/content-model.md`, `docs/content-values.md`, `docs/content-querying.md`, `docs/taxonomy.md`, `docs/menus.md`, `docs/theme-templates.md`, `docs/site-themes.md`, `docs/media-library.md`, `docs/content-languages.md`, `docs/README.md`.
- Tests: Feature tests for routing, readers, shell and links; the full PHP suite (global middleware and providers); e2e `public-site` and `smoke`. Theme templates have no tests (checked with `php artisan theme:check` and visually).
- The `dcculture` theme is not given a switcher here (its markup follows its own mock-up); it receives the same data and can place it in a follow-up.
- Depends on all previous changes in this series.

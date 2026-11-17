# Proposal

## Why

The site has no sitemap. The legacy site (kultura-poltava) serves an XML sitemap for search engines (`/sitemap.xml`) and a human-readable page (`/sitemap`), both driven by per-type settings in its admin panel. The legacy import deliberately skipped those settings, so after the switch to this CMS the site would lose both pages and the editors would have no way to choose what appears in them. The legacy XML also has a defect worth not copying: it lists only the 50 oldest records per type, so most of the news never reaches the sitemap.

## What Changes

- A new **"Sitemap"** tab in the section menu of `content` and `page` types (after "SEO"; blocks have no public address and no tab), also an item of the row menu, with two bordered blocks:
  - **XML sitemap**: "Content type" (the public list page `/{type slug}`; `content` types only), "Content" (the visible records, or the visible pages for a `page` type), "Change frequency" and "URL priority" (shown and stored only while at least one of the two checkboxes is on).
  - **Web sitemap**: "Content type" and "Content" checkboxes.
- The settings are stored in `content_types.settings.sitemap`. A type with no stored settings is **not** in either map (no defaults).
- `GET /sitemap.xml` (core): an XML sitemap built from those settings, with the home page first (`monthly`, `1.0`) and then, per type, the list address and the visible records with `lastmod` / `changefreq` / `priority`. All visible records are listed (no per-type limit); the file is capped at the protocol limit of 50 000 URLs, and a cut is logged.
- `GET /sitemap` (core + theme): the human-readable site map as a tree (home, pages, types with their child types and records), rendered by the theme template `sitemap/page` (hook `sitemap`); the `default` and `dcculture` themes get that template.
- `sitemap` becomes a reserved first path segment, like `search` (no page slug and no type slug can take it).
- English documentation of the settings, the two addresses and the theme template.

Not in scope (decided in exploration): a sitemap index for more than 50 000 URLs, caching of the responses, `hreflang` / per-language variants (addresses do not depend on the language), the archive view `?period=archive`, migration of legacy sitemap settings.

## Capabilities

### New Capabilities

- `type-sitemap`: the per-type sitemap settings (`settings.sitemap`), the XML sitemap `GET /sitemap.xml` and the web sitemap `GET /sitemap` with the contents each setting selects, ordering, visibility rules, the reserved path and the theme template contract.

### Modified Capabilities

- `admin-content-types`: new requirement for the "Sitemap" tab of the type section (menu position, availability by kind, the form, saving, permissions, 404 for blocks); validation of `settings.sitemap`.

## Impact

- **Code (backend)**: `routes/web.php` (admin tab routes for `content-types` and `page-types`; public `/sitemap.xml` and `/sitemap` before `/{path}`), a new admin controller and form request for the tab, `App\Models\ContentType` (validation of `settings.sitemap`), a new `App\Content\Sitemap*` reader/builder, new public controllers, `App\Content\PublicPaths::SITE_PATHS` and `config/content.php` `reserved_paths`.
- **Code (frontend)**: `resources/js/Components/Admin/ContentTypes/typeSectionItems.ts` (new item), a new page `Pages/Admin/ContentTypes/Sitemap.tsx` and a settings section component, translations in `lang/en.json` / `lang/uk.json`.
- **Themes**: `themes/default/views/sitemap/page.blade.php` and the same template in `themes/dcculture`.
- **Tests**: PHP Feature tests for the admin tab, validation, both public addresses and the reserved path; e2e additions in `admin-content-types` and `admin-page-types` (new menu item, the tab), and a public check in `public-site` if it fits there. No tests for theme templates (project rule).
- **Docs**: new `docs/type-sitemap.md`; updated `docs/admin-content-types.md` (tabs), `docs/admin-page-types.md` (tabs), `docs/public-site.md` (routes, reserved paths), `docs/theme-templates.md` (`sitemap/page`), `docs/legacy-import.md` (sitemap settings still do not move; the sitemap is configured by hand), `docs/e2e-testing.md` (area table), and the `docs/README.md` index.
- **Existing data**: a page or a type that already has the slug `sitemap` would become unreachable; the change must check for that before the reserved path takes effect.

# Type sitemap

Every `content` and `page` type has its own sitemap settings. Two maps are built from them: an **XML sitemap** for search engines (`GET /sitemap.xml`) and a **web sitemap** for visitors (`GET /sitemap`). Editors decide on the type's "Sitemap" tab ([admin-content-types.md](admin-content-types.md#sitemap-tab-content-and-page)) what each map shows. A type that is not configured is in neither map: there are no defaults that put a type into a map.

## Settings

Stored in `content_types.settings.sitemap` (validated by `ContentType::assertValidSitemapSettings()`, so every write path is covered, including cloning):

```json
{
    "xml": { "type": true, "nodes": true, "changefreq": "monthly", "priority": "0.8" },
    "web": { "type": true, "nodes": false }
}
```

| Key | Meaning |
| --- | --- |
| `xml.type`, `web.type` | The public list page of the type, `/{type slug}`. Kind `content` only; a `page` type has no list page and the key is rejected for it. |
| `xml.nodes`, `web.nodes` | The visible records of the type (for a `page` type, its visible pages). |
| `xml.changefreq` | One of `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never`. |
| `xml.priority` | A string from `"0.0"` to `"1.0"` in steps of `0.1`. |

- Only the checkboxes that are on are stored. `changefreq` / `priority` are stored only while `xml.type` or `xml.nodes` is on, with the defaults `weekly` / `0.5`; turning both XML checkboxes off drops them.
- An all-off form removes `settings.sitemap`, so such a type looks like one never configured. Other keys of `settings` (`list`, `public`) are never touched by the tab.
- A `block` type has no public address and cannot have `settings.sitemap`.
- Cloning a type copies `settings.sitemap` with the rest of `settings`, so a clone appears in the maps at once.
- A `content` type without a slug has no public address: neither its list nor its records appear in either map.

`Webtack\Ecms\Content\SitemapSettings` reads the settings of all `content` and `page` types once (one query) and is used by both maps, so they cannot disagree about what a setting means.

## XML sitemap

`GET /sitemap.xml` (`SitemapXmlController`, route `public.sitemap.xml`, built by `Webtack\Ecms\Content\SitemapXml`) answers 200 with `application/xml; charset=utf-8` and a `urlset` of the sitemaps.org protocol. It is public and not themed. `loc` is absolute on the host of the request. It does not depend on the reading language.

- The **home page** `/` is always first, with `changefreq` `monthly`, `priority` `1.0` and no `lastmod`.
- Types follow ordered by key. For each: the list address (`xml.type`), then the visible nodes from the latest changed to the oldest (`xml.nodes`): `/{slug}` for a page, `/{type slug}/{node slug}` for a record. The archive view (`?period=archive`) is not listed.
- Every address of a type carries that type's `changefreq` and `priority`.
- A node is visible when it is active, its publication date has come and it is not unpublished (`ContentNode::scopeVisible()`).
- `lastmod` is the node's `updated_at` as `Y-m-dTH:i:sP` (with the offset, for example `2026-11-13T23:37:48+02:00`). For a list address it is the latest `updated_at` among the visible nodes of the type, and it is omitted when there is none.
- An address that would appear twice is written once.
- **Cost.** The number of queries does not depend on the number of types or nodes: one for the types, one grouped `max(updated_at)` for the list dates and one streamed read of the nodes. There is no per-type limit (the legacy module listed only the 50 oldest records per type).

**Limit.** The protocol allows 50 000 `url` elements per file (`SitemapXml::LIMIT`). When the settings select more, the document is cut at the limit (the home page and the earlier types are kept), the response stays 200 and a warning is written to the application log. A sitemap index for more addresses is not provided: the way out is to turn off the XML checkboxes of the types that matter least.

## Web sitemap

`GET /sitemap` (`SitemapController`, route `public.sitemap`, built by `Webtack\Ecms\Content\SitemapTree`) renders the theme template `sitemap/page` (hook `sitemap`) inside the site shell with the document title "Sitemap". It is public and indexable. Only the exact address is the page; `/sitemap/anything` gets the site 404.

The tree, in order:

1. **Home** (`/`, title "Home").
2. The visible pages of every `page` type with `web.nodes` on, one flat list ordered by title.
3. The tree of `content` types by `parent_type`, siblings ordered by public title. A type with `web.type` on is an item (its public title, linking to `/{type slug}`) whose children are its records (when `web.nodes` is on, ordered by title) followed by its child types. A type with `web.type` off contributes its records and its child types' items at the level of its parent, so no enabled content is lost. A type without a slug contributes nothing itself but its child types are still visited. A `parent_type` cycle is guarded (types of a cycle are visited as roots).

Titles: a type's public title is its `section_title` (reading language, then base language), then its name; a node's title is its `title` in the reading language, then the base language, then its slug. Titles are compared case-insensitively by code point. Items are relative links. The query count is fixed (types, visible nodes, node titles, type titles).

## Theme template

`sitemap/page` (hook `sitemap`) receives the shared variables of every public page ([theme-templates.md](theme-templates.md#sitemap-page)) and `$items`: a list of `['title' => string, 'url' => string, 'children' => list]`. `title` is plain text for the template to escape; `url` is relative. The `default` and `dcculture` themes provide it (`views/sitemap/page.blade.php` with the recursive partial `views/sitemap/list.blade.php`). A theme without the template fails like any public page without one.

## Reserved path

`sitemap` is a reserved first path segment (`content.reserved_paths`) and a site path (`PublicPaths::SITE_PATHS`), like `search`: no page slug and no `content` type slug starting with `sitemap` can be saved, and `/sitemap/anything` gets the site 404. `sitemap.xml` is listed too because the guard `ReservedPathsGuardTest` requires every first route segment to be reserved (the slug format has no dot, so it could never be a slug anyway). Before this path took effect, the data was checked: no page or type used the slug `sitemap`. The guard only blocks new saves, so a page or type that already has the slug must be renamed by hand.

## Differences from the legacy module

The legacy module stored its settings in its own table (`entity_type`, `entity_id`, `web`, `xml`), listed only the 50 oldest records per type in the XML and answered `text/xml`. Here the settings live in the type, all visible records are listed, and the type is `application/xml; charset=utf-8`. The legacy settings are not imported ([legacy-import.md](legacy-import.md)); each type is configured by hand on its "Sitemap" tab.

## Tests

`tests/Feature/Content/ContentTypeTest.php` (validation), `tests/Feature/Admin/ContentTypeSitemapTest.php` (the tab), `tests/Feature/Public/SitemapXmlTest.php`, `tests/Feature/Public/SitemapWebTest.php`, `tests/Unit/Content/PublicPathsTest.php` (the reserved path) and the sitemap cases of `e2e/admin-content-types.spec.js` and `e2e/admin-page-types.spec.js`. Theme templates have no tests (project rule).

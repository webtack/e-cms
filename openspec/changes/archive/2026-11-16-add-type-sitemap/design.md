# Design

## Context

See `proposal.md` for the motivation and `specs/` for the requirements. The current state that shapes the approach:

- Type tabs are one list (`typeSectionItems.ts`) rendered by both the section menu and the row menu; the `seo` item is already filtered out for blocks, and the SEO routes live in a separate loop of `routes/web.php` for `content` and `page` only.
- `content_types.settings` is a JSON column with validated keys: `settings.list` and `settings.public` are validated in `ContentType` (unknown keys of `settings.public` are rejected). Cloning copies the whole `settings`.
- Public routes end with `/{path}`; reserved first segments live in `config/content.php` (`reserved_paths`) and `PublicPaths::SITE_PATHS` marks the reserved paths that are pages of the site (`search`), so their sub-addresses get the site 404.
- Public pages are rendered through `ThemeManager::render()` with candidate templates and a hook; a missing template throws. `PublicController::respond()` is the shared entry. `TypeSection::title()` gives the public title of a type.
- Node visibility is `ContentNode::scopeVisible()`; node titles are values read through `ContentValueReader` / `ContentNodeQuery`.
- The legacy module stored the settings in its own table and limited the XML to the 50 oldest records per type; see `proposal.md`.

## Goals / Non-Goals

**Goals:**
- One place to read the settings (a service) used by both maps, so the XML and the web page cannot disagree about what a setting means.
- A fixed, small number of queries per request: not per type and not per node.

**Non-Goals:**
- A sitemap index, caching, `hreflang`, and the archive period (see `proposal.md`).
- Per-node overrides (a node cannot be excluded or given its own priority).
- Moving the sitemap into a theme hook system beyond the single template.

## Decisions

### Settings live in `settings.sitemap`, not in a table
The same reasons as for `settings.public` / `settings.list`: the settings belong to the type, are read together with it, are copied by cloning and deleted with it by cascade. The legacy table (`entity_type`, `entity_id`, `web`, `xml`) would add a relation and a second place to delete from. Shape: `{ xml: { type, nodes, changefreq, priority }, web: { type, nodes } }`; absent means "not in the maps". *Alternative:* a `sitemap` table — rejected (extra table, no query benefit: all types are loaded anyway to build the maps).

### Validation sits in the model, like `settings.public`
`ContentType` gets one more validation step for `settings.sitemap` (keys, booleans, the closed `changefreq` list, the `priority` set, no `type` for pages, none for blocks), so every write path is covered (the tab, cloning, seeders, the importer). The form request validates the same shape for field-level messages and the model stays the last line. *Alternative:* validate only in the form request — rejected: cloning and other writers would bypass it.

### A dedicated tab controller and form request
`GET|PUT /admin/{content-types|page-types}/{key}/sitemap` go to a new controller with a form request, registered in the same `content` + `page` loop as the SEO route (so blocks get the route-level 404 and the `can:types` middleware). It writes only `settings.sitemap` and keeps every other key of `settings`. The "everything off" save removes the key rather than storing an empty object, so a type with nothing enabled looks the same as one never configured. *Alternative:* extend the Display controller — rejected: it merges `settings.public` with its own default-dropping rules and has a different save button; the SEO tab already sets the precedent of one tab = one concern.

### Frontend follows the existing tab pattern
A new item `sitemap` in `typeSectionItems` (after `seo`, filtered out for `block`, icon from `lucide-react`), a page `Pages/Admin/ContentTypes/Sitemap.tsx` that renders `ContentTypeNav` plus a section component `SitemapSettingsSection` built from the shared `FormSection` / `FieldGroup` and the sticky `FormActionBar`, so it looks like the neighbouring tabs. The kind decides whether the "Content type" checkbox is rendered. Labels use the existing `t()` and the keys are added to `lang/en.json` and `lang/uk.json`.

### One reader service, two builders
A service reads the settings of all `content` and `page` types once and exposes, per group (`xml` / `web`), the enabled types. The XML builder and the web builder use it, each with its own query:

- **XML**: for the types with `xml.nodes`, visible nodes are selected in one query (type key, slug, `updated_at`), ordered by type key then `updated_at` desc, streamed in chunks and written until the 50 000-th URL; the `lastmod` of a list address comes from one grouped `max(updated_at)` over the visible nodes of the enabled types. Home is written first. Addresses are de-duplicated by `loc`. The cut is logged with `Log::warning`.
- **Web**: for the types with `web.nodes`, visible nodes are read in one query, titles in one batch through the existing value reader (reading language, then the base language, then the slug), and the tree is assembled in memory from the type hierarchy (`parent_type`) of the `content` types. Pages are a flat list.

*Alternative:* load each type's nodes in a loop like the legacy module — rejected (queries per type, and the legacy loop is where the 50-record limit came from).

The tree rule "a type without its own item promotes its content to the parent's level" is applied in the tree assembly; a type without a slug contributes neither itself nor its records but its children are still visited. A cycle in `parent_type` is guarded by a visited set, as `ContentType::ancestors()` does.

### Routes and reserved path
`GET /sitemap.xml` and `GET /sitemap` are registered right before `/{path}` next to `/search`. `sitemap` is added to `reserved_paths` and to `PublicPaths::SITE_PATHS`, so slugs cannot take it (the existing reserved-path guard and its tests apply) and `/sitemap/x` gets the site 404. `sitemap.xml` needs no entry: the slug pattern has no dot, so it never matches `/{path}`. The XML response is built directly (a Blade/`DOMDocument`-free writer that escapes values), not through the theme: the XML is not a themed page.

### The web page goes through the theme
A new controller extends `PublicController` and renders candidate `sitemap/page` with hook `sitemap`, passing `items` (title, url, children) and the title "Sitemap". The templates are added to `themes/default` and `themes/dcculture` (no parent theme exists, so both need them); a recursive partial prints the nested list. No tests for the theme templates (project rule); `php artisan theme:check`, the theme build and a visual check are the verification.

### The legacy `Content-Type` and layout choices
`application/xml; charset=utf-8` replaces the legacy `text/xml`; both are accepted by search engines and the former is the type the protocol documentation uses. The legacy generator comment is dropped.

## Risks / Trade-offs

- [A page or type already has the slug `sitemap`, and the reserved path makes it unreachable] → a task checks the database and the demo data before enabling the path; if there is a conflict, it is renamed by hand before the change is released (the guard only blocks new saves).
- [A huge web tree: all visible records of every enabled type are listed (news can be thousands)] → one query and one batched title read keep the cost flat; the page is plain HTML. Editors choose per type whether records are shown. Pagination of the web page is not provided.
- [A response cut at 50 000 URLs silently hides later types] → the cut is ordered (home, then types by key, newest first) and logged as a warning; a sitemap index is the documented next step.
- [Cloning a type copies its sitemap settings, so the clone appears in the maps at once] → matches the existing rule that cloning copies `settings` unchanged; documented.
- [No cache: every crawler hit runs the queries] → the queries are fixed in number and indexed by `is_active` / `published_at`; caching with invalidation is deferred (`proposal.md`).
- [The menu scenarios in older specs list exact tabs and menu items] → the e2e checks of the menu composition are updated in the same change (see `tasks.md`).

## Migration Plan

No schema change and no data migration: absent settings mean "not in the maps". After deploy an editor opens the "Sitemap" tab of each type that should appear. Before deploy, check that no page or type uses the slug `sitemap`. Rollback is the removal of the routes and the tab; the stored `settings.sitemap` values are inert (the model validation would still accept them).

Documentation of the designed components: `docs/type-sitemap.md` (settings, both addresses, rules), `docs/admin-content-types.md` and `docs/admin-page-types.md` (the tab), `docs/public-site.md` (routes and the reserved path), `docs/theme-templates.md` (`sitemap/page`).

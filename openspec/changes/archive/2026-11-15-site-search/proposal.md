# Proposal

## Why

The public site has no search: a visitor can only browse menus and type lists. The `dcculture` header already shows a search field, but it is static design content (`action="#"`) and goes nowhere. The content querying layer searches only inside one content type, so there is no way to find a page or record across the whole site.

## What Changes

- Add the public route `GET /search?q=…&page=N` served by a new `Public\SearchController`, registered before `/{path}`.
- Reserve `search` as a system first segment: add it to `content.reserved_paths`, so no page slug or `content` type slug (first segment) can take it and the controller never serves it as a node address.
- Add `App\Content\SiteSearch`: a case-insensitive `LIKE` search over the title and the text fields (`string`, `text`, `richtext`) of all visible nodes of kinds `page` and `content`, in the reading language without fallback. Blocks and nodes without a public address (a `content` type without a slug) are skipped. Results are paginated.
- Query rules: the term is trimmed; fewer than 3 characters (or empty) shows the search page with the form only and runs no database query; the term is cut at 100 characters; `%` and `_` are escaped.
- Render through `ThemeManager::render()` with the candidate `search/results` and the hook `search`; the page is `noindex`; pagination links keep `q`.
- Theme `dcculture` only: the header search form submits to `/search` with `GET`, the same form (with the current `q`) is printed on the results page; new `search/results` template, a result card (title, a plain-text excerpt, the section title of the type), an empty state, and `dcculture.search.*` translations. The `default` theme is not changed.
- Docs: `docs/public-site.md`, `docs/theme-templates.md`, `docs/theme-dcculture.md`, and `docs/content-model.md` (reserved paths) are updated in English.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `public-site`: new requirements for the `/search` route, the reserved path `search`, the search rules (scope, visibility, language, minimum and maximum term length, escaping, pagination) and the `search/results` template candidate with its variables.
- `dcculture-theme`: the header search form submits to the search page; the search results page, result card, empty state, and search form on the results page.

## Impact

- Code: `routes/web.php`, `config/content.php`, new `app/Content/SiteSearch.php`, new `app/Http/Controllers/Public/SearchController.php`, `themes/dcculture/views/search/results.blade.php` and `partials/search-field.blade.php`, theme styles under `themes/dcculture/assets`, `lang/{en,uk}/dcculture.php`.
- No migrations and no new dependencies; the database is queried with `LIKE` on the existing `content_values_*` tables (no index is used for `%…%`, the same trade-off as the per-type search).
- Existing data: a page or a type that already uses the slug `search` (or a type slug starting with `search/`) becomes unreachable after the reservation; this must be checked before applying.
- Tests: PHP Feature tests for `SiteSearch`, the route, and the reservation; no tests for the theme (project rule for `themes/*`).
- Docs: `docs/public-site.md`, `docs/theme-templates.md`, `docs/theme-dcculture.md`, `docs/content-model.md` (updated).

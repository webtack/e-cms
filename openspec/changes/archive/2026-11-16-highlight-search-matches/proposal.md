# Proposal

## Why

The site search page shows each result with an excerpt that is the start of the first non-empty text value, regardless of the search term. A node often matches in another field or deep in a long text, so the visitor sees a card with no trace of the word they searched for and cannot tell why it was found. Showing the part of the text around the match and highlighting the term makes the results self-explanatory.

## What Changes

- The excerpt of a search result is built around the match: the first text field (in field order) whose plain text contains the term is used, and the excerpt is a window of the text around the first occurrence, with "…" at a cut edge. When no text field contains the term in plain text (the match is in the title only, or the database collation matched what the case-insensitive PHP comparison cannot), the excerpt keeps today's behaviour: the start of the first non-empty text value.
- `SearchResult` additionally exposes the title and the excerpt split into parts (`text` plus a `match` flag), so a template can highlight the term without printing raw HTML. The plain `title` and `excerpt` strings stay as they are.
- The `dcculture` result card prints the title and the excerpt from these parts and wraps matching parts in `<mark>`; every part is escaped by Blade. `search.css` styles the mark.
- Highlighting is case-insensitive and covers every occurrence of the term in the title and in the excerpt. A term the PHP comparison cannot find (a collation-only match, for example with diacritics) leaves the text unhighlighted.
- No change to what matches, the order of results, pagination, the number of queries, or the term rules.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `public-site`: the "Search results page" requirement changes: the excerpt is built around the match, and a result carries the title and the excerpt split into match and non-match parts.
- `dcculture-theme`: the "Search results page in the theme" requirement changes: the result card highlights the term in the title and the excerpt.

## Impact

- Code: `app/Content/SiteSearch.php` (excerpt choice and window), `app/Content/SearchResult.php` (parts), a small helper that splits a text by the term (in `app/Content/`), `themes/dcculture/views/partials/search-result.blade.php`, `themes/dcculture/assets/components/search.css`.
- Tests: new cases in `tests/Feature/Public/SiteSearchTest.php` for the excerpt window and the parts. No tests for the theme (project rule for theme frontend).
- Docs: `docs/public-site.md` (Site search: excerpt and `SearchResult` parts), `docs/theme-templates.md` (Site search results: the new `SearchResult` members), `docs/theme-dcculture.md` (Site search: highlighted card).
- No migrations, no new configuration, no new dependencies. The theme assets are rebuilt with `npm run build`.

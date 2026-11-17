# Design

## Context

`App\Content\SiteSearch` finds nodes with `LIKE '%term%'` and then builds each `SearchResult` from one batch read of values per type (`ContentValueReader::load`). The excerpt comes from `excerpt()`: the first non-empty text value in field order, as plain text, cut from the start by `shorten()` to `content.meta_description_max` (160) at a word boundary. The values of all text fields of the shown nodes are already in memory when the excerpt is built, so choosing a different field or window needs no extra query. The results card is `themes/dcculture/views/partials/search-result.blade.php`; it prints `$result->title` and `$result->excerpt` escaped. Collation of the value tables is `utf8mb4_unicode_ci` (case- and accent-insensitive); SQLite in tests is ASCII-case-insensitive only. See `docs/public-site.md` (Site search), `docs/theme-templates.md` (Site search results) and `docs/theme-dcculture.md` (Site search).

## Goals / Non-Goals

**Goals:**
- The excerpt shows the first match; the term is highlighted in the title and the excerpt.
- Highlighting cannot open an XSS hole and works in any theme without raw HTML.
- No extra queries; the search rules, order and cost stay as documented.

**Non-Goals:**
- Accent-insensitive highlighting that follows the database collation, stemming, or highlighting of word forms.
- Several snippets per result, or highlighting inside the page the visitor opens from the result.
- Ranking results by where or how often the term occurs.

## Decisions

**1. Ready-made parts on `SearchResult`, not a template helper.** `SearchResult` gets two more members, `titleParts` and `excerptParts`, each a list of `['text' => string, 'match' => bool]`. The existing `title` and `excerpt` strings stay, so themes that do not highlight keep working. A theme only loops over parts and escapes them: `@foreach ($result->excerptParts as $part) @if ($part['match'])<mark>{{ $part['text'] }}</mark>@else{{ $part['text'] }}@endif @endforeach`. Alternative: pass the term to the template and let each theme split the text. Rejected: every theme would repeat the splitting and the escaping risk. Alternative: return HTML with `<mark>` already inserted. Rejected: it forces `{!! !!}` and makes escaping the core's job to get right every time.

**2. One small splitter in core.** A final class `App\Content\TermHighlighter` with `parts(string $text, string $term): list<array{text: string, match: bool}>`. It uses `preg_match_all('/'.preg_quote($term, '/').'/iu', ..., PREG_OFFSET_CAPTURE)` and `substr` on the byte offsets, so the case-insensitive comparison and the offsets agree even for letters whose case folding changes the length (which `mb_stripos` offsets do not guarantee). `preg_quote` makes `%`, `_`, `.`, `(` literal. The same class answers "does the text contain the term" (`contains`) for the field choice, so the choice and the highlighting always use one comparison. An invalid UTF-8 text makes `preg_match_all` return `false`; the splitter then returns one unflagged part.

**3. Excerpt choice and window.** `excerpt()` receives the term. For each text field in field order it builds the plain text exactly as today (richtext through `HtmlSanitizer::text`, other types whitespace-collapsed) and takes the first one for which `contains` is true. If none matches, it falls back to today's "first non-empty text" and `shorten()` from the start. The window around a match: when the text fits in `meta_description_max`, it is returned whole; otherwise the window starts about a third of the maximum before the first occurrence (moved forward to a word boundary), is filled up to the maximum, and is cut back to a word boundary at the end. "…" is added at an edge that is not the edge of the text, and its length counts in the maximum, so the excerpt is never longer than `meta_description_max`. `shorten()` is kept for the fallback; the window code reuses its boundary and trailing-punctuation rules. A term longer than the room in the window cannot happen: the term is at most 100 characters and the maximum is 160 by default, and when the maximum is configured below the term length the window is simply the start of the match.

**4. Parts are computed from the final strings.** `titleParts` is built from the displayed title (the slug when the node has no title), `excerptParts` from the final excerpt after the window and the "…" are added. So parts always join back to `title` and `excerpt`, and a match cut by the window edge is not flagged as a whole term. The term passed to `SiteSearch::search()` is already clean, so no further normalisation is needed.

**5. Disagreement between SQL and PHP is a graceful fallback.** The SQL `LIKE` follows the collation; the PHP comparison is case-insensitive only. When they disagree (diacritics under `utf8mb4_unicode_ci`, a term that matches only inside HTML markup of a `richtext` value), no field contains the term in plain text, so the excerpt is the old start-of-text one and nothing is flagged. The result is still correct and found; it is only not highlighted. Accent folding would need a transliteration table or `intl` and is a non-goal.

**6. Theme.** `search-result.blade.php` prints the title link and the excerpt from the parts; the link still wraps the whole title. `search.css` adds a `.search-result mark` style (a light teal-tinted background, inherited text colour and weight, small radius) so it keeps contrast on the white card and on hover, and it does not change the three-line clamp.

## Risks / Trade-offs

- [Highlight is missing for collation-only matches] → The fallback shows the old excerpt; documented in `docs/public-site.md` as a known limit.
- [The window code is easy to get off by one around "…" and multibyte letters] → Feature tests cover a match at the start, the middle and the end of the text, multibyte text, and the length bound.
- [`SearchResult` gets two more constructor arguments] → It is built in one place (`SiteSearch`); tests that construct it directly, if any, are updated.
- [`preg_match_all` with a very long text] → Texts are bounded by the value length of a field and the term by 100 characters; the pattern is a literal, so there is no backtracking risk.

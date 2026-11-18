# Design

## Context

The composite values already exist: `ComposedValue` (`split` / `compose`) writes the scalar into `value`, and the rest into the JSON `options` of the same row (`YoutubeType` — the first use). The type `url` uses this mechanism without changes: `table()` returns `content_values_string`, `options` = `{"title": "…"}` or `NULL`. Description of the components: `docs/content-values.md` (composite values), `docs/admin-content-nodes.md` (the widgets of the fields), `docs/admin-content-fields.md` (the types and `settings`).

## Goals / Non-Goals

**Goals:**
- One class `UrlType` + a row in the registry; no migrations.
- A safe link: only `http(s)` and the relative forms.
- A simple widget from two `Input`s in one row.

**Non-Goals:**
- Checking the availability of the link, getting the title from the page.
- The settings of the type (the allowed schemes, only internal ones), `target`/`rel`.
- The public output of the link (it is shown by the frontend of the site).

## Decisions

- **Parsing of a link** — a public static `UrlType::isValid(string): bool` (by the pattern of `YoutubeType::parse`). The rule: after `trim` the string is not empty, without whitespace/control characters (`/[\s\x00-\x1F\x7F]/`), and either (a) starts with `/` (but not `//`), `./`, `../`, `#`, `?`; or (b) `parse_url` gives the scheme `http`/`https` and a non-empty `host`; or (c) starts with `mailto:` or `tel:` and has a non-empty part after the colon. Alternative — `FILTER_VALIDATE_URL`/Laravel `url`: it rejects the relative ones and gives unexpected exceptions; discarded. The backslash (`/\evil.com`, which browsers read as `//evil.com`) is rejected together with `//`.
- **Composite value**: `split` → `[trim(url), title !== '' ? ['title' => title] : null]`; `compose` → `['url' => scalar, 'title' => options['title'] ?? null]`; `isBlank` — by `url`, as in `YoutubeType`. The input may also be a bare string (as in `youtube`).
- **Filters**: `eq`, `in`, `contains` (as `string`), the search `searchable()` — `false` (the value is composite, the search of the list looks by text fields; to be aligned with the implementation of `searchable` in `StringType`).
- **Form**: the state is an object `{url, title}` (`UrlValue`, `emptyUrl()` in `types.ts`), by analogy with `YoutubeValue`; `NodeFieldItem` is extended; `ContentNodeForm.initialValue` and `NodeFieldList` (a multiple field, the usual button «Додати елемент» — "Add element") handle `url`. The widget — two `Input`s in `grid grid-cols-2` (in a column on a narrow screen).
- **Table**: `ContentNodeController::text()` — `url` is given to the client as an object `{url, title}` (as `image` / `file`), and `NodeCell` on the page of the list draws `<a target="_blank" rel="noopener noreferrer">` with the text `title` or `url`. The column type for the table is `text`. A relative link in a new tab is resolved relative to the admin (the same host), so it leads to the page of the site by the same path.
- **No sorting**: `NodeListSettings` (`sortable`) adds `url` to the exclusions together with `youtube` and `term`; the value is composite and there is no need to sort by it.

## Risks / Trade-offs

- [The translations of the composite values are not verified for `url`] → in the tests check writing/reading of a translatable and a multiple field; the behaviour follows the general rules (unlike `youtube`, there is no prohibition of translations).
- [A relative link is resolved relative to the page where it is shown] → we store it as entered; the responsibility lies with the frontend of the site.
- [A new branch in `NodeFieldInput` / `initialValue` / `NodeFieldList` grows the code of the `youtube` special cases] → keep `url` as a separate small component `UrlField.tsx`.

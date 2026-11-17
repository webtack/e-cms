# Proposal

## Why

`HtmlSanitizer` unwraps `table`, `thead`, `tbody`, `tr`, `td` and `th`: the tags go, the text stays, so the cells of a table glue together (`Час` / `Захід` / `10:00` / `Відкриття` becomes `ЧасЗахід10:00Відкриття`). The Tiptap editor has no table support either, so a table cannot be written by an editor and would be lost on the next save of a node. Tables are needed: the legacy site has them in news (`news:801`, `news:1035` and `news:1933` with 35 tables and 588 rows of contest results), and editors will keep adding tabular data. The `import-legacy-site` dry run reported 38 `table` and 596 `tr` removed by the sanitizer, all from these three nodes. The sanitizer also unwraps `b` and `i`, so bold and italic from pasted text (Word, other sites) are lost although the editor reads them; this change keeps them too.

## What Changes

- **Sanitizer:** `table`, `thead`, `tbody`, `tfoot`, `tr`, `td` and `th` become allowed tags of the `richtext` allow-list; `colspan` and `rowspan` (whole numbers) are the only kept attributes on cells. The Word-style inline `width` and `style` on cells stay removed, so pasted tables do not carry fixed widths. Every other tag, attribute and event handler is still dropped.
- **Bold and italic:** `b` and `i` also become allowed tags (kept as written, without attributes); the editor reads them as bold and italic and rewrites them to `strong` and `em` on the next save. The sanitizer unwrapped them before, so pasted bold and italic were lost.
- **Visible text:** the `text()` extraction (length limits, search, empty check) separates cells and rows, so table text no longer glues.
- **Editor:** the Tiptap editor reads, shows and writes tables, and the toolbar can insert a table and add or remove rows and columns and a header row. Existing tables survive opening and saving a node.
- **Public site:** the theme `default` already styles tables (`.richtext table`: borders, padding, horizontal scroll for a wide table in `themes/default/assets/app.css`); this change writes that behavior down as a requirement and checks it on a stored table, with no new styles expected.
- **Editor look:** the editor content (`.rich-text-content`) gets table styles, because the editor does not use the theme style sheet.
- **Legacy import:** no importer code changes; the tables pass through the sanitizer, so `tag:table` and `tag:tr` leave the sanitizer tally of the import report. The change is a prerequisite of the owner's first real pass (task 8.2 of `import-legacy-site`), after which the dry run is repeated to confirm it.
- `caption` is not allowed: Tiptap has no table caption, so the editor would drop it on the next save; its text stays as a paragraph. `tfoot` rows are read by the editor as ordinary rows.
- Out of scope: merging or splitting cells in the editor beyond `colspan` / `rowspan` preservation, column widths, sorting or filtering of tables, a table field type.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-values`: the allow-list requirement for `richtext` (tags and attributes that survive cleaning) and the visible-text rule used by length limits.
- `admin-content-nodes`: the `richtext` editor requirements (table display, editing and the toolbar actions).
- `default-theme-styling`: a new requirement for how a table in a `richtext` value looks on the public site (already implemented in the style sheet, not yet specified).

## Impact

- Code: `app/Content/HtmlSanitizer.php` (allow-list, attributes, `text()`), possibly a small attribute sanitizer for `colspan` / `rowspan` next to `ImageWidthSanitizer`; `resources/js/Components/Admin/ContentNodes/richTextExtensions.ts`, `RichTextToolbar.tsx`, `RichTextEditor.tsx`; `resources/css/app.css` (`.rich-text-content` table styles); the `default` theme style sheet is only checked; `package.json` (`@tiptap/extension-table` family, same version line as the other Tiptap packages).
- Tests: `HtmlSanitizerTest` (kept tags, attributes, removed widths and styles, cell text separation), `tests/Feature/Legacy/RichTextRewriterTest.php` (a table survives the import), an e2e scenario in the spec for the node form editor (find it with `npx playwright test --list`).
- Docs to update: `docs/content-values.md` (allowed tags, the unwrapped list without tables), `docs/admin-content-nodes.md` (editor actions), `docs/site-themes.md` (the `.richtext` rules of the `default` theme, only if it lists them; check with `grep`), `docs/e2e-testing.md` if a new spec or consumer appears, and `docs/legacy-import.md` (the table note, if it mentions the removed tags).
- No migration and no data change; stored `richtext` values already cleaned keep their text and only newly saved values can contain tables.

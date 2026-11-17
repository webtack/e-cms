# Design

## Context

See [proposal.md](proposal.md) for the motivation and the specs in `specs/` for the requirements. What exists today:

- `App\Content\HtmlSanitizer` builds a `symfony/html-sanitizer` config from two constants: `ALLOWED_TAGS` (kept) and `UNWRAPPED_TAGS` (`blockElement`: the tag goes, the text stays). The table tags are in `UNWRAPPED_TAGS`. Attributes are policed by small `AttributeSanitizerInterface` classes next to it (`StyleAttributeSanitizer`, `IframeSrcSanitizer`, `ImageWidthSanitizer`, ...). `text()` turns the cleaned HTML into plain text and adds a space only after `</p|h1-6|li|blockquote|figcaption>` and `<br>`.
- `RichTextType` uses `text()` for the empty check (together with `hasImage()` / `hasFrame()`) and for the `min` / `max` length; the legacy `RichTextRewriter` calls the same sanitizer, so the importer needs no change.
- The editor extensions are in `richTextExtensions.ts` with the rule "exactly what the server allow-list keeps"; the toolbar (`RichTextToolbar.tsx`) puts the formatting buttons inside `<fieldset disabled={source}>`, which is how "Source" mode disables them, and uses `DropdownMenu` for the alignment menu. Button labels go through `t()` with the keys in `lang/en.json` and `lang/uk.json`.
- The editor content is styled by `.rich-text-content` rules in `resources/css/app.css`. The public site is styled by `.richtext` rules in `themes/default/assets/app.css`, which already include `table`, `th` and `td` (borders, padding, `display: block; overflow-x: auto`).
- The data to protect (legacy dry run): three `news` bodies with 38 tables and 596 rows. Word tables there carry `style`, `width` and `valign` on cells, `colspan` on 35 cells, paragraphs inside the cells, and a `thead` made of `td` cells.

## Goals / Non-Goals

**Goals:**

- A table written by the editor, or imported, survives the server cleaning and the next open and save of the node in the editor, with the same rows, columns, header cells and cell spans.
- The server allow-list and the editor schema stay equal: nothing the server keeps is dropped by the editor on save, and nothing the editor writes is dropped by the server.
- No new surface for script or layout injection: tables add no `style`, `class`, event handler or URL-bearing attribute.

**Non-Goals:**

- Cell merging and splitting commands, column resizing, alignment commands for whole columns, `caption`, `colgroup`, a table of contents, sorting. A merged cell that is already stored (`colspan` / `rowspan`) is preserved but cannot be created in the editor.
- A rewrite of the legacy tables (for example, turning the 35 tables of `news:1933` into something else). The importer stays unchanged.

## Decisions

### 1. Allow the six table tags, keep two attributes

`table`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td` move from `UNWRAPPED_TAGS` to `ALLOWED_TAGS` (`allowElement`). `th` and `td` get `colspan` and `rowspan`; no tag gets `style`, `width`, `align`, `valign`, `class` or `scope`.

Why: widths from Word (`width:467.7pt`) break a responsive page, and the theme already controls look through its own `.richtext table` rules. Alignment inside a cell still works through the paragraph `text-align` that the sanitizer already keeps, so nothing the editor can create is lost. Alternative: also allow `style` on cells with a width. Rejected: more surface (`StyleAttributeSanitizer` rules per tag) and fixed widths are exactly what the pasted tables should not bring.

`caption`, `colgroup` and `col` join `UNWRAPPED_TAGS` (the text of a `caption` stays). Why: the Tiptap table model has no caption, so keeping it on the server would make the editor drop it on the next save, which breaks the equality goal. `colgroup` and `col` carry only widths, and the editor's own `getHTML()` output includes them unless disabled (see decision 4); the server drops them.

`b` and `i` also move from `UNWRAPPED_TAGS` to `ALLOWED_TAGS`, without attributes, and are kept as written, not renamed to `strong` / `em`. Why: `symfony/html-sanitizer` has no element renaming (its config only allows, blocks or drops an element), so a rename would need a separate step around `sanitize()`. Tiptap's Bold and Italic read `b` / `strong` and `i` / `em` and write `strong` / `em`, so the editor loses nothing and only rewrites the markup on the next save. Accepted: stored values may hold either form until then; both look the same.

### 2. `colspan` / `rowspan` through a small attribute sanitizer

A new `final class TableSpanSanitizer implements AttributeSanitizerInterface` (same shape as `ImageWidthSanitizer`): supports `td` and `th`, attributes `colspan` and `rowspan`, keeps a whole number from 1 to 100 (normalized, so `03` becomes `3`), otherwise `null` (the cell stays, the attribute goes). Registered with `withAttributeSanitizer` in the config. The bound of 100 is a guard against absurd values from pasted HTML; the legacy data uses small numbers (the sanitizer tally shows 35 `colspan`).

### 3. Cell separation in `text()`

The regex in `text()` gets `</td>`, `</th>` and `</tr>` as separators (a space), so `10:00` and `Opening` become `10:00 Opening`. Since `text()` collapses whitespace afterwards, extra spaces cost nothing. The empty check is unchanged: a table with no text, image or frame is empty, as an empty paragraph is. Side effect to accept: a freshly inserted blank table with no content is not saved on its own.

### 4. Editor: `@tiptap/extension-table`, not resizable

Add `@tiptap/extension-table` (version `^3.32.2`, the same line as the other Tiptap packages), which exports `Table`, `TableRow`, `TableHeader` and `TableCell`. Configure `Table.configure({ resizable: false })`: with column resizing off, no per-column width state is kept in the document (`colwidth`), the markup stays `table > tbody > tr > th|td` plus whatever the server then strips, and the editor needs no custom node view.

The cell attributes `colspan` and `rowspan` are part of the extension's defaults, so existing merged cells round-trip. `thead` / `tbody` / `tfoot` are read as one table by ProseMirror's table parser (rows are collected from all sections), which gives the "legacy markup" scenario of the spec; the first row of a legacy `thead` made of `td` cells stays a row of ordinary cells, as stored.

Alternative: write a minimal custom table node. Rejected: selection across cells, tab navigation and the row and column commands all come with the maintained extension.

`TextAlign` is configured for `['heading', 'paragraph']`, and paragraphs in cells are the only place alignment lives, so it needs no change.

### 5. Toolbar: one button, one menu

In `RichTextToolbar.tsx` a "Table" button inside the existing `<fieldset disabled={source}>`:

- the cursor outside a table: it runs `insertTable({ rows: 3, cols: 3, withHeaderRow: true })`;
- the cursor in a table: it opens a `DropdownMenu` (the pattern of the alignment menu) with the eight actions of the spec (`addRowBefore`, `addRowAfter`, `deleteRow`, `addColumnBefore`, `addColumnAfter`, `deleteColumn`, `toggleHeaderRow`, `deleteTable`). Each item is disabled when the command's `can()` is false.

The state `editor.isActive('table')` decides which behavior the button has; the button carries `aria-pressed` while the cursor is in a table, like the other state buttons. Labels use new `t()` keys added to both `lang/en.json` and `lang/uk.json`. Alternative: a separate inline toolbar that appears over the table. Rejected: a second UI pattern for one feature, and the existing toolbar and menus already carry the focus handling (`onMouseDown` prevent default, `onCloseAutoFocus` prevent default).

### 6. Look

- Editor: `.rich-text-content table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse }`, `th` and `td` with a border and padding, `th` bold on a muted background, a `.selectedCell` highlight (the class ProseMirror's table plugin puts on selected cells), and no top margin on paragraphs inside cells (the existing `> * + *` rule is direct-child only, so paragraphs in cells need a small `p` reset). It uses the admin tokens already used by the `blockquote` and `figcaption` rules.
- Public: the theme already has the rules. The spec records them; the work is a check, and a style fix only if the check finds a gap (for example `th` not distinguishable from `td`, which the current rules do not do: `th` and `td` share one rule). If so, add a `th` rule in `themes/default/assets/app.css`.

### 7. No data migration

Stored values were cleaned by the old config, so their tables are already flat text. Nothing is rewritten. After this change, the legacy dry run is repeated (task 8.2 of `import-legacy-site`) and the tables arrive intact because the importer already passes the HTML through the sanitizer.

## Risks / Trade-offs

- [Editor and server drift: the editor emits something the server strips, so saved data differs from what the editor showed] → The e2e scenario saves a table and compares the value in "Source" mode before and after reload; the server test feeds the editor's own output (`colgroup`, `col`, `style="min-width"`) and expects it to clean to the plain structure.
- [`news:1933` is large (283 KB, 588 rows); the editor could be slow or the table hard to use] → Only a smoke check in the editor is planned (open, no edits, save). Heavy editing of such a table is not a goal; if it is too slow, that is a separate performance question.
- [A wide table inside the narrow public layout] → Horizontal scroll in the table's own block, already in the theme.
- [Two forms of bold and italic (`b` / `i` and `strong` / `em`) in stored values] → Harmless: same look, and the editor normalizes a node to `strong` / `em` on its next save. A server-side rename is possible later if the single form is ever needed.
- [Legacy `thead` rows of `td` cells are not header cells, so they look like data] → Preserved as stored; "Switch the header row" gives editors a way to fix a single table.
- [Mixed-up cell widths after Word paste, since `width` is removed] → Deliberate; the browser's auto layout is what we want.
- [`blockElement` on `caption` / `colgroup` / `col` keeps the text only if the library treats the unknown tag as the spec expects] → A server test covers the "Caption and column groups are removed" scenario.

## Migration Plan

No data migration and no schema change. Order: sanitizer and tests first (they alone fix the legacy import), then the editor, then the styles and docs. Rollback is a revert of the commit: values saved with tables in between would then be flattened again by the old sanitizer on their next save (a stored value is read as stored, so the tables remain in the DB until the node is re-saved).

## Open Questions

- Whether `th` should get a `scope` attribute for accessibility. It can be added later with one attribute in the sanitizer and does not change the specs now.
- Whether the "Table" menu should later get a "Merge cells" action; out of scope here.

Docs describing these components: `docs/content-values.md` (the sanitizer and the allowed tags), `docs/admin-content-nodes.md` (the editor and its toolbar), `docs/site-themes.md` (theme styling, if it lists `.richtext` rules), `docs/legacy-import.md` (the removed-tags remark).

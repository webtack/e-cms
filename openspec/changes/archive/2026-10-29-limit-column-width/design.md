# Design

## Context

`TableCell` has `whitespace-nowrap`, so a cell is always as wide as its longest content; `Column` (see [docs/admin-data-table.md](../../../docs/admin-data-table.md)) has no means to limit the width. The description under the name is output by the `cell` of the `name` column in `Pages/Admin/ContentTypes/Index.tsx` (introduced by the changes `add-content-type-form`, currently with `line-clamp-2`). The motivation is in proposal.md.

## Goals / Non-Goals

**Goals:**
- A general column property for limiting the width with truncation to one line.
- Apply it to the name column of content types.

**Non-Goals:**
- User-driven resizing of columns, a minimum and fixed width (`minWidth`), text wrapping, changes to the server contract.

## Decisions

1. **Name and type of the property: `width?: string`** in `Column` — a CSS length (`'24rem'`, `'40ch'`). The name is simple, and the semantics is an upper bound: the actual width depends on the content, a short text does not stretch the column. This is recorded in the JSDoc and the docs. There is no minimum/fixed width (a Non-Goal).
2. **The limit — on the `th` and on the content wrapper.** The column header (`TableHead` in `DataTable.tsx`) gets `style={{ maxWidth: width }}`. `max-width` on table cells with automatic layout is not guaranteed, so the truncation itself is done by the wrapper: `DataTableRow` for a column with `width` renders `<div className="truncate" style={{ maxWidth: width }}>` inside `TableCell`. Such a wrapper does not stretch for short text, and for long text gives `overflow: hidden` + an ellipsis. Without `width` the markup does not change.
3. **A custom `cell` truncates itself.** The wrapper with `truncate` acts on one line of text; if `cell` returns several blocks (name + description), the truncation of each line is done by `cell` itself with the class `truncate`, and the wrapper only provides the width limit. Therefore the `name` column sets `width` and puts `truncate` on the name and the description in `cell`; `title` with the full description remains.
4. **The header text is not truncated.** `th` gets only `max-width`, and the header text itself is not shortened; the column does not become narrower than its header.
5. **The number of lines of the description.** `line-clamp-2` is replaced by `truncate` (one line) to meet the requirement to limit the height of the table rows; this changes only the class in `Index.tsx`.

## Risks / Trade-offs

- [A narrow limit hides useful text] → the full description is in `title`; the value of the limit is up to the page.
- [`max-width` on `th` in a table with automatic layout may not be honoured by the browser] → the limit is guaranteed by the wrapper of cells with `truncate`; `max-width` on `th` is additionally checked in e2e, and if it does not work in Chromium, `table-layout: fixed` is proposed as a separate decision.
- [A column is not narrower than its header] → acceptable, the headers are short.
- [Overlap with the unfinished `add-content-type-form`] → apply after it; there is no conflict in the specifications, because the requirements are only added.

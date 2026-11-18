## Why

In the table of content types the "Name" column grows together with the longest description (table cells do not wrap text), and the remaining columns are pushed off the screen. The data table has no way to limit the width of a column, so a general mechanism is needed, not a one-off fix of the page.

## What Changes

- An optional property `width` (a CSS length, for example `24rem`) is added to the column description `Column`: it is written as `max-width` of the column header (`th`) and as the limit of the content of the body cells; longer cell text is truncated with an ellipsis (`truncate`) to one line. Without the property the columns behave as before.
- The "Name" column of the content types table gets a `width`; both the name and the description under it (instead of truncation to two lines) are truncated to one line, and the full text remains in the `title` hint.

Out of scope: user-driven resizing of columns, minimum and fixed width (`minWidth`), text wrapping, server-side columns (`TableColumn`) — the width concerns only the display.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-data-table`: a new requirement on limiting the width of a column via `width`.
- `admin-content-types`: a new requirement on limiting the width of the name column with truncation of the description to one line.

## Impact

- Code: `Components/Admin/DataTable/types.ts` (`Column.width`), `DataTableRow.tsx` (a content wrapper with truncation), `DataTable.tsx` (`style.maxWidth` on the column's `TableHead`); `Pages/Admin/ContentTypes/Index.tsx` (`width` for `name`, `truncate` instead of `line-clamp-2`).
- Tests: e2e `admin-data-table` and `admin-content-types` (column width, no horizontal expansion).
- Docs: `docs/admin-data-table.md` (the section "Column description"), `docs/admin-content-types.md` (the name column).
- Dependency: the change overlaps with `add-content-type-form`, which introduces the description under the name in `Index.tsx`; apply it after that one (or after its archiving).

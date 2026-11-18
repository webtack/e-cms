# Tasks

## 1. Column property

- [x] 1.1 Add the optional `width?: string` (the maximum width of cells) to `Column` in `Components/Admin/DataTable/types.ts` with JSDoc in English and `@example`
- [x] 1.2 In `DataTable.tsx` set `style={{ maxWidth: width }}` on the `TableHead` of a column with `width`; in `DataTableRow.tsx` wrap the cell content of such a column in `<div className="truncate" style={{ maxWidth: width }}>`; without `width` the markup does not change; `npm run build` passes
- [x] 1.3 Add `width` to one column of the demo table (`DataTableDemo`) with a long value in the data, so that the mechanism is visible and can be checked in e2e
- [x] 1.4 Update the section "Column description" in `docs/admin-data-table.md` (`width`: the purpose, `max-width` on `th`, the wrapper with truncation, an example); check the links

## 2. Name column of content types

- [x] 2.1 In `Pages/Admin/ContentTypes/Index.tsx` give the `name` column a `width` (for example `24rem`), put `truncate` on the name and the description instead of `line-clamp-2`, keep the `title` with the full description
- [x] 2.2 Update the description of the `name` column in `docs/admin-content-types.md` (limited width, one line, ellipsis)

## 3. Check

- [x] 3.1 Extend the e2e `admin-data-table` (`th` has `max-width`, a long value is truncated, the cell is not wider than the limit, a column without a limit is unchanged) and `admin-content-types` (a long description on one line, the remaining columns in place, a narrow 375px screen without horizontal scrolling); `npm run test:e2e -- admin-data-table admin-content-types` passes
- [x] 3.2 The full `npm run test:e2e` and `docker compose exec app php artisan test` pass; `openspec validate limit-column-width` without errors

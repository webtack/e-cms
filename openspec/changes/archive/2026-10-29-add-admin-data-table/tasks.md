# Tasks

## 1. Primitives and types

- [x] 1.1 Add via the shadcn CLI on the host `table`, `select`, `popover`, `checkbox`, `badge`; check that `grep -rn "@/components" resources/js` is empty and `npm run build` passes
- [x] 1.2 Create `Components/Admin/DataTable/types.ts` (`Column`, `TableState`, `TableMeta`, `TablePageProps`, `FilterOperator`, the prop `mode: 'server' | 'client'`, `getRowId`, `rowHandle`) and `params.ts` (parsing and serialization of the address: `q`, `filters[N]`, `sort`, `dir`, `page`, `per_page`; the table of operators by column type); JSDoc in English with `@example`; check `npx tsc --noEmit` and the `@example`s in the browser console on the demo page (group 4)

## 2. Server helper

- [x] 2.1 Implement `App\Support\Table\TableRequest` (the column description, normalization of search, filters, sorting, `page`, `per_page`; invalid values are ignored and go into `ignored`) and `App\Support\Table\TablePage` (the response `rows/meta/query/ignored/operators`, limiting the page beyond the range, applying to a collection); PHPDoc with `@example`; check with the tests `tests/Unit/Support/Table/TableRequestTest.php` and `TablePageTest.php` (each operator by type, `between` with two bounds, an unknown column, an invalid operator, `per_page` above the maximum, a page beyond the range, an empty result) via `docker compose exec app php artisan test --filter=Table`
- [x] 2.2 Create `docs/admin-data-table.md` with the sections "Address contract", "Operators by type", "Server helper" and add a line to `docs/README.md`; check that the parameter names and operators in the document match the tests of 2.1

## 3. The DataTable component

- [x] 3.1 The hook `useTableState` and `DataTable` with sorting (the cycle asc → desc → none, `aria-sort`), pagination (`DataTablePagination`: navigation, summary, page size, disabled boundaries), the loading state (`aria-busy`) and the empty state; `push` for page/sorting, `replace` for search; JSDoc with `@example`; check `npm run build` and manually on the demo page (group 4)
- [x] 3.2 The toolbar: a search field with a debounce of 300 ms, a reset button, a message about ignored parameters (`DataTableToolbar`); check `npm run build` and the search scenarios on the demo page
- [x] 3.3 Filters (`DataTableFilters`): adding/removing rows column → operator → value, the input element by type (text, number, date, yes/no, select), two fields for `between`, chips of active filters, applying only complete filters; check `npm run build` and manually on the demo page for all column types
- [x] 3.4 Column visibility (`DataTableColumnMenu` + `useColumnVisibility`): `localStorage` by `tableId` with `try/catch`, `defaultVisible`, `hideable: false`, hidden columns remain available for filters and sorting; check `npm run build` and manually (reload, two tables, a blocked storage)
- [x] 3.5 Client mode: the pure function `clientEngine.ts` (search, filters with all operators, sorting with empties at the end and stability, pagination, `meta`), the hook `useClientTableState`, the choice of source by `mode` in `DataTable`; `getRowId`, the optional slot `rowHandle` and a separate `DataTableRow` (extension points for drag-and-drop, without the dragging itself); JSDoc with `@example`; check `npm run build` and manually on the client demo table (group 4)
- [x] 3.6 Translations of all the component's strings in `lang/en.json` and `lang/uk.json` (with the placeholders `:from`, `:to`, `:total`); check `docker compose exec app php artisan test --filter=TranslationParity`
- [x] 3.7 Extend `docs/admin-data-table.md` with the sections "Column description", "Modes server and client" (a guideline on which to choose), "Usage in a page" (an example controller and `.tsx`), "Column visibility", "Preparation for drag-and-drop" (extension points, the conditions of availability of dragging, the explicit note that server mode does not support it); check that the examples in the document match the code of the demo page

## 4. Demo page and e2e

- [x] 4.1 The controller `Admin\Ui\DataTableDemoController` (404 in production), the route `admin.ui.data-table`, ~42 deterministic rows in memory with columns of all types, the page `Pages/Admin/Ui/DataTableDemo.tsx` with two tables — server (≈42 rows) and client (≈8 rows, like languages), different `tableId`, without an item in the navigation; the Feature test `tests/Feature/Admin/DataTableDemoTest.php` (guest → login, authenticated → 200 and props, production → 404, sorting/filter/search through the parameters, invalid parameters do not give 500) via `docker compose exec app php artisan test --filter=DataTableDemo`
- [x] 4.2 Playwright `e2e/admin-data-table.spec.js`: search, filter (numeric, boolean, range), the sorting cycle with `aria-sort`, pagination and page size, state restoration after a reload, hiding a column with persistence, the empty state, 375 px without horizontal scrolling of the page, Ukrainian localization; for the client table — no network requests when the state changes, the same result as the server table on shared data, the independence of column visibility from the server table; check `npm run test:e2e`
- [x] 4.3 Update `docs/e2e-testing.md` (the new file of scenarios) and `docs/admin-shell.md` (the structure `Components/Admin/DataTable`); check that the list of files in the documents matches the directories

## 5. End-to-end check

- [x] 5.1 Full run: `docker compose exec app php artisan test`, `docker compose exec app vendor/bin/pint --dirty --format agent`, `npm run build`, `npm run test:e2e` — everything green; if something fails, fix it before finishing

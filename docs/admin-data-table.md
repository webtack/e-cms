# Admin data table

The universal `DataTable` component for lists in the admin panel: search, filters, sorting, pagination, and selection of visible columns. The component knows nothing about the domain: it receives a description of the columns and a data page (or all rows in client mode). New sections connect the table with a column description and one call of the helper in the controller. Demo — the page `/admin/ui/data-table` (see [below](#demo-page)).

## Address contract

The state of the table in server mode lives in the address parameters, so reloading the page or opening the link in a new tab restores the same view.

| Parameter | Value | Example |
| --- | --- | --- |
| `q` | search string | `q=lamp` |
| `filters[N][c]` | column key | `filters[0][c]=price` |
| `filters[N][o]` | operator | `filters[0][o]=gte` |
| `filters[N][v]` | value; for `between` — two elements `v[0]` and `v[1]` | `filters[0][v]=100`, `filters[1][v][0]=2024-01-01&filters[1][v][1]=2024-12-31` |
| `sort` | key of the sorted column | `sort=price` |
| `dir` | `asc` or `desc` (without `dir`, `asc` applies) | `dir=desc` |
| `page` | page number, an integer from 1 | `page=2` |
| `per_page` | page size: 15, 30, 50, or 100 | `per_page=30` |

The address is built by `toQueryString` with explicit indexes (`filters[0][c]`, `filters[0][v][0]`): the standard Inertia array format (`filters[][c]`) is not distinguished by PHP for filters. The order of parameters in the address is not guaranteed: Inertia rewrites the query string itself (`dir=asc&sort=price`), so read the parameters by their names.

Default values (empty search, no filters or sorting, `page=1`, `per_page=15`) do not get into the address. The numbers `N` in filters are renumbered from zero on each address write; unfinished filters (without a value, `between` without one of the bounds) do not get into the address. Parsing and writing the address on the client are the pure functions `parseTableParams` and `serializeTableState` in `resources/js/Components/Admin/DataTable/params.ts`.

Filters are combined with the AND condition. Only one column can be sorted at a time.

### Invalid parameters

The server does **not respond with an error** to an invalid address: the invalid value is dropped, the rest is applied, and the dropped value is returned in `ignored` as `{ param, reason }`. Above the table, the component shows a message about the ignored parameters with a reset button.

| `reason` | When |
| --- | --- |
| `unknown_column` | the column is not in the description (in a filter or in `sort`) |
| `not_filterable` | the column does not allow a filter |
| `not_sortable` | the column does not allow sorting |
| `invalid_operator` | the operator is not allowed for the column type |
| `invalid_value` | the value does not fit the type (not a number, not a date `YYYY-MM-DD`, not `true`/`false`, outside `options`, `between` without two bounds), and also invalid `q`, `page`, `per_page`, and `filters` that are not an array |
| `invalid_direction` | `dir` is not `asc`/`desc`; sorting is reset |
| `sort_missing` | `dir` without `sort` |
| `too_many_filters` | more than 10 filters (the extra ones are dropped) |

A `per_page` above 100 is **not** considered an error: 100 applies without an entry in `ignored`. A value that is not in the set 15/30/50/100 (for example, 20) falls back to 15 with an entry `invalid_value`. A page number outside the range is brought to the last page without an error.

## Operators by type

The names match the operators of node querying ([content-querying.md](content-querying.md#operators-by-type)), so the table can be connected to `ContentNodeQuery` without a translator.

| Column type | Operators | Value |
| --- | --- | --- |
| `text` | `eq`, `contains` | string |
| `number` | `eq`, `gt`, `gte`, `lt`, `lte`, `between` | number |
| `date` | `eq`, `gt`, `gte`, `lt`, `lte`, `between` | date `YYYY-MM-DD` |
| `boolean` | `eq` | `true` / `false` (`1` / `0` are also accepted) |
| `select` | `eq` | one of the values of `options` |

`between` — an inclusive range, `v` = `[from, to]`. The single source of truth for operators is `TableRequest::OPERATORS` in PHP; the server passes the table in the prop `operators`, and the TypeScript side (`OPERATORS_BY_TYPE` in `types.ts`) holds only a fallback copy.

### Comparison rules

These rules are the same for server-side processing of a collection (`TablePage::fromCollection()`) and for client mode:

- search and `contains` are case-insensitive; `eq` for text is case-insensitive too (as string comparison in MySQL with the usual collation);
- search applies to columns with `searchable` (by default only `text`); a match in one is enough;
- numbers are compared numerically, dates chronologically, text and `select` values by the character codes of the lowercase form; `false` comes before `true`;
- an empty value (`null`, `''`) does **not** match any filter and is always at the end when sorting, regardless of direction;
- sorting is stable: equal values keep their original order.

## Server helper

The directory `src/Support/Table/`. The helper **runs no queries**: it parses the request into a normalized state, and the controller applies it to its own source (Eloquent, `ContentNodeQuery`, a collection).

| Class | Purpose |
| --- | --- |
| `TableColumn` | server-side column description: `key`, `type`, `sortable`, `filterable`, `searchable`, `options` (for `select`) |
| `TableRequest` | `TableRequest::from($request, $columns)` parses and normalizes the parameters; properties `search`, `filters`, `sort`, `dir`, `page`, `perPage`, `ignored`; `withTotal($total)` brings the page into range; `toState()` returns the state in the form of the TypeScript `TableState` |
| `TablePage` | builds the table prop `{ rows, meta, query, ignored, operators }` |

Columns can be passed as arrays or as `TableColumn` objects; by default `sortable` and `filterable` are `false`.

### In-memory collection

```php
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

$table = TableRequest::from($request, [
    ['key' => 'name', 'type' => 'text', 'sortable' => true],
    ['key' => 'price', 'type' => 'number', 'sortable' => true, 'filterable' => true],
]);

return Inertia::render('Admin/Things', [
    'table' => TablePage::fromCollection($things, $table),
]);
```

### Custom source (database, EAV query)

First count all rows by the filters, bring the page into range, select this page, and pass the result:

```php
$table = TableRequest::from($request, $columns);
// ... apply $table->search, $table->filters, $table->sort/dir to the query ...
$total = $query->count();
$table = $table->withTotal($total);
$rows = $query->forPage($table->page, $table->perPage)->get();

return Inertia::render('Admin/Things', ['table' => TablePage::make($rows, $total, $table)]);
```

### Response shape

```php
[
    'rows' => [...],                       // rows of the current page
    'meta' => ['page' => 2, 'perPage' => 15, 'total' => 42, 'lastPage' => 3, 'from' => 16, 'to' => 30],
    'query' => ['search' => '', 'filters' => [], 'sort' => null, 'dir' => null, 'page' => 2, 'perPage' => 15],
    'ignored' => [],                       // [['param' => 'sort', 'reason' => 'unknown_column'], ...]
    'operators' => ['text' => ['eq', 'contains'], ...],
]
```

`meta.from` and `meta.to` are 0 when there are no rows; `meta.lastPage` is at least 1. `query` is the state the server actually applied (the component shows exactly it).

## Column description

Columns are described by an array `Column<Row>` (`resources/js/Components/Admin/DataTable/types.ts`); the same `key` and `type` are repeated in the PHP description `TableColumn` (for server mode).

| Field | Value |
| --- | --- |
| `key` | the name of the row property; it is also the column name in the address and on the server |
| `header` | the translation key of the header |
| `type` | `text`, `number`, `date`, `boolean`, `select`; determines the operators and the filter input element |
| `sortable`, `filterable` | whether the column can be sorted / filtered; default `false`. An unsortable column does not react to clicks |
| `hideable` | whether it can be hidden; default `true` |
| `defaultVisible` | whether it is visible without a saved choice; default `true` |
| `searchable` | whether the search field searches in it; default `true` only for `text` |
| `options` | `[{ value, label }]` for `select` (`label` is a translation key) |
| `quickFilter`, `allLabel` | only for `select`: show the column as a list next to the search, see [quick filter](#quick-filter-quickfilter); `allLabel` is the translation key of the reset item (default `All`) |
| `width` | the maximum width of the body cell content (a CSS length, for example `24rem`): longer text is cut into one line with an ellipsis, and shorter text does not stretch the column. This is only an upper bound (there is no minimum or fixed width); the column header (`th`) gets the same `max-width`, but its text is not cut; search, filters, and sorting do not change. The cell content is wrapped in `<div class="truncate">` with `max-width`; a custom `cell` with several blocks cuts each line itself (the class `truncate`) and, if needed, gives the full text in `title` |
| `booleanTone` | only for `boolean`: `'warning'` shows a false value as an amber badge instead of quiet muted text (for example the Users "Active" column); ignored when `cell` is set |
| `cell(row)` | a custom cell render; by default: a boolean as a [`BooleanBadge`](admin-visual-style.md#booleanbadge-and-status-tones) ("Yes" as a success badge, "No" as muted text), `select` as the option's label, empty as nothing |

The header row is drawn on a faint tint of the primary colour and a data row is highlighted on hover (`Components/ui/table.tsx`); a row being dragged keeps its own `bg-muted` highlight. Filter values for boolean columns stay "Yes"/"No" and are not affected by the cell styling.

Besides the columns, the table requires `tableId` (the key for saving column visibility) and `getRowId(row)` (a stable unique row key).

The optional prop `emptyMessage` — the translation key of the empty table text instead of the standard "No data yet." (for example, "No files yet." in the media library). If search or filters found nothing, "Nothing found." is shown.

```tsx
const columns: Column<Product>[] = [
    { key: 'id', header: 'ID', type: 'number', sortable: true, filterable: true, hideable: false },
    { key: 'status', header: 'Status', type: 'select', sortable: true, filterable: true,
      options: [{ value: 'draft', label: 'Draft' }], cell: (row) => <StatusBadge status={row.status} /> },
    { key: 'note', header: 'Note', type: 'text', sortable: true, defaultVisible: false, width: '12rem' },
];
```

## Modes `server` and `client`

The mode is set by the prop `mode` (default `server`); the look and behavior are the same.

| | `server` | `client` |
| --- | --- | --- |
| Data | a page from the server (`data`) | all rows at once (`rows`) |
| Who processes | the server by the address parameters | the browser (`clientEngine.ts`) |
| Table state | in the address, restored after reload | in the page's memory, the address does not change |
| Requests | an Inertia visit on each change (only the table prop is updated) | none |
| Several tables on a page | yes, but they share the address parameter names | yes, independently |

**What to choose.** `client` — for small sets (a guideline: up to a few hundred rows; content languages, dictionaries, type fields). `server` — for large ones (content nodes). The threshold remains the decision of the page developer; the component does not guess it. Two server tables on one page would conflict over `q`, `page`, and so on, so a page should have no more than one server table (if needed, a table prefix is added in a separate change).

The client engine `runClientEngine(rows, columns, state)` is a pure function with the same rules as `TablePage::fromCollection()` (see [comparison rules](#comparison-rules)).

## Usage in a page

The controller (server mode):

```php
$table = TableRequest::from($request, [
    ['key' => 'id', 'type' => 'number', 'sortable' => true, 'filterable' => true],
    ['key' => 'name', 'type' => 'text', 'sortable' => true, 'filterable' => true],
    ['key' => 'status', 'type' => 'select', 'sortable' => true, 'filterable' => true, 'options' => ['draft', 'review', 'published']],
]);

return Inertia::render('Admin/Ui/DataTableDemo', [
    'table' => TablePage::fromCollection($rows, $table),
    'clientRows' => array_slice($rows, 0, 8),
]);
```

The page (`Pages/Admin/Ui/DataTableDemo.tsx`):

```tsx
<DataTable filterable tableId="demo-server" columns={columns} getRowId={(row) => row.id} data={table} />

<DataTable filterable mode="client" tableId="demo-client" columns={columns} getRowId={(row) => row.id} rows={clientRows} />
```

- `filterable` — a table prop that enables the "Filters" button in the toolbar. **It is disabled by default**: a table without it has no filter controls (search, sorting, and columns work as usual). The column-level `filterable` remains and decides which columns can be filtered (on the server — `TableColumn::filterable`). A table without `filterable` must not declare filterable columns on the server either: then filters in the address are ignored (reason `not_filterable`). Example — the page of [content types](admin-content-types.md).

- `data` — the prop `table` built by `TablePage`; the prop `prop` (default `table`) is the name of this prop on the page, needed to update only it (`only`).
- All text strings (headers, option labels) are translation keys: add them to `lang/en.json` and `lang/uk.json`.
- A new page without a menu item is added to `extraTitles` (`Components/Admin/navigation.ts`) so that breadcrumbs work.

### Behavior

- **Search** — a field with a 300 ms delay; it changes the address via `replace`, so "Back" is not cluttered; it resets the page.
- **Filters** (only with the `filterable` prop) — the "Filters" button opens a panel: rows "column → operator → value"; the input element depends on the type, and `between` gives two fields. Filters are applied with the "Apply" button, only complete ones; active ones are shown as chips, each removed separately. Chips also show filters for hidden columns.
- **Quick filter** — see [below](#quick-filter-quickfilter).
- **Sorting** — clicking the header: ascending → descending → no sorting; the header is a `<button>` in `<th aria-sort>`.
- **Pagination** — first/previous/next/last, the summary "Showing 16–30 of 42", page size 15/30/50/100; unavailable actions are disabled. The page, sorting, filters, and page size use `push` ("Back" works).
- **Quick clicks** — until the visit completes, the next change is calculated from the already requested state, so three quick clicks on a header give asc → desc → no sorting.
- **Loading** — `aria-busy` on the table and a dimmed body; the data does not disappear.
- **Empty state** — "Nothing found" with a "Reset" button if search, filters, or sorting are active; "No data yet" without a button if there are no parameters.
- **Ignored parameters** — a strip "Some parameters were ignored" with a "Reset" button.
- **Reset** clears search, filters, and sorting, and returns to the first page; the page size stays.
- **Horizontal scroll** — at any window width (mobile and desktop, sidebar expanded or collapsed), a table whose columns do not fit scrolls horizontally inside its container (`table-container`); the page and the shell content area do not widen. This relies on `min-w-0` on `SidebarInset` in `Layouts/AdminLayout.tsx` (see [admin-shell.md](admin-shell.md)): without it the flex item grows to the table width and the container never overflows.

### Quick filter (`quickFilter`)

A column of type `select` with `quickFilter: true` (and `options`) is shown in the toolbar as a **dropdown next to the search field**, without the "Filters" button and without the builder (the table's `filterable` prop is not needed for this). The first item (`allLabel`, default "All") removes the filter.

- The list has no state of its own: the value is taken from `state.filters` (the `eq` filter of this column), and the choice is written through `controller.setFilters` (the helpers `filterValue` and `withColumnFilter` in `stateActions.ts`). So everything works as with a regular filter: the address (in server mode), the chip, "Reset", and the transition to the first page; removing the chip returns the list to "All".
- The server describes the column as `select` with `filterable: true` and `options` (values, not pairs) — otherwise the value in the address will be dropped. A table without columns with `quickFilter` has no additional lists.
- The label of the list for screen readers is the column header. Example — [media library](media-library.md#media-library-page), the filter by file type.

```tsx
{ key: 'mime', header: 'Type', type: 'select', quickFilter: true, allLabel: 'All types',
  options: types.map((type) => ({ value: type, label: type })) }
```

## Column visibility

The "Columns" button opens a menu with checkboxes. The choice is saved in `localStorage` separately for each table: the key `admin.table.{tableId}.columns`, the value — an array of **hidden** keys (so new columns appear by default). Without a saved choice `defaultVisible` applies. For the node list, the composition and default order of columns are set by the type (`settings.list.visible`, see [admin-content-nodes.md](admin-content-nodes.md#how-columns-are-built)); `localStorage` only hides columns on top of it and does not change the type's settings. A column with `hideable: false` is always visible; in the menu it is marked and disabled.

- Hiding does not change search, filters, sorting, the page, the address, or the query; a hidden column remains available for filters and sorting.
- If the storage is unavailable (private window, blocking), all reads and writes are wrapped in `try/catch`: the choice lives until leaving the page, and the user sees no errors.
- Tables with different `tableId`s are independent; `tableId` must be unique within the admin panel.
- Limitation: a column added later with `defaultVisible: false` will appear visible for a user with a saved choice (only the list of hidden keys is saved).

## Row actions and toolbar

Two optional extensions, the same in server and client modes. Without them the table looks and behaves as before.

- `rowActions={(row) => ReactNode}` — the last column with row actions (usually a group of buttons: a main action and a "⋮" menu, see [content types](admin-content-types.md)). It is not a `Column`: the header is empty with the screen-reader-only label "Actions", the column cannot be hidden (it is not in the "Columns" menu), and it takes no part in search, filters, or sorting. The empty state's `colSpan` accounts for it.
- `toolbarActions={ReactNode}` — elements at the end of the toolbar (for example, a "Create" button). On narrow screens they move to a new line; changing the search, filters, or page does not reset them.

```tsx
<DataTable
    tableId="content-types"
    columns={columns}
    getRowId={(row) => row.key}
    data={table}
    rowActions={(row) => <ContentTypeRowActions type={row} />}
    toolbarActions={<Button size="sm">{t('Create')}</Button>}
/>
```

An example of use — the [content types](admin-content-types.md) page and the demo page (both tables have an "Actions" menu and a "Create" button).

## Reordering by drag and drop (`reorder`)

An optional prop `reorder?: { onReorder(orderedIds: string[]): void }`, **only in client mode** (in server mode it is absent from the types). Without it, the markup and behavior of the table do not change, and the drag library does not take part in rendering.

```tsx
<DataTable
    mode="client"
    tableId="content-fields"
    columns={columns}
    getRowId={(row) => row.key}
    rows={fields}
    reorder={{ onReorder: (ids) => setFields(ids.map((id) => fields.find((f) => f.key === id)!)) }}
/>
```

- **Handle.** With `reorder`, each row has a handle (`GripVertical`, `aria-label` "Drag to reorder") in the first cell; an empty narrow column appears in the header for it. With the mouse, the row is dragged by the handle (activation threshold 4 px, so a click does not start a drag). From the keyboard: focus on the handle → space lifts the row → arrows move it → space confirms (Esc cancels). The library is `@dnd-kit/core` + `@dnd-kit/sortable` (`PointerSensor`, `KeyboardSensor` with `sortableKeyboardCoordinates`, `verticalListSortingStrategy`); the announcements for screen readers are provided by the library itself.
- **Result.** After a drop that changed the row's position, `onReorder` is called with the identifiers of **all** rows in the new order (rows are `String(getRowId(row))`). The table does not save the order itself: the page updates its `rows` (then the new order is visible at once) and, if needed, saves it on the server. Example — [the list of type fields](admin-content-fields.md#order).
- **When the handles are active.** Only when the whole set is visible in its original order: there is no search, no active filters, no sorting by a column, and all rows fit on one page (`meta.total <= perPage`). Otherwise the handles are `disabled` with a hint (`title`) "You can change the order only when all rows are shown without search, filters, and sorting.". "Reset" returns the original order and activates the handles (if the set fits on a page).
- **Slot `rowHandle`.** `rowHandle(row)` is an optional custom content of the same leading cell; with `reorder` it is rendered **after** the handle. Without `reorder` the cell appears only when `rowHandle` is passed.
- **Accessibility.** The handle is a real `<button>` with `aria-roledescription` and `aria-describedby` from the library, focusable from the keyboard; `touch-action: none` on the handle allows dragging on touch screens without scrolling the page.

An optional prop `paginated?: boolean` (**client mode only**, default `true`). With `paginated={false}` every row matching search and filters is shown at once (the page size is unbounded) and the pagination bar (summary, page size, navigation) is not rendered; search, filters, sorting, and reordering still work. It is used for trees that are reordered by dragging, where splitting into pages would make the handles unavailable ([menu items](admin-menus.md#items-page)).

**Server mode with pagination deliberately does not support reordering**: moving between pages is unreliable, and reordering is not a scenario there. For a large set this is a separate decision (another screen or client mode).

## Structure

`resources/js/Components/Admin/DataTable/`:

| File | Purpose |
| --- | --- |
| `DataTable.tsx` | composition; choice of the source by `mode`; the drag context for `reorder` |
| `DataTableToolbar.tsx` | search, filters, the column menu, "Reset", the `toolbarActions` slot |
| `DataTableFilters.tsx` | the filter panel and the chips of active filters |
| `DataTableColumnMenu.tsx` | the column visibility menu |
| `DataTablePagination.tsx` | summary, page size, navigation |
| `DataTableRow.tsx` | one row: the `rowActions` cell, the leading `rowHandle` cell, the draggable row mode with a handle (`sortable`) |
| `useTableState.ts` | the server source: state from the prop, changes through Inertia |
| `useClientTableState.ts` | the client source: state in `useState`, processing by `clientEngine` |
| `useColumnVisibility.ts` | column visibility in `localStorage` |
| `clientEngine.ts` | a pure function for search, filters, sorting, and pagination |
| `stateActions.ts` | pure state transitions shared by both modes |
| `params.ts` | parsing and writing of the address parameters |
| `types.ts` | types, constants, the fallback operators table |

shadcn/ui primitives: `table`, `select`, `popover`, `checkbox`, `badge` (`Components/ui/`). Row reordering (`reorder`) uses `@dnd-kit/core` and `@dnd-kit/sortable` (npm, on the host).

## Demo page

`GET /admin/ui/data-table` (`admin.ui.data-table`, the controller `Webtack\Ecms\Http\Controllers\Admin\Ui\DataTableDemoController`, the page `Pages/Admin/Ui/DataTableDemo.tsx`). Only for authenticated users and only outside production (in production — 404); it is not added to the navigation. It has three tables: a server one (42 deterministic rows in memory with columns of all types; `tableId` `demo-server`), a client one (the first 8 of the same rows; `tableId` `demo-client`), and a client one with drag and drop (the first 5 rows; `tableId` `demo-reorder`, without `filterable`; the order lives in the page state and is not saved anywhere). The "Note" column is hidden by default, and every fourth row has no note (to check empty values).

## Tests

`tests/Unit/Support/Table/TableRequestTest.php` (parsing, all operators by type, `between`, an unknown column, an invalid operator, `per_page` above the maximum, a page out of range) and `TablePageTest.php` (each operator on a collection, search, sorting with empties at the end, stability, pagination, an empty result).

`tests/Feature/Admin/DataTableDemoTest.php` (a guest, authenticated, production → 404, sorting/filter/search/page through parameters, invalid parameters without 500).

```bash
vendor/bin/phpunit --filter=Table
vendor/bin/phpunit --filter=DataTableDemo
```

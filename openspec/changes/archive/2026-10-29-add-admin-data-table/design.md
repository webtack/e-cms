# Design

## Context

The admin shell (Inertia + React 19 + TypeScript + Tailwind 4 + shadcn/ui) is described in `docs/admin-shell.md`, interface translations in `docs/admin-i18n.md`, e2e in `docs/e2e-testing.md`. At present `resources/js/Components/ui` has no `table`, `select`, `popover`, `checkbox`, `badge`; there is no data list in the admin yet. The content querying backend (`docs/content-querying.md`) uses the operators `eq`, `gt`, `gte`, `lt`, `lte`, `between`, `contains` and `OR` search over text fields; the component must speak the same operators so that it can later connect to `ContentNodeQuery` without a translator. The motivation is in proposal.md.

## Goals / Non-Goals

**Goals:**
- One `DataTable` component that knows nothing about the domain: it receives a description of the columns and a data page, and manages only the address parameters.
- A server contract (parameter names, operators, pagination shape) that works the same way for Eloquent, `ContentNodeQuery` and arrays.
- New sections connect the table with a column description and one helper call in the controller.

**Non-Goals:**
- Client-side data processing (sorting/filters in the browser) as a general mode, row selection, bulk actions, storing column visibility on the server, virtualization.

## Decisions

**1. Server-side state, not client-side.** Search, filters, sorting and pagination are performed by the server according to the address parameters; the component only builds the address through Inertia `router.get` (`preserveState`, `preserveScroll`, `only: [table prop]`). This matches how `ContentNodeQuery` works and allows the state to be restored from the address. *Alternative:* `@tanstack/react-table` in client mode — rejected: there is too much data for the client, and a new dependency would be needed only to manage state; server mode still requires its own address management.

**1a. `server` and `client` modes.** The mode is set by the prop `mode` (default `server`). The client mode is intended for small sets (content languages, reference lists): the rows arrive as a single array, and the processing is done in `clientEngine.ts` — a pure function `(rows, columns, state) → { rows, meta }` with the same operators and rules as the server (case-insensitive `contains` and search, numeric/chronological comparison, empty values at the end in both directions, stable sorting). The rest of the component (toolbar, filters, headers, pagination, column visibility) is shared: it works with `state` and `meta`, and only the hook determines the source. The client table state is kept in `useState` and does not get into the address (so as not to make unnecessary Inertia visits and not to mix the state of several tables on a page). *Alternative:* the client table state also in the address — rejected: with two tables on a page there would be parameter name conflicts; if needed, we will add a table prefix separately. The threshold for switching from `client` to `server` remains the decision of the page developer; the component does not guess, and the documentation gives a guideline (up to several hundred rows).

**2. Address contract.** `q` — search; `filters[N][c]`, `filters[N][o]`, `filters[N][v]` — column, operator, value (for `between`, `v` is an array of two elements); `sort` + `dir` (`asc|desc`); `page`; `per_page`. Short names keep the address compact; the numbers `N` are renumbered at serialization. The page size is chosen from the set 15/30/50/100, default 15 (as `content.per_page`), maximum 100 (as `content.max_per_page`).

**3. Column description.** `Column<Row>`: `key`, `header` (translation key), `type` (`text|number|date|boolean|select`), `sortable`, `filterable`, `hideable` (default true), `defaultVisible` (default true), `options` for `select`, `cell(row)` — a custom cell renderer. The same description is serialized in the PHP helper (key, type, sortable, filterable, searchable) for parameter validation.

**4. Operators by type** (a single table in TS and PHP): `text` → `eq`, `contains`; `number` and `date` → `eq`, `gt`, `gte`, `lt`, `lte`, `between`; `boolean` → `eq`; `select` → `eq`. Duplicating the table in TS and PHP is deliberate: PHP is the source of truth for validation, TS is for the UI; consistency is checked by the e2e scenario and a Feature test, and the server can pass the operator table in a prop so that the UI does not diverge (**we choose passing it from the server**; TS has only a fallback copy for types).

**5. PHP helper.** `App\Support\Table\TableRequest` parses the `Request` according to the column description: it normalizes and validates the parameters, **ignores** incorrect ones (without 500/422), collects the list `ignored` (parameter + reason) and limits `per_page`. `App\Support\Table\TablePage` builds the response `{ rows, meta: { page, perPage, total, lastPage, from, to }, query: {...normalized state}, ignored }` from a `LengthAwarePaginator` or from a collection. A page number beyond the range is brought to the last one: the helper accepts `total` before the selection is executed (or repeats the query once). The helper does not execute SQL itself — it returns the normalized state, and the controller applies it to its own source (Eloquent, `ContentNodeQuery`, a collection). *Alternative:* a trait on Eloquent — rejected: the main source (the EAV selection) is not an Eloquent query.

**6. Components** in `resources/js/Components/Admin/DataTable/`: `DataTable.tsx` (composition), `DataTableToolbar.tsx` (search, filter button, column menu, reset), `DataTableFilters.tsx` (filter rows + chips of active filters), `DataTableColumnMenu.tsx`, `DataTablePagination.tsx`, `useTableState.ts` (reading/writing the address, debounce), `useColumnVisibility.ts` (localStorage), `types.ts`, `params.ts` (pure functions for parsing/serializing the address). The shadcn primitives — `table`, `select`, `popover`, `checkbox`, `badge` (via the CLI per `docs/admin-shell.md`); input fields — the existing `input`, `button`, `dropdown-menu`.

**7. Column visibility.** The `localStorage` key: `admin.table.{tableId}.columns` → an array of hidden keys (we store the hidden ones so that new columns appear by default). All reads/writes are in `try/catch`; without storage the state lives in the page memory. Visibility does not get into the address and does not affect the query (columns that are not shown may still be returned by the server; the optimization "do not load hidden fields" is a separate change). Hidden columns remain available for filters/sorting.

**8. Address and history.** A change of page, sorting, page size or filter — `push` (the "Back" action works); typing in search — `replace`. Search with a debounce of 300 ms, resets `page` to 1; any change of filters/sorting/size also resets `page`.

**9. Loading.** The Inertia events `onStart/onFinish` turn on `aria-busy` and dim the table body; the data is not cleared, so the table does not "jump".

**10. Parameter errors.** The helper returns `ignored`; the component shows an unobtrusive message above the table ("Some parameters were ignored") with a reset button. Flash is not used, because the response is an ordinary GET page.

**11. Accessibility.** The header of a sorted column is a `<button>` inside `<th aria-sort>`; icons have `aria-label`/`sr-only` text; the table container is `overflow-x-auto`.

**12. Demo page.** `GET /admin/ui/data-table` (`admin.ui.data-table`); the controller returns 404 in production (`abort_if(app()->isProduction(), 404)`); it is not added to the navigation. The data is a deterministic array of ~42 rows in memory (text, number, decimal, date, boolean, select), processed by the same helper on a collection — without a DB and migrations. A second table on the page works in `client` mode with fewer rows (≈8, like languages), which checks the independence of the states and column visibility of the two tables (different `tableId`). Conditional registration is not needed: the check in the controller is simpler to test.

**13. Preparation for drag-and-drop of rows.** Dragging to change the order (`weight`) will certainly be needed (the order of type fields, content languages, later blocks), but this change does not implement it. To add it without reworking the component, we lay down: (a) a mandatory stable row key `getRowId(row)` for each table; (b) an optional first column slot `rowHandle` (rendered in the cell before the data, not used in this change); (c) rendering of rows in a separate component `DataTableRow`, which can later be wrapped with the sortable context of a drag library (candidate — `@dnd-kit/core` + `@dnd-kit/sortable`, the choice is confirmed by a separate change); (d) an accessibility rule: dragging is active only when the order by `weight` applies — without search, filters and sorting by another column, otherwise the visible order would not match the real one. Decision: dragging is foreseen **only for the client mode**, where the whole set is on one page (languages, type fields). In server mode with pagination we deliberately do not support it: moving between pages is unreliable, and changing the order there is not a scenario. If a large set needs ordering, that is a separate decision (for example, another screen or client mode). Saving the order is a separate endpoint that writes `weight`; its contract is not part of this change.

**14. Languages.** New rows are English phrase keys in `t()` + pairs in `lang/en.json` and `lang/uk.json` (checked by `TranslationParityTest`). The pagination summary and the count use the placeholders `:from`, `:to`, `:total`.

## Risks / Trade-offs

- [The operator tables in PHP and TS diverge] → the operators are passed from the server as a prop; a Feature test checks the table, e2e checks the choice of an operator by type.
- [Complex serialization of nested parameters `filters[N][..]` through Inertia] → the logic is in pure functions in `params.ts` and is covered by e2e (state restoration after a reload); there is no separate JS unit runner in the project, and we do not plan to add a dependency for this.
- [Visibility in `localStorage` is not synchronized between devices] → accepted; server-side storage is a separate change.
- [The demo page in production code] → closed by an environment check and covered by a test; not in the navigation.
- [Divergence of the client engine and server behaviour (case, empty values, number comparison)] → the rules are fixed in the spec; e2e compares the results on identical data in the two modes; the server Feature test records the same rules.
- [Drag-and-drop, laid down in advance, may require changes] → in this change we add only cheap extension points (`getRowId`, `rowHandle`, `DataTableRow`); the choice of library and the contract for saving the order are deliberately deferred. The server mode does not support dragging by the owner's decision.
- [Drift from ContentNodeQuery] → the operator names match deliberately; the connection to the content is done in `add-content-admin-ui`.

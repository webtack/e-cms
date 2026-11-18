# Proposal

## Why

Future admin sections (content types, nodes, languages, users) need identical list tables: search and filters, sorting, pagination, a choice of visible columns. Without a shared component each section implements this in its own way, and the state of the list (filters, page) is lost when the page is refreshed. We first create a universal component, and then build the content admin on top of it (see `add-content-admin-ui`).

## What Changes

- A new reusable `DataTable` component for the admin (React + TypeScript, shadcn/ui, without new npm dependencies), which displays data from the server. The table state (search, filters, sorting, page, page size) lives in the address parameters and is executed by the server; the component only manages the address through Inertia.
- Two data source modes with the same look and behaviour: `server` (the default; large sets, such as content nodes) and `client` (small sets, such as content languages: all rows are passed at once, and search, filters, sorting and pagination are performed by the browser with the same operators and rules).
- The column description is declarative: key, header, whether it can be sorted, a custom cell render, default visibility, whether the column can be hidden.
- A toolbar: a search field with a debounce, a filter builder (field → operator → value; the operators and the input type depend on the column type), a column visibility menu, a reset button.
- Sorting by clicking a column header (asc → desc → no sorting), a direction indicator.
- Pagination: moving between pages, choosing the page size, a summary "showing X–Y of N".
- Column visibility is stored in the browser for each table separately (by `tableId`), works without storage (a private window), and the mobile version does not break (horizontal scrolling of the table).
- An empty state, a loading state (a request in progress) and a parameter error.
- A server contract: a small PHP helper that parses the table parameters from the request and builds a paginator in the form the component understands, so that controllers do not duplicate the parsing of the address.
- The component interface is localized (uk/en) with the existing `t()`.
- A demo page of the component only in non-production environments (for developers and e2e tests), without an item in the navigation; it contains two tables: a server one and a client one.
- Preparation for changing the order of rows by drag-and-drop: the function itself is not implemented in this change, but the component has a stable row key, a slot for a row control element and clearly described conditions under which dragging will be available: only in client mode; in server mode with pagination, dragging is deliberately not supported (see design.md).
- Out of scope: connecting the component to real sections (types, nodes, languages), row selection and bulk actions, inline editing, export, storing visibility on the server, the drag-and-drop of rows itself (a separate next change), and the reordering of columns by dragging.

## Capabilities

### New Capabilities
- `admin-data-table`: the behaviour of the universal admin table — search, filters, sorting, pagination, column visibility, state in the address.

### Modified Capabilities

None.

## Impact

- Code: `resources/js/Components/Admin/DataTable/*` (the component, the filter panel, the column menu, pagination, types, address parameter utilities, the client row processing engine), new shadcn/ui primitives (`table`, `select`, `popover`, `checkbox`, `badge`), a PHP helper in `app/Support/` (parsing of table parameters), a controller and a demo page, a route for non-production only; translations in `lang/en.json` and `lang/uk.json`.
- Dependencies: no new packages (only shadcn/ui files).
- Tests: Feature tests of the helper and the demo page in `tests/Feature/Admin/`, e2e `e2e/admin-data-table.spec.js`.
- The existing change `add-content-admin-ui` is not modified by this change; its tasks for lists of nodes/types will use `DataTable` in the future (to be agreed separately).
- Documentation (in Ukrainian): create `docs/admin-data-table.md`; update `docs/admin-shell.md` (the structure of `Components/Admin`), `docs/e2e-testing.md` and the index `docs/README.md`.

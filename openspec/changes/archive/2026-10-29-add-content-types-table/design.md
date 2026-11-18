# Design

## Context

The `ContentType` model (`key`, `name`, `description`, `kind`, `parent_type`) and the relations `fields()` / `nodes()` are described in [docs/content-model.md](../../../docs/content-model.md). The data table ([docs/admin-data-table.md](../../../docs/admin-data-table.md)): the server parses the address via `TableRequest`, and `TablePage` builds the `table` prop; `DataTable` renders it. The current `DataTable` (`DataTable.tsx`, `DataTableRow.tsx`, `DataTableToolbar.tsx`) has neither a row action column nor a slot in the toolbar. The navigation is `resources/js/components/Admin/navigation.ts` (the item `Content Types` is now `mock: true`), see [docs/admin-shell.md](../../../docs/admin-shell.md). The motivation is in proposal.md.

## Goals / Non-Goals

**Goals:**
- A server-side table of types on the existing `TableRequest`/`TablePage` without changing their contract.
- General, domain-independent extensions of `DataTable`: row actions and a toolbar slot, suitable for all future sections.
- Counters without N+1.

**Non-Goals:**
- Create/edit forms, the fields page, deletion of types, a tree by `parent_type` (separate changes; only an inactive skeleton here).
- New authorization: access as for the rest of the admin (`auth` + `verified`).
- Changes to the DB schema, bulk actions, row selection.

## Decisions

1. **Server mode, data through `TablePage::fromCollection()`.** The table works in `mode="server"`: everything is done by the server by the address. The source is one query `ContentType::withCount(['fields','nodes'])->get()`, converted into rows; there are dozens of types, so the existing, tested collection logic (the same as the client engine) is better than duplicating the operators in SQL. Alternative — `TablePage::make()` with an Eloquent query: it would require separate implementations of operators and sorting by counters in SQL. The page contract does not depend on the choice, so a switch to `make()` is possible later.
2. **The default sorting — in the controller.** Without `sort`, `TableRequest` does not sort, so the rows are pre-ordered by name (the sorting of `fromCollection` is stable); an explicit `sort` from the address overrides it.
3. **Columns.** `name`, `key`, `description` — `text`; `kind` — `select` with options from `config('content.kinds')`; `parent_type` — `text`, `searchable: false`; `fields_count`, `nodes_count` — `number`. All except the description are sortable; all are filterable. The row keys are snake_case, like the model attributes. The description `defaultVisible: false`; `name`, `key` — `hideable: false`.
4. **Row actions as an optional prop `rowActions?: (row) => ReactNode` in `DataTableBaseProps`.** `DataTableView` adds the last column (an empty header with an `sr-only` label "Actions"), and `DataTableRow` renders the result of `rowActions(row)` in it. The column is not a `Column`, so it does not take part in visibility, search, filters or sorting. The `colSpan` of the empty state takes it into account. Alternative — `actions` inside `Column.cell`: it would break column selection (it can be hidden) and would duplicate the layout.
5. **The toolbar slot: the prop `toolbarActions?: ReactNode`** is passed to `DataTableToolbar` and rendered at the end of the row (`ml-auto`, wraps on narrow screens). Alternative — a page outside the table: it would not align with the search/filters and would duplicate the flex layout.
6. **The row menu — the component `ContentTypeRowActions`** (`resources/js/components/Admin/ContentTypes/`) on the shadcn `DropdownMenu`: the trigger — `Button variant="ghost" size="icon"` with the `MoreHorizontal` icon and `aria-label` "Actions"; the items Edit / Fields / Delete (`variant="destructive"` for deletion, a `DropdownMenuSeparator` before it). **Skeleton:** the items and the "Create" button are rendered with `disabled`; the description of the actions is collected in one place (an array `{ key, label, icon, destructive }`), so connecting the routes and the deletion dialog is a change in one file.
7. **Route and controller.** `GET /admin/content-types` → `Admin\ContentTypeController@index` (a resource name, so that `create/store/edit/destroy` fit next to it), name `admin.content-types.index`, in the group `auth`+`verified`. The page `Admin/ContentTypes/Index.tsx` with `AdminLayout`, `tableId="content-types"`.
8. **Navigation.** In `navigation.ts` remove `mock: true` from the item `Content Types`; `isActive` and `getBreadcrumbs` work without changes.
9. **Demonstration.** The demo page `DataTableDemo` receives `rowActions` and `toolbarActions` in the server and client tables, so that the e2e tests of the data table cover the extensions independently of the domain.

## Risks / Trade-offs

- [All types are loaded into memory on each request] → acceptable for the expected volume; the path of migration to `TablePage::make()` is described above.
- [`fields_count` includes the system field `title`] → we show the real count, recorded in the specification.
- [Inactive items may be misleading] → the standard disabled style of shadcn and `aria-disabled`; the inactivity is recorded in the specification and removed by the next changes.
- [A change of the shared `DataTable` may affect the existing pages] → the new props are optional; the existing e2e and Feature tests of the table must remain green.
- [New translation keys can be forgotten] → a task with a check of both `lang/*.json` and e2e in two languages.

## Documentation

`docs/admin-content-types.md` (new), `docs/admin-data-table.md` (the section "Row actions and toolbar actions"), `docs/admin-shell.md`, the index `docs/README.md`.

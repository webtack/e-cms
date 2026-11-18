# Tasks

## 1. DataTable: row actions and the toolbar slot

- [x] 1.1 In `types.ts` add the optional `rowActions?: (row) => ReactNode` and `toolbarActions?: ReactNode` to `DataTableBaseProps` with JSDoc in English and `@example`; check `npm run build` without type errors
- [x] 1.2 In `DataTable.tsx`/`DataTableRow.tsx` add the last actions column (an empty header with an `sr-only` "Actions" label, not part of the column visibility, the `colSpan` of the empty state takes it into account); check that without `rowActions` the table markup has not changed (the existing table e2e tests are green)
- [x] 1.3 In `DataTableToolbar.tsx` render `toolbarActions` at the end of the row with wrapping on narrow screens; check visually at 375px and on desktop
- [x] 1.4 Connect `rowActions` and `toolbarActions` to `DataTableDemo` (both tables) and add translations; extend `e2e/admin-data-table.spec.js` (the "⋮" menu opens with the keyboard, there is no actions column in the column choice, the button survives a change of the search); `npm run test:e2e -- admin-data-table` passes
- [x] 1.5 Update `docs/admin-data-table.md` (the section on row actions and the toolbar slot, a code example); check the links

## 2. Backend: route and controller

- [x] 2.1 Add `App\Http\Controllers\Admin\ContentTypeController::index` (`ContentType::withCount(['fields','nodes'])`, rows by name, columns per the design, `TablePage::fromCollection`, `Inertia::render('Admin/ContentTypes/Index')`) with PHPDoc in English and `@example`
- [x] 2.2 Register `GET /admin/content-types` (`admin.content-types.index`) in the group `auth`+`verified` in `routes/web.php`; check `docker compose exec app php artisan route:list --name=content-types`
- [x] 2.3 Write `tests/Feature/Admin/ContentTypeIndexTest.php`: the guest redirect, the component and the props, the counters, the order by name, search by key, a filter by kind and by a counter, sorting by a counter, pagination, an ignored parameter, the empty state; `docker compose exec app php artisan test --filter=ContentTypeIndexTest` passes
- [x] 2.4 `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks

## 3. Page, row menu, navigation

- [x] 3.1 Create `resources/js/components/Admin/ContentTypes/ContentTypeRowActions.tsx` (`DropdownMenu`, the "⋮" trigger with `aria-label`, the items Edit/Fields/Delete inactive, Delete destructive after a separator) with JSDoc in English; check in the browser
- [x] 3.2 Create `resources/js/pages/Admin/ContentTypes/Index.tsx` (`DataTable` in server mode, `tableId="content-types"`, the columns per the design, the `cell`/`options` of kinds via `t()`, the inactive "Create" button in `toolbarActions`, `rowActions`) with JSDoc; `npm run build` passes
- [x] 3.3 Remove `mock: true` from the `Content Types` item in `navigation.ts`; check that the item is an active link, highlighted on `/admin/content-types`, and the breadcrumbs show "Content Types"
- [x] 3.4 Add translation keys (columns, kinds, "Create", "Edit", "Fields", "Delete", "Actions") to `lang/uk.json` and `lang/en.json` in alphabetical order; check the page in Ukrainian
- [x] 3.5 Create `docs/admin-content-types.md` (the route, columns, data source, the skeleton of actions and how to connect them), update `docs/admin-shell.md` and `docs/README.md`; check the links

## 4. E2E and integration check

- [x] 4.1 Add `e2e/admin-content-types.spec.js`: navigation via the sidebar, search and filter update the address, sorting, pagination, column visibility, the "⋮" menu with three items, the inactive "Create" button, uk/en; `npm run test:e2e -- admin-content-types` passes
- [x] 4.2 Check that the existing shell e2e tests do not treat Content Types as a layout; the full `npm run test:e2e` passes
- [x] 4.3 The full `docker compose exec app php artisan test` and `openspec validate add-content-types-table` without errors

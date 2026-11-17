# Tasks

## 1. Dashboard reads the configured list

- [x] 1.1 In `app/Dashboard/DashboardData.php` read `dashboard.create_types` from `AppSettings` in `create()`: when absent keep the current output; when set, map the keys onto the accessible types in stored order, skipping inaccessible and deleted types, and return a `create_flat` boolean next to `create` (JSDoc/PHPDoc in English with `@example`). Verify with new cases in `tests/Feature/Dashboard/DashboardDataTest.php` (not configured, configured order, key without access, deleted type, nothing accessible hides the menu): `docker compose exec app php artisan test --compact --filter=DashboardDataTest`.
- [x] 1.2 Add `create_flat` to `DashboardProps` in `resources/js/Components/Admin/Dashboard/types.ts`, pass it through `Pages/Admin/Dashboard.tsx`, and make `QuickCreateMenu.tsx` render a flat list without kind headings when it is true (JSDoc with `@example`). Verify by `npm run build` and, in the browser, that the unconfigured menu is still grouped.
- [x] 1.3 Update `docs/admin-dashboard.md` (the Quick create row, `create_flat`, configured vs default behavior, link to `admin-navigation-menu.md`). Verify the section describes both modes and the links resolve.

## 2. Settings page: "Create menu" section

- [x] 2.1 Add the route `PUT /admin/settings/navigation/create-types` (name `settings.navigation.create-types`) under `can:settings` in `routes/web.php` and the `updateCreateTypes` action in `NavigationMenuController`: validate `types` as an array of unique existing `content_types.key`; an empty array calls `AppSettings::forget('dashboard.create_types')`, otherwise `set()`. Verify with new tests in `tests/Feature/Navigation` (save, reorder, empty list clears the key, unknown and duplicate type rejected, 403 without `settings`): `docker compose exec app php artisan test --compact --filter=CreateMenuSettingsTest` (new test file).
- [x] 2.2 In `NavigationMenuController::index()` pass `createTypes` (saved keys in order resolved to `key`, `name`, `kind`, dropping deleted types) and `createAvailableTypes` (types not in the list). Verify in the same test file that the props match for a stale key.
- [x] 2.3 Add the "Create menu" section to `resources/js/Pages/Admin/Settings/Navigation.tsx`: type select with an Add button, a sortable list with handle (own `DndContext`, mouse and keyboard) and a remove button; each change sends the full list through `PUT …/create-types` (`data-testid`s `create-menu-*`); strings in `lang/en.json` and `lang/uk.json`; JSDoc updated. Verify with `npm run build` and manual check of add, reorder, remove.
- [x] 2.4 Update `docs/admin-navigation-menu.md` (the section, the new route in the table, the independence from the tree) and add the `dashboard.` prefix to the key table in `docs/app-settings.md`. Verify links and that `docs/README.md` needs no change.

## 3. e2e

- [x] 3.1 Extend `e2e/admin-favorites-menu.spec.js` with a test that adds two types in the "Create menu" section, reorders them and removes one, checking persistence after reload; clean the setting in the test teardown. Verify: `npm run test:e2e -- e2e/admin-favorites-menu.spec.js -g "Create menu"`.
- [x] 3.2 Extend `e2e/admin-dashboard.spec.js` with a test that the configured list shows only the chosen types in order without kind headings, and that clearing it restores the grouped menu; clean the setting afterwards. Verify: `npm run test:e2e -- e2e/admin-dashboard.spec.js -g "Create"`.
- [x] 3.3 Update the rows of `admin-dashboard` and `admin-favorites-menu` in `docs/e2e-testing.md` (the checks list). Verify the descriptions mention the configurable Create menu.

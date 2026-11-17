# Tasks

## 1. Schema and model

- [x] 1.1 Add a migration with nullable unique `node_uuid` on `admin_menu_entries` (FK to `content_nodes.uuid`, `cascadeOnDelete`), update the migration comment; verify with `docker compose exec app php artisan migrate` and a test that deleting a node removes its menu row. This task changes the DB schema, so run the full `docker compose exec app php artisan test` after it.
- [x] 1.2 In `App\Models\AdminMenuEntry` add `node_uuid` to `#[Fillable]`, `isNodeLink()` / `isTypeLink()`, and make `isGroup()` mean "no type and no node"; update the class JSDoc-style docblock with an `@example`; verify with `docker compose exec app php artisan test --compact --filter=AdminMenuTest`.

## 2. Writing the menu

- [x] 2.1 Add `AdminMenu::addNodeLink(string $uuid, ?AdminMenuEntry $group)` (node exists, not already in the menu, parent is a root group; errors on `node_uuid` / `parent_id`); verify with new cases in `tests/Feature/Navigation/AdminMenuTest.php` (add to root, add to group, duplicate, unknown node, nested group, type and its node together).
- [x] 2.2 Check that `AdminMenu::save()` keeps node links valid as group children and root items, and that an entry list missing a node link is still rejected; verify with `AdminMenuTest` reorder cases for node links.
- [x] 2.3 Extend `POST /admin/settings/navigation/links` in `NavigationMenuController::storeLink` to accept exactly one of `type_key` or `node_uuid`; extend `index` to return node link rows (`node_uuid`, node title, type name, kind) read in one batched query with the `slug` fallback; verify with feature tests including the 403 without the `settings` right (`RouteCoverageTest` still passes: `docker compose exec app php artisan test --compact tests/Feature/Access/RouteCoverageTest.php`).
- [x] 2.4 Document the schema, rules, routes and the `node_uuid` payload in `docs/admin-navigation-menu.md`; verify the doc matches the routes via `docker compose exec app php artisan route:list --path=admin/settings/navigation`.

## 3. Sidebar data and access

- [x] 3.1 In `SidebarNavigation::menu()` build node links: one batched query for the nodes of all node entries and their titles (current locale, then `default`, then `slug`), keep a link only when the level on the node's type is `any`, or `own` with `user_id` equal to the user; emit `{ type: 'link', node: true, key, name, kind, href }` with href `/admin/{section}/{type}/nodes/{uuid}/edit`; verify with new cases in `tests/Feature/Navigation/SidebarNavigationTest.php` (any, own by author, own by another author, none, group hidden when empty, renamed title, deleted node) and a check that the number of queries does not grow with the number of node links.
- [x] 3.2 Keep the node visibility rule in one place shared with `ContentNodePolicy` (a method on `AccessResolver` or the policy used by both); verify with a test that compares the sidebar decision with `Gate::allows('update', $node)` for each level.
- [x] 3.3 Update the "Favorites and admin menu" section of `docs/access-control.md` and the shape of `navigation.menu` in `docs/admin-navigation-menu.md`; verify the examples against the test expectations.

## 4. Admin UI

- [x] 4.1 Update the types in `resources/js/types` for node links in `navigation.menu` and in the settings tree; verify with `npm run build` on the host (no TypeScript errors).
- [x] 4.2 In `Pages/Admin/Settings/Navigation.tsx` add the "Add node" control (type select, then `RefAutocomplete` over `/admin/lookup/nodes`, target root or group) and node link rows with the node icon, title and type name, draggable like type links, deletable; add the new phrases to `lang/en.json` and `lang/uk.json`; verify manually in the browser and with the e2e task 5.1.
- [x] 4.3 In `Components/Admin/AppSidebar.tsx` render node links with a node icon and exact-path activity; make a type link active only when no node link of the menu matches the URL, keep the "owned by a link" rule for section items, and expand a group that holds the active node link; add JSDoc to changed public code; verify with e2e task 5.1.
- [x] 4.4 Update the sidebar description and the active-item rule in `docs/admin-shell.md` (blocks "Navigation" and "Expanded groups"); verify the text matches the implemented behavior.

## 5. End-to-end

- [x] 5.1 Extend `e2e/admin-favorites-menu.spec.js` with: adding a node on the settings page, the node link and its label in the sidebar, opening it makes only it active (type link in the menu too), a node link inside a group expands the group, hidden for a user with `own` on another author's node; run only `npm run test:e2e -- e2e/admin-favorites-menu.spec.js` (this task touches the sidebar and the shell, so also run `npm run test:e2e -- e2e/admin-shell.spec.js` if it exists; the full suite is required only if the sidebar behavior outside the menu block changed). Check `docs/e2e-testing.md` tables remain correct.
- [x] 5.2 Run `docker compose exec app vendor/bin/pint --dirty --format agent` and the PHP tests of the changed area (`--filter=AdminMenu`, `--filter=SidebarNavigation`); verify they pass and name the skipped tests and the reason in the final report.

# Tasks

## 1. Favourites: schema and service

- [x] 1.1 Migration `user_favorite_types` (FK `user_id` and `type_key` with `cascadeOnDelete`, a unique pair), the model and the relation `User::favoriteTypes`; check: `docker compose exec app php artisan migrate` passes, the rollback (`migrate:rollback`) removes the table.
- [x] 1.2 Controller and routes `POST`/`DELETE /admin/favorites/{typeKey}` with a check of the level per type (403 for `none`), JSDoc/PHPDoc in English with `@example`; check: `docker compose exec app php artisan test --compact --filter=Favorite` (adding, removing, 403, another user's list does not change, cascade on deletion of a type).
- [x] 1.3 Documentation of the favourites: create `docs/admin-favorites.md` (the schema, the routes, the filtering by access), add to `docs/README.md` and a cross-reference from `docs/access-control.md`; check: the references in the index work.

## 2. Admin menu: schema, service, page

- [x] 2.1 Migration `admin_menu_entries` (self-FK `parent_id`, nullable FK `type_key`, `title` JSON, `position`, a unique `type_key`), the model; check: `migrate` and `migrate:rollback` pass; for the schema migrations — the full `docker compose exec app php artisan test --compact` (it changes the DB schema).
- [x] 2.2 The service of writing the menu (creating/renaming/deleting a group, adding/deleting a link, saving the new structure with the order and the parent in one request) with the validation of depth 2, the uniqueness of the type and of a non-empty name of the group; check: `docker compose exec app php artisan test --compact --filter=AdminMenu`.
- [x] 2.3 Controller, form requests and routes `/admin/settings/navigation` under `can:settings`; check: a Feature test of 403 without the right, successful actions with the right.
- [x] 2.4 The page `Pages/Admin/Settings/Navigation.tsx` (groups, links to types, ordering and moving between the root and a group by drag-and-drop with the mouse through `@dnd-kit`, editing the name of a group); check: `npx tsc --noEmit` and `npm run build` on the host without errors; the drag-and-drop is covered by the e2e task 5.1.
- [x] 2.5 Documentation of the admin menu: create `docs/admin-navigation-menu.md` and a row in `docs/README.md`; check: the references work.

## 3. Shared prop `navigation` and filtering by access

- [x] 3.1 The service of selecting the visible items of the favourites and the menu by `AccessResolver::nodeLevel` (groups without leaves are dropped), the lazy prop `navigation` in `HandleInertiaRequests` and the type in `resources/js/types/index.d.ts`; check: `docker compose exec app php artisan test --compact --filter=SidebarNavigation` (revoking access hides the item, restoring shows it, a deleted type disappears, an empty group is hidden).
- [x] 3.2 Update `docs/access-control.md` with a section about the filtering of the favourites and the menu by levels; check: the section refers to `admin-favorites.md` and `admin-navigation-menu.md`.

## 4. Sidebar and the star

- [x] 4.1 `navigation.ts`: the groups Settings («Структура», «Доступ») with the existing `visible`, the visibility of the groups, the search of the leaves in `getBreadcrumbs`/`isActive`; JSDoc in English with `@example`; check: `npx tsc --noEmit` without errors.
- [x] 4.2 `AppSidebar.tsx`: the blocks Dashboard, «Обране», the admin menu (collapsible groups, icons by the kind), Pages (a mock-up), Media Library, a collapsible Settings that expands when a subitem is active; check: `npm run build` on the host, a manual review in the browser for users with different roles.
- [x] 4.3 The star «Обране» in the heading of `Pages/Admin/ContentNodes/Index.tsx` (the prop `isFavorite`, `preserveState`/`preserveScroll`); check: `npm run build`; a Feature test passes `isFavorite`.
- [x] 4.4 Translations of the new keys in `lang/en.json` and `lang/uk.json`; check: `docker compose exec app php artisan test --compact --filter=Locale` (or the test of translations) passes.
- [x] 4.5 Update `docs/admin-shell.md` (the section Sidebar, «Як додати пункт меню» — "How to add a menu item", the structure of Settings); check: the description matches the behaviour of `navigation.ts`.

## 5. E2E

- [x] 5.1 A new spec `e2e/admin-favorites-menu.spec.js` (the star adds/removes a type in «Обране», a group of the menu with a type, drag-and-drop by the mouse changes the order and it is saved after reloading, the disappearance of the item after a change of role, the expanded Settings with subitems by rights) and the rows in the tables «Область → спек» and «Спільний код → спеки» in `docs/e2e-testing.md`; check: `npm run test:e2e -- e2e/admin-favorites-menu.spec.js` on the host.
- [x] 5.2 Adapt the existing specs that click the items of the sidebar (the shell/sidebar changes): the full `npm run test:e2e` on the host, because the shell/sidebar changes; if needed reset `locale` to `en` for the e2e user.

## 6. Refinement of the sidebar (after the review)

- [x] 6.1 `navigation.ts`: the sections «Структура»/«Доступ» in the root instead of Settings, remove the item Pages, update `visibleSettings`/`isGroupActive` and the JSDoc; check: `npx tsc --noEmit`.
- [x] 6.2 `AppSidebar.tsx`: the sections in the root (`SidebarGroup` with a heading), the block of the admin menu with the heading «Navigation» and an icon (as in «Обране»), `SidebarFooter` with the link to `/admin/settings/navigation` by `can.system.settings`, compact indents between the groups; check: `npm run build`, a review in the browser for roles with different rights.
- [x] 6.3 The star «Обране» in the row of the list of types: `favoriteKeys` in the controllers of the lists of types and `Pages/Admin/ContentTypes/Index.tsx` (also in the mode without the right `types`); check: `docker compose exec app php artisan test --compact --filter=Favorite` and the Feature tests of the lists of types.
- [x] 6.4 Translations: the key `Navigation` (uk: «Навігація»), the rest of the existing ones in `lang/en.json` and `lang/uk.json`; remove the unused keys of Pages/Settings, if there are any; check: `--filter=Locale`.
- [x] 6.5 Update `docs/admin-shell.md`, `docs/admin-favorites.md`, `docs/admin-navigation-menu.md` for the new structure; check: the description matches `navigation.ts`.
- [x] 6.6 E2E: update `admin-favorites-menu.spec.js` (the heading «Navigation», the star from the list of types, the footer link, the absence of Pages and Settings) and the specs that click «Settings»/«Pages»; run the specs of the variable area, and on a change of the shell/sidebar — the full `npm run test:e2e`.

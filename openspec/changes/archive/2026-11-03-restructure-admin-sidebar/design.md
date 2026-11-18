# Design

## Context

The sidebar is a flat array `navigation` in `Components/Admin/navigation.ts` (`visible(can)` per item), which is drawn by `AppSidebar.tsx`; the same structure feeds the breadcrumbs (`getBreadcrumbs`). The user's rights are already shared by `HandleInertiaRequests` (`auth.user.can`, in particular `can.nodes[typeKey]`), and `AccessResolver::nodeLevel` computes the level per type on the server. The records of a type are opened at `/admin/{content|page|block}-types/{key}/nodes`. A type is identified by the immutable `content_types.key` (unique, referenced by other tables). `ContentType::deleting` already cleans `role_permissions`. Documentation: [admin-shell.md](../../../docs/admin-shell.md), [access-control.md](../../../docs/access-control.md), [admin-site-settings.md](../../../docs/admin-site-settings.md). The motivation is in proposal.md.

## Goals / Non-Goals

**Goals:**
- The sections «Структура» ("Structure") and «Доступ» ("Access") in the root of the sidebar, which keep the existing visibility conditions and the breadcrumbs; compact indents between the groups.
- The favourites (per user) and the admin menu (shared) with filtering by the access rights during reading.
- A single source of truth about access — the server; the client receives the already filtered data.

**Non-Goals:**
- Groups within groups, arbitrary links (only types), choosing icons by the admin.
- Drag-and-drop of the favourites (the order is by the time of adding).
- A change of the rights or of the model of roles.

## Decisions

1. **The sections «Структура»/«Доступ» — a static structure on the client.** `navigation.ts` receives the groups `NavGroup[]` («Структура», «Доступ»), which `AppSidebar` draws in the root as `SidebarGroup` with `SidebarGroupLabel`, without a collapsible Settings; `visible` stays on the leaves, the section is visible when at least one leaf is visible. The Pages item (a mock-up) is removed from `navigation`. The breadcrumbs and `isActive` look up the leaves, so `getBreadcrumbs` stays a pure function. *Alternatives:* a collapsible Settings (rejected: an extra click, the user asks for the items in the root); a hub page `/admin/settings` (rejected: adds a step and breaks `/admin/settings` as «Site»).

2. **Favourites — the table `user_favorite_types`.** Columns: `id`, `user_id` (FK `cascadeOnDelete`), `type_key` (FK to `content_types.key`, `cascadeOnDelete`), `created_at`; a unique pair (`user_id`, `type_key`). The order — `created_at`/`id`. Deleting a type or a user removes the rows by cascade. *Alternatives:* the keys `user.{id}.*` in `app_settings` (does not match the format `модуль.назва` — "module.name", foreign data in the common storage) and a JSON column in `users` (no FK, manual cleanups needed) — rejected.

3. **The admin menu — the table `admin_menu_entries`.** Columns: `id`, `parent_id` (self-FK, `cascadeOnDelete`, null for the root), `type_key` (nullable FK to `content_types.key`, `cascadeOnDelete`), `title` (JSON `{"default": "..."}` for groups, null for links), `position`. A row with `type_key` is a link, without it — a group; `type_key` is unique in the table. Depth 2 is ensured by the validation on the server: the parent can only be a group with `parent_id = null`, and a group has no parent. A table, not a key in `app_settings`, because FKs to the types are needed (automatic removal on deletion) and the uniqueness of the type. The translatable title is stored in the same format as `site.name`; the base language (`default`) is edited, reading — the current locale with a fallback to `default`.

4. **Filtering during reading, not cleaning.** The service (`SidebarNavigation`) takes the user, takes the rows of the favourites and the menu, and keeps those types where `AccessResolver::nodeLevel` is not `none`; groups without leaves are dropped. An access that disappeared and came back returns the item without loss of data. The result is shared through `HandleInertiaRequests` as a lazy prop `navigation: { favorites, menu }`, so it is updated with every Inertia response. Deleting a type removes the rows by cascade (FK), no separate events are needed. Adding to the favourites checks the access (403 for `none`).

5. **Icons by the kind of type.** The prop carries the `kind` of the type, the client maps `content → Table2`, `page → FileStack`, `block → Blocks`; a group — `Folder`/`FolderOpen`. There is no `icon` column in the DB.

6. **The star.** The heading of the page of records receives the prop `isFavorite`; the button makes `POST`/`DELETE /admin/favorites/{typeKey}` through Inertia with `preserveState`/`preserveScroll`, so that the state of the table stays. The routes of the favourites are open to any active user (but with a check of the access to the type).

7. **The page of the menu.** `GET /admin/settings/navigation` and the mutations under `can:settings`. Actions: add/rename/delete a group, add/delete a link, order and move a type between the root and a group — all by drag-and-drop with the mouse. We use the existing `@dnd-kit` (`core` + `sortable`, see `Components/Admin/Settings/SortableList.tsx`) with drag handles, as in the taxonomy and the menus of the site; keyboard ordering through the handle remains. The new structure (the ids in order, `parent_id`) is saved in one request after the release. The client also keeps depth 2 (a group does not accept a group), and so does the server.

8. **The heading of the block of the menu.** The block of the admin menu has a `SidebarGroupLabel` «Navigation» (uk: «Навігація») with an icon, in the same style as «Обране» ("Favourites"); without visible items the block and the heading are not displayed. This differs from the text of the settings page «Admin menu» (uk: «Меню адміна»), so that the heading of the block does not match the name of the button.
9. **The footer of the sidebar.** `SidebarFooter` with an icon link (`Settings`) «Admin menu» to `/admin/settings/navigation` (`data-testid="sidebar-admin-menu-link"`), visible by `can.system.settings`; in the collapsed sidebar an icon with a tooltip remains.
10. **The star in the list of types.** The controllers of the lists of types (content/page/block) pass `favoriteKeys` (the keys of the favourites of the current user). The row of `Pages/Admin/ContentTypes/Index.tsx` has a star next to the actions (also in the mode without the right `types`); the call `POST`/`DELETE /admin/favorites/{typeKey}` with `preserveState`/`preserveScroll`. The star in the heading of the page of records remains.
11. **Indents.** Between the groups of the sidebar reduce the vertical indents (compact `SidebarGroup`/`SidebarGroupLabel`), without changing the logic.
12. **Routes and synchronization.** The new routes are added to the existing groups in `routes/web.php`; the names: `admin.favorites.store/destroy`, `admin.settings.navigation.*`.

## Risks / Trade-offs

- [Requests on every page] → two small requests (the favourites of the user, the tree of the menu) + the levels from the already cached `AccessResolver`; the lazy prop is not executed for non-Inertia responses. We do not introduce a cache between requests.
- [The requirement `settings` for the menu, but not for the type] → the admin settings only arrange the links; the access to the list itself is still determined by the level per type.
- [Expanded groups of the admin menu] → a group with the active item is expanded; manual collapsing is not remembered between visits.
- [Entrance to the settings of the menu only with the right `settings`] → the link in the footer is shown by `can.system.settings`; the server still gives 403.
- [Names in `uk`] → the new translation keys (`Favorites`, `Structure`, `Access`, `Site`, `Navigation` etc.) are added to both `lang/*.json`.

## Migration Plan

Two new migrations without touching the data; the rollback — `dropIfExists`. There is no initial filling: an empty favourites list and an empty admin menu mean only new blocks that are not displayed. The old addresses of the sections do not change.

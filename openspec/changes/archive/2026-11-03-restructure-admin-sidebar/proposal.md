# Proposal

## Why

The sidebar of the admin is a flat static list: the items of managing the structure (types, taxonomy, menus), access (users, roles) and the site are mixed with the working ones, and the records of a type are opened only through the table of types → «Записи» ("Records"). An editor needs a quick entrance to the types he works with every day, and the administrator needs a way to assemble for everybody a menu from the needed types and groups and to remove the service items from the main level.

## What Changes

- The management items (Content Types, Page Types, Block Types, Taxonomy, Menus, Users, Roles, Site) move from a collapsible/flat list into the root of the sidebar in two labelled sections: **«Структура»** («Structure») (the types ×3, Taxonomy, Menus) and **«Доступ»** («Access») (Users, Roles, Site). Each item keeps its own visibility condition; a section without visible items is hidden. There is no group Settings. The page `/admin/settings` remains and becomes the item **Site** of the section «Доступ».
- A new personal menu **«Обране»** («Favourites»): a star in the heading of the page of records of a type and in the row of the list of types adds/removes the type; the list belongs to the user (a separate table) and is shown in the sidebar above the admin menu.
- A new **admin menu for everyone**: the page `/admin/settings/navigation` (the right `settings`) to configure a tree of two levels — a root link to a type or a group (a translatable name) with links to types. The menu is shown in the sidebar to all users in a separate block with the heading **«Navigation»** (like «Обране»). The entrance to the settings is an icon-link at the bottom of the sidebar (the footer) for the users with the right `settings`.
- The items «Обране» and the admin menu lead to the list of records of a type. The icon is defined by the kind of the type (`content` — `Table2`, `page` — `FileStack`, `block` — `Blocks`), the groups — `Folder`/`FolderOpen`.
- **Access**: a type is shown in «Обране» and in the admin menu only if the user's level on the type is not `none`; the saved unavailable or deleted types are filtered out during reading (the data is not erased; when the access returns the item appears again). A group without visible types is hidden.
- The item Pages (a mock-up without functionality) is **removed** from the sidebar. Dashboard and Media Library remain at the top level. The indents between the groups of the sidebar are reduced.

## Capabilities

### New Capabilities
- `admin-favorites`: the personal menu «Обране» — the table of favourite types of the user, the star on the page of records, the display in the sidebar, the filtering by access.
- `admin-navigation-menu`: the admin menu for everyone — a tree of groups and links to types (two levels), the settings page, translatable names of the groups, the display in the sidebar with the account of the access.

### Modified Capabilities
- `admin-shell`: the requirement «Бічна навігація» ("Sidebar navigation") — the new structure (Dashboard, Обране, Navigation, Media Library, the sections «Структура» and «Доступ» in the root, the footer with the link to the settings of the menu), without Pages and the collapsible Settings; compact indents between the groups.

## Impact

- Backend: the migrations `user_favorite_types` and `admin_menu_entries`; the models and the service of selecting the visible items (by `AccessResolver`); the controllers of the favourites and of the settings of the menu; the routes under `can:settings` for the settings; the shared prop `navigation` in `HandleInertiaRequests`.
- Frontend: `AppSidebar.tsx`, `navigation.ts` (the sections «Структура»/«Доступ», without Pages, the breadcrumbs), `SidebarFooter`, the star in `Pages/Admin/ContentNodes/Index.tsx` and in the row of `Pages/Admin/ContentTypes/Index.tsx` (the controllers of the lists of types pass `favoriteKeys`), the new page `Pages/Admin/Settings/Navigation.tsx`, the types in `resources/js/types/index.d.ts`, the translations in `lang/en.json` and `lang/uk.json`.
- Tests: PHP tests of the new areas (the favourites, the menu, the filtering by access), e2e — `admin-shell` (a full run because of the change of the sidebar), a new spec for the favourites and the menu.
- Documentation (`docs/`): update `admin-shell.md` (the section Sidebar), `access-control.md` (the filtering by levels), `e2e-testing.md` (the tables), `README.md` (the index); create `admin-favorites.md` and `admin-navigation-menu.md`.

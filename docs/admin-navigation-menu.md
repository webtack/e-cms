# Admin menu

The menu that the administrator configures and that all users see in the sidebar under the [Favorites](admin-favorites.md) block, as a separate block with the heading "Navigation": a two-level tree — links to types and links to nodes at the root, and groups (with a translatable name) with links to types and nodes.

## Schema

The table `admin_menu_entries` (model `Webtack\Ecms\Models\AdminMenuEntry`):

| Column | Description |
| --- | --- |
| `id` | The identifier in routes (`{entry}`) |
| `parent_id` | Self-FK (`cascadeOnDelete`); `null` for the root |
| `type_key` | FK to `content_types.key` (`cascadeOnDelete`), unique; `null` for a group and for a node link |
| `node_uuid` | FK to `content_nodes.uuid` (`cascadeOnDelete`), unique; `null` for a group and for a type link |
| `title` | JSON `{"default": "...", "uk": "..."}` for a group; `null` for a link |
| `position` | The order at the root or within a group |

A row with `type_key` is a link to a type, a row with `node_uuid` is a link to a node, a row with neither is a group (`isTypeLink()`, `isNodeLink()`, `isGroup()` on the model). The label of a node link is never stored: it is the node title read at display time (current locale, then `default`, then the node `slug`), so renaming the node renames the item. The name of a group is read in the current locale with a fallback to `default`; the page edits only `default` (other languages are not erased when renaming). Deleting a type removes its link by cascade, deleting a node removes its link, deleting a group — its links too.

The rules are kept by `Webtack\Ecms\Navigation\AdminMenu` (errors are `ValidationException`, so they appear next to the field): a group only at the root, a link — at the root or in a group, a type no more than once, a node no more than once (a type and one of its nodes may both be in the menu), the group name not empty (up to 100 characters), the order is saved in one request in which each entry occurs exactly once.

## Settings page

`/admin/settings/navigation` (`NavigationMenuController`, all routes under `can:settings`). The entry point is the icon link "Admin menu" in the sidebar footer (only with the `settings` right). The page is a subpage of **Site** ([site settings](admin-site-settings.md)): in the breadcrumbs — "Admin → Site → Admin menu", and the footer link is highlighted, not the Site item.

| Method and address | Name | Action |
| --- | --- | --- |
| `GET /admin/settings/navigation` | `admin.settings.navigation.index` | The tree (without filtering by access; node rows carry `node_uuid`, `node_title`, and `name`/`kind` of the node type, titles read in one query), the types that are not yet in the menu, and the [Create menu](#create-menu) props `createTypes` and `createAvailableTypes` |
| `POST …/groups` | `…groups.store` | A new group (`title`) |
| `PUT …/groups/{entry}` | `…groups.update` | Renaming (`title`) |
| `POST …/links` | `…links.store` | A link to a type (`type_key`) or to a node (`node_uuid`) — exactly one of them; `parent_id` or the root. An unknown or repeated type or node is a validation error on `type_key` / `node_uuid` |
| `PUT …/order` | `…order` | The new structure: `items[i][id]`, `items[i][children][]` |
| `DELETE …/entries/{entry}` | `…entries.destroy` | Deleting a link or a group with links |
| `PUT …/create-types` | `settings.navigation.create-types` | The whole ordered list of the dashboard "Create" menu: `types[]` (existing, unique `content_types.key`); an empty array clears the setting |

Dragging — `@dnd-kit` (a handle on each row; keyboard control is also available): a type or node link is moved within the list and between the root and a group; a group — only at the root. The new structure is saved by one `PUT …/order` after release.

## Create menu

The section "Create menu" at the bottom of the settings page (`Components/Admin/Settings/CreateMenuSettings.tsx`) chooses the types of the dashboard "Create" menu and their order, see [Configured Create menu](admin-dashboard.md#configured-create-menu). It is independent of the admin menu tree: a type may be in both, in either or in none.

- Storage: the [app setting](app-settings.md) `dashboard.create_types`, an ordered list of type keys. No table and no migration.
- Props: `createTypes` (the saved keys in order, resolved to `key`, `name`, `kind`; keys of deleted types are dropped) and `createAvailableTypes` (the types not in the list).
- Editing: a select with an "Add" button appends a type; each row has a handle (`@dnd-kit`, mouse and keyboard, its own `DndContext`) and a remove button. Every change sends the full list in one `PUT …/create-types`. Saving an empty list calls `AppSettings::forget()`, so the dashboard menu returns to its default grouped behavior.
- Validation: `types` is required (may be empty); each item must exist in `content_types.key` and occur once; otherwise a validation error and the list does not change.
- `data-testid`: `create-menu`, `create-menu-type`, `create-menu-add`, `create-menu-list`, `create-menu-item` (with `data-type-key`), `create-menu-handle`, `create-menu-remove`.

## Display in the sidebar

`SidebarNavigation::forUser()` provides `navigation.menu` — a list of `{ type: 'link', key, name, kind, href }` for a type, `{ type: 'link', node: true, key, name, kind, href }` for a node (`key` is the node `uuid`, `name` its title, `kind` the kind of its type, `href` is `/admin/{section}/{type}/nodes/{uuid}/edit`) and `{ type: 'group', id, title, children }`. The filter by level per type and hiding of empty groups — as in [Favorites](admin-favorites.md#filtering-by-access); a node link is kept only when the user may edit the node (`AccessResolver::canEditNode()`, see [access control](access-control.md#favorites-and-admin-menu)); the saved structure is not changed by this. The nodes and their titles of all node links are read in two queries (`NodeTitles`), so the cost does not grow with the number of links. A group is collapsible (`Folder`/`FolderOpen`), expanded when it contains the active item.

## Tests

`tests/Feature/Navigation/AdminMenuTest.php`, `SidebarNavigationTest.php`, `CreateMenuSettingsTest.php` (the Create menu list); `tests/Feature/Access/RouteCoverageTest.php` covers the 403 without the right; e2e — `e2e/admin-favorites-menu.spec.js`.

# Proposal

## Why

The admin menu (`/admin/settings/navigation`) can only link to types, so an administrator cannot put a frequently edited node (for example the home page or a settings-like page node) into the sidebar. They have to open the type list and look for the node every time.

## What Changes

- A menu entry can now be a link to a node, in addition to a link to a type and a group. A node link opens the edit form of the node.
- Node links live at the root or in a group, like type links; a node can be in the menu at most once.
- The label of a node link is the node title, read at display time, so renaming the node renames the menu item.
- A node link is shown to a user only when they may edit that node (the `update` rule: `any` on the type, or `own` and the user is the author). Hidden items are not removed from the saved structure.
- Deleting a node removes its link from the menu (database cascade).
- The settings page gets a way to add a node: choose a type, then search a node by title (reusing the existing node lookup).
- The sidebar highlights exactly one item for the node edit page: the node link wins over the type link and over the section item.
- Non-goal: nodes in "Favorites"; nesting deeper than two levels; link labels other than the node title.

## Capabilities

### New Capabilities

<!-- None. -->

### Modified Capabilities

- `admin-navigation-menu`: the structure gains a third kind of entry (link to a node); the sidebar display, the active-item rule and the access rule cover node links.

## Impact

- Database: new migration adds nullable unique `node_uuid` (FK to `content_nodes.uuid`, cascade on delete) to `admin_menu_entries`.
- Backend: `App\Models\AdminMenuEntry` (kind detection), `App\Navigation\AdminMenu` (add node link, validation), `App\Navigation\SidebarNavigation` (batched title and access lookup), `NavigationMenuController` and its routes (`links` store accepts a node), `routes/web.php`.
- Frontend: `Pages/Admin/Settings/Navigation.tsx` (add-node picker, node rows), `Components/Admin/AppSidebar.tsx` (node link item, active rule), `resources/js/types`, `lang/en.json`, `lang/uk.json`.
- Tests: `tests/Feature/Navigation/AdminMenuTest.php`, `SidebarNavigationTest.php`, `e2e/admin-favorites-menu.spec.js`.
- Docs: `docs/admin-navigation-menu.md`, `docs/access-control.md` (section "Favorites and admin menu"), `docs/admin-shell.md` (sidebar, active item); `docs/e2e-testing.md` only if spec tables change (they should not).

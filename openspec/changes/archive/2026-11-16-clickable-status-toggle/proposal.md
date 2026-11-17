## Why

Publishing or unpublishing a node or a menu item currently requires opening the edit form, flipping the visibility switch and saving. The status badge in the lists already shows the state, so it is the natural one-click control for the most frequent editorial action.

## What Changes

- The status badge in the node list (`ContentNodes/Index`) and in the menu items list (`Menus/Items/Index`) becomes a clickable control. A click toggles only `is_active`; `published_at` and `unpublished_at` are never touched.
  - `published`, `scheduled`, `expired` -> `is_active = false` (shown as `disabled`).
  - `disabled` -> `is_active = true` (the badge then shows the real state: `published`, `scheduled` or `expired`, depending on the dates).
- Two new lightweight endpoints that change only `is_active` and answer with `back()` and a flash message: `PATCH /{section}/{contentType}/nodes/{node}/active` and `PATCH /menus/{menu}/items/{item}/active`.
- No confirmation dialog: the action is reversible and meant to be one click.
- Authorization equals editing: the node `update` policy for nodes, the `menus` permission for menu items. Rows the user cannot edit keep a plain, non-clickable badge.
- One shared clickable badge component replaces the two display-only badges in these lists. The node form (`NodeServicePanel`) and the menu item form are unchanged.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: the state badge in the node list is clickable and toggles `is_active` through a dedicated endpoint.
- `admin-menus`: the status badge in the items list is clickable and toggles `is_active` through a dedicated endpoint.

## Impact

- Backend: `routes/web.php`, `ContentNodeController` (new action), `MenuItemController` (new action), one small form request per action (or inline validation of boolean `is_active`).
- Frontend: a shared clickable status badge component, `Pages/Admin/ContentNodes/Index.tsx`, `Pages/Admin/Menus/Items/Index.tsx`, `NodeStatusBadge` / `MenuItemStatusBadge` (kept for display-only use).
- Translations: `lang/en.json`, `lang/uk.json` (flash messages, accessible labels).
- Docs: `docs/admin-content-nodes.md`, `docs/admin-menus.md` (behavior and endpoints), `docs/e2e-testing.md` (only if a new spec is added; otherwise no change).
- Tests: PHP Feature tests for both endpoints; e2e in `admin-content-nodes` (`-g` narrowed) and `admin-menus`.

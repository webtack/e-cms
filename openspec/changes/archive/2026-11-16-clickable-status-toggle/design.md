## Context

`ContentNode::status()` and `MenuItem::status()` derive the state from `is_active` and two dates. The list pages render it with display-only badges (`NodeStatusBadge` in `NodeServicePanel.tsx`, `MenuItemStatusBadge`). The existing update actions need the whole form (slug and values for nodes; translations and `StoreMenuItemRequest::sharedRules` for items), so they cannot be reused for a single flag. Node rows already carry `can_edit` (`Gate::allows('update', $node)`); the menu items page is behind `can:menus`. Node routes are generated in one loop for the `content`, `page` and `block` sections with a `kind` default.

## Goals / Non-Goals

**Goals:**
- One click flips `is_active` from the list for nodes and menu items.
- Dates are never modified, so the action is predictable and reversible.

**Non-Goals:**
- No "publish now" / "renew" behavior that changes `published_at` or `unpublished_at`.
- No confirmation dialog, no bulk action, no change to the edit forms or to the badge in `NodeServicePanel`.

## Decisions

- **Set, not toggle, on the wire.** The client sends the target value (`is_active: true|false`) to `PATCH .../active`. It is idempotent: a double click or a stale list cannot flip the state the wrong way. The client computes the target from the row: `status === 'disabled'` -> true, otherwise false.
- **Dedicated endpoints** `PATCH .../nodes/{node}/active` (added to the shared `$nodeRoutes` loop, name `{section}.nodes.active`) and `PATCH /menus/{menu}/items/{item}/active` (name `menus.items.active`, inside the `can:menus` group). They only validate a boolean and answer with `back()` and a flash. Rejected alternative: reuse `update` with a partial payload, because it would need relaxing required fields and the schedule/slug checks.
- **Authorization:** the node action uses `Gate::authorize('update', $node)` (same as edit); the item action relies on the `menus` middleware and the existing route binding within the menu.
- **Persistence:** the node is saved with `forceFill`/`update` of `is_active` only and then `touch()`, so `updated_at` moves like in `update`. The item is saved through the model (`$item->update(['is_active' => ...])`) so model-level rules still apply; a `ValidationException` is turned into an `error` flash as in `destroy`.
- **Shared component** `StatusToggleBadge` (in `Components/Admin/`): renders the existing badge inside a `<button type="button">` with `data-status`, calls `router.patch(url, { is_active }, { preserveScroll: true, preserveState: true })`, disables itself while the request is in flight, and has an `aria-label` such as "Disable" / "Enable". When no handler is given it renders the plain badge, so rows the user cannot edit stay display-only. `NodeStatusBadge` and `MenuItemStatusBadge` keep their markup and are reused by the new component.
- **Messages:** flash strings "Node enabled." / "Node disabled." and "Menu item enabled." / "Menu item disabled.", added to `lang/en.json` and `lang/uk.json`.

## Risks / Trade-offs

- Clicking "Scheduled" or "Expired" disables the item instead of "publishing" it -> accepted by the owner; the edit form remains the place to fix dates.
- The badge sits in a sortable column of `DataTable`; the button must not trigger row or header handlers -> stop propagation inside the component and cover it in e2e.
- The list is server-paginated for nodes: after the PATCH the page reloads the current props with `preserveScroll`, so sorting by `status` may move the row; acceptable.

## Documentation

`docs/admin-content-nodes.md` (node list, endpoint) and `docs/admin-menus.md` (items list, endpoint) describe the affected components; `docs/e2e-testing.md` needs no change unless a new spec file is added.

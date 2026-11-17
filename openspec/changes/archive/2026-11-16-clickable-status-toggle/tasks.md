## 1. Nodes: endpoint

- [x] 1.1 Add `PATCH /{section}/{contentType}/nodes/{node}/active` to `$nodeRoutes` in `routes/web.php` (name `{section}.nodes.active`) and a `setActive` action in `ContentNodeController` that authorizes `update`, validates boolean `is_active`, saves only that flag, touches the node and returns `back()` with a flash; add the flash strings to `lang/en.json` and `lang/uk.json`. Add English docblocks with `@example`.
- [x] 1.2 Feature tests (allowed, 403 for a user who cannot edit, invalid value, dates unchanged, all three sections) and run `docker compose exec app php artisan test --compact --filter=ContentNode`; run `docker compose exec app vendor/bin/pint --dirty --format agent`.
- [x] 1.3 Update `docs/admin-content-nodes.md` with the endpoint and its rules.

## 2. Menu items: endpoint

- [x] 2.1 Add `PATCH /menus/{menu}/items/{item}/active` (name `menus.items.active`) inside the `can:menus` group and a `setActive` action in `MenuItemController` that validates boolean `is_active`, updates only that flag through the model, turns a `ValidationException` into an error flash, and returns `back()` with a flash; add the strings to `lang/en.json` and `lang/uk.json`. Add English docblocks with `@example`.
- [x] 2.2 Feature tests (allowed, 403 without `menus`, 404 for an item of another menu, invalid value, dates unchanged) and run `docker compose exec app php artisan test --compact --filter=MenuItem`; run Pint.
- [x] 2.3 Update `docs/admin-menus.md` with the endpoint and its rules.

## 3. Frontend

- [x] 3.1 Create the shared `StatusToggleBadge` component (button around the existing badge, `data-status`, `aria-label`, in-flight disabled state, `router.patch` with `preserveScroll`, plain badge when no handler) with JSDoc and `@example`.
- [x] 3.2 Use it in `Pages/Admin/ContentNodes/Index.tsx` (only when `row.can_edit`) and in `Pages/Admin/Menus/Items/Index.tsx`; target value is `status === 'disabled'`. Add the accessible-label strings to `lang/en.json` and `lang/uk.json`. Run `npm run build` on the host.
- [x] 3.3 e2e: add scenarios to `e2e/admin-content-nodes.spec.js` (run with `-g` narrowed to the new tests) and `e2e/admin-menus.spec.js` (`-g` for the status tests): unpublish, enable into scheduled/expired, non-editable row stays plain. Update the docs sections of 1.3 and 2.3 if behavior notes change.

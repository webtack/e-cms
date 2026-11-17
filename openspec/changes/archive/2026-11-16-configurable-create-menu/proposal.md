# Proposal

## Why

The dashboard "Create" menu always lists every type the user may create nodes of, grouped by kind. On a site with many types this is a long, noisy list, and the administrator cannot put the types that editors use daily first or hide the rest. The administrator needs to choose which types appear in the menu and in which order.

## What Changes

- The administrator picks the types of the dashboard "Create" menu and their order on the existing page `/admin/settings/navigation` (new section "Create menu", rights `settings`, as for the rest of the page).
- The selection is stored in `app_settings` under the key `dashboard.create_types` as an ordered list of type keys. No migration is needed.
- When the list is configured, the dashboard "Create" menu shows exactly those types, in the configured order, as a flat list without the Content / Page / Block headings.
- The user's access still applies: a configured type on which the user's level is `none` is not shown. When no configured type remains accessible, the menu is hidden.
- When the key is not set, behavior stays as today: all accessible types, grouped by kind. Saving an empty list removes the key, so the default applies again.
- A configured key of a type that no longer exists is skipped silently when the menu is built.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-dashboard`: the "Quick create" requirement lists the configured types in the configured order when a list is set, and keeps the current grouped behavior otherwise.
- `admin-navigation-menu`: the settings page gains the "Create menu" section for choosing, ordering and removing the types of the dashboard "Create" menu.

## Impact

- Code: `app/Dashboard/DashboardData.php` (`create()`), `app/Http/Controllers/Admin/NavigationMenuController.php` (new actions and props), `routes/web.php` (new routes under `can:settings`), `resources/js/Pages/Admin/Settings/Navigation.tsx`, `resources/js/Components/Admin/Dashboard/QuickCreateMenu.tsx` and `types.ts` (flat mode), `lang/en.json` and `lang/uk.json`.
- Data: a new `app_settings` key `dashboard.create_types`; the prefix `dashboard.` is added to the key table in `docs/app-settings.md`.
- Docs updated: `docs/admin-dashboard.md`, `docs/admin-navigation-menu.md`, `docs/app-settings.md`, `docs/e2e-testing.md` (rows for the dashboard and menu specs).
- Tests: PHP Feature tests in `tests/Feature/Dashboard` and `tests/Feature/Navigation`; e2e specs `admin-dashboard` and `admin-favorites-menu`.

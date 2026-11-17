# Design

## Context

The dashboard "Create" menu is built in `DashboardData::create()` from the types accessible to the user (level `own`/`any`), sorted by kind then name, and rendered grouped by kind by `QuickCreateMenu`. The settings page `/admin/settings/navigation` (`NavigationMenuController`, routes under `can:settings`) edits the sidebar tree stored in `admin_menu_entries`; it already has a type select and `@dnd-kit` sorting. General key-value settings live in `app_settings` (`App\Support\AppSettings`, keys `module.name`). See proposal.md for motivation and the specs for behavior.

Documented components: `docs/admin-dashboard.md`, `docs/admin-navigation-menu.md`, `docs/app-settings.md`.

## Goals / Non-Goals

**Goals:**
- One ordered list of type keys, edited on the existing settings page.
- No schema change; default behavior is unchanged until the list is saved.

**Non-Goals:**
- Per-role or per-user create menus.
- Grouping or headings inside the configured list.
- Linking the list to the admin menu tree (they are independent).

## Decisions

1. **Storage: `app_settings` key `dashboard.create_types` (JSON list of type keys).** The data is a flat ordered list with no per-item attributes, so a table with FK, position and cascade is overkill. Alternative: a new table like `admin_menu_entries` (gives cascade delete of removed types), rejected because stale keys are cheap to skip at read time.
2. **Read path: filter, do not fail.** `DashboardData::create()` reads the key; if it is absent, behavior is unchanged. Otherwise it maps the keys onto the already-computed accessible types (`$types`, keyed by type key), keeping the stored order and dropping keys that are not accessible or no longer exist. An empty result hides the menu (the existing "empty list" rule). The same `$types` collection is reused, so no extra query beyond the one settings read.
3. **Payload shape: a `grouped` flag on the `create` prop is avoided; the server decides the order and the client decides rendering by a separate boolean.** `DashboardData` returns `create` as before plus a `create_flat` boolean (true when configured). `QuickCreateMenu` renders a flat list when `create_flat` is true and the grouped list otherwise. This keeps the item shape (`key`, `name`, `kind`, `url`) stable.
4. **Write path: one endpoint saves the whole list.** `PUT /admin/settings/navigation/create-types` with `types[]` (ordered keys). Validation: array, each item a string that exists in `content_types.key`, no duplicates. An empty array calls `AppSettings::forget()`; a non-empty one calls `set()`. This keeps add, remove and reorder as one request, like the existing `…/order`.
5. **UI: a "Create menu" section on `Navigation.tsx`.** Props `createTypes` (the saved list with `key`, `name`, `kind`, in order) and the available types for the select (all types not yet in the list). Add and remove send the new full list; drag end sends the reordered list. The list reuses the sortable pattern of the tree (handle, keyboard sensor) but is a separate `DndContext` so it does not interact with the menu tree.
6. **Stale keys on the settings page.** When the page builds `createTypes`, keys of deleted types are dropped, so the next save clears them from storage.

## Risks / Trade-offs

- [Stale key of a deleted type stays in storage until the next save] → skipped at read time; harmless.
- [Configured list excludes a type editors need] → the administrator controls it; clearing the list restores the default.
- [All configured types inaccessible hides the menu even though other types are creatable] → intentional (agreed in exploration); documented in the spec.
- [Two sortable lists on one page] → separate `DndContext` instances and distinct `data-testid`s.

## Migration Plan

No migration. Existing installations keep the default behavior until the administrator saves a list. Rollback: delete the key (or clear the list in the UI).

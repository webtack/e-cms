# Design

## Context

`ContentTypeNav.tsx` (the section tabs) and `ContentTypeRowActions.tsx` (the row "⋮" menu) both live in `resources/js/Components/Admin/ContentTypes/` and each keeps its own list of links to `{section.basePath}/{key}/…`. The tabs show `settings`, `display`, `seo` (not for `block`), `fields`, `nodes`; the menu shows `Edit`, `Fields`, `Nodes`, `Delete`. The tabs hide everything except `nodes` for users without the `types` permission; the row menu is rendered only when `canManage` is true (`Pages/Admin/ContentTypes/Index.tsx`), otherwise the row has only the "Nodes" button. The routes for `display` and `seo` already exist behind `can:types`, and `seo` is registered only for `content` and `page`. See proposal.md - Why.

Documentation of the components: `docs/admin-content-types.md` (row actions, section menu), `docs/admin-page-types.md` (shared `section` prop), `docs/e2e-testing.md` (e2e tables).

## Goals / Non-Goals

**Goals:**
- One definition of the destinations of an existing type, used by both the tabs and the row menu.
- The row menu gets "Display" and "SEO" with the kind rule for `block` kept in one place.

**Non-Goals:**
- No change to routes, controllers, permissions, or the visual look of the tabs and the menu.
- The "Delete" item and its dialog stay in `ContentTypeRowActions`; "Delete" is an action, not a destination, and has no tab.

## Decisions

**1. A new module `Components/Admin/ContentTypes/typeSectionItems.ts` exports the list.**
A function `typeSectionItems(kind)` returns an ordered array of `{ id, label, menuLabel?, path, icon }`, where `id` is `'settings' | 'display' | 'seo' | 'fields' | 'nodes'`, `label` is the translation key of the tab, `menuLabel` is the translation key of the menu item when it differs (only `settings`: the tab is "Settings", the menu item is "Edit", because the main row button is already "Settings"), `path` is the suffix (`edit`, `display`, `seo`, `fields`, `nodes`) and `icon` is a lucide component. The function drops `seo` for `kind === 'block'`. The module keeps no knowledge of permissions or hrefs: callers build `${section.basePath}/${key}/${path}`.
- Alternative: a constant array plus a `kinds` field on each item. Rejected: only one rule exists, and a function states it more simply.
- Alternative: make the tabs render `ContentTypeRowActions`-style items through a shared React component. Rejected: the tabs and the menu have different markup (links with underline vs `DropdownMenuItem`), so only the data is shared.

**2. The `types` permission filter stays in `ContentTypeNav`.** The row menu is already gated by `canManage`, so it needs no extra filter. Moving the filter into the shared module would force it to read page props and mix data with auth.

**3. Menu order is the tab order, then a separator, then "Delete".** This is the order of the specs (Edit, Display, SEO, Fields, Nodes, Delete). The item `settings` maps to the menu label "Edit" and keeps the `Pencil` icon, `fields` keeps `ListTree`, `nodes` keeps `FileText`. The icons of `display` and `seo` are chosen at implementation from lucide (distinct from the others); icons are not covered by the specs.

**4. No new translation keys.** "Display" and "SEO" exist in `lang/en.json` and `lang/uk.json`; "Edit", "Fields", "Nodes" already exist.

## Risks / Trade-offs

- [The e2e specs assert the exact menu text] → update the three assertions (`admin-content-types` in Ukrainian, `admin-page-types`, `admin-block-types`) and add a navigation check for the new items; there is no SEO item expectation for `block`.
- [A future tab added without a menu item] → impossible by design for destinations listed in `typeSectionItems`; the item is added once and both components render it.
- [The "Edit" and "Settings" duplication stays] → accepted; the main button is "Settings", the menu item is "Edit", as in the current specs.

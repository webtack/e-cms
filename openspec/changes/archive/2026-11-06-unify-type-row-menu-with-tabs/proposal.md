# Proposal

## Why

The "⋮" menu in a row of the types table (content, page and block sections) lacks two destinations that the type's own section tabs have: "Display" and "SEO". The tabs (`ContentTypeNav`) and the row menu (`ContentTypeRowActions`) each hold their own hand-written list of links, so they drift apart. The main spec of `admin-content-types` has also drifted from the code: it says the page and block sections have no "Nodes" item in the row menu, while the code and the other specs show it in all three sections.

## What Changes

- The row "⋮" menu gets the items "Display" (all sections) and "SEO" (`content` and `page` sections only; a block type has no SEO tab and the route answers 404). The final order is: "Edit", "Display", "SEO", "Fields", "Nodes", a separator, "Delete".
- The destinations of the section tabs and of the row menu come from one shared list of type section items (id, translation key, address suffix, icon, availability per kind). Both `ContentTypeNav` and `ContentTypeRowActions` render from it, so a destination added to one appears in the other.
- The "Edit" menu item keeps its label (the tab of the same destination is called "Settings"; the main row button is already "Settings").
- Fix the wrong statement in the `admin-content-types` requirement "Row action menu" about the absence of "Nodes" in the page and block sections.
- No change to routes, permissions, controllers or the tab bar behavior (including the rule that without the `types` permission only the "Nodes" destination is reachable).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-types`: requirement "Row action menu" — the content types menu lists "Edit", "Display", "SEO", "Fields", "Nodes", "Delete"; "Nodes" is present in all sections; the menu and the section tabs share their destinations.
- `admin-page-types`: requirement "Row action menu of a page type" — the menu lists "Edit", "Display", "SEO", "Fields", "Nodes", "Delete".
- `admin-block-types`: requirement "Row action menu of a block type" — the menu lists "Edit", "Display", "Fields", "Nodes", "Delete" (no "SEO").

## Impact

- Code: `resources/js/Components/Admin/ContentTypes/ContentTypeNav.tsx`, `ContentTypeRowActions.tsx`, and a new shared module in the same directory (the shared item list). The page `Pages/Admin/ContentTypes/Index.tsx` is the only consumer of the row menu and needs no change.
- Translations: the keys "Display" and "SEO" already exist (used by the tabs); no new keys are expected.
- e2e: the exact menu assertions in `e2e/admin-content-types.spec.js` (the Ukrainian variant), `e2e/admin-page-types.spec.js` and `e2e/admin-block-types.spec.js` change; a navigation check for the new items is added.
- Docs to update: `docs/admin-content-types.md` (the "Row actions" section and the `ContentTypeNav` description), `docs/admin-page-types.md` (shared component notes), `docs/e2e-testing.md` (only if the "area → spec" or "shared code → specs" rows change).
- PHP: no changes, so no PHP tests are needed.

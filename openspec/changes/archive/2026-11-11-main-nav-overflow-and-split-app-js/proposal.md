# Proposal

## Why

From 1024 px the main navigation of the `dcculture` theme is one non-wrapping row inside the content container. The menu `main` has eight top-level items plus the home icon, which need roughly 1100 px, while the container is 896 px wide at 1024 px and 1040 px at 1280 px. The items run past the container (and the dropdown panels are anchored to the bar, so horizontal scrolling of the list is not an option). The menu is edited in the admin panel, so more or longer items can appear at any time and a fixed shrink of paddings would not hold. The theme script `assets/app.js` (352 lines) already mixes four unrelated features and is about to get a fifth.

## What Changes

- **Overflow behaviour (from 1024 px).** The bar never runs past the container:
    - Without the script the list wraps onto a second row (every link stays reachable).
    - With the script the items that do not fit are hidden from the bar and shown in a trailing "Ще" dropdown item. The "Ще" panel is the usual three-column dropdown with the hidden items; a hidden item that has sub-items is shown as a group: its link as a heading and its sub-links under it. The group is never split between columns.
    - The state follows the width of the bar (window resize, font loading), so the split is recomputed, not fixed.
    - If the active item or the item that holds the active sub-item is hidden, the "Ще" item shows the trail state.
    - Below 1024 px nothing is measured or hidden: the full-screen modal lists every item as before.
- Menu data stays two levels deep; the group inside "Ще" is a presentation of the hidden top-level item and its own sub-items, not a third level of the menu.
- **Script split.** `assets/app.js` becomes an entry that only imports and starts the parts. The parts move to `assets/js/`: shared modal helper, menu modal and accordion, search modal, video modal, and the new overflow logic. Behaviour of existing features does not change.
- No tests are added for the theme frontend (project rule); verification is the theme check, the theme build and a visual check at 1024, 1100, 1280 and 1440 px.
- Out of scope: deeper menu data (more than two levels), changing the order or the content of the menu, a different behaviour of the mobile modal.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `dcculture-theme`: the requirement for the main navigation gets the overflow behaviour from 1024 px (wrap without the script, "Ще" dropdown with the script, groups for items with sub-items, trail state of "Ще").

## Impact

- Code (theme): `themes/dcculture/assets/app.js` (reduced to the entry), new `themes/dcculture/assets/js/*.js`, `themes/dcculture/assets/components/main-nav.css`, `themes/dcculture/views/menus/menu--main.blade.php` (header contract comment, wrap-safe bar height).
- Docs updated: `docs/theme-dcculture.md` (navigation, responsive layout, script contract and file layout).
- Specs: delta of `openspec/specs/dcculture-theme/spec.md`.
- Build: the theme build must keep working with imports from `assets/js/`; checked with `theme:check` and `npm run theme:build`.
- Risk: a brief layout jump when the script runs (two rows collapse into one); handled in the design.

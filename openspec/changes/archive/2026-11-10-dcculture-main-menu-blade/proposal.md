# Proposal

## Why

The main navigation of the `dcculture` theme (`partials/main-nav.blade.php`) is still static design markup, so the menu `main` that editors maintain in the admin panel ([menus.md](../../../docs/menus.md)) has no effect on the public site. The theme inherits only the plain list template `menus/menu` from `default`, which does not produce the teal bar, the dropdowns or the mobile modal accordion. The next step of the theme is to render the real menu tree in the approved design.

## What Changes

- Add the menu template `themes/dcculture/views/menus/menu--main.blade.php` (the candidate `menus/menu--{key}` of [theme-templates.md](../../../docs/theme-templates.md#menus)). It prints the `<nav class="main-nav" id="main-nav">` markup of the design from the visible `main` menu tree: a fixed home-icon link, the top-level items, and for items with children a dropdown with its sub-items in three columns closed by a brand stripe.
- `partials/main-nav.blade.php` stops containing static items and places the menu with `@menu('main')`; `layout` is unchanged.
- Item data is used as stored: title (reading language), address, `target`, `css_class`, `is_external`. An external item (`is_external`) in a dropdown gets the "external" arrow variant; `target="_blank"` and `rel="noopener noreferrer"` follow the rules of the `default` template.
- The dropdown is always three columns, but the template does not count or split the sub-items: they are printed in menu order as one flat list and the CSS (`columns: 3`) balances them into three columns for any number of items.
- The markup contract of `assets/app.js` stays as it is (`#main-nav`, `.main-nav__bar`, `.main-nav__close`, `.main-nav__list`, `.main-nav__item--has-dropdown`, `.dropdown`), so hover/focus dropdowns and the mobile modal keep working without script changes.
- If the menu `main` does not exist or has no visible items, the navigation bar is not printed.
- **BREAKING (theme only):** the static items ("Департамент", "Діяльність", …) disappear from the page until the menu `main` is filled in the admin panel.

## Capabilities

### New Capabilities

None.

### Modified Capabilities
- `dcculture-theme`: the requirement "Main navigation" no longer says the items are static markup; they come from the `main` menu, with the dropdown layout, empty-menu and unsafe-address behavior specified.

## Impact

- New: `themes/dcculture/views/menus/menu--main.blade.php`.
- Changed: `themes/dcculture/views/partials/main-nav.blade.php` (reduced to `@menu('main')` with a header comment), header comments in `themes/dcculture/assets/app.js` and `assets/components/main-nav.css` if they still say "static".
- Changed: `themes/dcculture/assets/components/main-nav.css` (three-column dropdown via CSS multi-column instead of three fixed `.dropdown__col` blocks).
- No PHP, database or JS behavior changes; `MenuRenderer` and `MenuTree` are reused as they are.
- Docs: update `docs/theme-dcculture.md` (templates tree, data seams table, the `main` menu contract) and add a link from `docs/theme-templates.md#the-main-menu-in-the-header`; `docs/README.md` index links stay valid (no files added).
- Tests: none are added or run — the public theme frontend is consumer code ([CLAUDE.md](../../../CLAUDE.md)); verification is `theme:check`, `npm run theme:build -- dcculture` and a visual check against the design.
- Out of scope: the active-item highlight, a third menu level, the footer and other menus, creating the `main` menu data (done by the editor in the admin panel).

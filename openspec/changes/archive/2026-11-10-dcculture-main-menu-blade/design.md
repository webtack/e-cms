# Design

## Context

- `MenuRenderer` ([theme-templates.md](../../../docs/theme-templates.md#menus)) resolves `menus/menu--{key}` before `menus/menu`, passes `$menu`, `$key` and `$items` (visible tree, any depth, invalid addresses already set to `null`) and prints `''` for a missing or empty menu. Only these variables are available inside the template (no `$site`).
- The CSS (`assets/components/main-nav.css`) and `assets/app.js` (`initMainNav`) already define the markup contract used by the static partial: `nav.main-nav#main-nav` → `.main-nav__bar` (modal header) + `ul.main-nav__list` → `li.main-nav__item[--has-dropdown]` → `a.main-nav__link` + `div.dropdown > .dropdown__inner > .dropdown__col* > a.dropdown__link`, and `.brand-stripe`. The script toggles `.is-open` and `aria-expanded` on `a.main-nav__link` of dropdown items and prevents navigation there below `lg`.
- The topbar menu button has `aria-controls="main-nav"`; `initMainNav` returns early when the element is absent.
- Theme frontend rule: no PHP/e2e tests; verified with `theme:check`, the theme build and a visual comparison ([theme-dcculture.md](../../../docs/theme-dcculture.md)).

## Goals / Non-Goals

**Goals:**
- Render the real `main` menu in the existing markup, with no script changes and only the dropdown rules adjusted in CSS (multi-column).
- Keep the partial `main-nav` as the single place where `layout` includes the navigation.

**Non-Goals:**
- Active-item highlight, third-level items, other menus (footer), a PHP-side helper or new template variables, seeding menu data.

## Decisions

1. **A menu template, not inline Blade in the partial.** The markup lives in `themes/dcculture/views/menus/menu--main.blade.php`; `partials/main-nav.blade.php` becomes a one-line `@menu('main')` with a header comment. *Why:* it uses the documented candidate cascade, so `MenuRenderer` already handles missing/empty menus and strips unsafe addresses. *Alternative:* query `MenuTree` in the partial — rejected, it duplicates the renderer and would bypass the address safety filter.
2. **The home-icon link is fixed markup in the template, not a menu item.** The design always has it first and it has no title; editors would otherwise have to add an icon-only item. It links to `/`.
3. **Two levels, always three columns, split by CSS.** The sub-items of an item are printed in the stored order as one flat list (`.dropdown__inner` > `a.dropdown__link`*); the template does not count or chunk them. `main-nav.css` makes `.dropdown__inner` a CSS multi-column container (`columns: 3`, `column-gap`, `column-rule` in the line colour, `break-inside: avoid` on links), so the browser balances the columns for any count and one item simply sits in the first column. The three fixed `.dropdown__col` blocks and the `nth-child` bullet colours per column are removed; the bullet is teal. Below `lg` the dropdown stays one column (`columns: 1`). *Alternatives:* `array_chunk` in the template — rejected (depends on the number of items); CSS grid with row-wise flow — rejected (reads across, not down). Children of sub-items are not rendered.
4. **Link variants.** A top-level item with children and no address renders `<a class="main-nav__link" href="#" aria-haspopup="true">` (the script already treats it as the accordion trigger); with an address it renders that address (the script still intercepts the click below `lg`). A leaf without an address renders `<span class="main-nav__link">`. Dropdown links use the right-chevron icon, or the `dropdown__link--external` arrow icon when `is_external`; a sub-item without an address is a `<span class="dropdown__link">`. `target`/`rel` and the "constant attributes only" approach are copied from `default/menus/list` so no stored text reaches unescaped output. An item with a missing title falls back to its address, and is skipped when both are empty (same as `default`).
5. **`css_class`** of an item is appended to its `li.main-nav__item`.
6. **`aria-label`** of the `<nav>` is `$menu->name`; the modal title and close label stay the fixed Ukrainian strings of the design ("Меню", "Закрити меню") because no translation mechanism for theme strings exists.
7. **Empty menu prints nothing.** The topbar menu button then does nothing below `lg` (the script returns early). Accepted: a site without a `main` menu has no navigation anyway.

## Risks / Trade-offs

- [The site shows no navigation until `main` is filled in] → document the `main` key contract in `docs/theme-dcculture.md`; the editor creates the menu in the admin panel.
- [CSS columns can leave the last column shorter and the column rule can touch the brand stripe edge] → checked visually with 1, 2, 4 and 8 sub-items.
- [Topbar button visible without navigation] → rare, accepted for now.
- [Long item titles] → handled by CSS wrapping; checked visually.

## Documentation

`docs/theme-dcculture.md` (templates tree, the data seam for `main-nav`, a short "Main menu" section), `docs/theme-templates.md` (cross-link from "The `main` menu in the header").

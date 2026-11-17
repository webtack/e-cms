# Tasks

## 1. Menu template

- [x] 1.1 Add `themes/dcculture/views/menus/menu--main.blade.php` with the `nav.main-nav#main-nav` markup: modal bar, fixed home link, top-level items, dropdowns with one flat list of sub-items (no counting or chunking) and the brand stripe, external/`target` handling, `css_class`, with a Blade header comment in English listing variables and the markup contract; verify with `docker compose exec app php artisan theme:check` (exit code 0) and by rendering a page with a seeded `main` menu that has leaf, parent, external and address-less items.
- [x] 1.2 Replace the static items in `themes/dcculture/views/partials/main-nav.blade.php` with `@menu('main')` and an updated header comment; verify that a page with no `main` menu returns 200 without a navigation bar and a page with the menu shows its items.
- [x] 1.3 Change the dropdown rules in `themes/dcculture/assets/components/main-nav.css` so `.dropdown__inner` uses `columns: 3` with a column rule, `break-inside: avoid` on links and one column below `lg` (drop `.dropdown__col` rules); verify at 1440 px with 1, 2, 4 and 8 sub-items that the panel has three balanced columns and at 375 px that it is one column.
- [x] 1.4 Update the "static" wording in the header comments of `themes/dcculture/assets/app.js` and `assets/components/main-nav.css` if present; verify with `grep -n -i static` on both files.

## 2. Build, visual check and docs

- [x] 2.1 Run `npm run theme:build -- dcculture` on the host and compare the result with the design at 360, 768, 1024 and 1440 px: hover/focus dropdown, mobile modal, accordion, Escape/close, long titles; verify each against the scenarios of the `dcculture-theme` delta spec.
- [x] 2.2 Update `docs/theme-dcculture.md` (templates tree, `main-nav` data seam now done, "Main menu" contract: key `main`, two levels, three-column CSS dropdown, empty menu, fixed home link) and add a cross-link in `docs/theme-templates.md#the-main-menu-in-the-header`; verify the relative links resolve and `docs/README.md` needs no new entries.

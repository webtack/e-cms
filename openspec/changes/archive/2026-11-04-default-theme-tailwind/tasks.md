# Tasks

## 1. Build of the styles of the theme

- [x] 1.1 Rewrite `themes/default/assets/css/app.css`: `@import 'tailwindcss';`, `@source` on `../../views` and on the `views` of the other themes (`../../../*/views`), the block `@theme` with the system font stack and the colours, and also ordinary selectors `.richtext` (headings, paragraphs, lists, links, quotes, tables) without `@apply`. Check the build `npm run build` on the host without errors and that `public/build/manifest.json` contains `themes/default/assets/css/app.css`.
- [x] 1.2 Check that `@source` gets into the build: a class used only in `themes/default/views/menus/list.blade.php` (for example `underline`) is in the compiled CSS (grep over `public/build/assets/*.css`); and that the rule `.richtext h2` is in the same file.

## 2. Frame of the page: html, layout, home, 404

- [x] 2.1 Style with utilities `html.blade.php` (a class on `<body>`: font, colour, background; `@vite` unchanged), `layout.blade.php` (the header with the logo, the name, the menu `main` with `flex-wrap`; the main zone with `max-w` and `px-4`/`lg:px-6`; the footer with an indent on top), `home.blade.php` and `errors/404.blade.php`, keeping the classes `site-header`, `site-main`, `site-footer` as hooks; check `npm run build` and opening `http://localhost:8080/` in the browser at 375px and 1440px that there is no horizontal scroll.
- [x] 2.2 Add the test `tests/Feature/Theme/DefaultThemeStylingTest.php`: the home page with `APP_THEME=default` contains `site-header`, `site-main`, `site-footer` and the link to `themes/default/assets/css/app.css` (or to the built file from the manifest), the 404 has the status 404 and contains `site-main`; check `docker compose exec app php artisan test --filter=DefaultThemeStylingTest`.
- [x] 2.3 Extend `e2e/public-site.spec.js` with a test at 375px: `document.documentElement.scrollWidth <= window.innerWidth` on the home page and on a page with a list, and the header has non-zero indents (`getComputedStyle`); check `npm run test:e2e -- e2e/public-site.spec.js`.
- [x] 2.4 Update `docs/site-themes.md` in the section «Ресурси теми (Vite)»: `default/assets/css/app.css` is the Tailwind build, `@source` covers the `views` of the themes; check that the references in the section are not broken.

## 3. Menu, list, card, page, block

- [x] 3.1 Style `menus/menu.blade.php` and `menus/list.blade.php`: a horizontal list with `flex-wrap` on a wide screen, nested items with an indent (`ml-*`) and a visible difference for the parent; keep the classes `menu`, `menu--{key}`, `menu__item`, and the `css_class` of the item; check `docker compose exec app php artisan test --filter=MenuRendererTest`.
- [x] 3.2 Style `content/list.blade.php` (the heading, `record-list` in a column with indents, the paginator separated), `content/preview.blade.php` (`card`, `card__title`) and `content/details.blade.php`, `pages/page.blade.php` (`page` with a limited width), `blocks/block.blade.php` (`block`, `block--{slug}`); check `docker compose exec app php artisan test --filter=PublicRoutesTest`.
- [x] 3.3 Update `docs/theme-templates.md`: the section «Шаблони `default`» and «Меню», if the described classes changed; check that all the mentioned classes are in the templates (grep).
- [x] 3.4 Extend `e2e/public-site.spec.js` with a check that on the page with the menu `main` the items are visible, and the header has a `flex` layout (`getComputedStyle(...).display === 'flex'`); check `npm run test:e2e -- e2e/public-site.spec.js`.

## 4. Fields

- [x] 4.1 Style the templates `fields/field.blade.php` and `fields/field-*.blade.php` (15 types): `field` with an indent, `field__label` smaller and muted, `field__item` with an indent; in `field-richtext.blade.php` add the class `richtext` to `field__item` (the styles of the content are in `app.css`, see 1.1); `field-youtube` with an `aspect-video w-full` wrapper; `field-image` and `field-file` with `max-w-full h-auto` and captions `field__caption`/`field__description`; keep all the classes `field`, `field-{ftype}`, `field--{key}`; check `docker compose exec app php artisan test --filter=DefaultFieldTemplatesTest`.
- [x] 4.2 Extend `tests/Feature/Theme/DefaultFieldTemplatesTest.php` with a check that for `richtext` the `field__item` has the class `richtext`, and for `youtube` there is `aspect-video`; check `docker compose exec app php artisan test --filter=DefaultFieldTemplatesTest`.
- [x] 4.3 Update `docs/theme-templates.md` in the section «Шаблони `default`» (the classes of the fields, `richtext`); check that the examples in the document match the markup.
- [x] 4.4 Extend `e2e/public-site.spec.js` with a check of an image in a field: the width of the `img` does not exceed the width of the content at 375px; check `npm run test:e2e -- e2e/public-site.spec.js`.

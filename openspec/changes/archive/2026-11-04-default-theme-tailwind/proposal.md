# Proposal

## Why

The theme `default` after the skeleton (`add-theme-skeleton`) and the field templates serves plain HTML with one flat `app.css` of a few lines. A site without styles does not look acceptable even for a demo and for checking the theme system. The admin already builds Tailwind 4 through `@tailwindcss/vite`, so styling the public theme with the same tool is cheaper than writing a separate CSS framework or pulling in a new UI library.

## What Changes

- `themes/default/assets/css/app.css` is rewritten for Tailwind 4: `@import 'tailwindcss'` and `@source` on the templates `themes/*/views`, so that the utilities from all themes get into the build. The styles of the elements are written with the Tailwind utilities in the templates; `app.css` contains the import, `@source`, the tokens `@theme` and ordinary selectors `.richtext` for the content from the editor. The hook classes of the templates remain unchanged.
- The templates of `default` receive the Tailwind utilities in the markup: `html`/`layout` (the header with the logo, the name and the menu `main`, the main zone with a limited width, the footer with the contacts), `home`, `pages/page`, `content/list` and the card `content/preview`, `content/details`, `blocks/block`, `menus/menu` and `menus/list` (a horizontal menu on the desktop, convenient on the mobile), `errors/404`, and also the templates of the fields `fields/*` (the captions, the values, the images, the links, YouTube, richtext).
- For the fields an agreed look is set: the indents and typography for `richtext`, adaptive images, a YouTube block with the 16:9 aspect ratio.
- The styles work on the mobile (375px) without a horizontal scroll of the page; on the desktop the content is centred with a width limit.
- The hook classes that already exist in the templates and are used as the contract for overriding by other themes (`site-header`, `site-main`, `site-footer`, `menu`, `menu--{key}`, `menu__item`, `field`, `field-{ftype}`, `field--{key}`, `field__label`, `field__item`, `page`, `record-list`) remain in place. The utilities are added next to them, not instead of them.
- No new npm dependencies are added: without `@tailwindcss/typography`, without UI libraries, without React on the public part. The font and the colours are set by the Tailwind tokens `@theme` in the file of the theme.
- Dark mode, a switcher of the theme and fonts from a CDN are out of the scope: these are separate decisions, not made in this change.

**Not BREAKING**: the template variables, the routes, `ThemeManager`, the hooks and the cascade of candidates do not change. The change concerns only the look of the theme `default`.

## Capabilities

### New Capabilities
- `default-theme-styling`: the visual requirements for the theme `default`: the Tailwind build of its CSS through Vite, the conformity of the templates to the agreed look (the header, the menu, the pages, the lists, the cards, the fields), the adaptivity down to 375px, the preservation of the hook classes for overriding in other themes.

### Modified Capabilities
- None. The behaviour of the rendering, of the cascade of the templates and of the routing does not change; `site-themes` describes only that `default` has `assets/css/app.css`, and this fact remains true.

## Impact

- Code: `themes/default/assets/css/app.css`, `themes/default/views/**/*.blade.php` (layout, html, home, pages, content, blocks, menus, errors, fields). `vite.config.js` and `resources/css/app.css` (the admin) do not change. Tailwind 4 and `@tailwindcss/vite` are already in `package.json`.
- Build: `npm run build` on the host (or `npm run dev`), so that `public/build/manifest.json` contains `themes/default/assets/css/app.css`.
- PHP tests (variable area): `tests/Feature/Theme` and `tests/Feature/Public/PublicRoutesTest.php`, if they check the markup; there are no changes in the PHP logic.
- E2E (variable area): `e2e/public-site.spec.js` (the public pages, the menu, 404, the service paths) and `e2e/smoke.spec.js`; both — by the table «Шаблони теми» ("Templates of the theme") in `docs/e2e-testing.md`.
- Documentation that is updated (in Ukrainian): `docs/theme-templates.md` (the section «Шаблони `default`» and the descriptions of the classes, if the structure of the markup changes), `docs/site-themes.md` (the section «Ресурси теми (Vite)»: `default/assets/css/app.css` is now the Tailwind build, and `@source` covers the templates of the themes). There are no new files in `docs/`, so `docs/README.md` does not change.
- Dependencies: without changes.

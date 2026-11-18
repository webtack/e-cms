# Design

## Context

- The theme `default` (`themes/default/`) already has all the templates: `html`, `layout`, `home`, `pages/page`, `content/{list,preview,details}`, `blocks/block`, `menus/{menu,list}`, `errors/404` and `fields/field-*` for 15 types. The markup without styles, the hook classes are already stable (see `docs/theme-templates.md`).
- `themes/default/assets/css/app.css` is plain CSS of a few lines. It is connected by `@vite($assets)` in `html` through `App\Theme\ThemeManager::assets()` (`docs/site-themes.md#ресурси-теми-vite`).
- `vite.config.js` already contains `@tailwindcss/vite` and builds `themes/*/assets/{css,js}/*`. The admin builds Tailwind 4 from `resources/css/app.css`. No new dependencies are needed for Tailwind: `tailwindcss` and `@tailwindcss/vite` are in `package.json`.
- The public page does not load the font `Instrument Sans` (the admin connects it only through the plugin fonts). To avoid adding external requests, the theme uses the system font stack.
- The values of `richtext` come as HTML from the editor without classes, so they cannot be styled with utilities in the markup.

The motivation and the list of changes — in proposal.md («Why» and «What Changes» sections).

## Goals / Non-Goals

**Goals:**
- The theme `default` looks acceptable at 375px and at 1440px without new dependencies and without JavaScript.
- The styles live in the build of the theme, not in the admin: `resources/css/app.css` and `vite.config.js` do not change.
- The hook classes from `docs/theme-templates.md` remain, so the other themes work as before.

**Non-Goals:**
- Dark mode, a switcher of themes, colour tokens for the admin.
- A burger menu with JavaScript: the menu wraps onto several lines, without scripts.
- Styles for the blocks outside the typical `blocks/block` (the separate blocks of the theme override them themselves).
- Connecting fonts from a CDN and third-party typography libraries.

## Decisions

### Tailwind 4 through the existing `@tailwindcss/vite`
The file `themes/default/assets/css/app.css` becomes the entry of Tailwind: `@import 'tailwindcss';` and `@source` on `../../views` (the templates of `default`) and on `views` of the other themes, so that their utilities also get into the build. Alternative: plain CSS without Tailwind. Rejected, because the user asked for Tailwind and the tool is already configured in the project.

### Tokens in `@theme` of the file of the theme
The colours, the font `system-ui` and the base indents are set in `@theme { ... }` of the same file. The admin does not see them, because its `@theme` is in `resources/css/app.css`. Alternative: a shared `@theme` in `resources/`. Rejected, so that the theme does not depend on the admin and can override the tokens.

### Utilities in the templates, ordinary selectors for richtext
- The styles of the elements — the classes of Tailwind directly in the markup: `html` (on `<body>` — the base font and colour), `layout`, `home`, the menu, the cards, the fields, 404. The classes that the templates already output (`site-header`, `menu__item`, `field__label`, `card`, `record-list` etc.) remain unchanged and serve as hooks for the other themes.
- `richtext` (HTML from the editor, without classes) is styled by ordinary CSS selectors under the class `richtext`, which is added to `field__item`: `.richtext h2 { … }`, `.richtext ul { … }`, `.richtext a { … }` etc. Without `@apply` and without `@layer`; the values are written in ordinary CSS (or refer to the tokens of `@theme` through `var(--…)`).
- `app.css` contains `@import 'tailwindcss';`, `@source`, `@theme` (the tokens) and the block of the selectors `.richtext`.
- Alternatives rejected: arbitrary variants for `richtext` (long and hard to read), `@apply` for the card/menu (duplicates the styles next to the markup), `@tailwindcss/typography` (a new dependency for the sake of one field).

### The hook classes remain, the utilities are added
`site-header`, `menu__item`, `field__label` etc. remain as they are. The utilities are added to the same elements, for example `class="site-header mx-auto flex flex-wrap items-center gap-4 px-4 py-3"`. So the override in another theme does not break the selectors that rely on them. The removal of the hook classes is forbidden by the specification `default-theme-styling`.

### Adaptivity through `flex-wrap` and the breakpoints `sm`/`lg`
The header and the menu: `flex-wrap`, the items wrap. The main zone: `px-4` on the phone, `max-w-3xl`/`max-w-5xl` with `mx-auto` on the desktop. The video: `aspect-video` with `w-full`. The images: `max-w-full h-auto`. There is no horizontal scroll of the page, because each wide block has `overflow-x-auto` or `max-w-full`.

### The look without an external font
`font-family` through `system-ui` in `@theme`. This corresponds to the Non-Goals (no CDN) and avoids FOUT.

### The build on the host
The styles are connected through Vite. In development `npm run dev` or `npm run build` on the host (Node is not in Docker). Without the build the `html` has no `@vite` tags for CSS, and the site works without styles, as now (`docs/site-themes.md`).

## Risks / Trade-offs

- [`@source` does not find the templates of other themes, or the glob is not recognized] → check in apply that a class from `themes/default/views/menus/list.blade.php` is in the generated CSS (`public/build/assets/*.css`). This is an explicit check of the build, not relying on automatic detection.
- [Classes in Blade do not get into the build if the file is outside `@source`] → `@source` on all `views` of the themes, plus a check of the page in the browser at 375px.
- [The size of the CSS is duplicated with the admin] → Tailwind generates only the used utilities, and the pages of the admin do not load this file; the risk is minimal.
- [The change of the look breaks the e2e tests that look for text or structure] → `e2e/public-site.spec.js` and `e2e/smoke.spec.js` are run in the tasks; the selectors by the hook classes remain.
- [The utilities in the markup complicate reading for the other themes] → the documentation of the classes in `docs/theme-templates.md` and the comments of the Blade templates in English remain.

## Migration Plan

The change has no data and no migrations. Rollback: return `themes/default/assets/css/app.css` and the templates from git. After the deployment the build of the resources is needed (`npm run build`), otherwise the site serves without styles.

## Documentation

- `docs/theme-templates.md`: the section «Шаблони `default`» — "Templates of `default`" and the description of the classes, if the markup described there changes (the header, the footer, the menu).
- `docs/site-themes.md`: the section «Ресурси теми (Vite)» — `default/assets/css/app.css` is the Tailwind build, `@source` covers the templates of the themes.
- `docs/e2e-testing.md`: without changes, if the rows of the tables do not change; the row «Шаблони теми» ("Templates of the theme") already covers `public-site` and `smoke`.

## Open Questions

None: the decisions above can be taken without the owner. The exact syntax of `@source` for the path to the `views` of other themes is checked by the build in apply, this does not change the specification.

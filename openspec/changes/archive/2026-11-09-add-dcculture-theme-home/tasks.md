## 1. Build: copy theme images

- [x] 1.1 Add the copy plugin to `vite.theme.config.js` (`themes/<name>/assets/{fonts,images}` → `public/app/<name>/` at `buildStart`; no-op without the folders; touches only `public/app/<name>/`; JSDoc in English with `@example`); add `/public/app` to `.gitignore`
- [x] 1.2 Verify with `npm run theme:build -- default` (no `images/`, builds as before, no `public/app/default` created) and later with `dcculture` (copied files appear)
- [x] 1.3 Document it in `docs/site-themes.md` (theme assets section)

## 2. Theme skeleton and assets (CSS, fonts, images)

- [x] 2.1 Create `themes/dcculture/theme.json`; copy fonts to `assets/fonts/` and all images to `assets/images/` from the design (quoted source paths)
- [x] 2.2 Port `theme.css` and `components/*.css` into `assets/`; create `assets/app.css` (Tailwind 4, `source(none)`, `@source` for `../views` and `../../default/views`); `/app/dcculture/fonts/...` in `@font-face`, `/app/dcculture/images/...` for ornaments
- [x] 2.3 Run `npm run theme:build -- dcculture` on the host; confirm fonts and images load from the same origin, `public/app/dcculture/{fonts,images}` is filled and survives a second build, and no external requests remain
- [x] 2.4 Run `docker compose exec app php artisan theme:check` and confirm `dcculture` reports OK

## 3. Shared frame on every page

- [x] 3.1 Create `views/partials/{topbar,site-header,main-nav,site-footer}.blade.php` from design blocks 01, 02, 03, 10 (static content; header comment lists future data variables; dropdown and brand stripes included; images as `/app/dcculture/images/...`)
- [x] 3.2 Create `views/layout.blade.php` composing top bar + brand stripe, header, nav, `@yield('content')`, footer; verify home, a content page and 404 all show the frame
- [x] 3.3 Visual check of the frame in the browser at 1440 px against the design (top bar, header ornament, nav hover/focus dropdowns, footer)

## 4. Home page

- [x] 4.1 Create `views/partials/home/{hero,about-city,news-afisha,tourist-banner,contest-band,map-section}.blade.php` from blocks 04–09, images as `/app/dcculture/images/...`
- [x] 4.2 Create `views/home.blade.php` extending `layout` and including the six sections in order
- [x] 4.3 Visual comparison at 1440 px against `poltava-home-standalone.html` (hero strip animation, news/afisha columns, banner, contests, map)

## 5. Docs

- [x] 5.1 Write `docs/theme-dcculture.md` (partials map, CSS layout, data seams for the next change, build and activation); link it from `docs/theme-templates.md` and `docs/README.md`

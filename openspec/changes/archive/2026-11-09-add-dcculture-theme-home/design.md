# Design

## Context

See proposal.md for motivation. Observed state of the project:

- The theme system already exists (`docs/site-themes.md`): `themes/<name>/{theme.json,hooks.php,assets,views}`, `default` is always the last template source, and a theme overrides only the templates it needs. `layout.blade.php` (frame) and `home.blade.php` (route `/`) are plain Blade templates; all public pages (`pages/page`, `content/*`, `errors/404`) `@extends('theme::layout')`, so a frame defined in `layout` reaches every page.
- Theme assets are built by `vite.theme.config.js` (`THEME=<name>`, entries `assets/{vendor,app}.{css,scss,js}`, output `public/themes/<name>/`, `manifest.json`). `default/assets/app.css` is Tailwind 4 with `@import 'tailwindcss' source(none)` and explicit `@source` paths.
- The design (`/Users/ruslan/Documents/Сайти/dkultura/design/dc-kulture-html`) is Tailwind 4: `theme.css` (tokens + `@font-face` + base), 19 component CSS files (`@layer components` + `@apply`), 10 HTML blocks, fonts in `public/fonts`, images in `public/images`. Image URLs in CSS are `url("../images/...")`; in HTML they are `src="images/..."`.
- `public/images/` holds admin files and is not touched by this change. nginx serves static files with `ETag`/`Last-Modified` and no `Cache-Control`.
- The local `.env` already has `APP_THEME=dcculture`; `.env.example` has `default`.

## Goals / Non-Goals

**Goals:**
- A `dcculture` theme that renders the design's frame on every page and the home page body, pixel-faithful to the desktop design.
- Frame and home sections as separate partials, ready to be fed with real data later without restructuring.
- Everything the theme needs lives inside `themes/dcculture/`; plain URLs for Blade images, in dev and production alike.

**Non-Goals:**
- Binding any data (site settings, `main` menu, blocks, nodes) — a later change.
- Mobile/tablet layout and styles for inherited `default` templates (`.richtext`, field output, pagination); `default` templates will render inside the new frame with only the utilities they already use.
- Replacing the Tailwind pipeline or adding npm packages.
- Cache-busting of Blade images (see Risks).

## Decisions

1. **Frame in `layout.blade.php`, parts in partials.** `themes/dcculture/views/layout.blade.php` extends `theme::html` (inherited from `default`) and composes `@include('theme::partials.topbar')`, `site-header`, `main-nav`, `@yield('content')`, `site-footer`; the small brand stripe is part of the topbar partial, the dropdown stripes part of the nav partial. `home.blade.php` extends `layout` and includes `partials/home/{hero,about-city,news-afisha,tourist-banner,contest-band,map-section}`. `@include('theme::…')` is used because the `theme` namespace already resolves `[active, default]`.
   *Alternative:* Blade anonymous components (`<x-topbar/>`, as the design README suggests). Rejected for now: component paths are not part of the theme cascade, and registering them would be new theme infrastructure. Partials with `@include` need none, and can later take explicit variables.

2. **One Tailwind entry, design file layout kept.** `themes/dcculture/assets/app.css` mirrors the design's `app.css` (`@import 'tailwindcss' source(none)`, `@source` for `../views` and `../../default/views` so inherited templates keep their utilities, then `theme.css` and `components/*.css` as partials). Names and file split of the design are kept to ease diffing against future design updates. Component files use `@apply` inside the same entry, which Tailwind 4 resolves without `@reference`.
   *Alternative:* copy the design's prebuilt `public/css/app.css`. Rejected: it would not follow theme builds, dev server or `@source` rules.

3. **All static files live in `themes/dcculture/assets/` and are copied to one folder, `public/app/dcculture/`.**
   - Sources: `assets/fonts/` and `assets/images/`. Result: `public/app/dcculture/{fonts,images}`. URLs are `/app/dcculture/fonts/<file>` and `/app/dcculture/images/<file>`, used identically in Blade and in CSS (`@font-face`, ornaments). Absolute URLs are left untouched by Vite, so there is one rule and no hashed duplicates.
   - A small inline plugin in `vite.theme.config.js` does the copy at `buildStart`, which runs for `vite build` and for the dev server. The target is outside Vite's output folder (`public/themes/<theme>/`), so the build's clearing does not erase it. It runs only when the source folders exist, writes only into `public/app/<theme>/`, and skips unchanged files (size and mtime).
   - `public/app/` is generated, so it is added to `.gitignore`; the theme folder is the source of truth. After cloning, a developer runs `npm run theme:build -- dcculture`.
   *Why `public/app/<theme>/`:* the owner's choice; it keeps static files apart from the build output and from the existing admin files in `public/images/`.
   *Alternatives:* Vite's `publicDir` (copies into `public/themes/<name>/`); `vite-plugin-static-copy` (new dependency for a few lines of code); a `theme_asset()` PHP helper (more moving parts); Vite-fingerprinted fonts via relative `url()` (a second mechanism).

4. **No JavaScript in the theme.** `assets/` has only `app.css` and its partials; the theme has no `app.js`.

5. **Static content first, with data seams marked.** Each partial holds the design's literal text and, in a Blade comment at the top, lists which variable later replaces it (as the design's HTML comments already do, e.g. `props: date, workStatus, phone`). No PHP variables are introduced now, so the partials keep working on an empty database.

6. **Home page body uses the design's sections 04–09 and the frame uses 01–03 and 10.** The `container-site` atom (1200 px, 120 px side margins) is part of CSS.

7. **Docs.** `docs/theme-dcculture.md` describes the theme (partials map, CSS layout, data seams, how to build); `docs/site-themes.md` describes the image copy step; `docs/theme-templates.md` links to the new document; `docs/README.md` index is updated.

## Risks / Trade-offs

- [Inherited `default` templates (richtext, fields, pagination) look unstyled in the new theme] → documented non-goal; the frame is correct, page styling is the next change.
- [Design's token names collide with `default`'s (`ink`, `muted`, `line`)] → separate builds, so no clash; values differ by design, which is intended.
- [Images have no content hash, so a browser may keep a replaced image for a while: nginx sends no `Cache-Control`, only `ETag`/`Last-Modified`] → accepted for developer-managed placeholders for now; if it bites, add `?v=<filemtime>` via a helper or `Cache-Control: no-cache` for `/images/`. The same applies to fonts.
- [2.6 MB of placeholder images (one 1.7 MB `news-1.jpg`)] → they are placeholders replaced by real content later; only images referenced by used partials are copied.
- [A public path `/app/...` could clash with a content type whose slug is `app`] → unlikely; nginx serves existing static files before Laravel, so a clash would be noticed at once.
- [Cyrillic path of the design source] → implementation copies files with explicit quoted paths; nothing in the repo refers to the source path.
- [Desktop-only design looks broken on narrow screens] → accepted by the proposal; the container simply scrolls horizontally rather than breaking the frame.

## Migration Plan

1. Create the theme, build with `npm run theme:build -- dcculture` (host), keep `APP_THEME=dcculture` locally.
2. No DB migration. Roll back by setting `APP_THEME=default`; `default` is untouched.

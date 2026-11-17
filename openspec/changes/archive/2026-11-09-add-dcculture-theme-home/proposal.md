# Proposal

## Why

The backend, admin panel and the `default` theme are in place, but the real public site of the Department of Culture and Youth of Poltava City Council (`dcculture`) still has no look. A finished static design exists (Tailwind 4, no JavaScript, desktop layout "DC Desktop 6"). The first frontend step is a dedicated theme with the home page, so the visual language, assets pipeline and the shared page frame are settled before the other pages are built.

## What Changes

- Add the theme `themes/dcculture/` (`theme.json`, `assets/`, `views/`) that overrides only what differs from `default`.
- Port the design's CSS: tokens (`@theme`), self-hosted fonts (Nunito, Inter, Philosopher), and one CSS file per component, into `themes/dcculture/assets/` as a Tailwind 4 build behind the existing `app.css` entry.
- Keep all the design's static files in `themes/dcculture/assets/fonts/` and `assets/images/`.
- Extend the theme Vite config so these folders are copied on build and on dev start into one folder `public/app/<name>/` (`public/app/dcculture/`); Blade templates and the theme CSS use `/app/dcculture/images/<file>` and `/app/dcculture/fonts/<file>`. No PHP changes.
- Override `layout.blade.php` so that the **shared frame** is rendered on **every** public page (home, pages, lists, details, 404): `topbar` + small `brand-stripe`, `site-header`, `main-nav` (with its dropdowns and their `brand-stripe`), the `@yield('content')` area, and `site-footer`. Each part is a separate partial so it can be replaced independently.
- Override `home.blade.php` with the home page body: `hero`, `about-city`, `news-afisha`, `tourist-banner`, `contest-band`, `map-section`, each a partial.
- All texts, links, menu items, contacts, news and images are **static markup copied from the design** ("as is"); wiring them to site settings, menus, blocks and content nodes is explicitly a later change.
- Activation stays via `APP_THEME=dcculture` (already set in the local `.env`; `.env.example` keeps `default`).

## Capabilities

### New Capabilities
- `dcculture-theme`: the theme `dcculture` — its structure, the shared page frame present on every public page, the home page template, and the design's styling (tokens, fonts, components, no JavaScript).

### Modified Capabilities
- `site-themes`: add a requirement for theme static files copied to `public/<name>/` by the theme build.

## Impact

- New: `themes/dcculture/**` (views, partials, CSS, fonts, images).
- Build: `vite.theme.config.js` gets a small copy step for `themes/<name>/assets/{fonts,images}`; generated `public/app/` is git-ignored. Existing files in `public/images/` are untouched. No PHP changes.
- Build: the existing `npm run theme:build -- dcculture` / `theme:dev -- dcculture` (`vite.theme.config.js`) are reused; no new npm dependencies expected beyond what the design's `package.json` already shares with the project (Tailwind 4 is present).
- Tests: none are added or run for the frontend (the theme is consumer code on top of the core; problems found later are fixed in separate changes). Verification is the theme check command and a visual comparison with the design.
- Docs: new `docs/theme-dcculture.md`; update `docs/site-themes.md` (theme static files), `docs/theme-templates.md` (link to the new theme), `docs/README.md` (index).
- Out of scope: backend data on the home page, the `main` menu / site settings / footer data wiring, mobile/tablet layouts (the design is desktop 1440 px), other page templates.

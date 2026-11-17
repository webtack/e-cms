# Proposal

## Why

Under `npm run theme:dev` the theme's CSS is loaded by the browser as a stylesheet from the dev server (`http://[::1]:5174/…/app.css`), so root-absolute URLs inside it (`url(/app/dcculture/images/bg_symphony.png)`, `@font-face` fonts, ornaments) resolve against the dev server origin. The static files live in `public/app/<theme>/` and are served only by the application (`:8080`); the dev server answers 404 for them (verified: `:5174/app/dcculture/fonts/…` → 404, `:8080/app/dcculture/fonts/…` → 200). While developing a theme, fonts and background images from CSS do not load. The built theme is not affected.

## What Changes

- The theme's dev server forwards requests for `/app/…` to the application (`APP_URL` from `.env`), so the bare URLs `/app/<theme>/…` used in CSS load from the dev server origin exactly as they do from the application.
- The URL form in CSS and templates does not change (`/app/<theme>/fonts|images/<file>`).
- `docs/site-themes.md` describes the dev-server behaviour for static files.

Not changing: the production build, `theme_asset()`, the copy of static files, the admin panel's Vite config, the "didn't resolve at build time" build warnings for these URLs.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `site-themes`: the requirement "Theme static files" gains the rule that the theme's dev server serves `/app/<name>/…` files too.

## Impact

- Code: `vite.theme.config.js` only (dev `server.proxy`); no PHP, no migrations, no new dependencies.
- Docs: `docs/site-themes.md` (sections "Commands" and "Static files (fonts, images)") is updated; `docs/README.md` needs no change.
- Tests: none. The change touches the theme build tooling and no browser behaviour of the admin panel (project rules: no PHP or e2e tests for theme frontend); verification is a manual check with `theme:dev` and `curl`.

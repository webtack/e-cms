# Design

## Context

See proposal.md for the problem. Observed state (read from the code and checked against a running dev server):

- `vite.theme.config.js` builds one theme; the `laravel-vite-plugin` writes `public/themes/<name>.hot` and the template connects the entries as `<link rel="stylesheet" href="http://[::1]:5174/…app.css">`. A stylesheet loaded this way resolves root-absolute URLs against the dev server origin (`:5174`).
- The plugin turns the Vite `publicDir` off, so the dev server serves nothing from `public/`; `copyThemeStatic` writes the theme's fonts and images to `public/app/<name>/`, which only the application (nginx, `:8080`) serves.
- Vite leaves `/app/…` URLs in CSS untouched (the build prints "didn't resolve at build time"); the same bare URL form is used in templates through `theme_asset()` (`docs/site-themes.md`, "Static files (fonts, images)").
- `.env` and `.env.example` define `APP_URL=http://localhost:8080`; the plugin already reads it (it prints `APP_URL` at start).

## Goals / Non-Goals

**Goals:**
- Fonts and images referenced from the theme's CSS load under `theme:dev` without changing how they are written.
- The dev-only fix has no effect on the build output.

**Non-Goals:**
- Hashing static files or switching the CSS to relative URLs (`../images/…`), which would change the build output and the `theme_asset()` contract.
- Removing the build warnings about unresolved `/app/…` URLs.
- Changing the admin panel's `vite.config.js`.

## Decisions

1. **Proxy `/app` from the dev server to the application.** `server.proxy` in `vite.theme.config.js` forwards `/app` to `APP_URL` (`changeOrigin: true`). The target is read from `.env` through Vite's `loadEnv` (all prefixes, from the project root), so it follows the project's own configuration; a missing `APP_URL` makes the config fail with a clear message, as it already does for a missing `THEME`. `server.proxy` applies to the dev server only, so `vite build` is unchanged.
   *Alternatives:*
   - *`publicDir` set to `public/app`:* rejected: the laravel plugin disables it on purpose and a build would copy it into the output.
   - *`server.origin` / `base` pointing at `:8080`:* rejected: they rewrite the URLs of Vite's own assets (entries, HMR client) too, not only the static files.
   - *Relative URLs in CSS:* rejected, see Non-Goals.
   - *Serving the folder with a small Vite middleware:* rejected: more code than the built-in proxy for the same result, and it would not follow the application's own caching and headers.

2. **Docs.** `docs/site-themes.md` gets one sentence in "Commands" (dev server) and one bullet in "Static files": the dev server forwards `/app/…` to `APP_URL`, so the application must be running while developing a theme.

## Risks / Trade-offs

- [`APP_URL` does not match the address the application is reachable at from the host (for example a different port)] → the proxy target is the same value the plugin and the templates already use; the docs name it as the setting to check.
- [The application is not running during `theme:dev`] → static files from CSS answer 502 from the proxy; the pages themselves need the application anyway, so this is the same prerequisite.
- [The dev server forwards `/app/…` for any path, not only theme folders] → only `public/app/` of the application is reachable through it, which is public already; entries and the HMR client live under other paths and are not affected.

## Migration Plan

Dev-tooling only: change `vite.theme.config.js`, restart `theme:dev`. Rollback is reverting the commit; no data, schema or build output changes.

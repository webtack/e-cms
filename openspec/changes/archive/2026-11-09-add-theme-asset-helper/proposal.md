# Proposal

## Why

Theme templates now reference static files (images, fonts) by bare paths such as `/app/dcculture/images/logo1.png` (change `add-dcculture-theme-home`). The files have no content hash, and nginx sends no `Cache-Control`, so after an image is replaced a browser may keep the old one. Laravel's Vite helpers cannot help here: a Blade image reaches the Vite manifest only through `import.meta.glob` in a JS entry, which the owner does not want, and the theme builds with its own Vite instance and build directory. A small core helper that adds a version to the URL solves it without touching the build.

## What Changes

- Add `ThemeManager::assetUrl(string $path): string`: takes a path relative to the theme's static directory (`images/logo1.png`) and returns `/app/<theme>/<path>?v=<filemtime>` of the copied file in `public/app/`.
- Lookup follows the theme cascade: the active theme first, then `default`. A file found in neither is returned as `/app/<active>/<path>` without `?v` (no error).
- Add the global helper `theme_asset()` (in `app/helpers.php`, like `image_style()`), a thin wrapper over `assetUrl()`. No Blade directive is added; templates call the helper.
- Replace the bare `/app/dcculture/images/...` paths in the `themes/dcculture/views` partials with `{{ theme_asset('images/...') }}`.
- CSS keeps bare absolute paths (`/app/dcculture/fonts/...`, ornaments): Vite leaves absolute URLs untouched and a PHP helper cannot run there. Fonts and CSS ornaments are rarely replaced; this is accepted.
- The helper does not depend on JavaScript: it reads only the copied files, so it works the same when the theme later gets `app.js` / `vendor.js` (already supported by the theme build).

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `site-themes`: add a requirement for versioned URLs of theme static files (`theme_asset()`).

## Impact

- Code: `app/Theme/ThemeManager.php` (new method), `app/helpers.php` (new function); `themes/dcculture/views/**` (partials switch to the helper). No migrations, no new packages.
- Tests: a PHP Feature test for the helper (`tests/Feature/Theme/`). None for the theme partials (frontend rule: no tests for theme templates).
- Docs: `docs/site-themes.md` (static files section), `docs/theme-templates.md` (helpers table), `docs/theme-dcculture.md`.
- Depends on `add-dcculture-theme-home` (it introduces `public/app/<name>/` and the "Theme static files" requirement); archive that change first.
- Out of scope: Vite-hashed images, versioning of URLs inside CSS, cache headers in nginx.

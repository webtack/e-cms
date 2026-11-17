# Design

## Context

- `add-dcculture-theme-home` copies `themes/<name>/assets/{fonts,images}` to `public/app/<name>/` at Vite `buildStart` and uses bare URLs `/app/dcculture/images/<file>` in Blade. It records the missing cache-busting as an accepted risk and names "`?v=<filemtime>` via a helper" as the fix.
- `ThemeManager` (`app/Theme/ThemeManager.php`) knows the active and fallback themes and already builds paths into `public/` (`public_path('themes/'.$active.'/manifest.json')`). `app/helpers.php` holds the global `image_style()`.
- The theme build already accepts `assets/{vendor,app}.{css,scss,js}`, so JavaScript in a theme needs no core change; this change must not require it.

## Goals / Non-Goals

**Goals:**
- One short call in templates instead of bare paths, with a version that changes when the file changes.
- Same cascade as templates (active theme, then `default`).

**Non-Goals:**
- Hashed file names, a Vite manifest entry for images, `import.meta.glob`.
- Versioning URLs inside CSS.
- nginx `Cache-Control` changes.

## Decisions

1. **Logic in `ThemeManager::assetUrl()`.** It already owns the theme names and paths; the helper only delegates, as `image_style()` does. Algorithm: normalise `$path` (trim leading `/`); if it has a `..` segment or is empty, return the unversioned active URL; for `[active, fallback]` (unique) check `public_path("app/{theme}/{path}")` with `is_file`; first hit returns `/app/{theme}/{path}?v={filemtime}`; otherwise `/app/{active}/{path}`.
   *Alternative:* a standalone helper class. Rejected: one method, no state.

2. **Version is `filemtime` of the copied file in `public/app/`,** the file that is actually served. The copy plugin keeps the source mtime only when it copies a changed file, so the version moves exactly when content is replaced and rebuilt. No query-string cache of results: `filemtime` is cheap and PHP's stat cache covers repeated calls in a request.
   *Alternative:* hash of content. Rejected: reads the whole file on each render.

3. **Cascade active → `default`.** Matches the template cascade. `default` has no static files today, so this is only a consistent rule for the future; the lookup costs one extra `is_file`.

4. **Only the global helper `theme_asset()`, no Blade directive.** It returns the raw string. Templates print it with `{{ }}`, so the URL is escaped in attributes; PHP code and hooks use the raw value.
   *Alternative:* a `@themeAsset` directive. Rejected: a second entry point with no extra capability.

5. **Missing file is not an error.** The page must not break because of an absent image; the URL without `?v` yields a normal 404 for that image, which is easy to notice.

6. **CSS stays bare.** `@font-face` and ornament `url()` keep `/app/dcculture/...`. Fonts and ornaments change rarely; if needed, `?v` can be added later by hand.

## Risks / Trade-offs

- [Version works only after the theme was built (files exist in `public/app/`)] → documented; without a build, files do not exist and the URL has no `?v`, same as today.
- [`?v` differs between servers if files are deployed with new mtimes] → harmless; it only forces one reload.
- [The helper cannot version CSS URLs] → accepted, see Decision 6.
- [This change relies on the archived state of `add-dcculture-theme-home`] → apply after that change is archived.

## Migration Plan

No data or config change. Roll back by reverting the partials to bare paths; the helper may stay unused.

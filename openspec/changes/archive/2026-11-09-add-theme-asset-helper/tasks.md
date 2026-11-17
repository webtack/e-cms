## 1. Core helper

- [x] 1.1 Add `ThemeManager::assetUrl(string $path): string` (active theme, then `default`; `?v=<filemtime>`; no `?v` and no error for a missing file; leading `/` ignored; `..` segment treated as missing); PHPDoc in English with `@example`
- [x] 1.2 Add the global helper `theme_asset()` to `app/helpers.php` (wrapper over `assetUrl()`, wrapped in `function_exists`), with comments. Remove the `@themeAsset` directive from `AppServiceProvider::boot()`
- [x] 1.3 Add `tests/Feature/Theme/ThemeAssetTest.php` (fixture themes): active-theme file, `default` fallback, changed mtime changes `?v`, missing file, `..` path, leading slash, rendering in a Blade template via `{{ theme_asset(...) }}` and escaping; run it with `docker compose exec app php artisan test --compact --filter=ThemeAsset` and Pint (`vendor/bin/pint --dirty --format agent`)

## 2. Use it in the theme

- [x] 2.1 Replace every `/app/dcculture/images/...` in `themes/dcculture/views/**` with `{{ theme_asset('images/...') }}`; CSS files stay unchanged
- [x] 2.2 Build the theme (`npm run theme:build -- dcculture`), open `/` and confirm all images load with `?v=...` and no 404; run `docker compose exec app php artisan theme:check`

## 3. Docs

- [x] 3.1 Update `docs/site-themes.md` (static files: `theme_asset()`, cascade, no `?v` for missing files), the helpers table in `docs/theme-templates.md`, and `docs/theme-dcculture.md` (images use `theme_asset()`, CSS keeps bare paths)

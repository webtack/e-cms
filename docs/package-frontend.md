# Package frontend

How the admin frontend (React + Inertia + Tailwind, built with Vite) reaches a host application. A host that installs the package needs **no Node, npm or build**: the package ships a compiled bundle and its own Inertia root view.

## Delivery

| Part | Where it lives | Notes |
| --- | --- | --- |
| Sources | `resources/js`, `resources/css` | Edited in the package only. |
| Compiled admin | `dist/build/` (`manifest.json`, `fonts-manifest.json`, `assets/*`) | **Committed** to the repository and tagged with releases (Composer cannot run a build). |
| Built theme `default` | `dist/themes/default/` | Output of `npm run build:themes`. |
| Static files of `default` | `dist/app/default/` | Copy of `themes/default/assets/{fonts,images}`; exists only when the theme has any. |
| Root view | `resources/views/app.blade.php`, view `ecms::app` | Used by `HandleInertiaRequests::$rootView`; the host's `app.blade.php` is never used. |

The font CSS inside the build points to `/vendor/ecms/build/assets/...` (`base` of `vite.config.js`), the place where the bundle is published. The fonts are part of the bundle (`fonts-manifest.json`), nothing is loaded from a CDN at run time.

## Publishing

| Tag | From → to | Purpose |
| --- | --- | --- |
| `ecms-assets` | `dist/build` → `public/vendor/ecms/build`; `dist/themes/default` → `public/themes/default`; `dist/app/default` → `public/app/default` (only when that directory exists) | The admin bundle and the built assets of the shipped theme. |
| `ecms-theme` | `themes/default` → `themes/default` of the host | The reference theme (`views/`, `assets/` sources, `hooks.php`, `theme.json`) as a model for a custom theme. |
| `ecms-theme-tooling` | `vite.theme.config.js`, `scripts/theme.mjs` → host root | A working Vite configuration and runner to build or develop a theme in the host. |

```bash
php artisan vendor:publish --tag=ecms-assets --force
```

- `ecms:install` publishes `ecms-assets` (always with `--force`: the files are the package's).
- Without `--force` an existing file is kept; with `--force` it is replaced by the package's current file.
- **After updating the package** publish `ecms-assets` again. Add it to the host's `composer.json` so it happens on every update:

```json
"scripts": {
    "post-update-cmd": [
        "@php artisan vendor:publish --tag=ecms-assets --force"
    ]
}
```

The browser reloads the admin only after the publish: the Inertia version is the hash of the published manifest.

## Root view and isolation from the host

`AdminAssets` (`Webtack\Ecms\Support\AdminAssets`) owns a private `Illuminate\Foundation\Vite` instance bound to `vendor/ecms/build` and the hot file `public/vendor/ecms/hot`. A view composer passes its tags (fonts, React refresh, entries) to `ecms::app`. The framework's Vite singleton, the host's `@vite`, `public/build` and its views are never read or changed.

`HandleInertiaRequests::version()` returns the hash of `public/vendor/ecms/build/manifest.json` (`null` when missing), so a CMS bundle change reloads browsers and a change of the host's `public/build` does not.

When neither the manifest nor the hot file exists, the root view prints a message with `php artisan vendor:publish --tag=ecms-assets --force` instead of a blank page.

## Building and the freshness check

Run on the host in the package directory (the first install needs `--legacy-peer-deps`):

```bash
npm install --legacy-peer-deps
npm run build:package   # tsc --noEmit + vite build → dist/build
npm run build:themes    # the theme default → dist/themes/default (+ dist/app/default)
npm run check:dist      # rebuilds into a temporary directory, compares file names with dist/build/assets
```

`check:dist` fails (exit code 1, lists `missing`/`stale` files) when a source changed and `dist/` was not rebuilt. Run it in CI and in the release checklist, and commit `dist/` together with the sources.

## Development with hot reload

The dev server runs from the package on the host and writes its hot file into the host, so the admin of a host loads from the dev server:

```bash
ECMS_HOT_FILE=/path/to/host/public/vendor/ecms/hot npm run dev
```

Remove the hot file (or stop the server, which deletes it) to use the published build again.

## Building a theme in the host

```bash
php artisan vendor:publish --tag=ecms-theme-tooling
npm install -D vite laravel-vite-plugin @tailwindcss/vite tailwindcss sass
npm pkg set scripts.theme:dev="node scripts/theme.mjs dev" scripts.theme:build="node scripts/theme.mjs build"
npm run theme:build -- default   # public/themes/default/manifest.json
```

The package never edits the host's `package.json`. The configuration is the same file the package uses for `build:themes`; see [Public site themes](site-themes.md#theme-assets-vite) for entries, SCSS and the dev server port (`THEME_DEV_PORT`).

## Notes

- **Drift of a published `default`.** A directory `themes/default` in the host shadows the package's theme. A copy published with `ecms-theme` that is not refreshed after a package update keeps serving the old templates; publish it again with `--force` (and do not edit it). A host theme named `default` collides with the shipped one.
- The built assets of `ecms-assets` and a build made in the host from the published sources land in the same `public/themes/default` with identical content.
- The font CSS uses an absolute path, so a host served from a sub-directory is not supported.

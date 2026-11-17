# Tasks

## 1. Package build

- [x] 1.1 Copy `resources/js`, `resources/css`, `tsconfig.json` and the Vite plugin dependencies into the package; add `package.json` (private, devDependencies and dependencies as in the demo, scripts `dev`, `build:package`, `build:themes`, `check:dist`) and `vite.config.js` with `publicDirectory: 'dist'`, `buildDirectory: 'build'`, `hotFile` from `ECMS_HOT_FILE`. Verify on the host: `npm install --legacy-peer-deps && npm run build:package` produces `dist/build/manifest.json` and `fonts-manifest.json`.
- [x] 1.2 Build the default theme into `dist/themes/default` (adapt `scripts/theme.mjs`/`vite.theme.config.js` for the package paths). Verify: `dist/themes/default/manifest.json` exists and lists `app.css`. The build also copies `assets/fonts` and `assets/images` of `default` to `dist/app/default` (existing `copyThemeStatic()`, adapted). Verify: a temporary `themes/default/assets/images/x.png` appears as `dist/app/default/images/x.png` after the build; remove the file afterwards.
- [x] 1.3 Add `check:dist` (rebuild to a temporary directory and compare manifest file names with `dist/`) and verify it passes on a fresh build and fails after touching a source file without rebuilding. Add JSDoc with `@example` to the scripts.

## 2. Root view, versioning, publishing

- [x] 2.1 Add `resources/views/app.blade.php` as `ecms::app` (dark-mode script kept) and an `AdminAssets` service with a dedicated `Vite` instance (build `vendor/ecms/build`, hot file `public/vendor/ecms/hot`) given to the view by a composer in the provider; register the `ecms` view namespace. Verify in a temporary host that the admin HTML references `/vendor/ecms/build/assets/…` and that a host page using `@vite` still resolves `public/build`.
- [x] 2.2 Set `HandleInertiaRequests::$rootView = 'ecms::app'` and override `version()` (hash of the published manifest, `null` when missing). Verify with a script: version changes when the manifest content changes and does not when the host's `public/build/manifest.json` changes.
- [x] 2.3 Add the missing-assets message to the root view and verify the response of `/admin/dashboard` contains the publish command when the manifest is absent.
- [x] 2.4 Register publish tag `ecms-assets` (`dist/build` → `public/vendor/ecms/build`, `dist/themes/default` → `public/themes/default`) and add the publish step to `ecms:install`. Verify: publishing twice is idempotent and `--force` overwrites. Add the entry `dist/app/default` → `public/app/default` only when the source directory exists. Verify: publishing without static files completes without an error, and with the temporary `x.png` creates `public/app/default/images/x.png`.
- [x] 2.5 Register the publish tags `ecms-theme` (the whole directory `themes/default` → `themes/default` of the host) and `ecms-theme-tooling` (`vite.theme.config.js`, `scripts/theme.mjs` → host root). Verify in a temporary host: publishing `ecms-theme` copies the complete tree (`views/`, `assets/`, `hooks.php`, `theme.json`) and a second run with `--force` restores a file that was edited; after publishing `ecms-theme-tooling` and installing the five dependencies, `THEME=default npx vite build -c vite.theme.config.js` produces `public/themes/default/manifest.json`, and `npm run theme:dev -- default` starts its dev server.
- [x] 2.6 Write `docs/package-frontend.md` (committed dist, publish, dev workflow, freshness check, host `post-update-cmd` hint, font note, the tags `ecms-theme` and `ecms-theme-tooling`, the drift note for a published `default`), update `docs/admin-shell.md` and `docs/package-installer.md`, fix `docs/README.md`; verify relative links resolve.

## 3. Integration check

- [x] 3.1 In a temporary host without `node_modules`, run `ecms:install` and open `/login` and `/admin/dashboard` in a browser (Playwright from the host against a served instance of that host, or the Claude-in-Chrome tool) and verify scripts/styles load without console errors.
- [x] 3.2 Run the dev server with `ECMS_HOT_FILE` pointing to the host's `public/vendor/ecms/hot` and verify the admin loads from the dev server (HMR connected).
- [x] 3.3 PHP/e2e: the PHP suite arrives in step 5 and e2e still run against the demo in step 7; this change runs only the checks above. State this in the summary. The change touches providers and middleware: the package suite will cover them in step 5.

# Tasks

## 1. Isolation of the admin build

- [x] 1.1 In `resources/css/app.css` replace `@import 'tailwindcss';` with `@import 'tailwindcss' source(none);` and add `@source '../../resources';`. Keep the vendor `@source` for the pagination templates. Do not add `@source` on `storage/framework/views` (the Blade cache contains the templates of the theme).
- [x] 1.2 Check that `themes/default/assets/css/app.css` and its `@source` are not changed.

## 2. Check of the build

- [x] 2.1 On the host run `npm run build`.
- [x] 2.2 Grep over `public/build/assets/app-*.css` (the CSS of the admin, its name from `public/build/manifest.json`): the classes `.max-w-prose` and `.gap-x-6` are absent; in the CSS of the theme (`themes/default/assets/css/app.css`) they are present. `.aspect-video` is not checked: the admin uses it through `@apply` in the iframe rule.
- [x] 2.3 Compare the number of rules of the admin CSS before and after the change. The difference must not contain classes which the sources of the admin (`resources/`) use.

## 3. Documentation

- [x] 3.1 In `docs/site-themes.md` (in Ukrainian) add a section about the isolation of the admin styles from the theme with a reference to `openspec/specs/default-theme-styling`. Update `docs/README.md`, if the structure of the documents changes.

## 4. Check of the behaviour

- [x] 4.1 The PHP tests do not change, so the PHP tests are not run.
- [x] 4.2 Run only the spec of the shell of the admin: `npm run test:e2e -- e2e/admin-shell.spec.js`. The change of the CSS affects the look of the admin, so the spec is needed; the full `npm run test:e2e` is not needed.

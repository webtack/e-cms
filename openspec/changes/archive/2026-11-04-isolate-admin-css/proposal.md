# Proposal

## Why

The admin is the core and must be independent of the theme: a change of a template of the theme (adding a Tailwind class) must not change the CSS of the admin. Now `resources/css/app.css` uses the automatic scanning of Tailwind, which covers the whole project, including `themes/`. Because of this, the classes that exist only in the templates of the theme (for example `aspect-video`, `max-w-prose`, `gap-x-6`) get into the admin build. The theme must remain completely free in its styling.

## What Changes

- The admin stylesheet `resources/css/app.css` stops scanning the whole project: the automatic scanning is turned off (`source(none)`), and the scanning is limited to `resources/` and the compiled templates `storage/framework/views`, plus the pagination templates from `vendor`.
- The theme `default` and its stylesheet do not change: the tokens, the templates and the scanning of `themes/*/views` remain as they are.
- A requirement is added that the admin build contains no utilities which are used only in the templates of the theme.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `default-theme-styling`: a requirement of the isolation of styles is added, so that the admin build does not depend on the templates of the theme. The requirement that the admin does not receive the styles of the theme already exists; the new requirement describes the build.

## Impact

- Code: `resources/css/app.css` (one scanning directive).
- Build: after `npm run build` the admin CSS contains no utilities that are only in `themes/`.
- The public theme: without changes.
- Documentation: the `docs/` file which describes the styles of the admin and of the theme (`docs/admin-shell.md` or `docs/site-themes.md`) gets a short description of the isolation rule. The specific file is specified in the tasks.
- Tests: the PHP tests do not change; the check is through the build and grep over `public/build`. E2E are not run, because the behaviour in the browser does not change.

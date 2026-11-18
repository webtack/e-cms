# Design

## Context

Vite builds two independent stylesheets: `resources/css/app.css` for the admin (connected by `app.blade.php`) and `themes/default/assets/css/app.css` for the theme (connected by `theme::html`). Both are built by one Tailwind 4 through `@tailwindcss/vite`. The theme already uses `source(none)` and scans only `themes/*/views`. The admin uses `@import 'tailwindcss'` without `source(none)`, so the automatic scanning covers the whole project, including `themes/`. See proposal.md, section «Why».

## Goals / Non-Goals

**Goals:**
- The admin build contains no utilities that exist only in the templates of the theme.
- The theme does not change: its tokens, templates and scanning remain as they are.

**Non-Goals:**
- Renaming the tokens of the theme or of the admin. The tokens live in separate builds and do not conflict.
- A change of the dark mode (the admin — the class `.dark`, the theme — the standard `dark:` of Tailwind). This is a separate decision.

## Decisions

**Turn off the automatic scanning of the admin and specify the sources explicitly.** In `resources/css/app.css` `@import 'tailwindcss' source(none);`, then `@source '../../resources';`. The vendor `@source` for the pagination templates is kept.
- Alternative: `@source not '../../themes';`. Rejected: with the automatic scanning `docs/` and `openspec/` remain, which also get into the build; the explicit list covers everything needed without exceptions.
- `@source` on `storage/framework/views` is not added. Reason: Blade compiles the templates of the theme into `storage/framework/views`, and the cache contained the classes of the theme (`max-w-prose`, `gap-x-6`), which got into the admin. The admin templates are in `resources/views`, so their utilities are covered by `@source '../../resources'`. Verified by the build: after removing this line all the classes of the admin CSS are found in `resources/` or in the pagination templates (the exception: `shimmer`, the name of a `@keyframes`).
- The class `aspect-video` remains in the admin CSS as part of the rule `.rich-text-content iframe` (`@apply` in `resources/css/app.css`), not as a separate class.

**Check through the build.** After `npm run build` (on the host) the absence of `.max-w-prose` and `.gap-x-6` in `public/build/assets/app-*.css` of the admin and their presence in the css of the theme are checked. The absence of a separate class `.aspect-video` is not a criterion: the iframe rule of the admin uses this utility through `@apply`. This is a simple check by grep, without new tests.

## Risks / Trade-offs

- **[An admin class is not found]** → If the admin uses a utility from `docs/` or `openspec/` (it never was in the code), it will disappear. Mitigation: after the build check the look of the key admin pages manually or through the existing e2e specs, which do not touch the styles of the theme.
- **[Regression of the admin]** → Mitigation: compare the number of rules of the admin CSS before and after the change. The difference must concern only the classes that are in `themes/`.

## Migration Plan

A change in `resources/css/app.css`: `source(none)` and the explicit `@source '../../resources'`, the `@source` on `storage/framework/views` removed. Rollback — return `@import 'tailwindcss';` without `source(none)` and the line `@source '../../storage/framework/views/*.php';`. There is no data migration.

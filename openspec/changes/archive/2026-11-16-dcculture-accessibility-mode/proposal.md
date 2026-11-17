# Proposal

## Why

The top bar of the `dcculture` theme already shows a "Версія для слабозорих" link, but it is a dead placeholder (`href="#"`). Visitors with low vision have no way to enlarge the text or switch to a high-contrast or grayscale view, and a public cultural-institution site is expected to offer it. The reference site (kultura-poltava.gov.ua) solves this with a small client-only dropdown; we need the same function in our theme and design.

## What Changes

- Turn the top-bar placeholder into a working dropdown button with six items: increase text size, decrease text size, grayscale, high contrast, negative contrast, reset.
- Text size has four steps (16, 18, 20, 22 px on `<html>`); the "decrease" item is disabled at the smallest step and "increase" at the largest.
- The three colour modes are mutually exclusive and are applied as `data-a11y-mode` on `<html>`; they override the theme's colour tokens and put `grayscale(100%)` on every `img` and on video posters (as the reference site does).
- The chosen text size and mode are stored in the visitor's browser (`localStorage`) and applied before the first paint by a small inline script in `<head>`, so there is no flash of the default view. Nothing is stored on the server.
- The button keeps its visible text on screens 768 px wide and wider (and shows only the icon below that, with an accessible name), and the dropdown is operable by keyboard and exposes its state to assistive technology.
- Convert the fixed pixel font sizes of the theme (`text-[Npx]` in `assets/components/*.css`) and the fixed pixel line heights (`leading-[Npx]`) to `rem`, so the text-size steps scale all text. At the default size the page looks exactly as before.
- Override `html.blade.php` in `dcculture` (copy of the `default` shell plus the inline script) to host the early script.
- Add Ukrainian and English strings for the panel.
- **No backend, database, route or site-setting changes.** Only the `dcculture` theme is touched; `default` and the core are unchanged.

## Capabilities

### New Capabilities
- `dcculture-accessibility`: the accessibility ("version for visually impaired") panel of the `dcculture` theme: the dropdown, the text-size steps, the three colour modes, reset, persistence in the browser, applying the saved view before first paint, keyboard and screen-reader behavior, and scaling of text with `rem`.

### Modified Capabilities
- `dcculture-theme`: the requirement "Top bar and header content" changes: the accessibility link of the top bar becomes the accessibility dropdown button instead of a link to a separate page.

## Impact

- Code (theme only): `themes/dcculture/views/html.blade.php` (new, overrides `default`), `views/partials/topbar.blade.php`, new `views/partials/a11y-panel.blade.php`, new `assets/js/a11y.js`, `assets/app.js`, new `assets/components/a11y.css`, `assets/components/topbar.css`, and the `rem` conversion in the components that use `text-[Npx]` / `leading-[Npx]` (about 15 files: `site-footer`, `contest-band`, `search`, `events`, `about-city`, `tourist-banner`, `news`, `site-header`, `main-nav`, `pill`, `empty-list`, `pagination`, `section-heading`, `archive-link`, `map-section`).
- Translations: `lang/uk/dcculture.php`, `lang/en/dcculture.php`.
- The menu overflow ("Ще") in `assets/js/nav-overflow.js` depends on text width, so it must recompute when the text size changes.
- Docs: `docs/theme-dcculture.md` updated (new "Accessibility panel" section, template tree, script list, note that `html.blade.php` is overridden and must follow `default`); `docs/theme-templates.md` unchanged unless the override note belongs there.
- Tests: none, by project rule for public-site themes. Verification is `php artisan theme:check`, `npm run theme:build -- dcculture`, and a visual check of every mode at 360, 768, 1024 and 1440 px.
- Not changed: PHP code, migrations, routes, admin panel, `default` theme, e2e specs.

## Non-goals

- Options the reference site does not have (images off, letter spacing, line height, font change).
- A server-side or per-account setting; the view is per browser.
- Making the `default` theme or other themes accessible through the same panel.
- A full WCAG audit of the theme; only the panel itself is built to be accessible.

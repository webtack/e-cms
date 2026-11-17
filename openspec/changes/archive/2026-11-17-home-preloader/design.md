# Design

## Context

See proposal.md for motivation. Observed state:

- The old site (`themes/default` of kultura-poltava) prints `#preloader` from `theme_preloader()` only when the home preprocess sets a flag. Markup: `.preloader-images` with 10 `.preloader-img-wrap > .preloader-img` (background image), then `.preloader-title` with `-first`, `.preloader-divider` and `-second`. Styles are `_preloader.scss`; the exit is `_preloader.js` (3000 ms move up, 3300 ms remove, independent of `window.load`).
- The data already exists here: node `block-preloader`, type `preloader`, fields `p_img` (image, multiple, max 10), `p_text` (text), `title` (string). No field is translatable.
- `dcculture` places blocks with `@block('slug')` and overrides block templates by `blocks/block-{type}` ([theme-templates.md](../../../docs/theme-templates.md#blocks)). `BlockRenderer` prints a block only when it is visible, so hidden/missing blocks print nothing.
- The home route is the same template for `/` and `/en`, so a block placed in `home.blade.php` covers both.
- Accessibility modes are `html[data-a11y-mode]` blocks in `components/a11y.css`; colours are tokens in `theme.css`.
- Old colours: text `#2fada3`, divider `#ffc107`, background `#fff` over `bg_symphony.png`.

## Goals / Non-Goals

**Goals:**
- Visually and temporally identical to the old preloader in the default mode.
- Home page only; zero cost on other pages (no markup, no query).

**Non-Goals:**
- No admin UI, new field, translation of the block, or change to the block's data.
- No "show once per session" memory: the old one runs on every home load and so does this.
- No change to the `default` theme or to the core.

## Decisions

1. **A block template, not a hook.** `views/blocks/block-preloader.blade.php` receives `$values` (raw), prints the overlay; `home.blade.php` places it with `@block('block-preloader')` at the top of `content`. Visibility, the missing-block case and the language of the values come from `BlockRenderer`. A hook would duplicate the lookup and visibility rules and add a variable to carry. Alternative: a `preprocess('home')` hook like `home-video.php`; rejected because the block cascade already does the same job.
2. **Images.** Each `p_img` item goes through `file_url()` then `image_style($url, '170x170')`, at most 10, as an inline `background-image` on `.preloader-img` (same as the old markup, so the CSS stays a port). Empty items are skipped; with no images the grid is not printed.
3. **Text.** `p_text` is plain text (`text` type), escaped with `{{ }}`; the old site stripped tags, which `{{ }}` makes unnecessary. If both `p_text` and `title` are empty the template prints nothing. Both lines are optional individually.
4. **CSS as a port.** `assets/components/preloader.css` is a plain CSS port of `_preloader.scss` (10 positioned diamonds from `$imgWrapSize=120`, `$offset=60`, the same keyframes and 3 s durations), registered in `app.css` before `a11y.css`. The old `animation: loader` keyframes are unused in the old markup and are not ported. `font-family` of the second line is `--font-accent` (Philosopher, already self-hosted). The mobile rule is `max-width: 991px` (old `mobile-screen`), kept as is.
5. **Tokens and modes.** Two new tokens in `theme.css`: `--color-preloader-text: #2fada3` and `--color-preloader-line: #ffc107`; background is `--color-paper` over the pattern. In `a11y.css`: `gray` sets `filter: grayscale(100%)` on `.preloader`; `contrast` and `negative` use `bg_symphony-dark.png`, grayscale images, and set both preloader tokens to the accent of the mode (`#7ab8ff`, `#ffd60a`, as the other tokens of those modes). This differs from the old site only in using the accents the theme already defines for these modes (the old used `#0d6efd` and `#ffc107`).
6. **Script.** `assets/js/preloader.js` exports `initPreloader(el)` imported by `app.js` and started with `document.querySelectorAll('#preloader')`: `setTimeout` 3000 ms sets `style.top = -clientHeight + 'px'` (CSS has `transition: top 0.3s ease-in-out`), 3300 ms removes it. Timers start when the script runs, like the old one.
7. **First paint.** The overlay is in the HTML, so it covers the page from the first paint of the markup; the CSS is in `<head>`. The script is a module and runs after parsing, so the 3 s starts at that point (same as old: script at the end of `body`).
8. **No JS fallback.** Without JavaScript the old overlay never leaves. Here `<noscript><style>#preloader{display:none}</style></noscript>` is printed inside the block, so the site stays usable. Only difference from the old site, and invisible with JS on.

## Risks / Trade-offs

- [Overlay covers content for 3 s on every home load] → required by the request; matches the old site.
- [Script fails to load, overlay stays] → the module is part of the single theme bundle that also drives the menu; failure would break more than this. Accepted.
- [Anchor/focus behind the overlay] → the overlay is `aria-hidden="true"` and removed after 3.3 s; keyboard focus can still reach the page behind it for that time. Accepted, same as old.
- [Image style `170x170` generated on first request] → 10 first-request generations after a cache clear; cached afterwards by `ImageStyles`.
- [Pixel drift from the Tailwind preflight or the theme's `box-sizing`] → compared visually with the live site at 1440 and 360 px.

## Migration Plan

Additive; nothing to migrate. Rollback: remove the `@block('block-preloader')` line from `home.blade.php`.

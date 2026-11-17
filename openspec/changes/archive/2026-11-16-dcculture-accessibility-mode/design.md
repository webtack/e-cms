# Design

## Context

See [proposal.md](proposal.md) for motivation and [specs/dcculture-accessibility/spec.md](specs/dcculture-accessibility/spec.md) for the requirements.

Current state of the `dcculture` theme (documented in `docs/theme-dcculture.md`):

- `partials/topbar.blade.php` already prints `<a class="topbar__a11y" href="#">` with an eye icon and `<span class="topbar__text">` (hidden below `md` by `max-md:hidden`, `topbar.css`). The translation key `dcculture.topbar.a11y` exists in `lang/uk` and `lang/en`.
- The theme extends `theme::html` from `default` (`themes/default/views/html.blade.php`); `html` is a required view of every theme, so `dcculture` may override it. The shell prints `{!! $assetTags !!}` in `<head>` and has no slot for an early script. No CSP is configured in the application.
- Colours are Tailwind 4 tokens in `assets/theme.css` (`@theme`: `--color-brand-*`, `--color-ink*`, `--color-surface`, `--color-line*`, `--color-muted`, ...). Components are `@layer components` + `@apply` in `assets/components/*.css`; a few colours are hard-coded (`text-[#e6eef1]`, `text-[#b8c7cd]`) and the decorative backgrounds are CSS `url()` images (`bg_symphony.png`, ornament SVG/PNG tiles, `slider-bg.jpg`).
- Font sizes are Tailwind `rem` utilities plus about 39 arbitrary `text-[Npx]` (12, 13, 14, 15, 17, 22, 32, 38, 44 px) and a few `leading-[Npx]`, all inside `assets/components/*.css`. Sizes in `px` do not follow the root font size.
- `assets/js/nav-overflow.js` keeps the main menu in one row from `lg`. It recomputes on a `ResizeObserver` of the list, on the `lg` media query and on `document.fonts.ready`; the list is a fixed-height row, so a change of the root font size does not trigger any of them.
- Scripts are small ES modules under `assets/js/`, each documenting its markup contract in the header comment and started from `assets/app.js`. `modal.js` provides `bindModal` for full-screen modals; it is not suited to a small dropdown.
- Tests for public themes are not written by project rule; verification is `theme:check`, the theme build and a visual check.

## Goals / Non-Goals

**Goals:**
- A working accessibility dropdown matching the reference site's six items, in the theme's design.
- A view that survives navigation and visits, applied before first paint.
- All text scaling with the steps, with an unchanged default look.
- Everything contained in `themes/dcculture` and its language files.

**Non-Goals:**
- Server-side state, accounts, cookies, or any change in PHP code.
- Reusing the panel in other themes; the shared files stay in `dcculture`.
- A generic theming engine: the modes are three fixed sets of overrides.

## Decisions

### 1. State lives on `<html>` as two things: an inline `font-size` and `data-a11y-mode`

The size step is written as `html.style.fontSize` (`16px`, `18px`, `20px`, `22px`) and the mode as `html[data-a11y-mode="gray|contrast|negative"]`. CSS selects on the attribute only. Mode values are our own short names, not the reference site's `theme-mode-*` ids.

- Why: this is the reference site's model, it needs no framework and the CSS cascade does the rest. The default step removes the inline style and the attribute, so "default view" is literally the absence of overrides.
- Alternative: a class per state on `<body>`. Rejected: `<html>` is available before `<body>` exists, which the early script needs.
- Alternative: a `data-a11y-size` attribute with CSS `font-size` rules. Rejected: it adds a second place that lists the steps; the inline style keeps the four values in one JS constant. (The early script and `a11y.js` share it, see 3.)

### 2. Storage: one `localStorage` key per value, validated on read

Keys `a11y:size` and `a11y:mode`. A value is accepted only if it is in the allowed list; otherwise it is ignored. All access is wrapped in `try/catch` (private windows, blocked storage); on failure the choice still applies to the current page. Reset removes both keys.

- Why: matches the reference site, no server round trip, nothing personal is stored. Cookies were rejected because they would be sent on every request for no benefit.
- Namespaced keys avoid clashing with other scripts on the same origin.

### 3. Early application: an inline script in an overridden `html.blade.php`

`themes/dcculture/views/html.blade.php` is a copy of the `default` shell with a small synchronous inline `<script>` placed in `<head>` before `{!! $assetTags !!}`. It reads the two keys, validates them with the same allowed lists and sets the attribute and the inline font size on `<html>`. The bundled `a11y.js` repeats nothing: it only reads the state that the early script (or its own choice) left on `<html>` and keeps the markers of the panel in sync.

- Why: the bundle is a deferred module, so applying the state there paints the default view first (a visible flash in the contrast modes). An inline script before the stylesheet removes it.
- Alternative: add a head slot to the `default` shell. Rejected by the owner: the work stays inside `dcculture`.
- Trade-off: `html.blade.php` is now a copy that must follow the `default` shell (variables, JSON-LD, meta). Mitigation: a header comment in the file says so and `docs/theme-dcculture.md` records it.
- The allowed lists are duplicated in the inline script and in `a11y.js` (the inline script cannot import). Both carry a comment pointing at the other.

### 4. Colour modes override tokens, not individual components

`assets/components/a11y.css` defines, per mode, a set of overrides of the `@theme` colour variables on `html[data-a11y-mode=…]` (for Tailwind 4 the tokens are CSS custom properties, so overriding them re-colours every utility built on them), plus targeted rules for what tokens do not reach.

| Mode | Page | Text | Accent |
|---|---|---|---|
| `gray` | light | as default | brand teal/green/amber and their soft variants mapped to grays |
| `contrast` | dark (`#000`-based) | light | blue (`#0d6efd`-like, lightened to reach 7:1 on dark) |
| `negative` | dark (`#000`-based) | light | yellow (`#ffc107`) |

- Targeted rules cover: `img` and video posters (`filter: grayscale(100%)`, applied in all three modes), hard-coded colours (`#e6eef1`, `#b8c7cd`), the decorative CSS backgrounds (`filter: grayscale(100%)` on the container or an overlay that hides the pattern in the dark modes, rather than new dark image files), borders (`--color-line*`) and the focus ring.
- Why tokens: about 30 component files use the tokens, so a handful of variables re-colours the theme and new components follow automatically. The reference site did the same with its Bootstrap variables.
- Exact colour values are chosen during implementation and checked against the 7:1 requirement for text on the page, footer, nav bar, cards and the dropdown itself.
- Rule for new components (added to `docs/theme-dcculture.md`): use tokens, not hard-coded colours.

### 5. Scaling: convert `text-[Npx]` / `leading-[Npx]` to `rem`, in its own first step

Every `text-[Npx]` becomes `text-[N/16 rem]` (for example 15 px → `0.9375rem`, 17 px → `1.0625rem`), and every `leading-[Npx]` likewise. The conversion is mechanical and lands first, so the default look can be compared with the old one before any new behaviour exists.

- Why: with the root font size as the single control, the existing `rem` utilities (`text-sm`, spacing) already scale; only the `px` values were left behind. Tailwind's own scale is not touched.
- Alternative: CSS `zoom` on `<body>` or a `transform`. Rejected: it breaks `position: sticky`/`fixed` layers (top bar, modals) and is inconsistent across browsers.
- Alternative: define the sizes as theme tokens (`--text-body: 0.9375rem`). Rejected as scope growth; a direct `rem` value keeps the change a pure unit change.
- Out of scope but checked: other fixed `px` values (heights such as `h-[292px]`, `h-[60px]`, widths) stay in `px`. Where a fixed height would clip larger text, it is changed to `min-height` (found by looking at the pages at 22 px). The main navigation bar `h-[60px]` is the known case, because its text grows.

### 6. The main navigation is told when the size changes

`a11y.js` dispatches a `CustomEvent('a11y:change')` on `window` after every change of size or mode. `nav-overflow.js` adds a listener to it that calls its existing `schedule`. Nothing else changes in `nav-overflow.js`.

- Why: nothing the script observes changes when only the root font size changes (see Context), so without the event the "Ще" item would show stale content and the bar could overflow at 22 px.
- Alternative: a `ResizeObserver` on every item. Rejected as heavier than one event for a rare action.

### 7. The dropdown is a disclosure, not a menu role

The button is `button[aria-expanded][aria-controls]` and the panel is a list of buttons. The six items are `<button type="button">` (not `<a href="#">` as on the reference site), so activation never scrolls or navigates. The active mode item gets `aria-current="true"` and `disabled`; the size items get `disabled` at the ends. The panel closes on Escape (focus back to the button), a click outside and focus leaving it. Items stay in the Tab order; no arrow-key menu pattern.

- Why: the ARIA `menu` role promises arrow-key navigation that has to be implemented and tested; a disclosure of plain buttons is accurate, simpler and fully keyboard-operable.
- Why not `bindModal`: it is a full-screen modal helper with scroll locking; this is a small popover.
- Without JavaScript the button has nothing to do. The panel is rendered with the `hidden` attribute and the button with `hidden` as well, and the script reveals the button on start. Because the topbar group is fixed markup, the button is revealed by removing `hidden` in `a11y.js`, so a visitor without scripts never sees a dead control (matches the spec).

### 8. Markup, texts and layout

- `partials/a11y-panel.blade.php` holds the whole wrapper: `div.a11y` > `button.topbar__a11y` (icon + `span.topbar__text` + `aria-label` with the same text, because the text is hidden below `md`) + `div.a11y__panel#a11y-panel` with a heading and the six buttons with icons (the reference site's icons are small inline SVG; we use the theme's inline `.icon` style). `topbar.blade.php` replaces the placeholder `<a>` with `@include('theme::partials.a11y-panel')`.
- New translation keys under `dcculture.a11y.*` (`title`, `increase`, `decrease`, `gray`, `contrast`, `negative`, `reset`) in `lang/uk` and `lang/en`; `topbar.a11y` stays the button text.
- The panel is positioned absolutely under the button, aligned to the right edge of the top-bar group and capped by `max-width: calc(100vw - 2rem)`, so it fits at 360 px. It sits above the sticky top bar's stacking context (the top bar is `topbar-sticky`; the panel is inside it and needs a z-index above the header and the main nav's `z-40`).
- Visible focus uses the same ring in every mode; in the dark modes the ring colour is a token override.

### 9. Documentation

`docs/theme-dcculture.md` gets an "Accessibility panel" section (markup contract, state, storage keys, modes and how to add a colour to a mode, the `rem` rule for new components, the overridden `html.blade.php`), the template tree and script list are updated. The header comment of `assets/js/a11y.js` follows the existing contract style.

## Risks / Trade-offs

- [Fixed-height components clip larger text] → look at each component at 22 px in the visual check; convert the offenders to `min-height`; keep the list of changed components in the task notes.
- [Hard-coded colours stay colourful in a mode] → targeted overrides plus a grep for `#` colours in `assets/components`; the visual check includes every mode on the home page, a news page, a list page, search and 404.
- [Contrast ratio 7:1 is not met on some pair] → check text/background pairs of the nav bar, footer, cards and the dropdown with a contrast checker while choosing the colour values.
- [Decorative backgrounds become noisy or bring colour in a mode] → grayscale filter on the container where it fits, otherwise hide the pattern in the dark modes.
- [`html.blade.php` copy drifts from `default`] → header comment, docs note; reviewed whenever the `default` shell changes.
- [Inline script and `a11y.js` disagree on allowed values] → both carry a comment pointing at the other; the values are three modes and four sizes.
- [The inline script runs before the DOM exists] → it only touches `document.documentElement`, which exists.
- [A `filter` on a container makes it the containing block for `position: fixed` descendants] → apply `grayscale` to `img`, posters and the pattern layers only, never to wrappers that hold fixed elements.

## Migration Plan

No data or schema migration. Deploy = merge, then `npm run theme:build -- dcculture` (the built `public/app/dcculture/` is generated, as for any theme change). Rollback: revert the change, or `APP_THEME=default`; visitors' stored keys are harmless and ignored by any other build.

## Open Questions

- The exact colour values of the three modes (chosen against the contrast target during implementation, shown on the home page for the owner's review).

# Design

## Context

See proposal.md - Why. Current state:

- `themes/dcculture/views/menus/menu--main.blade.php` prints `nav.main-nav#main-nav > ul.main-nav__list > li.main-nav__item`. From `lg` (1024 px) the list is `flex h-[60px] gap-3` without wrapping inside `.container-site` (896 px wide content at 1024 px, 1040 px at 1280 px, 1200 px from 1440 px). Dropdowns are `.dropdown` panels, `absolute inset-x-0` relative to `.main-nav`, opened by `:hover` / `:focus-within` in CSS. This is why horizontal scrolling of the list is impossible: any `overflow` on the list clips the panels.
- Below `lg` the same markup is a full-screen modal driven by `assets/app.js` (`bindModal`, `initMainNav`).
- `assets/app.js` (352 lines) holds: the breakpoint constant and the shared modal helper (`DESKTOP_QUERY`, `modals`, `bindModal`), the menu modal and accordion (`initMainNav`), the search modal (`initSearchModal`), and the video modal (`openVideo` and a delegated click handler). The video modal closes the other modals through the `modals` set.
- The theme build (see `docs/site-themes.md`) takes `assets/app.js` as an entry and follows imports of partials such as `assets/js/*.js`; partials do not become separate build files.

## Goals / Non-Goals

**Goals:**
- From 1024 px the bar never extends past the container, for any number and length of items.
- Every link stays reachable without JavaScript; the script only improves the layout.
- The existing hover / focus dropdown CSS and the server markup contract are reused, not duplicated.
- `app.js` becomes a small entry; each feature lives in its own file.

**Non-Goals:**
- No change to the menu data model, to menu levels (still two), or to the mobile modal behaviour.
- No change to the public behaviour of the search and video modals while moving them.
- No focus trap (as for the other modals of this theme).
- No tests for the theme frontend (project rule).

## Decisions

**1. Two layers: wrapping CSS as the base, a script as the improvement.** Without the script (`.main-nav__list` default from `lg`) the list wraps (`flex-wrap`, `min-h-[60px]`, a row gap), so the page never scrolls horizontally and all links stay reachable, as the theme's spec requires from 1024 px. When the script starts it adds `.is-fit` to the nav; `.is-fit` restores the single-row bar (`flex-nowrap`, fixed `h-[60px]`) and the script keeps the items inside it. *Alternatives:* shrinking paddings with `clamp()` only (does not hold when the menu grows); horizontal scroll (clips the dropdown panels); raising the breakpoint of the menu (breaks the single set of breakpoints and does not solve growth).

**2. The "Ще" item is built by the script from clones of the hidden items, the originals are only hidden.** The script appends `li.main-nav__item.main-nav__item--has-dropdown.main-nav__more` with a `<button type="button" class="main-nav__link" aria-haspopup="true">Ще</button>` and a chevron, and a `.dropdown` > `.dropdown__inner`. Reusing the classes `main-nav__item--has-dropdown` and `.dropdown` means the existing `:hover` / `:focus-within` CSS opens it with no extra handlers. Hidden originals get `.is-overflow` (`display: none` from `lg` only), so they leave the layout and the tab order and come back below `lg` with no script work. No server markup changes, so a page without the script is exactly as before plus wrapping. *Alternative:* moving the original `li` into the panel (changes the markup that the mobile modal relies on and needs moving back on every resize).

**3. Group markup in the "Ще" panel.** For each hidden item the script builds `.dropdown__group`: a leaf item becomes one `a.dropdown__link`; an item with sub-items becomes a heading (`a|span.dropdown__link.dropdown__link--heading`, the clone of its link without the chevron) followed by clones of its `.dropdown__link` sub-links. Clones keep `href`, `target`, `rel`, `aria-current`, the `is-active` class, the external mark and the item's own CSS class. `.dropdown__group` has `break-inside: avoid` so a group stays in one column inside the three-column panel. *Alternatives:* a flyout of the third level (poor for touch and keyboard); an accordion inside the panel (awkward in three columns).

**4. Measuring.** In `assets/js/nav-overflow.js`:
1. Work only when `(min-width: 1024px)` matches; below it clear `.is-overflow`, `.is-fit` and drop "Ще" (CSS also hides them, the script only keeps the state tidy).
2. Remove `.is-overflow`, read `offsetWidth` of every top-level `li` and the gap, read the content width of the list (`clientWidth` minus its horizontal padding), and the width of "Ще" (measured once after it is created, it does not depend on the hidden items).
3. If everything fits, hide nothing and hide "Ще". Otherwise take items from the start while their sum plus the width of "Ще" fits; the rest get `.is-overflow`, and "Ще" is filled and shown. The home link is never hidden. A 1 px tolerance avoids flicker at the boundary.
4. Reads are batched before writes in one `requestAnimationFrame`, so there is no layout thrash.
Triggers: a `ResizeObserver` on the list, `document.fonts.ready`, and the `matchMedia` change. Changing the content of the list does not change its own width, so the observer does not loop. "Ще" is rebuilt only when the set of hidden items changes.

**5. State.** `.is-trail` on "Ще" when any hidden item has `.is-active` or `.is-trail`; the clone that is active keeps `.is-active` and `aria-current="page"`, so the existing trail styles apply.

**6. Script split.** `assets/app.js` keeps the header contract summary and only imports and starts. New files in `assets/js/` (partials, not entries):

```
assets/app.js              entry: imports and starts the parts
assets/js/modal.js         DESKTOP_QUERY, the set of modals, bindModal, closeOtherModals
assets/js/main-nav.js      initMainNav (modal + accordion)
assets/js/nav-overflow.js  initNavOverflow (new)
assets/js/search-modal.js  initSearchModal
assets/js/video-modal.js   createCloseIcon, getVideoDialog, openVideo, the delegated click handler
```

The video modal and the other modals depend on the shared `modals` set only through `modal.js`. The header contract comment is split: each file describes the markup it uses. The functions keep their names and signatures (they are exported), so behaviour of the moved code is unchanged; the move is checked by the same manual flows as before (menu modal, search modal, video modal) after `npm run theme:build`.

**7. CSS.** In `main-nav.css`: base list wrap from `lg`; `.main-nav.is-fit .main-nav__list` single row; `@media (width >= 64rem) { .main-nav__item.is-overflow { display: none } }`; `.main-nav__more` shown only from `lg` and only when it has content (`hidden` attribute otherwise); `.dropdown__group` and `.dropdown__link--heading` (heading weight and colour of the theme, sub-links indented). The behaviour below `lg` is not touched.

## Risks / Trade-offs

- [A brief jump: the first paint may show two rows before the script collapses them] → the script is a deferred module that runs before `DOMContentLoaded` and does its first fit synchronously on start; check on a throttled connection and accept a one-frame jump if it remains.
- [Item widths change when the fonts load] → re-measure on `document.fonts.ready` and on every observer notification.
- [Duplicated links (clone and original) in the DOM] → the original is `display: none` from `lg`, so it is not announced and not focusable; below `lg` "Ще" is hidden and the originals are used.
- [A very long single item, or a narrow window just above 1024 px, hides almost everything] → the home link and "Ще" always stay, so the rest is still reachable.
- [The moved code regresses] → no logic changes in the move; verification is a manual run of the three modal flows and `theme:check`.

## Open Questions

- Resolved: no hidden state before the first fit. The first fit runs synchronously when the module starts, so the jump is a single collapse from two rows to one. Measured with a throttled connection (400 ms latency, 200 KB/s, CPU x4): the stylesheet paints the bar at about 4.8 s and the script applies the fit about 5.6 s later, so on a very slow connection the wrapped rows are visible for a moment. Accepted: hiding the bar until the script runs would make the menu depend on JavaScript from `lg`, which the spec forbids.

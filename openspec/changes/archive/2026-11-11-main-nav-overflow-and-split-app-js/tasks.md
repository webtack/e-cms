# Tasks

The theme frontend has no tests (project rule): the verification is `php artisan theme:check`, the theme build and a visual check. Comments and JSDoc are in English. The change touches only `themes/dcculture`, its docs and the spec.

## 1. Split `assets/app.js` (no behaviour change)

- [x] 1.1 Create `themes/dcculture/assets/js/modal.js` with `DESKTOP_QUERY`, the shared set of modals, `bindModal` and a helper that closes the other modals (used by the video modal); move their JSDoc with them.
- [x] 1.2 Create `assets/js/main-nav.js` (`initMainNav`), `assets/js/search-modal.js` (`initSearchModal`) and `assets/js/video-modal.js` (`createCloseIcon`, `getVideoDialog`, `openVideo`, the delegated click handler) as imports of `modal.js`; keep the exported names and signatures.
- [x] 1.3 Reduce `assets/app.js` to the entry: a short header comment pointing to the files, imports, and the start calls (`.main-nav`, `.search-modal`). Move each part of the old markup-contract comment into the file that uses it.
- [x] 1.4 Verify: `npm run theme:build -- dcculture` (or the project's theme build command from `docs/site-themes.md`) succeeds, `docker compose exec app php artisan theme:check` passes, and the menu modal, search modal and video modal still open and close on a phone width and from a desktop width.

## 2. Wrapping base and overflow CSS

- [x] 2.1 In `assets/components/main-nav.css` make the list wrap from `lg` (`flex-wrap`, `min-h-[60px]`, a row gap) and add `.main-nav.is-fit .main-nav__list` (single row, fixed `h-[60px]`, no wrapping).
- [x] 2.2 Add `@media (width >= 64rem) { .main-nav__item.is-overflow { display: none } }`, the styles of `.main-nav__more` (shown only from `lg`), `.dropdown__group` (`break-inside: avoid`) and `.dropdown__link--heading`, with sub-links indented under their heading. Do not change anything below `lg`.
- [x] 2.3 Update the header contract comment of `views/menus/menu--main.blade.php` (the added classes `is-fit`, `is-overflow`, `main-nav__more`, `dropdown__group`) and check the markup is otherwise unchanged.

## 3. Overflow script

- [x] 3.1 Create `assets/js/nav-overflow.js` exporting `initNavOverflow(nav)`: it creates the "Ще" item (button with chevron, `.dropdown` > `.dropdown__inner`), adds `.is-fit` to the nav, and runs the fit: batched reads, hides the last items that do not fit (`.is-overflow`) with "Ще" reserved, fills "Ще" with groups built from clones (leaf: one link; item with sub-items: heading plus sub-links), shows or hides "Ще", sets `.is-trail` on it when a hidden item is active or holds the active sub-item.
- [x] 3.2 Triggers: a `ResizeObserver` on the list (one `requestAnimationFrame` per burst), `document.fonts.ready`, and the `(min-width: 1024px)` change; below `lg` it clears `.is-overflow`, `.is-fit` and "Ще". Rebuild "Ще" only when the set of hidden items changes; use a 1 px tolerance.
- [x] 3.3 Start it from `assets/app.js` for every `.main-nav`; JSDoc with `@param` and `@example`.

## 4. Documentation, check and visual verification

- [x] 4.1 Update `docs/theme-dcculture.md` (English): the navigation and responsive sections (wrap without the script, "Ще" with it, groups, trail state of "Ще"), the new file layout of the script and the script contract (state classes `is-fit`, `is-overflow`).
- [x] 4.2 Visual check at 1024, 1100, 1280, 1440 px and at 375 px with the real menu `main` (8 top-level items): one row inside the container, "Ще" shown only when something is hidden, hover and keyboard focus open its three-column panel, a hidden item with sub-items is a group not split between columns, the active item in "Ще" gives "Ще" the trail state, resizing updates the split, no horizontal page scroll. Check with scripts disabled at 1024 px: two rows, all links reachable.
- [x] 4.3 Check the first paint on a throttled connection for the layout jump; decide whether a hidden state before the first fit is needed (open question in design.md) and record the decision in the design.
- [x] 4.4 Run `openspec validate main-nav-overflow-and-split-app-js` and report the checks that were run and skipped (no PHP tests and no e2e: theme frontend only).

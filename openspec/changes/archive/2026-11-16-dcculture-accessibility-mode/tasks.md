# Tasks

Public-theme work: no PHP Feature tests and no e2e specs (project rule). Verification is `docker compose exec app php artisan theme:check`, `npm run theme:build -- dcculture` (on the host) and a visual check in the browser at `http://localhost:8080` with `APP_THEME=dcculture`.

## 1. Scale text with the root font size

- [x] 1.1 List every `text-[Npx]` and `leading-[Npx]` in `themes/dcculture/assets/components/*.css`, `assets/theme.css` and `views/` (`grep -rnE "(text|leading)-\[[0-9.]+px\]"`) and verify the list is complete (the grep returns nothing after 1.2).
- [x] 1.2 Convert each to `rem` (`N / 16`, for example `text-[15px]` → `text-[0.9375rem]`, `leading-[26px]` → `leading-[1.625rem]`) and verify that `npm run theme:build -- dcculture` succeeds and the home page, a news page and a list page look the same as before at 16 px (compare at 360, 768, 1024 and 1440 px).
- [x] 1.3 Temporarily set `html { font-size: 22px }` in the browser devtools and fix every fixed height or width that clips or overlaps larger text (known case: the `h-[60px]` main-nav bar; change the offenders to `min-height`); verify the home page, a news page, the footer and the search page are readable at 22 px with no horizontal scrollbar from 360 px.
- [x] 1.4 Add the rule "font sizes in `rem`, colours from tokens" for new components to `docs/theme-dcculture.md` (CSS section) and verify the text is in place.

## 2. Panel markup and translations

- [x] 2.1 Add `lang/uk/dcculture.php` and `lang/en/dcculture.php` keys `a11y.title`, `a11y.increase`, `a11y.decrease`, `a11y.gray`, `a11y.contrast`, `a11y.negative`, `a11y.reset` (Ukrainian texts: «Для людей з порушенням зору», «Збільшити розмір тексту», «Зменшити розмір тексту», «Відтінки сірого», «Високий контраст», «Негативний контраст», «Скинути»); verify with `docker compose exec app php artisan tinker --execute="echo trans('dcculture.a11y.title');"` in both locales.
- [x] 2.2 Create `views/partials/a11y-panel.blade.php`: `button.topbar__a11y` (eye icon, `span.topbar__text`, `aria-label`, `aria-expanded`, `aria-controls`, `hidden` until the script starts) and `div.a11y__panel#a11y-panel[hidden]` with the heading and six `<button type="button">` items with `data-a11y-action` and their icons; document the markup contract in the file's header comment, and verify the page renders and `theme:check` passes.
- [x] 2.3 Replace the placeholder `<a class="topbar__a11y" href="#">` in `views/partials/topbar.blade.php` with `@include('theme::partials.a11y-panel')`, keep the button text visible from `md` and icon-only below it, and verify at 1440 px (text visible) and 375 px (icon only, accessible name present).

## 3. Early state and behaviour

- [x] 3.1 Create `views/html.blade.php` as a copy of `themes/default/views/html.blade.php` with a synchronous inline script in `<head>` before `{!! $assetTags !!}` that reads `a11y:size` and `a11y:mode` from `localStorage` (in `try/catch`, allowed values only: sizes 16, 18, 20, 22; modes `gray`, `contrast`, `negative`) and sets `html.style.fontSize` and `data-a11y-mode`; add a header comment saying the file follows the `default` shell and where the allowed values are duplicated, and verify `theme:check` passes and a stored mode is on `<html>` before the stylesheet loads (reload with the mode stored, no flash).
- [x] 3.2 Create `assets/js/a11y.js` (`initA11y`): reveals the button, opens/closes the panel (Escape returns focus to the button, click outside, focus leaving), applies size steps and modes, writes and removes the storage keys (`try/catch`), disables the decrease/increase item at the ends and the active mode item (`aria-current`), makes "Скинути" restore the default view, keeps the panel open after an item, and dispatches `window` event `a11y:change`; add JSDoc with `@example` and a header comment with the markup contract; verify in the browser: each of the six items, the persistence after reload and on another page, a corrupted stored value (`localStorage.setItem('a11y:mode','x')`) falls back to the default, and blocked storage does not raise an error.
- [x] 3.3 Start it from `assets/app.js` (`document.querySelectorAll('.a11y').forEach(initA11y)`, update the file's header list) and make `assets/js/nav-overflow.js` call its `schedule` on `a11y:change`; verify at 1440 px that increasing the text to 22 px keeps the menu in one row with the "Ще" item updated.
- [x] 3.4 Update `docs/theme-dcculture.md`: the template tree (`html.blade.php`, `partials/a11y-panel.blade.php`), the script list (`a11y.js`), a new "Accessibility panel" section (markup contract, storage keys, the early script and why `html.blade.php` is overridden and must follow `default`), and verify the cross-links work.

## 4. Colour modes

- [x] 4.1 Create `assets/components/a11y.css` (import it from `assets/app.css`) with the panel and button styles (position under the button, right-aligned, `max-width: calc(100vw - 2rem)`, z-index above the header and the main nav, visible focus ring, disabled and current states) and verify the panel fits a 360 px screen and sits above the header and the menu.
- [x] 4.2 Add the `gray` mode: override the brand colour tokens with grays on `html[data-a11y-mode="gray"]`, `filter: grayscale(100%)` on `img` and video posters (and on the decorative background layers without putting a filter on wrappers of fixed elements); verify the home page, a news page and the footer have no colour left except grayscale.
- [x] 4.3 Add the `contrast` and `negative` modes: dark token set, light text, blue and yellow accents, grayscale images and posters, overrides for hard-coded colours (`#e6eef1`, `#b8c7cd`, found with `grep -rnE "#[0-9a-fA-F]{3,8}" assets/components`), borders, form fields, the focus ring and the decorative patterns; choose the colour values so text pairs reach 7:1 and verify with a contrast checker on the top bar, nav bar, dropdown, cards, footer and the panel itself.
- [x] 4.4 Check every mode (and each at 16 and 22 px) on the home page, a news list and details page, the search page, the video list and a 404 at 360, 768 and 1440 px, fix what stays colourful, unreadable or clipped, and record the final colour values in `docs/theme-dcculture.md` (the "Accessibility panel" section, including how to add a colour to a mode).

## 5. Final check

- [x] 5.1 Run `docker compose exec app php artisan theme:check`, `npm run theme:build -- dcculture`, then do the keyboard pass (Tab to the button, Enter, Tab through the items, Enter on a mode, Escape) and the scripts-disabled check (button not shown, page readable); verify against the scenarios of `specs/dcculture-accessibility/spec.md` and `specs/dcculture-theme/spec.md`.

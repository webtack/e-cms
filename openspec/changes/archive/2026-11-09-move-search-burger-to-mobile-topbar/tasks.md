# Tasks

Per project rules no PHP or e2e tests are written for theme frontend; verification is `theme:check`, the theme build and a visual check at 1440, 768 and 375 px.

## 1. Markup

- [x] 1.1 In `topbar.blade.php` hide `.social-links` below `lg` and add the search and menu icon buttons (`lg:hidden`, `aria-label`, `aria-expanded="false"`, `aria-controls`); verify the rendered `/` contains both buttons and the social links
- [x] 1.2 In `main-nav.blade.php` remove the `.main-nav__toggle` button, add `id="main-nav"` and a close button; wrap the search form in `div#search-modal.search-modal` with a close button and remove `max-lg:hidden` from the include in `site-header.blade.php`; verify with `docker compose exec app php artisan theme:check`

## 2. Styles

- [x] 2.1 Update `topbar.css` and `main-nav.css`: style the topbar buttons, remove `.main-nav__toggle` rules, make the nav a full-screen modal when `.is-open` below `lg` (hidden otherwise), update the file's header comment; verify with `npm run build` and at 375 px and 1440 px
- [x] 2.2 Update `site-header.css`: `.search-modal` inline from `lg`, hidden below `lg`, dimmed full-screen layer with a white card when `.is-open`; add the `modal-open` scroll lock; verify the header shows the form at 1440 px and not at 375 px

## 3. Script

- [x] 3.1 Change `initMainNav` in `assets/app.js` to find the burger by `aria-controls`, handle the close button, scroll lock, Escape and focus return; update the file's JSDoc and markup contract; verify open/close, accordion and Escape at 375 px
- [x] 3.2 Add `initSearchModal` (JSDoc with `@example`): open/close via button, close button, dimmed area and Escape, focus into the input, one modal at a time, state reset on crossing `lg`; verify at 375 px and when resizing to 1440 px

## 4. Documentation

- [x] 4.1 Update `docs/theme-dcculture.md` (frame, topbar buttons, menu and search modals, script contract, no-script behavior); verify links in `docs/README.md` still resolve
- [x] 4.2 Visual check at 1440, 768 and 375 px against the spec scenarios (desktop unchanged, buttons replace social links, both modals open/close, no horizontal scroll at 360 px)

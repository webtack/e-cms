# Tasks

## 1. Block template and home placement

- [x] 1.1 Create `themes/dcculture/views/blocks/block-preloader.blade.php`: `#preloader.preloader[aria-hidden=true]` with up to 10 `.preloader-img-wrap > .preloader-img` (`file_url` + `image_style(..., '170x170')` as inline background), then `.preloader-title` (first line `p_text`, `.preloader-divider > span`, second line `title`), `<noscript>` hiding rule; prints nothing when both texts are empty and omits the grid without images. Verify: `docker compose exec app php artisan theme:check` passes and the home HTML contains one `#preloader` with 10 tiles.
- [x] 1.2 Place `@block('block-preloader')` at the start of the `content` section in `themes/dcculture/views/home.blade.php` and update the header comment of both files. Verify: `/` and `/en` (if enabled) contain `#preloader`; `/news` and the 404 page do not (`curl -s … | grep -c 'id="preloader"'`).
- [x] 1.3 Verify the hidden case: disable `block-preloader` in the admin, reload `/` and confirm no overlay and no error; enable it again.

## 2. Styles

- [x] 2.1 Add tokens `--color-preloader-text` and `--color-preloader-line` to `assets/theme.css`; copy `bg_symphony-dark.png` from the old theme (`kultura-poltava/themes/default/template/src/images`) to `assets/images/`. Verify: the file exists and the tokens are in the built CSS.
- [x] 2.2 Create `assets/components/preloader.css`: a port of `_preloader.scss` (container, 10 diamond positions, keyframes `img-1…10`, `show-title-first/second`, `show-divider`, mobile hide below 992 px, Philosopher for the second line) and import it in `assets/app.css` before `a11y.css`. Verify: `npm run theme:build` succeeds.
- [x] 2.3 Add the mode rules to `assets/components/a11y.css` (`gray` grayscale on `.preloader`; `contrast`/`negative` dark background, grayscale images, accent tokens) with a comment per rule. Verify: build succeeds; with each mode on, the overlay looks as specified (visual check).

## 3. Script

- [x] 3.1 Create `assets/js/preloader.js` (`initPreloader`: 3000 ms `top = -clientHeight px`, 3300 ms `remove()`), import and start it in `assets/app.js`, and list it in the header comment of `app.js`. Verify: after loading `/`, `#preloader` has a negative `top` at ~3 s and is gone at ~3.3 s (browser console or `curl`-less check with the browser).

## 4. Documentation and final check

- [x] 4.1 Add a "Home preloader" section to `docs/theme-dcculture.md` (data source, markup, timing, mobile, modes, tokens, `noscript`, the placement line to remove it) and the script to its "Script files" list. Verify: the section names every file touched by tasks 1-3.
- [x] 4.2 Compare with https://kultura-poltava.gov.ua/ at 1440 px and 360 px (layout, animation, exit) and in the three accessibility modes; run `docker compose exec app php artisan theme:check`. No PHP tests or e2e (theme frontend); report this in the summary.

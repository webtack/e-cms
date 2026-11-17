# Proposal

## Why

The "Afisha" block of the home page shows three events as one large card plus two compact cards. The large card crops the poster (`object-cover` in a 520 px box), and the compact cards are separate mini blocks without a picture. The block should show the five nearest events as a horizontally scrolled slider, one uniform slide per event, with the whole poster visible in a fixed-size picture.

## What Changes

- The hook `home-afisha` reads five not-past events instead of three (the selection, the sort and the item contract are unchanged).
- The block prints one slide type for every event (poster, date pill, title, "Read more"); the large card and the compact mini blocks (`.afisha-compact`) are removed.
- The slider shows one slide per screen (the slide is as wide as the column), scrolls horizontally with CSS scroll snapping, and works by swipe, wheel and keyboard without JavaScript; a small script adds the previous / next buttons, the dots and the autoplay.
- Autoplay moves to the next slide every ~6 seconds and returns to the first one after the last; it pauses on hover, while the focus is inside the slider, and while the browser tab is hidden. Defaults added by this change: autoplay is off under `prefers-reduced-motion: reduce` and stops for good after the visitor scrolls the slider by hand. There is no pause button.
- The poster is a fixed `400 × 500` picture that is never cropped: the image style with both sides (`800x1000`, 2× for retina) fits the original inside and fills the empty margins with a blurred copy, so the template needs neither `object-fit: cover` nor its own blur.
- New translatable strings for the buttons, the dots and the slider label (`home.afisha.*`, `uk` and `en`).
- The promo block "Follow us" under the block and the link to `/events` are unchanged.

## Capabilities

### New Capabilities

None.

### Modified Capabilities
- `dcculture-theme`: the requirement "Home block "Afisha" shows the nearest events" gets the count (five), the slider form and the whole-poster rule.

## Impact

- Code (theme `dcculture` only): `hooks/home-afisha.php` (`perPage`, docblock); `views/partials/home/news-afisha.blade.php` (markup and docblock); `assets/components/afisha.css` (slider styles, the large and compact card styles are removed); `assets/js/afisha-slider.js` (new) imported in `assets/app.js` (with the part list in its doc comment); `lang/uk/dcculture.php` and `lang/en/dcculture.php` (new keys). No core code, no migrations.
- Tests: none, by the project rule for theme frontend; verification is `php artisan theme:check` (via `docker compose exec app`), `npm run build` and a visual comparison.
- Docs updated: `docs/theme-dcculture.md` (sections "Home afisha" and the part list of the theme script); `docs/README.md` links stay valid.
- Specs: delta for `dcculture-theme`.
- Related change: `events-list-cards` (complete, not yet archived) also modifies the requirement "Home block "Afisha" shows the nearest events" (the date format); this change builds on its text, see design.md.

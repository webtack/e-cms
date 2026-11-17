# Design

## Context

The "Afisha" column (`.afisha-column`, 400 px wide from `lg`, full width below) is built in `partials/home/news-afisha.blade.php` from `$homeAfisha`, which `hooks/home-afisha.php` fills with three items. The first becomes `.afisha-card` (picture `h-[520px] object-cover`, hence the cropping), the others `.afisha-compact`. The "Follow us" promo block follows in the same column and is not touched.

The theme script (`assets/app.js`) imports small modules from `assets/js/` (`main-nav`, `nav-overflow`, `search-modal`, `video-modal`, `photo-lightbox`); there is no carousel and no third-party JS library. The image style with both sides (`800x1000`) fits the original inside without cropping and fills the margins with a blurred copy ([Image styles](../../../docs/image-styles.md), spec `image-styles`); transparent images keep transparent margins. Theme frontend has no PHP or e2e tests (project rule). Components are described in `docs/theme-dcculture.md` (section "Home afisha").

## Goals / Non-Goals

**Goals:**
- A slider of up to five uniform slides, one visible at a time, that is usable without JavaScript.
- The same selection, sort and item contract of the hook; only the count changes.
- A poster of a fixed size, shown whole, without CSS tricks.

**Non-Goals:**
- No third-party slider library, no new npm dependency.
- No pause button, no swipe-gesture code, no infinite (cloned) looping, no thumbnails.
- No change to the news column, the promo block, the events list or the events details page.

## Decisions

**1. CSS scroll snapping carries the slider; JavaScript only enhances it.** The track is a flex row with `overflow-x: auto`, `scroll-snap-type: x mandatory` and a hidden scrollbar; a slide is `flex: 0 0 100%` with `scroll-snap-align: start`. Swipe, wheel and Tab (every slide is a link, the browser scrolls a focused link into view) work natively. Alternative: Swiper or a transform-based slider; rejected because it adds a dependency and a second source of truth for the position, and breaks without JS.

**2. One module, `assets/js/afisha-slider.js`, exports `initAfishaSlider(root)`;** `app.js` calls it for each `[data-afisha-slider]`. The module follows the style of the existing parts (a doc comment that states the markup it expects). It owns the buttons, the dots and the autoplay; the position is always read from the track (`scrollLeft / clientWidth`, rounded), never kept in a variable, so native scrolling and the script cannot disagree. Active dot and the disabled state of the buttons are updated on the `scroll` event (a `requestAnimationFrame`-throttled handler) instead of an `IntersectionObserver`: with exactly one full-width slide per screen the rounded index is simpler and has no threshold tuning. This replaces the observer mentioned in the discussion; the behavior is the same.

**3. Buttons and dots exist only for the script.** The server prints the track and the slides. The script builds the previous / next buttons and the dots from the slide count (labels from `data-*` attributes filled with `trans('dcculture.home.afisha.*')`), so without JS the page shows no dead controls. With one slide the script builds nothing. Moving is `track.scrollTo({ left: index * width, behavior })`; the next button on the last slide is disabled, as the autoplay is the only thing that wraps to the first slide. Behavior is `'auto'` under `prefers-reduced-motion: reduce`, `'smooth'` otherwise.

**4. Accessibility.** The root has `role="region"`, `aria-roledescription="carousel"` and `aria-label` (the block heading); each slide `role="group"`, `aria-roledescription="slide"` and `aria-label="N / total"`. Slides that are not visible get `aria-hidden="true"` and `inert` (so Tab does not go into a hidden slide's link and focus cannot sit on an invisible element); the script toggles them with the active index. Without JS none of this is applied, and all slides stay in the tab order. The dots are buttons with `aria-current="true"` on the active one.

**5. Autoplay state machine.** One interval of 6000 ms advances (or wraps to the first slide). It is paused, not stopped, by three conditions kept as separate flags: pointer over the root (`mouseenter` / `mouseleave`), focus inside (`focusin` / `focusout` with `relatedTarget` check), tab hidden (`visibilitychange`). The timer runs only when no flag is set, and every resume restarts the full six seconds, so a slide is never skipped after a pause. Two defaults beyond the owner's list, recorded here: autoplay is not started at all when `matchMedia('(prefers-reduced-motion: reduce)')` matches, and it is stopped for good after a manual move (button, dot, `pointerdown` / `wheel` / `keydown` on the track), because a visitor who took control should not have the slide taken back. The programmatic scrolls of the script do not count as manual; a flag marks them. Alternative: a visible pause/play button (WCAG 2.2.2 pattern); not added by owner's decision, the pause on hover/focus and the permanent stop on manual use cover the same need.

**6. The poster is a fixed 400 × 500 picture from `image_style($image, '800x1000')`.** The `<img>` has `width="400" height="500"`, `class` with `block h-auto w-full` (the box is 400 px wide from `lg`, narrower on small screens, the proportions follow the attributes); the slide background is the existing light `#f3eadb` (shown only while loading and for transparent images). No `object-fit`, no CSS blur. Alternative: `object-contain` with a CSS blurred background layer; rejected, the image style already does this on the server and caches the result. Below `lg` the column is full width, so on a wide phone or tablet the slide would grow to the viewport; the track and the slide get `max-width: 400px` with `mx-auto`, so the picture stays 400 px and the proportions are the same everywhere.

**7. Slide content.** Picture (when the record has a poster), then the body: date pill (`date-pill--amber date-pill--lg`, text from `partials/event-dates`, not printed when empty), title, "Read more →". The whole slide is one link, like the removed large card. `.afisha-card*` classes are renamed `.afisha-slide*`; `.afisha-compact*` and the unused `.afisha-card__sticker` are deleted. The track gets `data-afisha-slider`; the classes of the controls (`.afisha-slider__btn`, `.afisha-slider__dots`, `.afisha-slider__dot`) live in `afisha.css`.

**8. Hook.** Only `perPage: 3` becomes `5` and the docblock ("The count is three: one large card…") is rewritten. `$homeAfisha` keeps its contract, so nothing else changes.

**9. Order with `events-list-cards`.** That change (complete, not archived) already modified the same requirement in `dcculture-theme` to add the date format. This delta repeats its sentence about the date format so it stays valid whichever change is archived first; archive `events-list-cards` first, so the capability `dcculture-events` that the sentence refers to exists in the main specs.

## Risks / Trade-offs

- [Rounded index on the `scroll` event is wrong during a momentum scroll that has not reached a snap point] → the dot follows the nearest slide, which is the slide that will win the snap; no visible problem with one slide per screen.
- [`inert` is missing in very old browsers] → `aria-hidden` stays as the fallback; Tab may then enter a hidden slide's link, which scrolls the slider to it, an acceptable degradation.
- [Smooth scrolling and the `scroll` handler in the same frame as autoplay] → the handler only reads the position; the manual-move flag is set by user events, not by `scroll`.
- [The 800 × 1000 variant is generated on the first request and cached] → the first visitor after a new poster waits for the resize (the same as every other image style).
- [A slider hides four of five events] → arrows, dots and the autoplay make them reachable; the link "all" to `/events` stays in the heading.

## Migration Plan

Theme-only change, no data and no schema. Deploy is a theme build (`npm run build`); rollback is reverting the commit. Existing generated image styles are not affected; `800x1000` files are created on demand.

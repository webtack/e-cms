# Design

## Context

See `proposal.md` for the motivation and `specs/dcculture-theme/spec.md` for the behavior. What the design builds on (read in the code, not assumed):

- A record page is rendered by the first existing candidate of `{type}/details--{id}`, `{type}/details`, `content/details` ([theme-templates.md](../../../docs/theme-templates.md#pages-list-and-details)); the theme has only `content/details`. The template receives `$node` (`created_at`, `views`), `$type`, `$fields` and `$values`, where `gallery` and `preview` are raw lists of `{uuid}.{ext}` (as `news/preview.blade.php` already reads `preview`).
- `news` fields: `preview` (image, multiple, required in the type), `gallery` (image, multiple, optional). Data: 3516 news, 3516 with a cover (21 of them with two values), 3308 with a gallery; gallery sizes 1 to 25+ (1 photo: 347, 2: 148, 13 or more: about 8 %); no cover repeats a gallery photo.
- Image styles ([image-styles.md](../../../docs/image-styles.md)): `{w}x{h}` gives an image of exactly that size with the margins filled by a blurred copy (no cropping); `{w}x` resizes by width only. Originals are already downscaled at upload to 1920 px (`files.image.max_width`), so the original is a sensible lightbox source. `PresentContext::file($uuid)` gives the pixel size of a file from rows the page has loaded (used by `news/preview.blade.php`).
- The theme has no `.richtext` styles (`themes/default` has them in its own CSS, which `dcculture` does not load), so the text of `body` is unstyled under Tailwind preflight.
- Existing modal conventions: `assets/js/video-modal.js` (a self-starting delegated click handler, a lazily created `<dialog>`, `html.modal-open` as the scroll lock, `closeOtherModals()`), and `assets/components/video-preview.css` which locks the page for every width while `.video-modal[open]`.
- Per project rules, theme frontend has no PHP or e2e tests; it is checked with `theme:check`, the build and a visual comparison.

## Goals / Non-Goals

**Goals:**
- A `news` page that matches the explored behavior, with the cover and the gallery as separate fields.
- Every photo reachable with and without the script; no layout shift while photos load.
- One lightbox script for the gallery (the cover is a plain picture); no external library or host.

**Non-Goals:**
- No change to the content model, the admin panel, the importer, or other themes; no merging of `preview` and `gallery`.
- No per-photo captions (the caption is the record title; the media description is not used).
- No share buttons, related news, tags or breadcrumbs redesign.
- No zoom, thumbnails strip, autoplay or fullscreen API in the lightbox.

## Decisions

### 1. A dedicated `news/details` template, fields printed by hand
`themes/dcculture/views/news/details.blade.php` extends the layout and prints the title, the meta line, the cover, `@field($values, 'body')`, `@field($values, 'gallery')` (the field template of decision 4) and then `@fields($values, $rest)` where `$rest` is `array_keys($fields)` minus `title`, `preview`, `body`, `gallery`. The body and the gallery go through `@field` so their field templates apply; only the cover is printed from the raw value, because it is a page element and not a field with a look of its own.
Alternative: a hook that adds variables to `content.details`. Rejected: the type already has its own candidate, and no PHP is needed.

### 2. The meta line is one partial used by the list card and the page
The date and views markup of `news/preview.blade.php` moves to `partials/news-meta.blade.php` (variables: `$node`) and both templates include it. The classes `news-meta*` already exist in `news.css`. This keeps the page and the card identical; the extraction is a move without visual change (the card is checked in the build comparison).
Alternative: copy the markup. Rejected: two places to keep in step.

### 3. Image sizes: width-only styles, cropping in CSS
- Tile: style `560x` (the tile is at most about 256 px wide in the 800 px column and about 170 px in two columns, so 560 is above 2x and also covers 1x of the wide case), shown with `aspect-ratio: 4/3; object-fit: cover`. A both-sides style (`560x420`) is rejected: it would put a blurred margin around portraits instead of cropping.
- Two photos: the same tile style as the grid, shown as 4:3 tiles in two columns at every width.
- One photo: style `1200x`, shown at the column width with `width`/`height` attributes from `PresentContext::file()` (the pixel size of the original, scaled to 1200 wide) so the box is reserved before the photo loads; without a known size the attributes are omitted.
- Cover: style `x350` (350 px high), the whole photo centred on a grey band the width of the column (decision of the owner at apply time, replacing the full-width cover and the old 467 px one).
- Lightbox: the original (the `url` of the item), the href of every link.
Every size is one style per role, so the image style files stay few.

### 4. The gallery is the field template `fields/field-image--gallery`
Following the convention of `field-file--files` and `field-youtube--youtube` ([theme-dcculture.md](../../../docs/theme-dcculture.md#video-card-and-modal)), a field of the type `image` with the key `gallery` is rendered by `views/fields/field-image--gallery.blade.php` (candidate `fields/field-{ftype}--{key}`), so any type or block that names such a field gets the same gallery, and `news/details` only calls `@field`. A type can still override it with `{type}/field-image--gallery`. The template receives `$items` (each with the public `url` of the original and the `description`), `$values` (the title is the caption), `$field` and `$node`. The pixel size for the size attributes is read with `PresentContext::file()` by the uuid in the file name of the item `url`; without a match the attributes are omitted.
The template counts `$items` and adds a modifier class: `news-gallery--single` (1), `news-gallery--pair` (2), `news-gallery--grid` (3 or more). Each photo is `<a href="{original}" data-lightbox="gallery" data-caption="{title}">` around an `<img>`; the cover is not a lightbox link. The `alt` of every image is the record title, as on the old site, because photos have no individual text.
The limit of eight is applied by the script, not by the server (decision 6), so the server markup always lists every photo.

### 5. The lightbox is one delegated script, like the video modal
`assets/js/photo-lightbox.js` is imported by `app.js` and starts itself with one delegated click handler for `a[data-lightbox]` (a click with a modifier key or a non-primary button is left to the browser). On the first use it creates one `<dialog class="photo-lightbox">` with the stage (`<img>`), a close button, previous and next buttons, the counter and the caption, and reuses it.
- **Group:** the links with the same `data-lightbox` value in the document, in document order, including the hidden ones (decision 6). The index of the clicked link is the start. A group of one (a gallery of one photo) hides the buttons and the counter.
- **Wrapping** and **keys:** left and right arrows change the photo and wrap; Escape and the backdrop click close (the native `dialog` behavior plus the same backdrop test as `video-modal.js`); focus goes back to the opener on `close`.
- **Swipe:** pointer events on the stage, `touch-action: pan-y`; a horizontal move of at least 50 px and larger than the vertical one changes the photo. A mouse drag is ignored (only `pointerType` touch and pen).
- **Loading:** the shown photo is set as `src` of the stage image; the next one is preloaded with a detached `Image`. The stage image has `max-width`/`max-height` of the viewport so the whole photo is always visible.
- **Modal state:** `closeOtherModals()` before `showModal()`, `html.modal-open` added and cleared on close using the same rule as `video-modal.js`; `video-preview.css` already keeps `html:has(.video-modal[open])` for every width, and the same selector is extended to `.photo-lightbox[open]`.
- **Accessibility:** the dialog has an accessible name, the buttons have `aria-label`s, the counter and the caption are in an `aria-live="polite"` region so a screen reader hears the photo change.
Alternative: Fancybox or another library. Rejected by the owner (no jQuery or heavy dependency; the theme has no vendoring convention) and unnecessary for this feature set.

### 6. "+N" is a progressive enhancement
With the script, `photo-lightbox.js` also finds every `.news-gallery--grid` with more than eight tiles on load, adds `is-collapsed` to it and writes the "+N" into the eighth tile (a `<span class="news-gallery__more">`, with `N = total - 8`). CSS hides the tiles after the eighth only under `.is-collapsed` (`display: none`; the links stay in the DOM so the group still has all photos). The "+N" tile is a normal link, so a click opens the eighth photo.
Without the script nothing is hidden and no "+N" exists, so every photo has a tile and the page stays correct. Alternative (a server-side limit): rejected because the hidden photos would be unreachable without the script.

### 7. Styles and text
`assets/components/news-details.css` (imported in `app.css` after `news.css`): the page column (the existing `.content-page`/`.content-details` width), the title, the cover, the gallery layouts and dimming, the lightbox, and the typography of the body under `.news-body` (paragraphs, links, lists, headings, images, tables and embedded media of the editor's HTML at phone width, no horizontal scroll). The breakpoint of the grid is Tailwind `md` (768 px); two columns below.

### Documentation
`docs/theme-dcculture.md` is the document that describes these components: its tree of `views/`, a "News details page" section (the order of parts, the field template, image styles), a "Photo gallery and lightbox" section (layouts, the "+N" rule, the markup contract `data-lightbox`, keys, no-script behavior) and the list of script files at the top of the doc. `docs/theme-templates.md` needs no change: it already documents the `{type}/details` candidate and the helpers.

## Risks / Trade-offs

- [Rich text of old news contains inline styles, tables or fixed-width images that break the narrow column] → `.news-body` caps images at 100% width, makes tables scroll inside their own box, and the visual comparison covers a long old news record and a short new one.
- [The moved meta partial changes the card by accident] → the markup is moved verbatim; the news list is compared before and after.
- [A gallery of 25 or more full-size originals is heavy to page through] → only the shown photo and the next one are loaded; thumbnails are 560 px wide and `loading="lazy"`.
- [Records with two covers (21 of 3516)] → only the first is shown, as the list card does; the second stays in the data. Showing both would need a decision about a second cover that has no use today.
- [Photo without a pixel size in the media row] → the `width`/`height` attributes are omitted and the layout shifts once while that photo loads; the grid tiles are unaffected because they use a fixed ratio.
- [`<dialog>` support] → the theme already depends on it for the video modal.

## Open Questions

None. The cover size was settled at apply time: `x350` on a grey band the width of the column.

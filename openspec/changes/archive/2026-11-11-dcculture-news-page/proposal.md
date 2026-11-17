# Proposal

## Why

The page of one news record (`/news/{slug}`) in the `dcculture` theme is rendered by the generic `content/details` template: the fields are printed one after another by the `default` field templates, so the page has no date, no view counter, an unstyled column of full-size photos for `gallery`, and no way to enlarge a photo. The legacy site showed the cover above the text and the photo gallery below it, with a lightbox; the news list already follows the new design, but its pages lead to a bare page. This change gives the news page its own markup and a usable photo gallery.

## What Changes

- A dedicated template `news/details` in the theme `dcculture`: the title, the date added and the views (the same meta line as the news list card), the cover (`preview`) above the text, the text (`body`), the photo gallery (`gallery`) after the text. The gallery is the field template `fields/field-image--gallery` (the convention of the theme for `files` and `youtube`: a field with that key looks the same wherever it appears), not a news-only partial. Other fields (`files`, `youtube`, …) keep their default templates and are printed after the gallery, as the generic template does.
- `preview` and `gallery` stay two separate fields (decision of the exploration: all 3516 news have a cover, 94 % have a gallery, a cover never repeats a gallery photo, and the list card depends on the cover). Nothing changes in the content model or the legacy import.
- The gallery is a grid of 4:3 thumbnails, three columns from `md` and two below, with no carousel and no autoplay (the legacy site used `owl-carousel` with autoplay and no controls). The number of photos selects the layout:
  - one photo: the full width of the text column, natural proportions;
  - two photos: the grid in two columns at every width (decision of the owner at apply time, instead of the legacy stack), 4:3 tiles;
  - three to eight photos: the grid;
  - more than eight: the first eight tiles; the eighth tile is dimmed and shows "+N" over its own photo (N = the total minus eight); activating it opens the lightbox on that eighth photo, and the rest follow with "next".
- A lightbox written for the theme (no jQuery, no owl-carousel, no Fancybox): opens on a thumbnail click or tap, shows the whole original photo, with previous/next buttons, a swipe gesture, keyboard arrows, Escape, a counter "3 / 12" and a caption (the title of the news). The cover does not open in the lightbox (decision of the owner at apply time): it is a plain picture. All photos of a group are reachable in the lightbox, including those without a tile behind "+N".
- No magnifier icon on thumbnails: a pointer cursor and a light dimming on hover; on touch screens a tap opens the photo.
- Without the script a thumbnail is a plain link to the original photo (opens in the same tab); the limit of eight tiles and the "+N" tile are applied by the script, so without it every photo has its own tile and stays reachable.

No PHP code, migration, content type or core file changes. Per the project rules, no tests are written for the public theme; it is checked with `php artisan theme:check`, the build and a visual comparison.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `dcculture-theme`: new requirements for the news details page (structure and meta line), the photo gallery layout (grid, one/two photos, "+N" after eight) and the photo lightbox (controls, groups, no-script behavior).

## Impact

- New theme files: `themes/dcculture/views/news/details.blade.php` and the field template `themes/dcculture/views/fields/field-image--gallery.blade.php`, `themes/dcculture/assets/components/news-details.css` (page, cover, gallery, lightbox styles), `themes/dcculture/assets/js/photo-lightbox.js`.
- Changed theme files: `themes/dcculture/assets/app.css` (import of the new component), `themes/dcculture/assets/app.js` (import of the lightbox, same self-starting delegated pattern as `video-modal.js`).
- Images: the existing `image_style()` helper with arbitrary sizes (`728x546`-like thumbnails for the grid, original for the lightbox); no new style definitions.
- Docs: `docs/theme-dcculture.md` gets the sections "News details page", "Photo gallery" and "Photo lightbox" and the script list is extended; `docs/README.md` needs no change (no file is added); `docs/e2e-testing.md` needs no change (no spec is added).
- Main spec: delta in `openspec/specs/dcculture-theme/spec.md` (archived with the change).
- No change to the admin panel, the backend, legacy import, or any other theme.

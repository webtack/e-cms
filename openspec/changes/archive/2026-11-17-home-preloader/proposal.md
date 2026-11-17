# Proposal

## Why

The old site (kultura-poltava.gov.ua) opens its home page with an animated preloader: a diamond grid of photos, a slogan, a growing divider and the site motto. The new `dcculture` theme has none, so the home page loses a recognisable part of the brand. The content already exists: the block `block-preloader` (type `preloader`) was imported with a title, a text and 10 images.

## What Changes

- The `dcculture` home page shows a full-screen preloader overlay, identical to the old one: same markup structure, sizes, 3-second staggered animations, slide-up exit (visible for 3000 ms, removed at 3300 ms).
- The data comes from the visible block `block-preloader`: `p_text` (first line), `title` (second line), `p_img` (up to 10 images, style `170x170`). When the block is hidden, missing or has no text, nothing is printed.
- The overlay is printed on the home page only (`/` and its language-prefixed variants), never on other pages.
- The image grid is hidden below 992 px, as on the old site.
- The overlay follows the accessibility modes: `gray` grayscales it, `contrast` and `negative` use the dark background `bg_symphony-dark.png` and the accent colour of the mode.
- New asset: `bg_symphony-dark.png`, copied from the old theme.

## Capabilities

### New Capabilities

### Modified Capabilities
- `dcculture-theme`: adds a requirement for the home-page preloader (data source, home-only, exit timing, mobile layout, accessibility modes).

## Impact

- `themes/dcculture`: block template `views/blocks/block-preloader.blade.php` (the markup), placed by `@block('block-preloader')` in `views/home.blade.php`, component `assets/components/preloader.css`, script `assets/js/preloader.js`, tokens in `assets/theme.css`, mode rules in `assets/components/a11y.css`, image `assets/images/bg_symphony-dark.png`.
- Docs: `docs/theme-dcculture.md` (new section).
- No schema, migration, admin, API or other theme change. No tests (theme frontend); checked with `theme:check`, the build and a visual comparison with the live site.

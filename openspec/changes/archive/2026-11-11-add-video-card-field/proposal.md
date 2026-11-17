# Proposal

## Why

The `video` page (`/video`) is rendered today by the generic `content/list` and `content/preview` templates: three columns of text cards with the default inline YouTube player. The old site showed a list of video thumbnails with a play icon in the centre and a title, and opened the video in a modal window. The `youtube` field already stores a local screenshot (`screen`), the video id and a title, so the card can be built without contacting YouTube. Making the card a template of the field (not of the `video` type) lets any type or block reuse it by naming a field `youtube`. The card falls back to the title of the record when the video has no title of its own, but a field template does not receive the values of its node today.

## What Changes

- **Core:** `FieldRenderer` passes the values of the node (`$values`, the ones the caller already read for `@field` / `@fields`) to every field template, so a template can use another field of the same record, such as `title`, without extra queries. The variable is `[]` when a field is rendered without them. This is additive: existing templates ignore it.
- New field template `themes/dcculture/views/fields/field-youtube--youtube.blade.php`, used by every field with the key `youtube`: a 16:9 card with the saved screenshot as background, a dimmed mask, a play icon in the centre and the title (`youtube.title`, else the `title` of the record, else the field name). Without a screenshot the card shows a neutral placeholder. The card is a link to the watch page (works without JavaScript, opens in a new tab) and carries the embed address for the modal.
- Fields of the type `youtube` with any other key keep the inline player of the `default` theme.
- The theme script `assets/app.js` gets one shared modal (`<dialog>`) that opens on a click on any video card: it creates a privacy-friendly `youtube-nocookie` iframe with autoplay on open and removes it on close; it closes with the close button, Escape and a click on the dimmed area, and returns focus to the card.
- New templates for the `video` type: `video/list.blade.php` (list heading and a two-column grid, one column on a phone) and `video/preview.blade.php` (only the fields, without the framed card and without a link to a details page).
- New CSS component `assets/components/video-preview.css` (and the modal styles), imported in `assets/app.css`; checked at 360, 768, 1024 and 1440 px.
- No tests are added for the theme frontend (project rule); verification of it is the theme check, the theme build and a visual check. The core change has PHP tests.
- Out of scope: configuring `list_fields`, the page size and the sorting of the `video` type (already done in the admin panel), pagination design, and changing `title` values of the existing records.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `theme-rendering`: a field template also receives `$values`, the values of its node.
- `dcculture-theme`: new requirements for the video card as the template of a field with the key `youtube`, the shared video modal, and the `video` list layout.

## Impact

- Code (core): `app/Theme/FieldRenderer.php` (`render()` gets the node values, `renderInScope()` and `renderManyInScope()` pass them); PHP tests in `tests/Feature/Theme/FieldRendererTest.php`. No migration and no new query.
- Code (theme): `themes/dcculture/views/fields/field-youtube--youtube.blade.php`, `themes/dcculture/views/video/list.blade.php`, `themes/dcculture/views/video/preview.blade.php`, `themes/dcculture/assets/components/video-preview.css`, `themes/dcculture/assets/app.css`, `themes/dcculture/assets/app.js`. 
- Docs updated: `docs/theme-dcculture.md` (templates tree, the video card and modal, the convention "a field with the key `youtube` is shown as a card", the script contract) and `docs/theme-templates.md` (the new `$values` variable of a field template).
- Specs: deltas of `openspec/specs/theme-rendering/spec.md` and `openspec/specs/dcculture-theme/spec.md`.
- Data: the card title is the `title` stored in the `youtube` value, which differs from the node `title` in the existing records (for example "Гімн Полтави" and "Гімн ПОЛТАВИ"); when the stored title is empty the record title is used. The editors check the six existing records in the admin panel.
- Every page or block that shows a field with the key `youtube` (including the details of a `video` record) now shows the card instead of the inline player.

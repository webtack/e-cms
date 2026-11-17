# Design

## Context

The `dcculture` theme overrides only `layout`, `home` and the generic `content/*` templates; field templates come from `default` (`themes/default/views/fields/field-youtube.blade.php`, an inline `iframe`). `FieldRenderer` picks the first existing candidate, the type directory `{t}/field-*` before `fields/field-*`, and `fields/field-youtube--{key}` before `fields/field-youtube`, across the active theme and then `default` (`docs/theme-templates.md`). A field template receives only `$items`, `$field`, `$key`, `$ftype`, `$multiple`, `$node` and `$type`; the node's `title` value is not available there (the model holds no values). The callers do have them: `@field($values, 'key')` and `@fields($values)` receive the node's values, and `FieldRenderer::render()` is the single place that builds the template variables. `YoutubeType::present()` returns `id`, `url` (watch), `embed` (`youtube-nocookie`), `title`, `description` and `screen` (a local URL or `null`). The theme script `assets/app.js` already has a modal helper (`bindModal`) for the menu and search, and the theme documents that it requests nothing from external hosts. The `video` type has the field `youtube` (`hide_label`), six records, and its `list_fields` are already set in the admin panel. See proposal.md for motivation.

## Goals / Non-Goals

**Goals:**
- One card template that any type or block reuses by naming a field `youtube`.
- A video modal that sends no request to YouTube until a visitor opens a video.
- A card that still works (a link to the watch page) without JavaScript.

**Non-Goals:**
- No migration, no new query, no change to the `default` theme templates, and no change to any other field template's behavior.
- No details page design for `video` records, no pagination design.
- No automatic correction of the stored titles.
- No focus trap, matching the menu and search modals of this theme.

## Decisions

**1. The card is the template `fields/field-youtube--youtube` of the `dcculture` theme.** The key in the name ties it to fields called `youtube`, so other `youtube` fields (for example `promo_video`) keep the inline player, and a type can still override it with `{t}/field-youtube--youtube`. Alternatives: `fields/field-youtube` (changes every youtube field of every type, including big in-page players), and `video/field-youtube` (works only for one type and defeats reuse).

**2. Title is `$item['title']`, else the record title `$values['title']`, else `$field->name`.** The node title is not an attribute of the model, so the field template is given the node values: `FieldRenderer::render()` takes an optional `array $values = []` (the values of the node by field key) and passes it to the template as `$values`; `renderInScope()` and `renderManyInScope()` pass the `$values` they already receive. No query is added: the list, details and block code have read these values before. Direct callers that pass nothing get `[]`, so existing calls keep working. The template uses `$values['title']` only when it is a non-empty string. Alternatives: a theme hook that reads the title per card (one query per card and a template that depends on a hook), and joining the title into the `youtube` item (couples a field type to another field). The stored `title` of the `youtube` value still wins, so editors can keep a video-specific title; the six existing records differ today (for example "Гімн Полтави" and "Гімн ПОЛТАВИ"), which is a data check, not code.

**3. The card is an `<a>` with `href` = the watch URL, `target="_blank"` and `rel="noopener noreferrer"`, plus `data-video-embed` = the `embed` URL and `data-video-title`.** Without the script it is an ordinary link. The picture is a CSS background on the 16:9 element (`style="background-image:url(...)"` from `$item['screen']`; the helper `image_style` is optional and only for a resized copy). Without `screen`, a CSS class gives the placeholder. YouTube thumbnails (`img.youtube.com`) are not used, because the theme and the field loading must not contact external hosts. The play icon is the theme's `images/icons/play.svg` or a CSS-drawn one, so no image request is added for it.

**4. One shared native `<dialog>` created by `app.js` on first use.** A delegated click handler on `document` handles `a[data-video-embed]`, so cards from lists, blocks or later-added markup work with no per-card wiring. On click it calls `preventDefault()`, builds the iframe with `src = embed + '?autoplay=1&rel=0'` (query appended with the right separator), `allow="autoplay; encrypted-media; picture-in-picture"`, `allowfullscreen` and `referrerpolicy="strict-origin-when-cross-origin"`, appends it to the 16:9 box, and calls `showModal()`. On `close` it empties the box (the sound stops) and focuses the opener. A click on the `<dialog>` element itself (the backdrop) and the close button call `close()`; Escape is native. `html.modal-open` (the existing scroll lock) is toggled so the page behind does not scroll. The embed URL is read from `data-video-embed` and is accepted only if it starts with `https://www.youtube-nocookie.com/embed/`, so a hand-edited attribute cannot load another host. Alternatives: a library such as VenoBox (a new dependency and foreign styling), and reusing `bindModal` (it is built for fixed markup of the menu and search and cannot create the player on demand).

**5. Two small type templates for the layout only.** `video/list.blade.php` copies `content/list` with a two-column grid class (`1` column below `md`); `video/preview.blade.php` is just `@fields($values)`, so no `content-card` frame and no title link to a details page appear (the field prints its own title). The card and modal styles live in `components/video-preview.css` (one file per component, as the theme documents) and are imported in `app.css`. `@source` already covers `themes/dcculture/views`, so the new templates are scanned.

**6. Documentation and tests.** The core change has PHP tests in `tests/Feature/Theme/FieldRendererTest.php` and is run with the neighbouring theme tests (see tasks). `docs/theme-templates.md` lists `$values` among the field template variables. `docs/theme-dcculture.md` gets the new files in the templates tree, a section "Video card and modal" with the convention "a field with the key `youtube` is a card" and the markup contract of the script (also in the header comment of `app.js`), and the script description updated.

## Risks / Trade-offs

- [Every field with the key `youtube` becomes a card, including on a details page, where an inline player might be wanted] → a type can override with `{t}/field-youtube--youtube`; a different key keeps the player. The convention is documented.
- [The `youtube.title` differs from the node title in existing records] → listed in tasks as an admin data check; the card falls back to the field name.
- [Records without a `screen`] → neutral placeholder, never a call to YouTube.
- [A new template variable `$values` could be large for a card] → it is the array the caller already holds (a list card reads only the title and the list fields); nothing is copied or queried.
- [`<dialog>` and autoplay depend on the browser] → `showModal()` is supported by all current browsers; if the script fails, the card is still a link to the watch page. Autoplay starts because the player is created by a user click.
- [No focus trap] → same limitation as the other theme modals; `<dialog>` `showModal()` makes the rest of the page inert, so focus does not leave the dialog.
- [Third-party content in an iframe] → only the `youtube-nocookie` embed prefix is accepted by the script; the URL comes from `present()` (built from the validated 11-character id).

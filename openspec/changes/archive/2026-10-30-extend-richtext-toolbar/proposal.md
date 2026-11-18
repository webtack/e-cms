# Proposal

## Why

The editor of the field `richtext` (see the archived change `add-richtext-field-type`) has a minimum: a heading, bold, italic, lists, a link. Content editors lack the usual capabilities of a text editor: underline, strikethrough, alignment, colours, clearing the formatting, inserting videos and frames, emoji and the full-screen mode.

## What Changes

- The toolbar of `RichTextEditor` gets actions: underline, strikethrough, clearing the formatting (keeps bold, italic, underline, links, headings; removes the rest), alignment (left, centre, right, justify), text colour, background colour, inserting a YouTube video, inserting a frame (`iframe`), emoji, expanding to full screen.
- The server `HtmlSanitizer` is extended with the allow-list: `u`, `s` (already allowed), `span` with a safe `style` (only `color` and `background-color`), block tags with `style="text-align: …"` and `iframe` only from the allowed hosts. Everything else, as before, is removed.
- The hosts from which frames are allowed are set in the configuration (`config/content.php`), by default — videos (YouTube, Vimeo), Google Drive and Google Docs/Sheets/Slides/Forms (`drive.google.com`, `docs.google.com`), Google Maps (`google.com/maps/embed`) and social networks (Facebook plugins, Instagram, X/Twitter, TikTok, Telegram). An entry of the list is a host or a host with a path prefix (for example `www.facebook.com/plugins/`), so as not to open the whole site.
- New frontend dependencies: Tiptap extensions for alignment, colour, highlight, YouTube; there are no new PHP packages.

**Assumptions (decisions not given in the request; recorded here and in `design.md`):**
- "Inserting a frame" — an arbitrary `https` frame by address or from a ready-made `<iframe>` code, but only from the hosts (and path prefixes) of the allowed list, which includes Google Drive and social networks; a frame always gets `sandbox`. The list of hosts can be changed in the configuration.
- "Emoji" — choosing Unicode emoji from a panel, the character is inserted (without images and new dependencies).
- The colour is chosen from a palette and a `#rrggbb` field; it is stored as `#rrggbb`.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-values`: the allow-list of cleaning `richtext` is extended (alignment, colour, background, frames from the allowed hosts).
- `admin-content-nodes`: the toolbar of the editor `richtext` gets new actions, the full-screen mode.

## Impact

- Code: `resources/js/Components/Admin/ContentNodes/RichTextEditor.tsx` (and, if needed, new files next to it), `resources/css/app.css`, `app/Content/HtmlSanitizer.php` (+ new attribute sanitizers `style` and `src` in `app/Content/`), `config/content.php`, `lang/en.json`, `lang/uk.json`, `package.json`.
- Tests: `tests/Feature/Content/HtmlSanitizerTest.php`, `tests/Feature/Content/RichTextFieldTypeTest.php`, `e2e/admin-content-nodes.spec.js`.
- Documentation: update `docs/content-values.md` (the allow-list, the hosts of frames, `style`), `docs/admin-content-nodes.md` (the toolbar), `docs/e2e-testing.md` (the new scenarios in the section `admin-content-nodes`; the spec tables without new rows).

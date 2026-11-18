# Design

## Context

The editor is `Components/Admin/ContentNodes/RichTextEditor.tsx` (Tiptap 3, `StarterKit` without `strike`, `code`, `codeBlock`, `horizontalRule`; headings h2–h4; a toolbar of `ToolButton` and `LinkButton` on a `Popover`). The server cleans the value in `RichTextType::prepare()` through `App\Content\HtmlSanitizer` (`symfony/html-sanitizer`): already allowed are `u`, `s`, `strong`, `em`, `p`, `br`, `h2`–`h4`, `blockquote`, `ul`, `ol`, `li`, `a[href]`; `span` is currently unwrapped (the tag is removed, the text stays). The attributes `style` and the tag `iframe` are forbidden. `symfony/html-sanitizer` has `AttributeSanitizerInterface` (`withAttributeSanitizer`), so custom checks of attribute values are added without new packages. The motivation is in `proposal.md`, the requirements are in `specs/`.

## Goals / Non-Goals

**Goals:**
- All actions of the request are available in the toolbar, and the saved HTML is restored by the editor without losses.
- The server allow-list remains the single authoritative layer: safe `style` and `iframe` pass, the rest does not.
- A minimum of new dependencies, no changes in the data model and no migrations.

**Non-Goals:**
- Social network embed scripts (`<script async src=…>`, the Twitter/Instagram `blockquote`): only the variants with `iframe` are supported.
- Tables, images, code blocks, arbitrary fonts and font sizes.
- Rendering on the site (the type gives the sanitized HTML).
- Settings of the toolbar composition through the `settings` of the field.
- Uploading videos from the media library.

## Decisions

**1. Tiptap extensions.** `@tiptap/extension-text-align` (types `heading`, `paragraph`; values `left|center|right|justify`), `@tiptap/extension-text-style` (contains `Color` and `BackgroundColor` in Tiptap 3; if the installed version does not have them — `@tiptap/extension-color` and `@tiptap/extension-highlight` with `multicolor`), `@tiptap/extension-youtube`. Underline and Strike come from `StarterKit` (in v3 Underline is already included); in the configuration `strike: false` is removed. The versions are confirmed by `npm install --legacy-peer-deps` and `npm run build` in the first task (as in the archived change). Check the documentation of the extensions through context7.

**2. Colour and background as `span[style]`.** Tiptap serializes the colour as `<span style="color: #rrggbb">`, the background as `background-color`. The colour is chosen from a built-in palette (buttons) and a field `#rrggbb`; the value is normalized to `#rrggbb` on the client. Alternative — classes instead of `style` — does not work for an arbitrary colour. The server no longer unwraps `span`, but allows it with `style`.

**3. Cleaning `style` on the server: `StyleAttributeSanitizer`.** It implements `AttributeSanitizerInterface`, is connected to `HtmlSanitizerConfig::withAttributeSanitizer()`, declares `getSupportedElements()` = `span`, `p`, `h2`–`h4`, `blockquote`, `li` and `getSupportedAttributes()` = `style`. The value is parsed into declarations; `text-align` (`left|center|right|justify`), `color`, `background-color` remain with a value by the regular expression `#rgb`, `#rrggbb`, `rgb(r, g, b)`; the rest is dropped; an empty result → `null` (the attribute is removed). The lists (`ul`/`ol`) and `strong`/`em` do not get `style`. Why not `allowAttribute('style')` without checks: that would open `url()`, `position` etc. Alternative — keywords `rgb`/colour names: colour names are rejected, so as not to maintain a dictionary.

**4. Frames: `IframeSrcSanitizer` and the host configuration.** `allowElement('iframe', ['src','width','height','title','allowfullscreen','allow'])`; the sanitizer of `src` keeps only `https` and a host from the list `config('content.richtext.iframe_hosts')` (by default: `www.youtube-nocookie.com/`, `www.youtube.com/embed/`, `player.vimeo.com/`, `drive.google.com/`, `docs.google.com/`, `www.google.com/maps/embed`, `www.facebook.com/plugins/`, `www.instagram.com/`, `platform.twitter.com/`, `www.tiktok.com/embed/`, `t.me/`). An entry is a host or a host with a path prefix; the host comparison is exact (without suffix matches, so that `youtube.com.evil.example` does not pass), the path must start with the prefix. Prefixes are needed for large sites (for example `facebook.com`), where only plugins are framed. One parsing (`parseIframeInput` on the client, `IframeSrcSanitizer` on the server) interprets the entries of the list in the same way. Google Forms are embedded by the address `docs.google.com/forms/d/e/ID/viewform?embedded=true`, which is already covered by the entry `docs.google.com/`; a separate entry is not needed (it can be narrowed to `docs.google.com/forms/` in the configuration, if Docs/Sheets are not needed). Google Drive and social networks need scripts and cookies of their own origin, so the `sandbox` stays `allow-scripts allow-same-origin allow-presentation allow-popups`; whether this is enough for each network is checked manually in task 5.1 (the automatic tests do not go to the network). A tag `iframe` without an acceptable `src` must disappear entirely, not remain empty: `HtmlSanitizer::clean()` after the cleaning removes an `iframe` without `src` (a pass of `DOMDocument` over the result); the exact way is confirmed by the tests of the scenarios "foreign host / not https" in task 2.4. `sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"` and `loading="lazy"` are set through `forceAttribute('iframe', ...)`. Alternative — allow any `https` — rejected as a vector for phishing frames in the admin and on the site.

`allow` (Permissions Policy) is cleaned by a separate `IframeAllowSanitizer`: the value is split by `;`, only bare names from the whitelist remain `accelerometer`, `autoplay`, `clipboard-write`, `encrypted-media`, `fullscreen`, `gyroscope`, `picture-in-picture`, `web-share` (without an origin), the rest (in particular `camera`, `microphone`, `geolocation`, `payment`) is dropped; an empty result → the attribute is removed. The list is stored in `config/content.php` (`richtext.iframe_allow`).

**5. Inserting YouTube videos.** A dialog (`Popover`) with an address field; `youtu.be/ID`, `youtube.com/watch?v=ID`, `youtube.com/embed/ID`, `shorts/ID` are parsed by the function `parseYoutubeId()`; the YouTube extension is inserted with `nocookie: true`, which gives `youtube-nocookie.com/embed/ID`. An invalid address — a message next to the field without insertion.

**6. Inserting a frame.** A dialog with one field that accepts both a link `https://…` and a whole `<iframe …></iframe>` code (also with text wrapping around it; the first `iframe` is taken). From the code `src`, `width`, `height`, `title`, `allowfullscreen`, `allow` are carried over (numeric `width`/`height` and the value `100%`); the rest of the attributes (`style`, `frameborder`, `onload` etc.) are dropped, `sandbox` and `loading` are set by the server. For a bare link `width`/`height` are not set, the editor shows the frame full width with a 16:9 ratio. The code is parsed by `DOMParser` on the client; the server cleans the value anyway by itself. The client checks the host by the same list: the list is passed in the shared Inertia props (`richtext.iframeHosts`) from the configuration, so that the client and the server do not diverge; a disallowed host — an error next to the field. For Tiptap a custom node `Iframe` is needed (`Node.create`, `atom`, `group: 'block'`, attributes `src`, `width`, `height`, `title`); the YouTube extension inserts its own node, so the parsing of an `iframe` from YouTube when loading the saved HTML must be restored by the YouTube extension itself, and other hosts — by the node `Iframe` (the parsing priority: Youtube checks the host).

**7. Clearing the formatting.** The command `clearFormatting()`: `unsetAllMarks()` followed by restoring the allowed marks does not work (it loses B/I/U/links). Therefore: for each mark except `bold`, `italic`, `underline`, `link` — `unsetMark`/`unsetColor`/`unsetBackgroundColor`/`unsetStrike`; `unsetTextAlign()` for the blocks in the selection; the heading nodes are not changed (not `clearNodes()`). It is implemented as one chain in `RichTextEditor`.

**8. Full-screen mode — CSS + state.** The state `fullscreen` adds to the wrapper `fixed inset-0 z-50 flex flex-col bg-background`, the editing area takes the free height (`flex-1 overflow-auto`); `Escape` turns it off (a listener on the document only in the mode). The editor stays in the same place of the tree (without a portal), so the state and the cursor are not lost; during the mode the scrolling of `body` is blocked. The popups (`Popover`) have a z-index higher than the wrapper. Alternative — the browser's Fullscreen API: inconsistent behaviour, does not expand the popups.

**9. Emoji.** Its own panel (`Popover` with a grid of ~60–80 popular emoji in groups) and `editor.chain().focus().insertContent(emoji).run()`. An emoji package is not added: the server allow-list changes nothing, because it is plain text.

**10. Code structure.** `RichTextEditor.tsx` grows, so the new dialogs and helper functions are moved next to it: `RichTextToolbar.tsx` (the toolbar), `richTextExtensions.ts` (the composition of the extensions and the node `Iframe`), `richTextUtils.ts` (`parseYoutubeId`, `parseIframeInput`, `normalizeHexColor`), `EmojiPicker.tsx`. Public functions with JSDoc in English and `@example`.

## Risks / Trade-offs

- [Social networks and Google may not display the embed because of their own `X-Frame-Options`/CSP, or they may need embed addresses different from the one copied from the browser] → it is beyond our control; the dialog hints the format of the embed address (`/preview` for Drive, the "Embed" code for social networks), and the client accepts the `<iframe>` code.

- [XSS through `style` and `iframe`] → a single layer on the server, strict regular expressions for values, exact host comparison, `sandbox`; tests: `url()`, `expression()`, `javascript:`, `data:`, `http`, `youtube.com.evil.example`, `//host`.
- [`allow-same-origin` together with `allow-scripts` in `sandbox` weakens the isolation, but is needed by the players of YouTube/Vimeo] → only trusted hosts from the configuration are allowed.
- [Parsing of a saved `iframe` by the wrong extension] → two nodes with clear `parseHTML` rules and an e2e check of restoring after a reload.
- [Justified alignment and `style` on `li`] → `text-align` is allowed only where Tiptap writes it; the server accepts a wider set safely.
- [The client and server host checks diverge] → the single source is `config/content.php`, passed to the client through the shared props.
- [The colour of foreign HTML when pasting from the clipboard] → `rgb(...)` is allowed on the server; the client normalizes to `#rrggbb` only its own insertions.
- [Growth of the bundle from the extensions] → the extensions are small; dynamic import is not planned if needed.

## Migration Plan

There are no migrations: the saved values remain valid (the new rules only allow more). Dependencies: `npm install --legacy-peer-deps @tiptap/extension-text-align @tiptap/extension-text-style @tiptap/extension-youtube` on the host, then `npm run build`. Rollback: remove the new buttons and the dependencies; the saved HTML with `style`/`iframe` after the rollback of the server code is cleaned at the next save.

## Documentation

`docs/content-values.md` (the allow-list, `style`, the frame hosts), `docs/admin-content-nodes.md` (the toolbar, the full-screen mode), `docs/e2e-testing.md` (the scenarios of the spec `admin-content-nodes`).

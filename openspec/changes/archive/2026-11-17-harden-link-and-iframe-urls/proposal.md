## Why

A second pass of the auth audit over the HTML sanitizer found no script injection (script, event handlers, `javascript:`/`data:` links, SVG and mXSS payloads, `srcdoc`, unsafe `style` all end up removed, and stored values are cleaned on write). It did find three address-validation gaps, confirmed by a throwaway probe, where the code is weaker than the rules the project already applies elsewhere:

1. A frame address in rich text is checked by host and path prefix only, so `https://www.youtube.com/embed/../account` (and the `%2e%2e` form) passes. Browsers resolve the dot segments, so the prefix stops restricting the path and any page of an allowed host can be embedded.
2. A link in rich text may start with `//` (`<a href="//evil.example/x">`), an external address that looks relative. The `url` field type and the menu item address already reject `//`.
3. The menu item address accepts `/\evil.example`. Browsers read a backslash as a slash, so it becomes `//evil.example`, an external link that passes the "relative address" rule. The `url` field type already rejects a backslash.

None is script execution; they let a user with edit rights place links or embedded pages the owner did not intend to allow.

## What Changes

- Frame addresses in rich text are refused when the path has a dot segment (`.` or `..`), a backslash, or an encoded dot, slash or backslash (`%2e`, `%2f`, `%5c`, any case). The frame is then removed as for any other disallowed address.
- Links in rich text whose address starts with `//` or contains a backslash lose the `href` (the link text stays), the same rule the `url` field type uses.
- The menu item address is refused when it contains a backslash.
- Existing valid addresses (YouTube, Vimeo, Google Drive and Docs forms, Google Maps embeds, Facebook, Instagram, X, TikTok frames; relative, `#`, `?`, `mailto:`, `tel:` and `http(s)` links) keep working.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `content-values`: adds requirements for link and frame addresses in rich text (no protocol-relative or backslash links, no dot segments or encoded separators in frame paths).
- `menus`: the requirement "Item address" also rejects an address with a backslash.

## Impact

- Code: `app/Content/IframeSrcSanitizer.php`, a new link address sanitizer for `a[href]` registered in `app/Content/HtmlSanitizer.php`, and `app/Menus/MenuUrl.php`.
- Documentation (English): `docs/content-values.md` (the `iframe` and link rules in the rich text section), `docs/menus.md` (the item address rule).
- Tests: `tests/Feature/Content/HtmlSanitizerTest.php`, `tests/Feature/Menus/MenuUrlTest.php`, and one HTTP-level case in `tests/Feature/Menus/MenuItemAdminTest.php`.
- No migrations, no new dependencies. Already saved values are not rewritten; the sanitizer also runs when a value is presented, so a stored link or frame that breaks the new rules stops being output on the public site.

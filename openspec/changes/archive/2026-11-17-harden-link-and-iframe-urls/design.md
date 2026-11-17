## Context

See proposal.md - Why. Current state, read from the code and a probe:

- `HtmlSanitizer` is built on the Symfony sanitizer with an element and attribute allow-list. Custom attribute sanitizers (`IframeSrcSanitizer`, `ImageSrcSanitizer`, `StyleAttributeSanitizer`, ...) run for their attribute, and `ImageSrcSanitizer` shows the pattern for an address that the library's own URL check would otherwise pass or drop. Links use the library's own check with `allowLinkSchemes([http, https, mailto, tel])` and `allowRelativeLinks()`; the probe showed that this keeps `//evil.example/x` and already drops `/\evil.example` and `javascript:` forms.
- `IframeSrcSanitizer` accepts an `https` address with no userinfo, port 443 or none, host equal to an entry of `richtext.iframe_hosts` and a path starting with the entry's prefix; it never looks inside the path.
- `UrlType` (the `url` field) already rejects `//` and a backslash; `MenuUrl::isValid` rejects `//` but not a backslash. The menu address is validated in `StoreMenuItemRequest`, in `MenuItem` and again when the tree is rendered (`MenuRenderer`), so one fix in `MenuUrl` covers all three.
- The sanitizer runs when a value is written and again when it is presented (`RichTextType`), so a stricter rule also hides an already stored link or frame from the public site without rewriting data.

Documentation describing these components: `docs/content-values.md` (rich text), `docs/menus.md`.

## Goals / Non-Goals

**Goals:**
- The address rules of rich text and menu items are the same as the project's own `url` field rules.
- Nothing that works today with an ordinary address stops working.

**Non-Goals:**
- Changing the list of allowed frame hosts or narrowing a host to a prefix (an owner decision about trust, not a code fix).
- The raw `{!! $values['body'] !!}` of the `home-about` block template, which relies on the field being `richtext` (theme code is not tested by project rule; it stays a note for the theme owner).
- Rewriting stored values.

## Decisions

**1. Frame path: reject, do not normalize.**
`IframeSrcSanitizer` rejects the address when the path has a segment `.` or `..`, a backslash, or a percent-encoded `.`, `/` or `\` (`%2e`, `%2f`, `%5c`, case-insensitive; checked on the raw path, before any decoding). Alternative: resolve dot segments and then test the prefix (rejected: the browser, the host and PHP may normalize differently, and a rejected address is simpler to reason about than a rewritten one). No legitimate frame address of the allowed hosts has any of these in its path.

**2. Links: a small sanitizer for `a[href]`.**
A new attribute sanitizer registered in `HtmlSanitizer` (next to the others) returns `null` for an `href` that starts with `//` or contains a backslash, and returns the value unchanged otherwise, leaving the scheme check to the library. The link keeps its text. Alternative: turn `allowRelativeLinks` off and list allowed relative forms (rejected: that also drops `/about`, `#top`, `?page=2`, which the editor's link dialog produces).

**3. `MenuUrl`: one more rule.**
`MenuUrl::isValid` returns false when the address contains a backslash, before the form checks, the same as `UrlType`. The percent-encoded `%5C` is not treated by browsers as a separator and stays valid.

## Risks / Trade-offs

- [A stored value that breaks a new rule loses its link or frame on the site] → Intended; the rules only affect crafted or mistyped addresses. Stored data was not scanned, so a task-free check before release is optional: search the rich text values for `href="//`, a backslash in `href`, and `..` or `%2e` in `iframe` addresses.
- [A frame of an allowed host with `%2f` in the query is unaffected] → Only the path is checked, as before; the query is the host's own data.
- [`..` rejected in a path that a host really uses] → None of the configured hosts uses it; a host that did would need an explicit decision.

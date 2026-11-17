# Proposal

## Why

Some records of the `dcculture` site exist only to point somewhere else: a document (a `file` field) or an external page (a `url` field). Opening such a record's own page shows an empty page the visitor has to click through. The visitor should land on the file or the link directly.

## What Changes

- A page of a node (the details of a `content` record at `/{type}/{slug}` and a `page` at `/{slug}`) redirects to the node's single file or link when that is the only thing the node shows.
- The redirect applies only when exactly one non-blank value exists among the visible fields of the node, and that value belongs to a `file` or `url` field (a multiple field with one non-blank item counts). Fields hidden from the page by the type's public display settings do not take part.
- The view of the node is counted before the redirect, so the visit is not lost from the `views` counter.
- The redirect can be switched off for chosen content types (by type key) and chosen nodes (by uuid) through two arrays in the theme's hook file; both empty means it applies everywhere.
- Cards in lists keep linking to the node's own address, so a click is counted and then redirected.
- No changes to the core, the admin panel, other themes or the database.

## Capabilities

### New Capabilities

### Modified Capabilities
- `dcculture-theme`: a new requirement for the redirect of a node page with a single file or link value.

## Impact

- Code: `themes/dcculture/hooks/single-value-redirect.php` (one shared `preprocess` callback for the hooks `content.details` and `page`).
- Docs: `docs/theme-dcculture.md` (a new section describing the redirect and its rule); `docs/site-themes.md` only if the hooks section needs a note that a `preprocess` callback may end the request with a redirect.
- Tests: none for the theme code (project rule for theme frontend); verified with `theme:check` and manual checks.

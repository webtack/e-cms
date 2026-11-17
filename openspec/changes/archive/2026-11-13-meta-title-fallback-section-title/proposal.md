# Proposal

## Why

A `content` type has two titles entered by the administrator: the "Section title" (`section_title`, the "Display" tab) and the "Meta title" (`meta_title`, the "SEO" tab). Today the `<title>` of the type's list page (`/news`) is the type's `meta_title`, and when it is empty the page falls back to the site name, even though the type already has a meaningful public title. The administrator has to type the same text twice. The Section title should be used as the meta title when no meta title is set.

## What Changes

- In the title chain of `App\Theme\PageMeta`, the type's own title source (step 4) becomes: the type's `meta_title`, then the type's `section_title`. An empty value counts as absent, as before.
- The Section title is read the same way as the public title of the type (`TypeSection`): in the reading language, then in the base language. The type's administrative `name` is **not** used as a title source, so a type with neither value still falls back to the site name.
- Only the type's own source changes. The node's `meta_title`, the field chosen in "Meta data", and the node's `title` keep their priority, so a node with a `title` is unaffected.
- Only `content` types hold `section_title`; `page` and `block` types are unchanged.

## Capabilities

### New Capabilities

### Modified Capabilities
- `public-site`: the requirement "Page meta data: node, type fields, site settings" — the type-level title source falls back from `meta_title` to `section_title`.

## Impact

- Code: `app/Theme/PageMeta.php` (title chain), `app/Theme/TypeSection.php` (a method returning the Section title without the name fallback).
- Tests: `tests/Feature/Public/PageMetaTest.php` (new cases); `tests/Feature/Theme` is checked for `TypeSection` consumers.
- Docs updated: `docs/public-site.md` (page meta data table and the "Title" rule), `docs/theme-templates.md` (the `$meta` description), and the docblock of `PageMeta`.
- No migrations, no admin UI changes, no theme changes.

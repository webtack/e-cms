# Proposal

## Why

The admin pages of a type repeat a page-specific title ("Nodes of “News”", "Edit content type", "Display") below the section menu, so the name of the type being managed is only visible on some tabs and is hidden on others. The public list page of a `content` type cannot be opened from the admin panel at all, although nodes already have a "Preview" action. A long node slug stretches the node table.

## What Changes

- `ContentTypeNav` shows the name of the type (`ContentType::name`, the "Name" of the Settings tab) as a heading **above** the section tabs, on every page that renders the menu. The per-page titles of the type pages (nodes list, fields list, type edit, "Display", "SEO") are removed. The favorites star of the node list moves next to the new heading.
- `ContentTypeNav` shows a "Preview" link as a tab right after the last tab. It opens the public list page of the type (`/{type slug}`) in a new tab; it exists only for kind `content` with a non-empty slug. The "⋮" row menu of the content types list gets the same "Preview" item (same condition).
- The type slug is passed to the menu by every controller that renders it.
- The service `slug` column of the node table and the `slug` column of the types table are cut into one line with an ellipsis (`width` plus the full value in `title`).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-types`: the section menu gets the type heading and the "Preview" link; the row menu gets "Preview"; the slug column of the types table is width-limited.
- `admin-content-nodes`: the node list heading is the type name above the menu; the slug column is width-limited.
- `admin-content-fields`: the fields page heading is the type name above the menu.

## Impact

- Code: `resources/js/Components/Admin/ContentTypes/ContentTypeNav.tsx`, `ContentTypeRowActions.tsx`; pages `Pages/Admin/ContentTypes/{Edit,Display,Seo,Index}.tsx`, `Pages/Admin/ContentFields/{Index,Create,Edit}.tsx`, `Pages/Admin/ContentNodes/{Index,Create,Edit}.tsx`; controllers `ContentTypeController`, `ContentFieldController`, `ContentNodeController` (add `slug` to the `type` prop); new translation keys in the Ukrainian locale file if any are added.
- Tests: Feature tests of the three controllers for the `type.slug` prop; e2e specs `admin-content-types`, `admin-content-fields`, `admin-content-nodes` (headings, favorites star, Preview), plus `admin-page-types`/`admin-block-types` where they assert the removed headings.
- Docs: `docs/admin-content-types.md` (section menu, row menu, columns), `docs/admin-content-nodes.md`, `docs/admin-content-fields.md`, `docs/admin-page-types.md`, `docs/admin-block-types.md` where they describe the headings, and `docs/e2e-testing.md` if the shared-code table changes.

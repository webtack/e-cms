# Proposal

## Why

The site needs navigation managed by the editor: a menu in the header, the footer, the side panel. Right now there is nowhere to store it, and the public addresses are not yet bound to the records of the content. Menus are needed with a tree of items that can be hidden, shown and unpublished on a schedule, and also a tree of visible items ready to be read by the front-end.

## What Changes

- A new domain model of menus: a **menu** with a unique immutable `key`, a name, a description and settings (an optional `max_depth`), and **menu items** in the form of a tree with one parent (`uuid`, `parent_uuid`, `weight`).
- An item has an optional `url` (a string): relative (`/about`, `#anchor`, `?query`) or absolute (`http(s)://`, `mailto:`, `tel:`); `NULL` means a heading item of a group without a link. Also `target` (`_self` or `_blank`), `css_class` and one image (a file of the profile `image`).
- Translations of an item (`title`, the public `description`) for `default` and the active languages of the content, without fallback to `default`.
- Visibility: `is_active`, `published_at` and `unpublished_at` (both dates are optional). The status (published, scheduled, expired, disabled) is computed, not stored; a hidden or unavailable by schedule parent hides the whole branch.
- The service `MenuTree::tree(menu_key, lang)` returns the nested tree of visible items only, with a computed `is_external`.
- A new section of the admin «Меню» (`/admin/menus`) modeled on «Таксономія»: the list of menus, the form of a menu, the tree of items in a table with indents, the form of an item with the parent selection, weight, changing the order by drag-and-drop, deletion with an explanation.
- The media library shows a menu item among the usages of a file with a link to its form (`MenuItemOwner`).
- Ukrainian translations of the new labels, the e2e spec of the section.

Not included in this change: binding an item to a content record (`node_uuid`) and deriving the URL from the slug, icons, public routes and Blade templates for the menu output, moving an item under another parent by drag-and-drop (the parent is changed by the form), roles and permissions, import-export.

## Capabilities

### New Capabilities
- `menus`: menus and items, the tree with one parent, the address of an item, translations, images, visibility by schedule, `MenuTree`, the deletion rules.
- `admin-menus`: the section «Меню» in the admin: the list of menus, the forms, the tree of items, the order by drag-and-drop, deletion with an explanation, a menu item in the list of usages of the media library.

### Modified Capabilities
- `admin-shell`: the item «Меню» in the side navigation.

## Impact

- Code: a new migration (`menus`, `menu_items`, `menu_item_translations`); the models `Menu`, `MenuItem`, `MenuItemTranslation`; the services in `App\Menus` (`MenuItemWriter`, `MenuTree`); `App\Files\Owners\MenuItemOwner` and the entry in `config/files.php`; the controllers `MenuController`, `MenuItemController` and the requests in `app/Http/Controllers/Admin`, `app/Http/Requests/Admin`; the routes in `routes/web.php`; React: `Pages/Admin/Menus/*`, `Components/Admin/Menus/*`, `navigation.ts`; `lang/uk.json`, `lang/en.json`.
- Depends on the existing `taxonomy` (the tree sample, `TermTree`), `media-library`/`file-storage` (images and `media_relations`), `content-languages` (the languages of the translations).
- Documentation (`docs/`): the new `docs/menus.md` and `docs/admin-menus.md`; updates of `docs/admin-shell.md` (navigation), `docs/media-library.md` (the owner `menu_item`), `docs/e2e-testing.md` (the rows «Область → спек» and «Спільний код → спеки»), `docs/README.md`.
- Tests: PHP Feature tests for the models, visibility, `MenuTree`, the controllers and the requests (the migration changes the DB schema, so the task of the migration calls for a full run); a new e2e `e2e/admin-menus.spec.js`; the dependent specs of the changed shared components (`admin-shell` for the sidebar).

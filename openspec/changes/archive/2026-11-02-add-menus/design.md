# Design

## Context

The taxonomy already contains everything the menu needs from the structure side: a tree with one parent (`taxonomy_terms.parent_uuid`, `weight`), an immutable container `key`, a separate translations table, one image tracked in `media_relations`, invariants in the models and `TermWriter`/`TermTree` in `App\Taxonomy`, and in the admin — the section `/admin/taxonomy` with the tree in a `DataTable`, a form with the parent selection and drag-and-drop. Visibility by schedule already exists in `content_nodes` (`is_active`, `published_at`, `ContentNode::scopeVisible()`), but there `published_at` is required and there is no unpublish date. There is no public front-end and no binding of addresses to records yet. The motivation and the requirements are in [proposal.md](proposal.md) and [specs/](specs/).

Described by the components: the new `docs/menus.md` (the domain, `MenuTree`), the new `docs/admin-menus.md` (the admin section), `docs/taxonomy.md` and `docs/admin-taxonomy.md` (the model, with which the decisions are agreed), `docs/media-library.md` (the registry of owners), `docs/admin-shell.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- Repeat the proven decisions of the taxonomy (key, `uuid`, invariants in the models, translations, image, admin), so that the sections behave the same way and do not require new experience from the editor and the developer.
- Visibility is computed on read: no cron jobs, queues and events for publication and unpublication.
- One request for the menu items (plus one for the translations) regardless of the depth of the tree.

**Non-Goals:**
- A shared abstraction «a tree with weights» for terms and items: refactoring of the taxonomy is outside this change.
- Public routes, Blade components and caching of the menu output.
- Binding an item to a content record; caching of the tree.

## Decisions

### 1. Separate tables modeled on the taxonomy

```
menus(id, key UNIQUE, name, description null, settings JSON null, timestamps)
menu_items(id, uuid UNIQUE,
           menu_key FK → menus.key RESTRICT,
           parent_uuid FK self → menu_items.uuid RESTRICT null,
           weight int default 0,
           url varchar(2048) null,
           target varchar(8) default '_self',
           css_class varchar(255) null,
           image varchar(64) null,
           is_active bool default true,
           published_at datetime null,
           unpublished_at datetime null,
           timestamps;
           INDEX(menu_key), INDEX(parent_uuid, weight))
menu_item_translations(id, item_uuid FK → menu_items.uuid CASCADE,
                       lang (binary collation), title, description null, timestamps;
                       UNIQUE(item_uuid, lang))
```

The identification, the collation of `lang`, the self-reference added in a separate `Schema::table`, and `RESTRICT` on all foreign keys except the translations match `create_taxonomy_tables`. An item has no `slug`: nobody addresses it, and the admin addresses it by `uuid`. The new migration only creates the tables, does not touch existing data; the rollback drops them.

**Alternatives:** items as content nodes (`ContentNode`) — drag a globally unique `slug`, service attributes and EAV values, which the fixed fields of an item do not need; menu settings in `app_settings` — there is no tree structure. Rejected.

### 2. Invariants in the models, writing in `MenuItemWriter`

The models `Menu`, `MenuItem`, `MenuItemTranslation` in `app/Models` check in `saving`/`deleting` (`ValidationException`): the format and immutability of `key`, `max_depth`, a parent from the same menu, absence of cycles, the depth taking the subtree into account, immutability of an item's menu, `target`, `css_class`, `url` and the interval of dates, and also the prohibition of deleting a parent with children and a menu with items. The service `App\Menus\MenuItemWriter` (`create(Menu, array)`/`update(MenuItem, array)`) in one transaction writes the item, the translations (`ContentLanguages::assertWritable`, `default` is required, an empty non-main translation is deleted) and the image (`FileRelations::sync('menu_item', $id, 'image', 'default', …)`, `FileValue`); the hook `deleting` calls `FileRelations::forgetOwner('menu_item', $id)`. Nothing is generated without `slug`.

The logic for computing the depth, the height of the subtree and detecting cycles I write in `App\Menus\MenuItemTree` with the same algorithm as in `TermTree`, and do not extract it into a shared trait (a Non-Goal): this way the taxonomy stays unchanged, and the duplication is small and transparent (≈ 150 lines). If a third tree-shaped entity appears, the shared abstraction will be extracted then.

### 3. The address: one check point, shared by the model and the queries

The rules of the address form live in one place (`App\Menus\MenuUrl`): `isValid(string)`, `isExternal(?string)`. The model calls it in `saving` (the last boundary), `StoreMenuItemRequest`/`UpdateMenuItemRequest` — in the rule of the field `url`, so the client sees the error next to the field, and the code/import does not bypass the check.

The rule: a string of up to 2048 characters without spaces and control characters; or it starts with `/` (but not `//`), `#`, `?`; or it parses as a URL with a scheme from the allowed list (`http`, `https`, `mailto`, `tel`). The allowed list of schemes, not a forbidden one (`javascript:`, `data:`, `vbscript:`…): any scheme outside the list is rejected. `//host` is rejected, because a protocol-relative address is ambiguous (it depends on the scheme of the current page) and is easily confused with a relative path. An empty string is normalized to `NULL`. `isExternal`: `true` for the four schemes, `false` for relative ones, `null` for `NULL`. No column is needed for this: the value is derived from `url`.

### 4. Visibility is computed on read

The flag `is_active` plus `published_at`/`unpublished_at` (UTC, to the minute, as `published_at` of the records) and the comparison with `now()`:

```
visible(item) = is_active
                AND (published_at   IS NULL OR published_at   <= now)
                AND (unpublished_at IS NULL OR unpublished_at >  now)
visible_in_tree(item) = visible(item) AND visible_in_tree(parent)   // root: visible(item)
```

`MenuItem::status(?CarbonInterface $now)` returns `disabled | scheduled | expired | published` (the order of the checks: disabled, scheduled, expired) and serves the admin. Passing `now` into the method makes it deterministic in tests. `MenuItem::scopeVisible()` remains for flat queries, but does not know about ancestors; `MenuTree` cuts the branches. The dates of the interval are validated: `unpublished_at > published_at` when both are set.

**Alternatives:** a scheduler that switches `is_active` by the schedule — the state diverges from the schedule when failures happen and erases the editor's intent; the unpublish date in the same column — does not fit. Computing on read rejects both risks and matches how the visibility of records already works.

### 5. `MenuTree`: two queries and a walk in memory

`App\Menus\MenuTree::tree(string $menuKey, ?string $lang = 'default')`: checks the menu (`ModelNotFoundException` for an unknown key) and the language (`ContentLanguages::assertReadable`), reads the items in one query (`orderBy weight, id`) and the translations of the needed language for their `uuid` in one query; builds a map of children by `parent_uuid` and walks from the roots without entering invisible nodes (so a branch is cut automatically and we are not afraid of cycles, because the invariants exclude them; protection against looping is additionally kept by the list of visited `uuid`). A node: `uuid`, `title`, `description` (without fallback), `url`, `target`, `css_class`, `image`, `is_external`, `children`. `now` is taken once per call, so the whole tree is consistent. A separate method `adminTree(Menu, lang)` for the admin returns all items with statuses (without cutting); the implementation is shared, a flag `onlyVisible`.

### 6. Admin: a copy of the structure of the taxonomy section

Routes `/admin/menus/**`, `Route::bind('menu')` by `key`, `Route::bind('item')` by `uuid` within a menu (404 for a foreign one), `reorder` declared before `{item}`. Controllers `MenuController`, `MenuItemController`, requests `Store/UpdateMenuRequest`, `Store/UpdateMenuItemRequest`, `ReorderMenuItemsRequest`. Pages `Pages/Admin/Menus/*` and `Pages/Admin/Menus/Items/*`, forms `MenuForm`, `MenuItemForm`, `MenuRowActions`, `MenuItemRowActions` in `Components/Admin/Menus`. The items page is a client-side `DataTable` with `reorder` (as for the terms); the column «Статус» — `MenuItem::status()`. In the item form: `parentOptions: {uuid, name, depth}` is computed by the server (excludes the item itself and its descendants, takes `max_depth` and the height of the subtree into account), `defaultWeight`, the languages from `ContentLanguages`, the image — `FileUpload` with `profile="image"`. The dates — `datetime-local` (to the minute), `is_active` — a switch. Order: exactly all `uuid` of the menu, `weight = position`, a direct `UPDATE` in a transaction. Deleting an item: a dialog without confirmation if it has children (unlike the terms, there are no usages).

**Alternative:** a separate page for dragging between parents — postponed (a Non-Goal, as in the taxonomy).

### 7. Media library

`App\Files\Owners\MenuItemOwner` (`MediaOwner`) is registered in `config/files.php` as `menu_item`; `describe(array $ids)` returns the base name and the link `/admin/menus/{menu_key}/items/{uuid}/edit` in one pass.

## Risks / Trade-offs

- [The duplication of the tree logic with `TermTree`] → deliberate, see decision 2; both implementations are covered by separate tests, and a refactoring is possible as a separate change.
- [An invisible branch hides visible descendants, the editor may not notice] → the admin shows the status of each item by its own data, not an inherited one; the row of a descendant under an invisible parent is not marked separately. Accepted: the behaviour is described in [docs/menus.md](../../../docs/menus.md), and a hint in the table can be added separately.
- [There is no front-end yet, so `MenuTree` is checked only by tests] → we cover the scenarios of the spec by Feature tests with a fixed `now`.
- [The data `published_at`/`unpublished_at` in UTC without conversion of time zones] → as for the records; the editor sees the time in the same form as in the records.
- [The allowed list of URL schemes rejects legitimate `ftp:`, `sms:`] → the list is easy to extend in `MenuUrl`; the spec now fixes four schemes, a change requires an update of the requirement.

## Migration Plan

1. The new migration creates three tables; there is no data to transfer.
2. Deployment: `php artisan migrate`, the usual front-end build. There are no new environment variables or dependencies.
3. Rollback: `migrate:rollback` drops the tables; before that it is worth deleting the menus through the model, to remove the image links in `media_relations` (as for the taxonomy), otherwise the `menu_item` records in `media_relations` would be left dangling.

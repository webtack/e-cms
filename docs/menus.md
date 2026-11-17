# Menus

Menus are a domain for site navigation, separate from content types and nodes: **menus** (the header, the footer, the sidebar) with **items** that form a tree with one parent. The tree is similar to [taxonomy](taxonomy.md) (the same approach to `key`, `uuid`, `max_depth`, translations, and images), but items have an address, presentation, and a visibility schedule. There is no binding of an item to a content node yet: the editor enters the address. The admin section is described in [admin-menus.md](admin-menus.md), the rendering of menus on the public site (`@menu('key')`, the templates `menus/menu`) — in [theme-templates.md](theme-templates.md#menus).

## Tables

| Table / model | Purpose |
| --- | --- |
| `menus` / `Menu` | Menu: `key` (unique, immutable identifier), `name`, `description`, `settings`. |
| `menu_items` / `MenuItem` | Item: `uuid`, `menu_key`, `parent_uuid`, `weight`, `url`, `target`, `css_class`, `image`, `is_active`, `published_at`, `unpublished_at`. |
| `menu_item_translations` / `MenuItemTranslation` | The title and public description in a language: `item_uuid`, `lang`, `title` (nullable), `description` (nullable). |

The set of item attributes is **fixed**: there are no custom fields. A menu does not belong to a content type.

## Identification and relations

- Only an item has a `uuid`: UUID v7, set explicitly or generated, **immutable**. A menu has no `uuid`: its identifier is the unique immutable `key`.
- Items and translations are linked by `uuid` (`parent_uuid`, `item_uuid`), an item with its menu — by `menu_key` (a foreign key to `menus.key`). The numeric `id` is only a primary key (needed by `media_relations.owner_id`). `Menu::items()` and `MenuItem::menu()` — by `key`, `MenuItem::parent()/children()/translations()` — by `uuid`.
- The `key` of a menu: `^[a-z][a-z0-9_]*$`, up to 64 characters, unique and immutable.
- An item has no `slug`: the admin panel addresses an item by its `uuid`.
- All foreign keys are `RESTRICT` (except translations: `CASCADE` with the item). The DB will not allow deleting a parent with children or a menu with items.

## Tree and depth

An item has at most one parent from the same menu; cycles and self-references are rejected (an error under the key `parent_uuid`). The menu of an item does not change after creation. Siblings are ordered by `weight`, then by `id`.

The menu's `settings.max_depth` is an integer ≥ 1 (a root has depth 1); without it the depth is not limited. The check is the depth of the parent + 1 + the height of the item's subtree (moving a branch takes the whole branch into account); the `max_depth` cannot be reduced below the actual height of the tree (an error under the key `settings.max_depth`). Heights are computed by `Webtack\Ecms\Menus\MenuItemTree` (a breadth-first traversal: one query per level, protection against looping) — the same algorithm as in [`TermTree`](taxonomy.md#services), but a separate class.

The invariants apply in the models (`saving`/`deleting`, `ValidationException`) regardless of how the record is written.

## Item address

The rules are in one place — `Webtack\Ecms\Menus\MenuUrl`; the model calls them in `saving`, and the admin requests — in the rule of the `url` field. A string up to 2048 characters without spaces, control characters and backslashes:

- **relative**: starts with `/` (but not `//`), `#`, or `?`;
- **absolute** with the scheme `http`, `https`, `mailto`, or `tel` (the list of allowed schemes; `javascript:`, `data:`, `about:` and others are rejected);
- `//host`, `about` (without a slash or a scheme) and any address with a backslash are rejected (browsers read `/\host` as `//host`; the percent-encoded `%5C` is not a separator and stays valid).

An empty string is stored as `NULL` — a **heading item of a group** without a link. The address is not checked against existing pages.

`MenuUrl::isExternal(?string)`: `true` for the four schemes, `false` for relative ones, `null` without an address (used in the tree for the site).

```php
MenuUrl::isValid('/about');              // true
MenuUrl::isValid('javascript:alert(1)'); // false
MenuUrl::isExternal('https://example.com'); // true
```

## Presentation

- `target`: `_self` (default) or `_blank`.
- `css_class`: one or more class names of Latin letters, digits, `-` and `_`, separated by a single space, up to 255 characters; an empty one is stored as `NULL`.
- `image`: one optional image — `{uuid}.{extension}` of a file of the `image` profile ([file-storage.md](file-storage.md)); not translated. The usage is tracked in `media_relations` (owner `menu_item`): [media-library.md](media-library.md#owner-registry).

## Translations

`default` plus the content languages by the rules of [content-languages.md](content-languages.md). The `title` in `default` is required; `description` is a public description, optional. In any other language the title and the description are each optional: a translation may hold only one of them (the other reads as `null`, also in `MenuTree`) and a row that holds neither does not exist. **There is no fallback to `default` by default**: a missing translation is `null`. The public site asks for the fallback explicitly (`MenuTree::tree(..., fallback: true)`): a blank title or description is replaced by the `default` one, each independently; the address, order, nesting and visibility never depend on the language.

One language is saved on its own by `MenuItemWriter::saveTranslation($item, $lang, ['title' => ..., 'description' => ...])`: only that language changes, a value equal to the `default` value of the same field is not stored, an empty value removes its field, and the row is deleted when nothing remains. `clearTranslation($item, $lang)` deletes the row. Both reject `default` and unavailable languages with a `ValidationException` under `lang`. An item is considered *translated* into a language when such a row exists; the admin form is described in [admin-translation-mode.md](admin-translation-mode.md).

## Visibility by schedule

Fields: `is_active` (default `true`), `published_at` and `unpublished_at` (UTC, to the minute, both optional). The status is **computed, not stored**:

```
visible(item) = is_active
                AND (published_at   IS NULL OR published_at   <= now)
                AND (unpublished_at IS NULL OR unpublished_at >  now)
visible_in_tree(item) = visible(item) AND visible_in_tree(parent)
```

| Status | Condition (checked in this order) |
| --- | --- |
| `disabled` ("Disabled") | `is_active = false` (takes precedence over the dates) |
| `scheduled` ("Scheduled") | `published_at` is in the future |
| `expired` ("Expired") | `unpublished_at` is in the past or equal to the current moment |
| `published` ("Published") | otherwise |

`MenuItem::status(?CarbonInterface $now)` returns the status of the item itself (without ancestors); `MenuItem::scopeVisible()` — a flat query by its own visibility. If both dates are set, `unpublished_at` must be later than `published_at` (an error under `unpublished_at`). Disabling an item does not erase the dates. An invisible item hides the whole branch: descendants are invisible regardless of their own flags and dates (`MenuTree` cuts them off). No cron jobs and no queues: the visibility is computed on read.

## Services

All in `Webtack\Ecms\Menus`.

- **`MenuItemWriter`** — `create(Menu, array)` / `update(MenuItem, array)`: the item, translations, and image in one transaction. Keys: `parent_uuid`, `weight`, `url`, `target`, `css_class`, `is_active`, `published_at`, `unpublished_at` (empty ones clear them; stored to the minute), `image` (empty clears it), `translations` (`lang => ['title', 'description']`; a non-base translation is deleted when both its title and description are empty). Only the passed keys are applied. The `default` title is required on creation and cannot be empty on change. Disabling (`is_active = false`) does not touch the dates.
- **`MenuTree`** — reads the tree with two queries (the menu items, then the translations of the language; plus the menu query) and traverses in memory, so the number of queries does not depend on the depth.
  - `tree(string $menuKey, ?string $lang = 'default', ?CarbonInterface $now = null, bool $fallback = false)` — the nested tree of **visible** items only for the site; an invisible item cuts off the whole branch. A node: `uuid`, `title`, `description` (in the language; with `$fallback` a blank one is the `default` text), `url`, `target`, `css_class`, `image`, `is_external`, `children`. An unknown key — `ModelNotFoundException`; an unsuitable language — `ValidationException` (the reading rules from [content-languages.md](content-languages.md)).
  - `adminTree(Menu, string $lang = 'default', ?CarbonInterface $now = null)` — all items in traversal order (roots by weight, under each its children) with `status`, `depth`, and `children_count` for the admin table; the status is its own, ancestors are not taken into account.
- **`MenuItemTree`** — heights, ancestors, and descendants (see "Tree and depth").
- **`MenuUrl`** — the check and the type of address (see "Item address").

```php
$item = app(MenuItemWriter::class)->create($menu, [
    'url' => '/about',
    'translations' => ['default' => ['title' => 'About'], 'en' => ['title' => 'About us']],
]);

app(MenuTree::class)->tree('main', 'default');
// [['uuid' => '…', 'title' => 'About', 'description' => null, 'url' => '/about', 'target' => '_self',
//   'css_class' => null, 'image' => null, 'is_external' => false, 'children' => []]]
```

### Reading the tree in the frontend

`MenuTree::tree()` is the data API and knows nothing about the request. The public site passes it through `Webtack\Ecms\Theme\MenuRenderer` before a template sees it, which adds to every item `is_active` (the item that matches the requested path) and `is_trail` (each ancestor of it), and turns a stored address that starts with `/` into an absolute URL. The matching rules, the longest-match tie-break, and the items that never match are described in [theme-templates.md](theme-templates.md#menus).

## File owner

The image of an item is the owner `menu_item` in the [media library registry](media-library.md#owner-registry): `Webtack\Ecms\Files\Owners\MenuItemOwner`, the label is the item's title in `default` (if absent — the item's `uuid`), the link — the item form `/admin/menus/{key}/items/{uuid}/edit`. `MenuItemWriter` syncs the relation (`FileRelations::sync('menu_item', $id, 'image', 'default', …)`), and the model's `deleting` hook removes it.

## Deletion

- An **item** is not deleted if it has children (`ValidationException` under the key `uuid`). The deletion removes the translations (the DB cascade) and removes the image relation (`FileRelations::forgetOwner('menu_item', $id)`).
- A **menu** is not deleted if it has items (the key `key`).

Deletion must be done through the models, so that the checks and the relation removal take effect. Before rolling back a migration, delete the menus through the model, otherwise `menu_item` records in `media_relations` will remain dangling.

Related: [taxonomy.md](taxonomy.md), [content-languages.md](content-languages.md), [media-library.md](media-library.md).

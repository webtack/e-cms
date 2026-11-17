# Menus in the admin panel

The `/admin/menus` section manages the site menus and the tree of their items. The domain rules (tables, identification, depth, address, translations, visibility schedule, deletion) are described in [menus.md](menus.md); here — routes, page responses, and form behavior. Access — `auth` + `verified` and the role right `menus` (without it 403 on all routes of the section); a guest is redirected to `/login`. See [access control by roles](access-control.md). The structure of the section repeats [taxonomy in the admin panel](admin-taxonomy.md).

## Routes

A menu is addressed by its `key` (`Route::bind('menu')` in `routes/service.php`; a menu has no `uuid`); an unknown key gives 404. An item is addressed by `uuid` within its menu (`Route::bind('item')`): the `uuid` of an item of another menu and an unknown `uuid` give 404. Route names start with `admin.menus.`.

| Method and address | Name | Action |
| --- | --- | --- |
| `GET /admin/menus` | `index` | list of menus |
| `GET /admin/menus/create` | `create` | create form for a menu |
| `POST /admin/menus` | `store` | create a menu |
| `GET /admin/menus/{menu}/edit` | `edit` | edit form for a menu |
| `PUT /admin/menus/{menu}` | `update` | update a menu |
| `DELETE /admin/menus/{menu}` | `destroy` | delete a menu |
| `GET /admin/menus/{menu}/items` | `items.index` | tree of items |
| `GET /admin/menus/{menu}/items/create` | `items.create` | create form for an item |
| `POST /admin/menus/{menu}/items` | `items.store` | create an item |
| `PUT /admin/menus/{menu}/items/order` | `items.reorder` | change the order |
| `GET /admin/menus/{menu}/items/{item}/edit` | `items.edit` | edit form for an item |
| `PUT /admin/menus/{menu}/items/{item}` | `items.update` | update an item |
| `DELETE /admin/menus/{menu}/items/{item}` | `items.destroy` | delete an item |
| `PATCH /admin/menus/{menu}/items/{item}/active` | `items.active` | set only `is_active` of an item |

`items/order` is declared before `{item}`, so `order` never becomes an item's `uuid`. The controllers are `MenuController` and `MenuItemController`, the requests are `StoreMenuRequest`, `UpdateMenuRequest`, `StoreMenuItemRequest`, `UpdateMenuItemRequest`, `ReorderMenuItemsRequest`.

## List of menus

`GET /admin/menus` shows the [data table](admin-data-table.md) in **server mode** (`TablePage::fromCollection()`): search, sorting, and pagination are on the server, the state is in the address, and there are no filters. The page is `Pages/Admin/Menus/Index.tsx`.

| Key | Type | Sorting | Notes |
| --- | --- | --- | --- |
| `name` | text | yes | always visible; under the name — the description; both lines are cut |
| `key` | text | yes | always visible |
| `items_count` | number | yes | the number of items (`withCount('items')`, no N+1) |
| `max_depth` | number | yes | empty if the depth is not limited; does not take part in search |

`description` is a server-side column without a client counterpart: the search `q` looks in the name, the key, and the description. Without an explicit `sort`, the rows go by name (case-insensitive). Above the table — the "Create" button. The row menu (`Components/Admin/Menus/MenuRowActions.tsx`): "Items", "Edit", "Delete".

## Creating and editing a menu

The form is `Components/Admin/Menus/MenuForm.tsx` (modes `create`/`edit`), the pages `Create.tsx` and `Edit.tsx`. Fields: `key`, `name`, `description`, `max_depth`.

- `key` — `^[a-z][a-z0-9_]*$`, up to 64 characters, unique; in the edit form it is read-only, and `UpdateMenuRequest` has no rule for it, so it does not get into `validated()`.
- `name` — required, up to 255 characters.
- `max_depth` — an integer from 1; an empty value means "no limit" and is stored as the absence of `settings.max_depth`. Other `settings` keys remain.
- The depth cannot be reduced below the actual height of the tree: `UpdateMenuRequest` compares it with `MenuItemTree::menuHeight()` and adds an error to `max_depth` (the model remains the last barrier).

After creation the user lands on the items page of the new menu; after an update the user stays on the edit form (`back()`); both cases with a `success` message.

## Deleting a menu

The "Delete" action opens a confirmation dialog. A menu with items is not deleted: the dialog explains the reason and does not offer confirmation; the server checks this again and returns `back()` with an `error` message. An empty menu is deleted, and the user returns to the list with a `success` message. If the model still throws a `ValidationException`, the controller shows its first message.

## Items page

`items.index` provides **all** items of the menu in traversal order of the tree (roots by weight, under each of them its children) in the [data table](admin-data-table.md) in **client mode without pagination** (`paginated={false}`, `Pages/Admin/Menus/Items/Index.tsx`): all items are shown at once, there is no page size or page navigation bar, so dragging is available whatever the number of items. A row (built by `MenuTree::adminTree()`): `uuid`, `title` (the base title), `url`, `weight`, `depth` (a root has 1), `children_count`, and `status`. Columns: the title (indented by `depth`, the attribute `data-depth`), the address (empty for a heading item), the status, the weight, the number of children. Above the table — a heading with the menu name, a link "Edit menu", and a "Create" button; the row menu: "Edit", "Delete". An empty menu shows a no-data message.

**Status** is derived (`MenuItem::status()`): "Published", "Scheduled", "Expired", "Disabled" (for the order of checks and the formula see [menus.md](menus.md#visibility-by-schedule)). It is computed for the item itself by its flag and dates; an inherited invisibility (a hidden parent) is **not** marked in the row. The column is `Components/Admin/Menus/MenuItemStatusBadge.tsx` (the attribute `data-status`), wrapped in the clickable `Components/Admin/StatusToggleBadge.tsx`: a click sends `PATCH /admin/menus/{menu}/items/{item}/active` with `is_active = (status === 'disabled')`, without a confirmation. `MenuItemController::setActive` validates a required boolean `is_active`, updates only that flag through the model (a `ValidationException` becomes an `error` flash), and answers with `back()` and `success` ("Menu item enabled." / "Menu item disabled."). The dates, title, address, parent, and weight stay, so enabling an item shows the status that follows from its own dates ("Expired" is possible). The route sits in the `can:menus` group (403 without the permission) and the item is addressed within its menu (404 for a foreign item).

## Item form

`Components/Admin/Menus/MenuItemForm.tsx` (modes `create`/`edit`), the pages `Items/Create.tsx` and `Items/Edit.tsx`. Fields:

- **Title and description** in the base language (`translations.default.*`; the edit page receives them as `item.title` and `item.description`): the title is required, up to 255 characters.
- **Address** (`url`) — relative (`/`, `#`, `?`) or `http`, `https`, `mailto`, `tel`; an empty one means a heading item without a link. The rule is `MenuUrl::isValid()` ([menus.md](menus.md#item-address)); the error is shown next to the field.
- **Open in** (`target`) — "In the same window" (`_self`) or "In a new window" (`_blank`). **CSS class** — class names separated by spaces.
- **Parent item** — a selection with indentation by depth, or "No parent". The list `parentOptions: {uuid, name, depth}` is computed by the server: all items of the menu except the item itself and its descendants (when editing), and only those for which `depth(parent) + subtree height ≤ max_depth`. The client does not compute the depth; the depth and cycles are additionally guarded by the model (an error next to `parent_uuid`). An empty parent during editing makes the item a root. The menu of an item cannot be changed.
- **Weight** — an integer from 0; during creation the default is the largest weight among the siblings + 1 (the server passes `defaultWeight`; an empty weight is also replaced by this value), during editing an empty weight keeps the current one.
- **Image** — one file of the `image` profile; the `FileUpload` widget with `profile="image"`, the value `{uuid}.{ext}`. Saving, replacing, and clearing set or remove the relation in `media_relations` through `MenuItemWriter`; a file of another profile gives an error next to the field. During editing the server passes `imageFile` (name, preview).
- **Active** (`is_active`, a toggle, enabled by default), **Show from** (`published_at`), and **Show until** (`unpublished_at`) — `datetime-local` to the minute, the time in UTC, without time zone conversion (as the publication date of nodes). Both dates are optional; an empty field clears the date. If both are set, the end date must be later than the start date (an error next to `unpublished_at`). Disabling an item does not erase the dates.
Saving the base form calls `MenuItemWriter` (the item, its base title and description, and the image in one transaction) and **never changes translations of other languages**; the form has no translation fields and no switcher on the create page; after creation the user lands on the items page with a `success` message, and after an update the user stays on the item form (`back()`) with the same message. The administrator's interface language and the content languages are independent.

### Item translations

The edit page follows the shared contract of [admin-translation-mode.md](admin-translation-mode.md). With multilingual mode on and at least one active additional language, the `LanguageSwitcher` appears above the form. `GET .../items/{uuid}/edit?lang=en` opens the translation mode: the same form, where only the title and the description are editable, prefilled from the translation or else from the base (the address, target, class, image, parent, weight, activity and dates keep their place, shown inert and dimmed with their base values); an unavailable language answers 404. In a translation the title and the description are each optional.

- `PUT /admin/menus/{menu}/items/{item}/translations/{lang}` (`title`, `description`) calls `MenuItemWriter::saveTranslation()`: a value equal to the base value is not stored, an empty one removes its field, other languages are untouched; the user stays on `?lang={lang}` with "Translation saved."
- `DELETE` of the same address calls `MenuItemWriter::clearTranslation()` and returns with "Translation cleared."
- Both routes sit under `can:menus`, like the base edit page.

## Deleting an item

The "Delete" action opens a dialog (`MenuItemRowActions.tsx`). An item with children is not deleted: the dialog explains that the child items must be moved or deleted first, and does not offer confirmation. The server checks the rule again and returns `back()` with an `error` message. An item without children is deleted together with its translations and the image relation (there are no usages, as for terms, so there are no other blockers).

## Drag-and-drop ordering

Rows are dragged by the handle (with the mouse and the keyboard) on condition that all rows are shown without search, filters, and sorting. The client sends `order[]` — the `uuid`s of all items in the new row order; `ReorderMenuItemsRequest` requires **exactly the whole set** of the menu's `uuid`s (without omissions, repeats, or foreign ones), and the controller in a transaction rewrites `weight = row position` with a direct `UPDATE`. Parents do not change.

**Limitation.** Dragging changes only the order. Since siblings are sorted by weight, their relative order matches the order of their rows, and a row dropped into a "foreign" branch returns under its own parent after the page is refreshed and only changes the order among its siblings. The parent of an item is changed by the form. A save error restores the previous order and shows a message (toast).

## Media library

An item among the usages of a file is shown with a label — its base title — and a link to `/admin/menus/{key}/items/{uuid}/edit` ([media-library.md](media-library.md)); the link is built by `Webtack\Ecms\Files\Owners\MenuItemOwner`.

## Tests

```bash
vendor/bin/phpunit --filter=MenuAdmin
vendor/bin/phpunit --filter=MenuItemAdmin
vendor/bin/phpunit --filter=MenuItemOwnerTest
```


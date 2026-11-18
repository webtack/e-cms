# Spec Delta

## Purpose

Defines the site menus and their items: the tree with one parent, the address of an item, translations, images, visibility by schedule and reading of the tree of visible items for the front-end. A menu exists separately from the types and records of the content.

## ADDED Requirements

### Requirement: Меню
The system SHALL store menus, each with an immutable unique key (format `^[a-z][a-z0-9_]*$`, up to 64 characters), which is its identifier, a name, an optional description and settings. The settings SHALL contain an optional maximum depth of the tree (an integer from 1); without it the depth is not limited. An attempt to change the key of an existing menu SHALL be rejected with a validation error.

#### Scenario: Creating a menu
- **WHEN** a menu with the key `main` and the name «Головне меню» is created
- **THEN** the menu is saved, and its key remains immutable

#### Scenario: Invalid key
- **WHEN** the key of a menu does not match the format or is already taken
- **THEN** the saving is rejected with a validation error for the field `key`

#### Scenario: Changing the key
- **WHEN** the key of an existing menu is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Invalid depth
- **WHEN** the maximum depth of a menu is set as `0`, a negative or a non-integer number
- **THEN** the saving is rejected with a validation error

### Requirement: Пункти меню та дерево з одним батьком
The system SHALL store menu items, each with an immutable unique `uuid` (UUID v7: set explicitly or generated), a weight, an optional parent item and translations; the link of an item to a menu SHALL be defined by the menu key, and to the parent — by the `uuid` of the parent. The parent SHALL belong to the same menu; an item SHALL have at most one parent. The tree SHALL NOT contain cycles. The menu of an item SHALL be immutable after creation. The siblings of one parent SHALL be ordered by weight, and with equal weight — by the internal numeric identifier. The depth of an item (the root has depth 1) SHALL NOT exceed the maximum depth of the menu; moving an item under a new parent SHALL take into account the depth of its whole subtree; a reduction of the maximum depth below the actual depth of the tree SHALL be rejected.

#### Scenario: Root and child
- **WHEN** the item «Каталог» without a parent and the item «Куртки» with the parent «Каталог» are created
- **THEN** «Каталог» is a root, «Куртки» its child

#### Scenario: Parent from another menu
- **WHEN** an item of another menu is chosen as the parent of an item
- **THEN** the saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Cycle
- **WHEN** the descendant «Куртки» of «Каталог» is chosen as the parent of «Каталог»
- **THEN** the change is rejected with a validation error

#### Scenario: Item as its own parent
- **WHEN** the item itself is chosen as its parent
- **THEN** the change is rejected with a validation error

#### Scenario: Changing the menu of an item
- **WHEN** the menu of an existing item is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Order of siblings
- **WHEN** the children of one parent have weights 2, 0 and 1
- **THEN** they are returned in the order of weights 0, 1, 2, and with equal weight — by the internal identifier

#### Scenario: Creation on the depth boundary
- **WHEN** the maximum depth of the menu is 2, and an item is created under an item of the second level
- **THEN** the saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Moving a subtree
- **WHEN** the maximum depth is 3, and an item with two levels of descendants is moved under an item of the second level
- **THEN** the move is rejected, because the depth of the subtree would exceed the limit

#### Scenario: Depth below the actual one
- **WHEN** the tree of the menu has depth 3, and the maximum depth is set to 2
- **THEN** the change is rejected with a validation error for the field of the maximum depth

### Requirement: Адреса пункту
An item SHALL have an optional address `url` — a string of up to 2048 characters. The allowed forms: relative, starting with `/` (but not with `//`), `#` or `?`; absolute with the scheme `http`, `https`, `mailto` or `tel`. Other schemes (in particular `javascript:` and `data:`), addresses with spaces or control characters, and addresses starting with `//` SHALL be rejected with a validation error for the field `url`. An empty address (`''` or `null`) SHALL be stored as absent and mean a heading item of a group without a link. The system does not check the address against existing pages and records.

#### Scenario: Relative address
- **WHEN** the address `/about` is set for an item
- **THEN** the address is saved

#### Scenario: Absolute address
- **WHEN** the address `https://example.com/page` or `mailto:info@example.com` is set for an item
- **THEN** the address is saved

#### Scenario: Unsafe scheme
- **WHEN** the address `javascript:alert(1)` is set for an item
- **THEN** the saving is rejected with a validation error for the field `url`

#### Scenario: Address without a scheme and without a slash
- **WHEN** the address `about` or `//example.com` is set for an item
- **THEN** the saving is rejected with a validation error for the field `url`

#### Scenario: Heading item of a group
- **WHEN** the address of an item is left empty
- **THEN** the item is saved without an address

### Requirement: Подання пункту
An item SHALL have `target` with the value `_self` (by default) or `_blank`, an optional `css_class` (one or several class names of Latin letters, digits, `-` and `_`, separated by spaces, up to 255 characters; an empty one is stored as absent) and one optional image — a file of the media library with the profile `image`, stored as `{uuid}.{extension}`. The image is not translated. The link of an item with a file SHALL be counted in the media library as a usage of the file by the owner `menu_item`; a file used by an item SHALL be unavailable for deletion from the media library. Replacing and clearing the image, as well as deleting the item, SHALL remove the link (the file itself remains in the media library).

#### Scenario: Invalid target
- **WHEN** the `target` of an item is set as `_top`
- **THEN** the saving is rejected with a validation error for the field `target`

#### Scenario: Invalid CSS class
- **WHEN** `css_class` contains characters outside the allowed set
- **THEN** the saving is rejected with a validation error for the field `css_class`

#### Scenario: Image of an item
- **WHEN** a file of the profile `image` is assigned to an item
- **THEN** the file is shown in the media library as used by the item and unavailable for deletion

#### Scenario: File of another profile
- **WHEN** a file that does not belong to the profile `image` is specified as the image
- **THEN** the saving is rejected with a validation error for the field `image`

#### Scenario: Replacing and clearing the image
- **WHEN** the image of an item is replaced or cleared
- **THEN** the link with the old file is removed, and the file remains in the media library

### Requirement: Переклади пунктів
An item SHALL have translations of `title` (name, up to 255 characters) and `description` (the public description, optional) for the language `default` and for the content languages allowed for writing by the rules of the content languages. The name in `default` SHALL be required. The fallback to `default` SHALL NOT apply: a missing translation is returned as `null`. An empty translation of a non-main language SHALL not be stored, and clearing an existing one SHALL delete it. Deleting an item SHALL delete its translations.

#### Scenario: Name in default
- **WHEN** an item is saved without a name in `default`
- **THEN** the saving is rejected with a validation error

#### Scenario: Translation in an active language
- **WHEN** multilingual support is enabled, the language `en` is active, and the item has a name and a description in `en`
- **THEN** the translation is saved

#### Scenario: Inactive language
- **WHEN** a translation in a language that is not allowed for writing is set for an item
- **THEN** the saving is rejected with a validation error for the key `lang`

#### Scenario: Missing translation
- **WHEN** the name of an item in the language `en` is requested, and there is no translation
- **THEN** `null` is returned, not the name of `default`

### Requirement: Видимість пункту за розкладом
An item SHALL have the flag `is_active` (enabled by default) and optional `published_at` and `unpublished_at` (UTC, to the minute). An empty date means the absence of the corresponding limit. An item SHALL be visible for the site when `is_active = true`, `published_at` is empty or not later than the current moment, and `unpublished_at` is empty or later than the current moment. If both dates are set, `unpublished_at` SHALL be later than `published_at`, otherwise the saving is rejected with a validation error for the field `unpublished_at`. The status SHALL be derived and not stored: «Вимкнено» (`is_active = false`, takes priority over the dates), «Заплановано» (`published_at` in the future), «Завершено» (`unpublished_at` in the past or equal to the current moment), otherwise «Опубліковано». Disabling an item does not change its dates. An item that is invisible SHALL hide its whole branch: its descendants are invisible regardless of their own flags and dates.

#### Scenario: Published item
- **WHEN** `is_active = true`, both dates are empty
- **THEN** the item is visible

#### Scenario: Scheduled item
- **WHEN** `published_at` is later than the current moment
- **THEN** the status is «Заплановано», the item is invisible, and it becomes visible when `published_at` arrives without any action

#### Scenario: Scheduled unpublishing
- **WHEN** `unpublished_at` arrives
- **THEN** the status is «Завершено», the item is invisible

#### Scenario: Disabled item
- **WHEN** `is_active = false`, and the dates correspond to the visibility range
- **THEN** the status is «Вимкнено», the item is invisible, the dates are not changed

#### Scenario: Contradictory interval
- **WHEN** `unpublished_at` is not later than `published_at`
- **THEN** the saving is rejected with a validation error for the field `unpublished_at`

#### Scenario: Hidden branch
- **WHEN** a parent item is invisible, and its child is itself visible
- **THEN** the child is invisible for the site

### Requirement: Дерево видимих пунктів для сайту
The system SHALL provide, by a menu key and a language, a nested tree of visible items only, ordered by weight. Each node SHALL contain `uuid`, the name and the description in the given language (without fallback: a missing translation gives `null`), `url`, `target`, `css_class`, the image, the flag `is_external` and the children. `is_external` SHALL be `true` for the addresses `http`, `https`, `mailto` and `tel`, `false` for relative addresses, and `null` for an item without an address. Invisible items together with their branches SHALL NOT get into the tree. An unknown menu key SHALL give an error, not an empty tree. The language SHALL correspond to the rules of reading the content languages.

#### Scenario: Only visible items
- **WHEN** the menu has a published item, a scheduled item and a disabled item
- **THEN** the tree contains only the published one

#### Scenario: Branch of a hidden parent
- **WHEN** a hidden item has visible descendants
- **THEN** neither it nor its descendants get into the tree

#### Scenario: Order and nesting
- **WHEN** the menu has roots with weights 1 and 0, and under the first root a child
- **THEN** the tree returns the roots in the order of weights, and the child inside its parent

#### Scenario: Flag of an external link
- **WHEN** the tree has items with the addresses `/about`, `https://example.com` and without an address
- **THEN** `is_external` equals `false`, `true` and `null` respectively

#### Scenario: Unknown menu
- **WHEN** the tree of a menu with a key that does not exist is requested
- **THEN** the result is an error

### Requirement: Видалення пункту та меню
An item that has children SHALL not be deleted. A menu that has items SHALL not be deleted. An attempt of deletion SHALL be rejected with a validation error; the database SHALL be the last barrier that does not allow deleting a parent with children. Deleting an item without children SHALL delete its translations and remove the link with the image. The deletion SHALL happen through the models, so that the checks and the removal of the links work.

#### Scenario: Item with children
- **WHEN** an item that has children is being deleted
- **THEN** the deletion is rejected, the item and its children are kept

#### Scenario: Leaf
- **WHEN** an item without children is being deleted
- **THEN** the item, its translations and the link of the image are deleted

#### Scenario: Menu with items
- **WHEN** a menu that has items is being deleted
- **THEN** the deletion is rejected

#### Scenario: Empty menu
- **WHEN** a menu without items is being deleted
- **THEN** the menu is deleted

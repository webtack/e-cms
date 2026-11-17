# menus Specification

## Purpose

Defines the site menus and their items: a tree with one parent, the item address, translations, images, visibility by schedule, and reading the tree of visible items for the frontend. A menu exists separately from content types and nodes.

## Requirements

### Requirement: Menu
The system SHALL store menus, each with an immutable unique key (format `^[a-z][a-z0-9_]*$`, up to 64 characters), which is its identifier, a name, an optional description, and settings. The settings SHALL contain an optional maximum depth of the tree (an integer from 1); without it the depth is not limited. An attempt to change the key of an existing menu SHALL be rejected with a validation error.

#### Scenario: Creating a menu
- **WHEN** a menu is created with the key `main` and the name "Main menu"
- **THEN** the menu is saved, and its key is immutable from then on

#### Scenario: Invalid key
- **WHEN** the key of a menu does not match the format or is already taken
- **THEN** saving is rejected with a validation error for the field `key`

#### Scenario: Changing the key
- **WHEN** the key of an existing menu is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Invalid depth
- **WHEN** the maximum depth of a menu is specified as `0`, a negative number, or a non-integer
- **THEN** saving is rejected with a validation error

### Requirement: Menu items and the tree with one parent
The system SHALL store menu items, each with an immutable unique `uuid` (UUID v7: set explicitly or generated), a weight, an optional parent item, and translations; the item's relation to its menu SHALL be determined by the menu key, and to its parent — by the parent's `uuid`. The parent SHALL belong to the same menu; an item SHALL have at most one parent. The tree SHALL NOT contain cycles. The menu of an item SHALL be immutable after creation. Sibling items of one parent SHALL be ordered by weight, and for equal weight — by the internal numeric identifier. The depth of an item (a root has depth 1) SHALL NOT exceed the maximum depth of the menu; moving an item under a new parent SHALL take into account the depth of its whole subtree; reducing the maximum depth below the actual depth of the tree SHALL be rejected.

#### Scenario: Root and sub-item
- **WHEN** the item "Catalog" is created without a parent, and the item "Jackets" with the parent "Catalog"
- **THEN** "Catalog" is a root, and "Jackets" is its child

#### Scenario: Parent from another menu
- **WHEN** an item of another menu is selected as the parent of an item
- **THEN** saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Cycle
- **WHEN** the descendant "Jackets" is selected as the parent of the item "Catalog"
- **THEN** the change is rejected with a validation error

#### Scenario: Item as its own parent
- **WHEN** the item itself is selected as its parent
- **THEN** the change is rejected with a validation error

#### Scenario: Changing the menu of an item
- **WHEN** the menu of an existing item is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Order of sibling items
- **WHEN** the children of one parent have weights 2, 0, and 1
- **THEN** they are returned in the order of weights 0, 1, 2, and for equal weight — by the internal identifier

#### Scenario: Creation at the depth limit
- **WHEN** the maximum depth of a menu is 2, and an item is created under an item of the second level
- **THEN** saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Moving a subtree
- **WHEN** the maximum depth is 3, and an item with two levels of descendants is moved under an item of the second level
- **THEN** the move is rejected, because the depth of the subtree would exceed the limit

#### Scenario: Depth below the actual one
- **WHEN** the tree of a menu has depth 3, and the maximum depth is set to 2
- **THEN** the change is rejected with a validation error for the maximum depth field

### Requirement: Item address
An item SHALL have an optional address `url` — a string of up to 2048 characters. The allowed forms: relative, starting with `/` (but not with `//`), `#`, or `?`; absolute with the scheme `http`, `https`, `mailto`, or `tel`. Other schemes (in particular `javascript:` and `data:`), addresses with spaces, control characters or a backslash, and addresses starting with `//` SHALL be rejected with a validation error for the field `url`. An empty address (`''` or `null`) SHALL be stored as absent and mean a heading item of a group without a link. The system does not check the address against existing pages and records.

#### Scenario: Relative address
- **WHEN** the address `/about` is set for an item
- **THEN** the address is saved

#### Scenario: Absolute address
- **WHEN** the address `https://example.com/page` or `mailto:info@example.com` is set for an item
- **THEN** the address is saved

#### Scenario: Dangerous scheme
- **WHEN** the address `javascript:alert(1)` is set for an item
- **THEN** saving is rejected with a validation error for the field `url`

#### Scenario: Address without a scheme and without a slash
- **WHEN** the address `about` or `//example.com` is set for an item
- **THEN** saving is rejected with a validation error for the field `url`

#### Scenario: Backslash in the address
- **WHEN** the address `/\example.com` is set for an item
- **THEN** saving is rejected with a validation error for the field `url`

#### Scenario: Heading item of a group
- **WHEN** the address of an item is left empty
- **THEN** the item is saved without an address

### Requirement: Presentation of an item
An item SHALL have `target` with the value `_self` (default) or `_blank`, an optional `css_class` (one or more class names of Latin letters, digits, `-` and `_`, separated by spaces, up to 255 characters; an empty one is stored as absent), and one optional image — a media library file with the `image` profile, stored as `{uuid}.{extension}`. The image is not translated. The relation of an item with the file SHALL be counted in the media library as a usage of the file by the owner `menu_item`; a file used by an item SHALL be unavailable for deletion from the media library. Replacing and clearing the image, as well as deleting the item, SHALL remove the relation (the file itself remains in the media library).

#### Scenario: Invalid target
- **WHEN** the `target` of an item is specified as `_top`
- **THEN** saving is rejected with a validation error for the field `target`

#### Scenario: Invalid CSS class
- **WHEN** `css_class` contains characters outside the allowed set
- **THEN** saving is rejected with a validation error for the field `css_class`

#### Scenario: Image of an item
- **WHEN** a file of the `image` profile is assigned to an item
- **THEN** the file is shown in the media library as used by the item and is unavailable for deletion

#### Scenario: File of another profile
- **WHEN** a file that does not belong to the `image` profile is specified as the image
- **THEN** saving is rejected with a validation error for the field `image`

#### Scenario: Replacing and clearing the image
- **WHEN** the image of an item is replaced or cleared
- **THEN** the relation with the old file is removed, and the file remains in the media library

### Requirement: Translations of items
An item SHALL have translations of `title` (the name, up to 255 characters) and `description` (the public description, optional) for the language `default` and for the content languages admissible for writing under the content language rules. The title in `default` SHALL be required. In any other language the title and the description SHALL each be optional: a translation MAY hold only one of them, and the other reads as missing; a translation that holds neither SHALL NOT exist. The system SHALL be able to save the translation of one language on its own: it SHALL change only that language, SHALL NOT store a value that equals the `default` value of the same field, and SHALL delete the translation when no field of it remains. A fallback to `default` SHALL NOT be applied: a missing translation is returned as `null`. An empty translation of a non-base language SHALL NOT be saved, and clearing every field of an existing one SHALL delete it. Deleting an item SHALL delete its translations.

#### Scenario: Title in default
- **WHEN** an item is saved without a title in `default`
- **THEN** saving is rejected with a validation error

#### Scenario: Translation in an active language
- **WHEN** multilingual mode is enabled, the language `en` is active, and a title and a description are set for the item in `en`
- **THEN** the translation is saved

#### Scenario: Inactive language
- **WHEN** a translation is set for an item in a language that is not admissible for writing
- **THEN** saving is rejected with a validation error for the key `lang`

#### Scenario: Missing translation
- **WHEN** the title of an item is requested in `en`, and there is no translation
- **THEN** `null` is returned, not the title of `default`

#### Scenario: Translation with only a description
- **WHEN** a translation in `en` is saved with a description and without a title
- **THEN** the translation exists, its title reads as missing and its description is stored

#### Scenario: Value equal to the base
- **WHEN** the translation of `en` is saved with a title equal to the `default` title and a different description
- **THEN** only the description is stored for `en`

#### Scenario: Saving one language
- **WHEN** the translation of `en` is saved for a item that also has a translation in `pl`
- **THEN** the translation in `pl` and the `default` values are unchanged

#### Scenario: Nothing left to store
- **WHEN** the translation of `en` is saved with every value empty or equal to `default`
- **THEN** the `en` translation does not exist afterwards

### Requirement: Visibility of an item by schedule
An item SHALL have the flag `is_active` (enabled by default) and optional `published_at` and `unpublished_at` (UTC, to the minute). An empty date means the absence of the corresponding bound. An item SHALL be visible to the site when `is_active = true`, and `published_at` is empty or not later than the current moment, and `unpublished_at` is empty or later than the current moment. If both dates are set, `unpublished_at` SHALL be later than `published_at`, otherwise saving is rejected with a validation error for the field `unpublished_at`. The status SHALL be derived and not stored: "Disabled" (`is_active = false`, takes precedence over the dates), "Scheduled" (`published_at` in the future), "Expired" (`unpublished_at` in the past or equal to the current moment), otherwise "Published". Disabling an item does not change its dates. An item that is invisible SHALL hide its whole branch: its descendants are invisible regardless of their own flags and dates.

#### Scenario: Published item
- **WHEN** `is_active = true` and both dates are empty
- **THEN** the item is visible

#### Scenario: Scheduled item
- **WHEN** `published_at` is later than the current moment
- **THEN** the status is "Scheduled", the item is invisible, and it becomes visible when `published_at` arrives, without any action

#### Scenario: Scheduled removal
- **WHEN** `unpublished_at` has arrived
- **THEN** the status is "Expired", the item is invisible

#### Scenario: Disabled item
- **WHEN** `is_active = false`, and the dates correspond to the visibility range
- **THEN** the status is "Disabled", the item is invisible, the dates are not changed

#### Scenario: Contradictory interval
- **WHEN** `unpublished_at` is not later than `published_at`
- **THEN** saving is rejected with a validation error for the field `unpublished_at`

#### Scenario: Hidden branch
- **WHEN** the parent item is invisible, and its child is itself visible
- **THEN** the child is invisible to the site

### Requirement: Tree of visible items for the site
The system SHALL provide, by the menu key and the language, a nested tree of only the visible items, ordered by weight. Each node SHALL contain the `uuid`, the title and the description in the specified language (without fallback: a missing translation gives `null`), `url`, `target`, `css_class`, the image, the flag `is_external`, and the children. `is_external` SHALL be `true` for the addresses `http`, `https`, `mailto`, and `tel`, `false` for relative addresses, and `null` for an item without an address. Invisible items together with their branches SHALL NOT get into the tree. An unknown menu key SHALL give an error, not an empty tree. The language SHALL correspond to the reading rules of content languages.

#### Scenario: Only visible items
- **WHEN** the menu has a published item, a scheduled item, and a disabled item
- **THEN** the tree contains only the published one

#### Scenario: Branch of a hidden parent
- **WHEN** a hidden item has visible descendants
- **THEN** neither it nor its descendants get into the tree

#### Scenario: Order and nesting
- **WHEN** the menu has roots with weights 1 and 0, and under the first root a child
- **THEN** the tree returns the roots in the order of weights, and the child inside its parent

#### Scenario: Sign of an external link
- **WHEN** the tree has items with the addresses `/about`, `https://example.com`, and without an address
- **THEN** `is_external` equals `false`, `true`, and `null` respectively

#### Scenario: Unknown menu
- **WHEN** the tree of a menu with a key that does not exist is requested
- **THEN** the result is an error

### Requirement: Deleting an item and a menu
An item that has children SHALL NOT be deleted. A menu that has items SHALL NOT be deleted. An attempt to delete SHALL be rejected with a validation error; the database SHALL be the last barrier that does not allow deleting a parent with children. Deleting an item without children SHALL delete its translations and remove the image relation. Deletion SHALL happen through the models, so that the checks and the relation removal take effect.

#### Scenario: Item with children
- **WHEN** an item that has children is deleted
- **THEN** the deletion is rejected, and the item and its children are kept

#### Scenario: Leaf
- **WHEN** an item without children is deleted
- **THEN** the item, its translations, and the image relation are deleted

#### Scenario: Menu with items
- **WHEN** a menu that has items is deleted
- **THEN** the deletion is rejected

#### Scenario: Empty menu
- **WHEN** a menu without items is deleted
- **THEN** the menu is deleted

### Requirement: Menu tree with fallback
The tree of visible items for the site SHALL support an explicit fallback mode: the title and the description of an item missing in the requested language SHALL be replaced by the `default` values, each independently. The address, order, nesting and visibility SHALL NOT depend on the language. Reading without the mode SHALL stay strict.

#### Scenario: Title falls back
- **WHEN** an item has no English title and the tree is requested in English with the fallback mode
- **THEN** the item shows its `default` title and keeps its place and address

#### Scenario: Partial translation
- **WHEN** an item has an English description only
- **THEN** its title is the `default` title and its description is the English one

#### Scenario: Strict tree
- **WHEN** the tree is requested in English without the mode and an item has no English title
- **THEN** the title reads as missing

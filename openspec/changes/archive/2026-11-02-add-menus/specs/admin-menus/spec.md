# Spec Delta

## Purpose

Gives the admin the section «Меню» for managing the site menus and the tree of their items: lists, forms, the visibility status by schedule, deletion with an explanation of the reason for refusal, and ordering by drag-and-drop. The section relies on the rules of the menu domain.

## ADDED Requirements

### Requirement: Доступ до розділу «Меню»
All pages of the section SHALL have the prefix `/admin/menus` and be accessible only to an authenticated user with a confirmed email (as the rest of the admin); a guest SHALL be redirected to `/login`. A menu SHALL be addressed by `key`, an item — by `uuid` within its menu; the key of a non-existent menu and the `uuid` of an item of another menu SHALL give a 404 response.

#### Scenario: Guest
- **WHEN** a guest opens `/admin/menus`
- **THEN** they are redirected to `/login`

#### Scenario: Item of a foreign menu
- **WHEN** `/admin/menus/main/items/{uuid}/edit` is opened, where the item belongs to the menu `footer`
- **THEN** the response is 404

### Requirement: Список меню
The page `/admin/menus` SHALL show a data table of menus with the columns: name, key, number of items and maximum depth (empty if there is no limit). The name and the key SHALL be not hideable. The search SHALL cover the name, the key and the description. Without an explicit sorting the rows SHALL go by name. Above the table there SHALL be the button «Створити». The row menu SHALL contain the actions «Пункти», «Редагувати» and «Видалити». An empty list SHALL show a message about the absence of data.

#### Scenario: Viewing menus
- **WHEN** the user opens `/admin/menus` when there is the menu «Головне» with 5 items
- **THEN** they see a row with the name «Головне», the key and the number of items 5

#### Scenario: Going to the items
- **WHEN** the user chooses the action «Пункти» in the menu of a row
- **THEN** `/admin/menus/{key}/items` opens

### Requirement: Створення меню
The admin SHALL have the form `/admin/menus/create` with the fields: key (required), name (required), description and maximum depth (an integer from 1; empty means no limit). The key SHALL match the format `^[a-z][a-z0-9_]*$`, up to 64 characters, and be unique; the name — up to 255 characters. After success the user SHALL land on the page of items of the new menu with a success message. Validation errors SHALL be shown next to the fields, the entered data SHALL be kept in the form. The form SHALL have a link «Скасувати» without changing the data.

#### Scenario: Successful creation
- **WHEN** the user enters the key `main`, the name «Головне меню» and saves
- **THEN** the menu is created, `/admin/menus/main/items` is opened and a success message is shown

#### Scenario: Occupied key
- **WHEN** the key matches the key of an existing menu
- **THEN** the form stays, an error appears next to the key, the entered data is kept

#### Scenario: Invalid depth
- **WHEN** the user enters the depth `0`
- **THEN** a validation error appears next to the depth field and the menu is not created

### Requirement: Редагування меню
The page `/admin/menus/{key}/edit` SHALL show the form with the current values; the key SHALL be read-only. Saving the changes SHALL update the name, the description and the maximum depth. A reduction of the depth below the actual depth of the tree SHALL be rejected with an error next to the depth field.

#### Scenario: Changing the name
- **WHEN** the user changes the name of the menu and saves
- **THEN** the change is saved, the key remained the same

#### Scenario: Depth below the actual one
- **WHEN** the menu has a tree of depth 3, and the user sets the depth 2
- **THEN** an error appears next to the depth field, the menu does not change

### Requirement: Видалення меню
The action «Видалити» SHALL open a confirmation dialog. A menu with items SHALL not be deleted: the dialog SHALL explain the reason and not offer confirmation, and the server SHALL reject the attempt with an error message. An empty menu SHALL be deleted, and the user SHALL land on the list of menus with a success message.

#### Scenario: Deleting an empty menu
- **WHEN** the user confirms the deletion of a menu without items
- **THEN** the menu is deleted, a success message is shown

#### Scenario: Menu with items
- **WHEN** the user tries to delete a menu that has items
- **THEN** the menu stays, a message with the reason for refusal is shown

### Requirement: Сторінка пунктів меню
The page `/admin/menus/{key}/items` SHALL show the items of the menu as a tree in a data table (client mode): the rows go in the order of the walk of the tree (roots by weight, under each of them its children by weight), the name SHALL have an indent by depth. Columns: name (in the base language), address (empty for a heading item), status («Опубліковано» — "Published", «Заплановано» — "Scheduled", «Завершено» — "Finished", «Вимкнено» — "Disabled"), weight and number of children. The status SHALL be computed for the item itself by its own flag and dates. Above the table there SHALL be a heading with the name of the menu, the link «Редагувати меню» ("Edit menu") and the button «Створити» ("Create", an item). The row menu SHALL contain the actions «Редагувати» ("Edit") and «Видалити» ("Delete"). An empty menu SHALL show a message about the absence of items.

#### Scenario: Indent by depth
- **WHEN** the menu has «Каталог» with a child «Куртки», which has a child «Зимові»
- **THEN** the rows go in the order «Каталог», «Куртки», «Зимові» with a growing indent of the name

#### Scenario: Statuses
- **WHEN** the menu has a disabled item, an item with a future `published_at` and an item with `unpublished_at` in the past
- **THEN** they are marked «Вимкнено», «Заплановано» and «Завершено» respectively

#### Scenario: Item without an address
- **WHEN** the menu has a heading item without an address
- **THEN** the address column in its row is empty

### Requirement: Створення пункту меню
The form `/admin/menus/{key}/items/create` SHALL have the fields: name in the base language (required, up to 255 characters), description, address, `target` (in the same window / in a new window), CSS class, image (optional; a file of the profile `image`), parent item (a choice among the items of the menu or «Без батька»), weight (an integer from 0), the flag «Активний» (enabled by default), the start date of display and the end date of display (optional, to the minute). The list of parents SHALL show the tree with indents and SHALL not offer items under which a new child would exceed the maximum depth. When multilingual support is enabled, the form SHALL contain for each active language separate name and description fields of the translation (an empty translation is not saved). The default weight SHALL equal the largest weight among the sibling items plus one. After success the user SHALL land on the page of items with a success message. Validation errors (in particular an invalid address and a contradictory interval of dates) SHALL be shown next to the corresponding fields.

#### Scenario: Creating a root
- **WHEN** the user enters the name «Про нас», the address `/about`, leaves the parent empty and saves
- **THEN** the item is created as a root, a success message is shown

#### Scenario: Child item
- **WHEN** the user chooses the parent «Каталог» and saves the item «Куртки»
- **THEN** «Куртки» is shown under «Каталог» with an indent

#### Scenario: Heading item
- **WHEN** the user saves an item without an address
- **THEN** the item is created, the address column is empty

#### Scenario: Invalid address
- **WHEN** the user enters the address `javascript:alert(1)`
- **THEN** an error appears next to the address field, the item is not created, the entered data is kept

#### Scenario: Contradictory schedule
- **WHEN** the end date of display is not later than the start date
- **THEN** an error appears next to the end date field

#### Scenario: Depth boundary
- **WHEN** the maximum depth of the menu is 2
- **THEN** among the possible parents there are no items of the second level

#### Scenario: Translations
- **WHEN** multilingual support is enabled, the language `en` is active, and the user enters the English name «About us»
- **THEN** the translation is saved, and the base name remains unchanged

#### Scenario: Without multilingual support
- **WHEN** multilingual support is disabled
- **THEN** the block of translations is absent from the form

### Requirement: Редагування пункту меню
The form `/admin/menus/{key}/items/{uuid}/edit` SHALL have the same fields with the current values. The list of parents SHALL not offer the item itself and its descendants, nor the items under which the subtree of the item would exceed the maximum depth. Saving SHALL update the item, its image and translations in one operation; clearing a translation SHALL delete it, clearing the image — remove the link with the file. The menu of an item cannot be changed. Disabling an item SHALL not erase its dates.

#### Scenario: Moving under another parent
- **WHEN** the user chooses the new parent «Акції» for «Куртки» and saves
- **THEN** «Куртки» is shown under «Акції»

#### Scenario: Parent among the descendants
- **WHEN** the user opens the form of the item «Каталог»
- **THEN** among the possible parents there are no «Каталог» and its descendants

#### Scenario: Disabling keeps the dates
- **WHEN** the user unticks the flag «Активний» of an item with the set dates and saves
- **THEN** the item has the status «Вимкнено», and its dates remained

#### Scenario: Clearing a translation
- **WHEN** the user clears the English name and description of an item
- **THEN** the English translation is deleted

#### Scenario: Image of an item
- **WHEN** the user chooses an image for the item from the media library and saves
- **THEN** when the form is reopened the image is shown, and the media library counts it as used

#### Scenario: File is not an image
- **WHEN** a file of another profile is specified as the image of the item
- **THEN** an error appears next to the field, the item is not changed

### Requirement: Видалення пункту з поясненням
The action «Видалити» SHALL open a confirmation dialog. An item with children SHALL not be deleted: the dialog SHALL explain that the child items must first be moved or deleted, and not offer confirmation, and the server SHALL reject the attempt with an error message. An item without children SHALL be deleted together with its translations, and the user SHALL land on the page of items with a success message.

#### Scenario: Item with children
- **WHEN** the user tries to delete an item that has children
- **THEN** the item stays, a message is shown that the child items must first be moved or deleted

#### Scenario: Leaf
- **WHEN** the user confirms the deletion of an item without children
- **THEN** the item is deleted, a success message is shown

### Requirement: Зміна порядку пунктів перетягуванням
The rows of the table of items SHALL be draggable by a handle (with the mouse and the keyboard), provided that all rows are shown without search, filters and sorting. After dropping, the new order SHALL be saved on the server: the weights of the items SHALL be rewritten according to the position of the row, and the parents remain unchanged; the parent can be changed only by the form of the item. A saving error SHALL return the previous order and show an error message. A request of the order SHALL be rejected if it does not contain exactly all items of the menu.

#### Scenario: Changing the order of siblings
- **WHEN** the user drags the root «А» before the root «Б»
- **THEN** after refreshing the page «А» goes before «Б»

#### Scenario: Incomplete list
- **WHEN** the server receives an order without one of the items of the menu
- **THEN** the request is rejected with a validation error, the order does not change

#### Scenario: Saving error
- **WHEN** the server rejects the new order
- **THEN** the table returns to the previous order, an error message is shown

### Requirement: Пункт меню в переліку використань медіатеки
The media library SHALL show a menu item among the owners that use a file: the label — the base name of the item, the link — to the edit form of the item `/admin/menus/{key}/items/{uuid}/edit`. For an already deleted owner the general rule of the media library SHALL apply (without a link).

#### Scenario: File in the image of an item
- **WHEN** the user opens the details of a file that is the image of the item «Про нас»
- **THEN** among the usages «Про нас» is shown with a link to the form of this item

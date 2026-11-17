# admin-menus Specification

## Purpose

Gives the admin panel a "Menus" section for managing the site menus and the tree of their items: lists, forms, the visibility status by schedule, deletion with an explanation of the reason for refusal, and drag-and-drop ordering. The section relies on the domain rules of menus.

## Requirements

### Requirement: Access to the "Menus" section
All pages of the section SHALL have the prefix `/admin/menus` and be available only to an authenticated user with a verified email (like the rest of the admin panel); a guest SHALL be redirected to `/login`. A menu SHALL be addressed by `key`, an item — by `uuid` within its menu; the key of a non-existent menu and the `uuid` of an item of another menu SHALL give a 404 response.

#### Scenario: Guest
- **WHEN** a guest opens `/admin/menus`
- **THEN** they are redirected to `/login`

#### Scenario: Item of another menu
- **WHEN** `/admin/menus/main/items/{uuid}/edit` is opened, where the item belongs to the menu `footer`
- **THEN** the response is 404

### Requirement: List of menus
The page `/admin/menus` SHALL show a data table of menus with the columns: name, key, number of items, and maximum depth (empty if unlimited). The name and the key SHALL be non-hideable. Search SHALL cover the name, the key, and the description. Without an explicit sort, the rows SHALL go by name. Above the table there SHALL be a "Create" button. The row menu SHALL contain the actions "Items", "Edit", and "Delete". An empty list SHALL show a no-data message.

#### Scenario: Viewing menus
- **WHEN** the user opens `/admin/menus` when there is a menu "Main" with 5 items
- **THEN** they see a row with the name "Main", the key, and the number of items 5

#### Scenario: Navigation to items
- **WHEN** the user selects the "Items" action in the row menu
- **THEN** `/admin/menus/{key}/items` opens

### Requirement: Creating a menu
The admin panel SHALL have the form `/admin/menus/create` with the fields: key (required), name (required), description, and maximum depth (an integer from 1; empty means unlimited). The key SHALL have the format `^[a-z][a-z0-9_]*$`, up to 64 characters, and be unique; the name — up to 255 characters. After success, the user SHALL land on the items page of the new menu with a success message. Validation errors SHALL be shown next to the fields, and the entered values SHALL be kept in the form. The form SHALL have a "Cancel" link without changes to the data.

#### Scenario: Successful creation
- **WHEN** the user enters the key `main`, the name "Main menu", and saves
- **THEN** the menu is created, `/admin/menus/main/items` is opened, and a success message is shown

#### Scenario: Taken key
- **WHEN** the key matches the key of an existing menu
- **THEN** the form remains, an error appears next to the key, and the entered text is saved

#### Scenario: Invalid depth
- **WHEN** the user enters the depth `0`
- **THEN** a validation error appears next to the depth field, and the menu is not created

### Requirement: Editing a menu
The page `/admin/menus/{key}/edit` SHALL show a form with the current values; the key SHALL be read-only. Saving the changes SHALL update the name, the description, and the maximum depth. Reducing the depth below the actual depth of the tree SHALL be rejected with an error next to the depth field.

#### Scenario: Changing the name
- **WHEN** the user changes the name of a menu and saves
- **THEN** the change is saved, and the key remains the same

#### Scenario: Depth below the actual one
- **WHEN** a menu has a tree of depth 3, and the user sets the depth 2
- **THEN** an error appears next to the depth field, and the menu does not change

### Requirement: Deleting a menu
The "Delete" action SHALL open a confirmation dialog. A menu with items SHALL not be deleted: the dialog SHALL explain the reason and not offer confirmation, and the server SHALL reject the attempt with an error message. An empty menu SHALL be deleted, and the user SHALL land on the list of menus with a success message.

#### Scenario: Deleting an empty menu
- **WHEN** the user confirms the deletion of a menu without items
- **THEN** the menu is deleted, and a success message is shown

#### Scenario: Menu with items
- **WHEN** the user attempts to delete a menu that has items
- **THEN** the menu remains, and a message about the reason for the refusal is shown

### Requirement: Items page of a menu
The page `/admin/menus/{key}/items` SHALL show the items of the menu as a tree in a data table (client mode): the rows go in the traversal order of the tree (roots by weight, under each its children by weight), and the name SHALL have an indent by depth. Columns: the name (in the base language), the address (empty for a heading item), the status (Published, Scheduled, Expired, Disabled), the weight, and the number of children. The status SHALL be computed for the item itself by its own flag and dates. Above the table there SHALL be a heading with the name of the menu, a link "Edit menu", and a "Create" button (for an item). The row menu SHALL contain the actions "Edit" and "Delete". An empty menu SHALL show a message that there are no items.

#### Scenario: Indent by depth
- **WHEN** the menu has "Catalog" with the child "Jackets", which has the child "Winter"
- **THEN** the rows go in the order "Catalog", "Jackets", "Winter" with an increasing indent of the name

#### Scenario: Statuses
- **WHEN** the menu has a disabled item, an item with a future `published_at`, and an item with `unpublished_at` in the past
- **THEN** they are marked "Disabled", "Scheduled", and "Expired" respectively

#### Scenario: Item without an address
- **WHEN** the menu has a heading item without an address
- **THEN** the address column in its row is empty

### Requirement: Creating a menu item
The form `/admin/menus/{key}/items/create` SHALL have the fields: the name in the base language (required, up to 255 characters), the description, the address, `target` (in the same window / in a new window), a CSS class, an image (optional; a file of the `image` profile), the parent item (a choice among the menu items or "No parent"), the weight (an integer from 0), the "Active" checkbox (enabled by default), the show-from date and the show-until date (optional, to the minute). The list of parents SHALL show the tree with indents and SHALL not offer the items under which the new child would exceed the maximum depth. The form SHALL NOT contain translations of any language: translations are entered in the translation mode of the edit form of an existing menu item. The default weight SHALL equal the largest weight among the sibling items plus one. After success, the user SHALL land on the items page with a success message. Validation errors (in particular an invalid address and a contradictory date interval) SHALL be shown next to the corresponding fields.

#### Scenario: Creating a root
- **WHEN** the user enters the name "About", the address `/about`, leaves the parent empty, and saves
- **THEN** the item is created as a root, and a success message is shown

#### Scenario: Sub-item
- **WHEN** the user selects the parent "Catalog" and saves the item "Jackets"
- **THEN** "Jackets" is displayed under "Catalog" with an indent

#### Scenario: Heading item
- **WHEN** the user saves an item without an address
- **THEN** the item is created, and the address column is empty

#### Scenario: Invalid address
- **WHEN** the user enters the address `javascript:alert(1)`
- **THEN** an error appears next to the address field, the item is not created, and the entered text is saved

#### Scenario: Contradictory schedule
- **WHEN** the show-until date is not later than the show-from date
- **THEN** an error appears next to the show-until date field

#### Scenario: Depth limit
- **WHEN** the maximum depth of the menu is 2
- **THEN** among the possible parents there are no items of the second level

#### Scenario: Translations
- **WHEN** multilingual mode is enabled, a language is active, and the user opens the form of a new menu item
- **THEN** the form has no translation fields and no language switcher, and the menu item can be translated after it is created, in the translation mode of its edit page

#### Scenario: Without multilingual mode
- **WHEN** multilingual mode is disabled
- **THEN** neither the create form nor the edit form shows a language switcher

### Requirement: Editing a menu item
The form `/admin/menus/{key}/items/{uuid}/edit` SHALL have the same fields with the current values. The list of parents SHALL not offer the item itself and its descendants, nor the items under which the item's subtree would exceed the maximum depth. Saving the base form SHALL update the item and its image in one operation and SHALL NOT change, create or delete any translation; clearing the image SHALL remove the relation with the file. Translations of the item SHALL be edited in the translation mode of this page, where the translatable fields are the title and the description; the address, `target`, CSS class, image, parent, weight, activity and dates are not translated. The menu of an item cannot be changed. Disabling an item SHALL NOT erase its dates.

#### Scenario: Moving under another parent
- **WHEN** the user selects a new parent "Sales" for "Jackets" and saves
- **THEN** "Jackets" is shown under "Sales"

#### Scenario: Parent among descendants
- **WHEN** the user opens the form of the item "Catalog"
- **THEN** among the possible parents there are no "Catalog" and its descendants

#### Scenario: Disabling keeps the dates
- **WHEN** the user unchecks the "Active" flag of an item with set dates and saves
- **THEN** the item has the status "Disabled", and its dates remain

#### Scenario: Image of an item
- **WHEN** the user selects an image for the item from the media library and saves
- **THEN** on reopening the form the image is shown, and the media library counts it as used

#### Scenario: File is not an image
- **WHEN** a file of another profile is specified as the image of the item
- **THEN** an error appears next to the field, and the item is not changed

#### Scenario: Base form keeps translations
- **WHEN** the user changes the base name of a menu item that has an English translation and saves the base form
- **THEN** the English translation is unchanged

#### Scenario: Translation mode fields of a menu item
- **WHEN** the user opens the edit page of a menu item with `?lang=en`
- **THEN** only the title and the description are shown, and the address, target, class, image, parent, weight, activity and dates are not

#### Scenario: Clearing a translation
- **WHEN** in the translation mode of `en` the user clears the translation of a menu item
- **THEN** the English translation is deleted

### Requirement: Deleting an item with an explanation
The "Delete" action SHALL open a confirmation dialog. An item with children SHALL not be deleted: the dialog SHALL explain that the child items must be moved or deleted first, and not offer confirmation, and the server SHALL reject the attempt with an error message. An item without children SHALL be deleted together with its translations, and the user SHALL land on the items page with a success message.

#### Scenario: Item with children
- **WHEN** the user attempts to delete an item with children
- **THEN** the item remains, and a message is shown that the child items must be moved or deleted first

#### Scenario: Leaf
- **WHEN** the user confirms the deletion of an item without children
- **THEN** the item is deleted, and a success message is shown

### Requirement: Changing the order of items by drag and drop
The rows of the items table SHALL be dragged by the handle (with the mouse and the keyboard) on condition that all rows are shown without search, filters, and sorting. After a drop, the new order SHALL be saved on the server: the weights of items SHALL be rewritten according to the position of the row, and the parents remain unchanged; the parent can be changed only by the item form. A save error SHALL restore the previous order and show an error message. An order request SHALL be rejected if it does not contain exactly all items of the menu.

#### Scenario: Changing the order of siblings
- **WHEN** the user drags the root "A" before the root "B"
- **THEN** after the page is updated, "A" comes before "B"

#### Scenario: Incomplete list
- **WHEN** the server receives an order without one of the items of the menu
- **THEN** the request is rejected with a validation error, and the order does not change

#### Scenario: Save error
- **WHEN** the server rejects the new order
- **THEN** the table returns to the previous order, and an error message is shown

### Requirement: Menu item in the media library usage list
The media library SHALL show a menu item among the owners that use a file: the label — the base name of the item, the link — to the item edit form `/admin/menus/{key}/items/{uuid}/edit`. For an owner that has already been deleted, the general media library rule SHALL apply (without a link).

#### Scenario: File as an image of an item
- **WHEN** the user opens the details of a file that is the image of the item "About"
- **THEN** "About" is shown among the usages with a link to the form of this item

### Requirement: One-click status toggle of a menu item
In the items list of a menu, the status badge of an item SHALL be a clickable control that toggles only `is_active` of that item, without a confirmation. A click on an item in the status "Published", "Scheduled" or "Expired" SHALL set `is_active = false`; a click on a "Disabled" item SHALL set `is_active = true`. `published_at`, `unpublished_at`, the title, the address, the parent and the weight SHALL stay unchanged, so after enabling, the badge shows the status that follows from the item's own dates. The change SHALL go through `PATCH /admin/menus/{menu}/items/{item}/active` with a boolean `is_active`, SHALL need the `menus` permission like the rest of the section, SHALL answer with a redirect back and a success message, and the item SHALL be addressed within its menu (an item of another menu gives 404). The control SHALL have an accessible name that says what the click does. The item form SHALL be unchanged.

#### Scenario: Disabling a published item
- **WHEN** the user clicks the "Published" badge of an item
- **THEN** `is_active` becomes false, the badge shows "Disabled", and the dates remain

#### Scenario: Enabling a disabled item
- **WHEN** the user clicks the "Disabled" badge of an item whose `unpublished_at` is in the past
- **THEN** `is_active` becomes true and the badge shows "Expired"

#### Scenario: User without the `menus` permission
- **WHEN** a user without `menus` sends `PATCH /admin/menus/{menu}/items/{item}/active`
- **THEN** the request is refused with 403 and the item is unchanged

#### Scenario: Item of another menu
- **WHEN** the request addresses an item that belongs to another menu
- **THEN** the response is 404

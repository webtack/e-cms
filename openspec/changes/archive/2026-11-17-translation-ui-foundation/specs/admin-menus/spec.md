# Spec Delta

## MODIFIED Requirements

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

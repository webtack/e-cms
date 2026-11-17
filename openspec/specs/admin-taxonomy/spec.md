# admin-taxonomy Specification

## Purpose

Gives the admin panel a "Taxonomy" section for managing vocabularies and the tree of terms: lists, forms, deletion with an explanation of the reason for refusal, and ordering. The section relies on the domain rules of taxonomy.

## Requirements

### Requirement: Access to the "Taxonomy" section
All pages of the section SHALL have the prefix `/admin/taxonomy` and be available only to an authenticated user with a verified email (like the rest of the admin panel); a guest SHALL be redirected to `/login`. A vocabulary SHALL be addressed by `key`, a term — by `uuid` within its vocabulary; the key of a non-existent vocabulary and the `uuid` of a term of another vocabulary SHALL give a 404 response.

#### Scenario: Guest
- **WHEN** a guest opens `/admin/taxonomy`
- **THEN** they are redirected to `/login`

#### Scenario: Term of another vocabulary
- **WHEN** `/admin/taxonomy/categories/terms/{uuid}/edit` is opened, where the term belongs to the vocabulary `tags`
- **THEN** the response is 404

### Requirement: List of vocabularies
The page `/admin/taxonomy` SHALL show a data table of vocabularies with the columns: name, key, number of terms, and maximum depth (empty if unlimited). The name and the key SHALL be non-hideable. Search SHALL cover the name, the key, and the description. Without an explicit sort, the rows SHALL go by name. Above the table there SHALL be a "Create" button. The row menu SHALL contain the actions "Terms", "Edit", and "Delete". An empty list SHALL show a no-data message.

#### Scenario: Viewing vocabularies
- **WHEN** the user opens `/admin/taxonomy` when there is a vocabulary "Categories" with 5 terms
- **THEN** they see a row with the name "Categories", the key, and the number of terms 5

#### Scenario: Navigation to terms
- **WHEN** the user selects the "Terms" action in the row menu
- **THEN** `/admin/taxonomy/{key}/terms` opens

### Requirement: Creating a vocabulary
The admin panel SHALL have the form `/admin/taxonomy/create` with the fields: key (required), name (required), description, and maximum depth (an integer from 1; empty means unlimited). The key SHALL have the format of content keys (lowercase Latin letters, digits, and `_`, starting with a letter, up to 64 characters) and be unique; the name — up to 255 characters. After success, the user SHALL land on the terms page of the new vocabulary with a success message. Validation errors SHALL be shown next to the fields, and the entered values SHALL be kept in the form. The form SHALL have a "Cancel" link without changes to the data.

#### Scenario: Successful creation
- **WHEN** the user enters the key `categories`, the name "Categories", and saves
- **THEN** the vocabulary is created, `/admin/taxonomy/categories/terms` is opened, and a success message is shown

#### Scenario: Taken key
- **WHEN** the key matches the key of an existing vocabulary
- **THEN** the form remains, an error appears next to the key, and the entered text is saved

#### Scenario: Invalid depth
- **WHEN** the user enters the depth `0`
- **THEN** a validation error appears next to the depth field, and the vocabulary is not created

### Requirement: Editing a vocabulary
The page `/admin/taxonomy/{key}/edit` SHALL show a form with the current values; the key SHALL be read-only. Saving the changes SHALL update the name, the description, and the maximum depth. Reducing the depth below the actual depth of the tree SHALL be rejected with an error next to the depth field.

#### Scenario: Changing the name
- **WHEN** the user changes the name of a vocabulary and saves
- **THEN** the change is saved, and the key remains the same

#### Scenario: Depth below the actual one
- **WHEN** a vocabulary has a tree of depth 3, and the user sets the depth 2
- **THEN** an error appears next to the depth field, and the vocabulary does not change

### Requirement: Deleting a vocabulary
The "Delete" action SHALL open a confirmation dialog. A vocabulary with terms, or a vocabulary referenced by a field of type "term", SHALL not be deleted: the user SHALL see a clear message about the reason (has terms / used by a field), and the vocabulary remains. An empty vocabulary that no one references SHALL be deleted, and the user SHALL land on the list of vocabularies with a success message.

#### Scenario: Deleting an empty vocabulary
- **WHEN** the user confirms the deletion of a vocabulary without terms that has no references
- **THEN** the vocabulary is deleted, and a success message is shown

#### Scenario: Vocabulary with terms
- **WHEN** the user confirms the deletion of a vocabulary that has terms
- **THEN** the vocabulary remains, and a message about the reason for the refusal is shown

### Requirement: Terms page of a vocabulary
The page `/admin/taxonomy/{key}/terms` SHALL show the terms of the vocabulary as a tree in a data table (client mode): the rows go in the traversal order of the tree (roots by weight, under each its children by weight), and the name SHALL have an indent by depth. Columns: the name (in the base language), `slug`, the weight, and the number of children. Above the table there SHALL be a heading with the name of the vocabulary, a link "Edit vocabulary", and a "Create" button (for a term). The row menu SHALL contain the actions "Edit" and "Delete". An empty vocabulary SHALL show a message that there are no terms.

#### Scenario: Indent by depth
- **WHEN** the vocabulary has "Clothing" with the child "Jackets", which has the child "Winter"
- **THEN** the rows go in the order "Clothing", "Jackets", "Winter" with an increasing indent of the name

#### Scenario: Order of sibling terms
- **WHEN** the roots "B" (weight 0) and "A" (weight 1)
- **THEN** "B" is shown before "A"

### Requirement: Creating a term
The form `/admin/taxonomy/{key}/terms/create` SHALL have the fields: the name in the base language (required, up to 255 characters), the description, an image (optional; a file of the `image` profile), `slug` (optional; an empty one is generated from the name), the parent term (a choice among the terms of the vocabulary or "No parent"), and the weight (an integer from 0). The list of parents SHALL show the tree with indents and SHALL not offer the terms under which the new child would exceed the maximum depth. The form SHALL NOT contain translations of any language: translations are entered in the translation mode of the edit form of an existing term. The default weight SHALL equal the largest weight among the sibling terms plus one. After success, the user SHALL land on the terms page with a success message.

#### Scenario: Creating a root
- **WHEN** the user enters the name "Clothing", leaves the parent empty, and saves
- **THEN** the term is created as a root with a slug generated from the name, and a success message is shown

#### Scenario: Sub-term
- **WHEN** the user selects the parent "Clothing" and saves the term "Jackets"
- **THEN** "Jackets" is displayed under "Clothing" with an indent

#### Scenario: Taken slug
- **WHEN** the user enters a slug that already exists in the vocabulary
- **THEN** an error appears next to the slug field, and the entered text is saved

#### Scenario: Depth limit
- **WHEN** the maximum depth of the vocabulary is 2
- **THEN** among the possible parents there are no terms of the second level

#### Scenario: Translations
- **WHEN** multilingual mode is enabled, a language is active, and the user opens the form of a new term
- **THEN** the form has no translation fields and no language switcher, and the term can be translated after it is created, in the translation mode of its edit page

#### Scenario: Without multilingual mode
- **WHEN** multilingual mode is disabled
- **THEN** neither the create form nor the edit form shows a language switcher

### Requirement: Editing a term
The form `/admin/taxonomy/{key}/terms/{uuid}/edit` SHALL have the same fields with the current values. The list of parents SHALL not offer the term itself and its descendants, nor the terms under which the term's subtree would exceed the maximum depth. Saving the base form SHALL update the term and its image in one operation and SHALL NOT change, create or delete any translation; clearing the image SHALL remove the relation with the file. Translations of the term SHALL be edited in the translation mode of this page, where the translatable fields are the name and the description. The vocabulary of a term cannot be changed.

#### Scenario: Moving under another parent
- **WHEN** the user selects a new parent "Footwear" for "Jackets" and saves
- **THEN** "Jackets" is shown under "Footwear"

#### Scenario: Parent among descendants
- **WHEN** the user opens the form of the term "Clothing"
- **THEN** among the possible parents there are no "Clothing" and its descendants

#### Scenario: Image of a term
- **WHEN** the user selects an image for the term from the media library and saves
- **THEN** on reopening the form the image is shown, and the media library counts it as used

#### Scenario: Replacing and clearing an image
- **WHEN** the user replaces the image of the term with another and then clears it
- **THEN** the relation with the previous file is removed, and after clearing the term has no image

#### Scenario: File is not an image
- **WHEN** a file of another profile is specified as the image of the term
- **THEN** an error appears next to the field, and the term is not changed

#### Scenario: Base form keeps translations
- **WHEN** the user changes the base name of a term that has an English translation and saves the base form
- **THEN** the English translation is unchanged

#### Scenario: Translation mode fields of a term
- **WHEN** the user opens the edit page of a term with `?lang=en`
- **THEN** only the name and the description are shown, and the slug, parent, weight and image are not

#### Scenario: Clearing a translation
- **WHEN** in the translation mode of `en` the user clears the translation of a term
- **THEN** the English translation is deleted

### Requirement: Deleting a term with an explanation
The "Delete" action SHALL open a confirmation dialog. A term with children, or a term used by field values, SHALL not be deleted: the dialog (or the message after an attempt) SHALL explain the reason; for usage it SHALL show the list of places (the field, the content type, and the number of values). A free leaf SHALL be deleted together with its translations, and the user SHALL land on the terms page with a success message.

#### Scenario: Term with children
- **WHEN** the user confirms the deletion of a term with children
- **THEN** the term remains, and a message is shown that the child terms must be moved or deleted first

#### Scenario: Term in use
- **WHEN** the user confirms the deletion of a term that is specified in the field `category` of two nodes
- **THEN** the term remains, and the message names the field `category` and the usage count 2

#### Scenario: Free leaf
- **WHEN** the user confirms the deletion of a term without children and usages
- **THEN** the term is deleted, and a success message is shown

### Requirement: Changing the order of terms by drag and drop
The rows of the terms table SHALL be dragged by the handle (with the mouse and the keyboard) on condition that all rows are shown without search, filters, and sorting. After a drop, the new order SHALL be saved on the server: the weights of terms SHALL be rewritten according to the position of the row, and the parents remain unchanged, so the order of sibling terms matches the order of their rows. A save error SHALL restore the previous order and show an error message. An order request SHALL be rejected if it does not contain exactly all terms of the vocabulary.

#### Scenario: Changing the order of siblings
- **WHEN** the user drags the root "A" before the root "B"
- **THEN** after the page is updated, "A" comes before "B"

#### Scenario: Incomplete list
- **WHEN** the server receives an order without one of the terms of the vocabulary
- **THEN** the request is rejected with a validation error, and the order does not change

#### Scenario: Save error
- **WHEN** the server rejects the new order
- **THEN** the table returns to the previous order, and an error message is shown

### Requirement: Term in the media library usage list
The media library SHALL show a term among the owners that use a file: the label — the base name of the term, the link — to the term edit form `/admin/taxonomy/{key}/terms/{uuid}/edit`. For an owner that has already been deleted, the general media library rule SHALL apply (without a link).

#### Scenario: File as an image of a term
- **WHEN** the user opens the details of a file that is the image of the term "Jackets"
- **THEN** "Jackets" is shown among the usages with a link to the form of this term

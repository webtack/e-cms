# Spec Delta

## Purpose

Gives the admin the section «Таксономія» for managing vocabularies and the tree of their terms: lists, forms, deletion with an explanation of the reason for refusal, and ordering. The section relies on the rules of the taxonomy domain.

## ADDED Requirements

### Requirement: Доступ до розділу «Таксономія»
All pages of the section SHALL have the prefix `/admin/taxonomy` and be accessible only to an authenticated user with a confirmed email (as the rest of the admin); a guest SHALL be redirected to `/login`. A vocabulary SHALL be addressed by `key`, a term — by `uuid` within its vocabulary; the key of a non-existent vocabulary and the `uuid` of a term of another vocabulary SHALL give a 404 response.

#### Scenario: Guest
- **WHEN** a guest opens `/admin/taxonomy`
- **THEN** they are redirected to `/login`

#### Scenario: Term of a foreign vocabulary
- **WHEN** `/admin/taxonomy/categories/terms/{uuid}/edit` is opened, where the term belongs to the vocabulary `tags`
- **THEN** the response is 404

### Requirement: Список словників
The page `/admin/taxonomy` SHALL show a data table of vocabularies with the columns: name, key, number of terms and maximum depth (empty if there is no limit). The name and the key SHALL be not hideable. The search SHALL cover the name, the key and the description. Without an explicit sorting the rows SHALL go by name. Above the table there SHALL be the button «Створити». The row menu SHALL contain the actions «Терміни», «Редагувати» and «Видалити». An empty list SHALL show a message about the absence of data.

#### Scenario: Viewing vocabularies
- **WHEN** the user opens `/admin/taxonomy` when there is the vocabulary «Категорії» with 5 terms
- **THEN** they see a row with the name «Категорії», the key and the number of terms 5

#### Scenario: Going to the terms
- **WHEN** the user chooses the action «Терміни» in the menu of a row
- **THEN** `/admin/taxonomy/{key}/terms` opens

### Requirement: Створення словника
The admin SHALL have the form `/admin/taxonomy/create` with the fields: key (required), name (required), description and maximum depth (an integer from 1; empty means no limit). The key SHALL have the format of content keys (lowercase Latin letters, digits and `_`, starting with a letter, up to 64 characters) and be unique; the name — up to 255 characters. After success the user SHALL land on the page of terms of the new vocabulary with a success message. Validation errors SHALL be shown next to the fields, the entered data SHALL be kept in the form. The form SHALL have a link «Скасувати» without changing the data.

#### Scenario: Successful creation
- **WHEN** the user enters the key `categories`, the name «Категорії» and saves
- **THEN** the vocabulary is created, `/admin/taxonomy/categories/terms` opens and a success message is shown

#### Scenario: Occupied key
- **WHEN** the key matches the key of an existing vocabulary
- **THEN** the form stays, an error appears next to the key, the entered data is kept

#### Scenario: Invalid depth
- **WHEN** the user enters the depth `0`
- **THEN** a validation error appears next to the depth field and the vocabulary is not created

### Requirement: Редагування словника
The page `/admin/taxonomy/{key}/edit` SHALL show the form with the current values; the key SHALL be read-only. Saving the changes SHALL update the name, the description and the maximum depth. A reduction of the depth below the actual depth of the tree SHALL be rejected with an error next to the depth field.

#### Scenario: Changing the name
- **WHEN** the user changes the name of the vocabulary and saves
- **THEN** the change is saved, the key remained the same

#### Scenario: Depth below the actual one
- **WHEN** the vocabulary has a tree of depth 3, and the user sets the depth 2
- **THEN** an error appears next to the depth field, the vocabulary does not change

### Requirement: Видалення словника
The action «Видалити» SHALL open a confirmation dialog. A vocabulary with terms, or a vocabulary that is referenced by a field of the type «термін», SHALL not be deleted: the user SHALL see a clear message with the reason (there are terms / it is used by a field), the vocabulary stays. An empty vocabulary that nobody references SHALL be deleted, and the user SHALL land on the list of vocabularies with a success message.

#### Scenario: Deleting an empty vocabulary
- **WHEN** the user confirms the deletion of a vocabulary without terms and without references
- **THEN** the vocabulary is deleted, a success message is shown

#### Scenario: Vocabulary with terms
- **WHEN** the user confirms the deletion of a vocabulary that has terms
- **THEN** the vocabulary stays, a message with the reason for refusal is shown

### Requirement: Сторінка термінів словника
The page `/admin/taxonomy/{key}/terms` SHALL show the terms of the vocabulary as a tree in a data table (client mode): the rows go in the order of the walk of the tree (roots by weight, under each of them its children by weight), the name SHALL have an indent by depth. Columns: name (in the base language), `slug`, weight and number of children. Above the table there SHALL be a heading with the name of the vocabulary, the link «Редагувати словник» and the button «Створити» (a term). The row menu SHALL contain the actions «Редагувати» and «Видалити». An empty vocabulary SHALL show a message about the absence of terms.

#### Scenario: Indent by depth
- **WHEN** the vocabulary has «Одяг» with a child «Куртки», which has a child «Зимові»
- **THEN** the rows go in the order «Одяг», «Куртки», «Зимові» with a growing indent of the name

#### Scenario: Order of sibling terms
- **WHEN** the roots «Б» (weight 0) and «А» (weight 1)
- **THEN** «Б» is shown before «А»

### Requirement: Створення терміна
The form `/admin/taxonomy/{key}/terms/create` SHALL have the fields: name in the base language (required, up to 255 characters), description, image (optional; a file of the profile `image`), `slug` (optional; an empty one is generated from the name), parent term (a choice among the terms of the vocabulary or «Без батька») and weight (an integer from 0). The list of parents SHALL show the tree with indents and SHALL not offer terms under which a new child would exceed the maximum depth. When multilingual support is enabled, the form SHALL contain for each active language separate name and description fields of the translation (an empty translation is not saved). The default weight SHALL equal the largest weight among the sibling terms plus one. After success the user SHALL land on the page of terms with a success message.

#### Scenario: Creating a root
- **WHEN** the user enters the name «Одяг», leaves the parent empty and saves
- **THEN** the term is created as a root with the slug generated from the name, a success message is shown

#### Scenario: Child term
- **WHEN** the user chooses the parent «Одяг» and saves the term «Куртки»
- **THEN** «Куртки» is shown under «Одяг» with an indent

#### Scenario: Occupied slug
- **WHEN** the user enters a slug that already exists in the vocabulary
- **THEN** an error appears next to the slug field, the entered data is kept

#### Scenario: Depth boundary
- **WHEN** the maximum depth of the vocabulary is 2
- **THEN** among the possible parents there are no terms of the second level

#### Scenario: Translations
- **WHEN** multilingual support is enabled, the language `en` is active, and the user enters the English name «Jackets»
- **THEN** the translation is saved, and the base name remains unchanged

#### Scenario: Without multilingual support
- **WHEN** multilingual support is disabled
- **THEN** the block of translations is absent from the form

### Requirement: Редагування терміна
The form `/admin/taxonomy/{key}/terms/{uuid}/edit` SHALL have the same fields with the current values. The list of parents SHALL not offer the term itself and its descendants, nor the terms under which the subtree of the term would exceed the maximum depth. Saving SHALL update the term, its image and translations in one operation; clearing a translation SHALL delete it, clearing the image — remove the link with the file. The vocabulary of a term cannot be changed.

#### Scenario: Moving under another parent
- **WHEN** the user chooses the new parent «Взуття» for «Куртки» and saves
- **THEN** «Куртки» is shown under «Взуття»

#### Scenario: Parent among the descendants
- **WHEN** the user opens the form of the term «Одяг»
- **THEN** among the possible parents there are no «Одяг» and its descendants

#### Scenario: Clearing a translation
- **WHEN** the user clears the English name and description of a term
- **THEN** the English translation is deleted

#### Scenario: Image of a term
- **WHEN** the user chooses an image for the term from the media library and saves
- **THEN** when the form is reopened the image is shown, and the media library counts it as used

#### Scenario: Replacing and clearing the image
- **WHEN** the user replaces the image of a term with another one, and then clears it
- **THEN** the link with the previous file is removed, after clearing the term has no image

#### Scenario: File is not an image
- **WHEN** a file of another profile is specified as the image of the term
- **THEN** an error appears next to the field, the term is not changed

### Requirement: Видалення терміна з поясненням
The action «Видалити» SHALL open a confirmation dialog. A term with children, or a term that is used by the values of fields, SHALL not be deleted: the dialog (or the message after the attempt) SHALL explain the reason; for usage it SHALL show the list of places (the field, the content type and the number of values). A free leaf SHALL be deleted together with its translations, and the user SHALL land on the page of terms with a success message.

#### Scenario: Term with children
- **WHEN** the user confirms the deletion of a term with children
- **THEN** the term stays, a message is shown that the child terms must first be moved or deleted

#### Scenario: Used term
- **WHEN** the user confirms the deletion of a term that is specified in the field `category` of two records
- **THEN** the term stays, the message names the field `category` and the number of usages 2

#### Scenario: Free leaf
- **WHEN** the user confirms the deletion of a term without children and usages
- **THEN** the term is deleted, a success message is shown

### Requirement: Зміна порядку термінів перетягуванням
The rows of the table of terms SHALL be draggable by a handle (with the mouse and the keyboard), provided that all rows are shown without search, filters and sorting. After dropping, the new order SHALL be saved on the server: the weights of the terms SHALL be rewritten according to the position of the row, and the parents remain unchanged, so the order of sibling terms corresponds to the order of their rows. A saving error SHALL return the previous order and show an error message. A request of the order SHALL be rejected if it does not contain exactly all terms of the vocabulary.

#### Scenario: Changing the order of siblings
- **WHEN** the user drags the root «А» before the root «Б»
- **THEN** after refreshing the page «А» goes before «Б»

#### Scenario: Incomplete list
- **WHEN** the server receives an order without one of the terms of the vocabulary
- **THEN** the request is rejected with a validation error, the order does not change

#### Scenario: Saving error
- **WHEN** the server rejects the new order
- **THEN** the table returns to the previous order, an error message is shown

### Requirement: Термін у переліку використань медіатеки
The media library SHALL show a term among the owners that use a file: the label — the base name of the term, the link — to the edit form of the term `/admin/taxonomy/{key}/terms/{uuid}/edit`. For an already deleted owner the general rule of the media library SHALL apply (without a link).

#### Scenario: File in the image of a term
- **WHEN** the user opens the details of a file that is the image of the term «Куртки»
- **THEN** among the usages «Куртки» is shown with a link to the form of this term

# admin-content-fields Specification

## Purpose

Gives the admin panel management of the fields of a content type: the list of fields in a table, creation, editing, and deletion, taking into account the rules of system fields and of fields with values.

## Requirements

### Requirement: Fields page of a type
The admin panel SHALL have the page `/admin/content-types/{key}/fields`, available only to authenticated users with a verified email, which shows the fields of a content type of kind `content` with this key, except the panel fields (`meta_*`, `section_*`; see the requirement about panel fields) in a data table in client mode: all fields are passed to the page, and search, sorting, pagination, and page size are performed by the browser without requests to the server. The table SHALL NOT have filters. Without an explicit sort the fields SHALL go by weight (`weight`), and for equal weight — by the order of creation. The page SHALL have no title of its own: the heading is the name of the type shown above the tabs of the section menu (see `admin-content-types`), and above the table there SHALL be a link back to the list of types. For a key that is not among the types of kind `content` (in particular a key of a type `page` or `block`), the page SHALL respond with 404.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/fields`
- **THEN** they see the table of fields of the type `news` and a heading with the name of the type above the tabs, with no title "Fields of “…”", and the table toolbar has no filter button

#### Scenario: Default order
- **WHEN** the type has the fields `title` (weight 0), `body` (weight 20), and `summary` (weight 10)
- **THEN** without sorting the table shows them in the order `title`, `summary`, `body`

#### Scenario: Search in the browser
- **WHEN** the user types text into the table search
- **THEN** the table narrows by the name or key of the field without a page reload and without a request to the server

#### Scenario: Type of another kind
- **WHEN** the user opens the fields page for the key of a type of kind `page`
- **THEN** the server responds with 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/fields`
- **THEN** they are redirected to the login page

#### Scenario: Panel fields are not shown
- **WHEN** the user opens the fields page of a type that has the fields `title`, `meta_title`, `section_title`, and `body`
- **THEN** the table has `title` and `body`, and there is no `meta_title` and `section_title`

### Requirement: Columns of the fields table
The table SHALL show for each field: the name, the key, the value type, the flags "multiple", "required", and "translatable", and the weight. System fields SHALL have a "System" mark under the name. The name and key columns SHALL be non-hideable. The flags SHALL be displayed as "Yes"/"No" in the interface language.

#### Scenario: Field title
- **WHEN** the user looks at the row of the field `title`
- **THEN** they see the type "string", the flags "required" and "translatable" with the value "Yes", "multiple" — "No", and the "System" mark

#### Scenario: Ordinary field
- **WHEN** the user looks at the row of an additional field without the system mark
- **THEN** there is no "System" mark in the row

### Requirement: Button to create a field
Above the fields table there SHALL be an active "Create" button in the toolbar, which opens the form for creating a field of this type.

#### Scenario: Navigation to the form
- **WHEN** the user clicks "Create" on the fields page of the type `news`
- **THEN** `/admin/content-types/news/fields/create` opens

### Requirement: Row action menu of a field
Each row of the fields table SHALL have a group of buttons: the main button "Edit" and next to it the "⋮" menu button. "Edit" SHALL open the edit form of this field. The "⋮" menu SHALL have the items "Edit" and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated). For a system field the "Delete" item SHALL be inactive; for a field that has values it remains active, but the dialog does not allow confirming the deletion (see "Deleting a field"). The main "Edit" button SHALL be active for any field, including a system one.

#### Scenario: Composition of the group
- **WHEN** the user sees the row of an ordinary field
- **THEN** the active "Edit" button and next to it the "⋮" menu button with an accessible name are visible in it

#### Scenario: Edit from the main button
- **WHEN** the user clicks "Edit" in the row of the field `summary` of the type `news`
- **THEN** `/admin/content-types/news/fields/summary/edit` opens

#### Scenario: Composition of the menu
- **WHEN** the user opens the "⋮" menu in the row of an ordinary field
- **THEN** they see the active items "Edit" and "Delete"

#### Scenario: System field
- **WHEN** the user sees the row of the field `title` and opens its "⋮" menu
- **THEN** the "Edit" button is active, the "Delete" item in the menu is inactive, and "Edit" is active

### Requirement: Form of creating a field
The admin panel SHALL have the page `/admin/content-types/{key}/fields/create` with the form: the key (required), the name (required), the value type (a required choice among the registered types), the checkboxes "multiple", "required", "translatable", and the weight. The default weight SHALL equal the largest weight of the fields of the type plus one. The form SHALL have a save button and a "Cancel" link that returns to the list of fields without changes. The labels, hints, and error messages SHALL be displayed in the interface language.

#### Scenario: Opening the form
- **WHEN** the user opens the form for creating a field of a type whose largest field has weight 20
- **THEN** they see the fields of the form, and the weight is pre-filled with the value 21

#### Scenario: Cancellation
- **WHEN** the user clicks "Cancel"
- **THEN** they return to the list of fields of the type, and no new field is created

### Requirement: Saving a new field
Submitting the creation form SHALL create a field in this type with the given values. The key SHALL have the format of field keys (lowercase Latin letters, digits, and `_`, starting with a letter, up to 64 characters), not match a reserved key (`uuid`, `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`, and also the keys of the system metadata and section fields: `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`), and be unique within the type (the same key in another type is admissible); the name — up to 255 characters; the value type SHALL be registered; the weight SHALL be an integer from 0 to 65535. Extra spaces at the edges of the name and key are trimmed. The created field SHALL NOT have the system flag, even if the request contains it. After success, the user SHALL land on the list of fields of the type with a success message, and the new field SHALL be in the table.

#### Scenario: Successful creation
- **WHEN** the user fills in the key `visits`, the name "Visits", the type "integer", and saves
- **THEN** the field is created, the user is returned to the list of fields with a success message, and `visits` is in the table

#### Scenario: Required values
- **WHEN** the user saves the form without a key or a name
- **THEN** the field is not created, and an error is shown next to the corresponding field

#### Scenario: Invalid key
- **WHEN** the user enters the key `My Field` or `1price`
- **THEN** the field is not created, and an error is shown next to the key field

#### Scenario: Reserved key
- **WHEN** the user enters the key `slug`, `uuid`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`, `meta_title`, or `section_cover`
- **THEN** the field is not created, and an error is shown next to the key field that the key is reserved

#### Scenario: Duplicate key within a type
- **WHEN** the user enters a key that already exists among the fields of this type (in particular `title`)
- **THEN** the field is not created, and an error is shown next to the key field

#### Scenario: Unregistered value type
- **WHEN** the request contains a value type that is not in the registry
- **THEN** the field is not created, and an error is shown next to the type field

#### Scenario: Invalid weight
- **WHEN** the user enters the weight -1 or 70000
- **THEN** the field is not created, and an error is shown next to the weight field

#### Scenario: Attempt to create a system field
- **WHEN** the creation request contains the system field flag
- **THEN** the field is created as non-system

### Requirement: Form of editing a field
The admin panel SHALL have the page `/admin/content-types/{key}/fields/{fieldKey}/edit` with the same form filled with the current values of the field. The key SHALL be read-only with a hint about immutability. The value type SHALL be blocked for every field with a hint about the immutability of the type; for a system field additionally "multiple" and "required" SHALL be blocked. For a field key that is not in this type, the page SHALL respond with 404.

#### Scenario: Opening the form
- **WHEN** the user selects "Edit" for the field `visits` of the type `news`
- **THEN** `/admin/content-types/news/fields/visits/edit` opens with the current values, and the key and the value type cannot be changed

#### Scenario: System field
- **WHEN** the user opens the form of the field `title`
- **THEN** the value type, "multiple", and "required" are blocked, and the name, "translatable", and the weight are available

#### Scenario: Field with values
- **WHEN** the user opens the form of a field that has values
- **THEN** the value type is blocked, and the other fields are available

#### Scenario: Field without values
- **WHEN** the user opens the form of a field that has no values
- **THEN** the value type is likewise blocked, and the other fields (name, weight, "multiple", "required", "translatable", settings) are available

#### Scenario: Unknown field
- **WHEN** the user opens the form for a key that is absent from the type
- **THEN** the server responds with 404

### Requirement: Saving changes of a field
Submitting the edit form SHALL update the name, the weight, and the flags of the field; the key, the owning type, and the value type SHALL NOT change. The values of the key and the owning type from the request are ignored. A value type with a value different from the field's SHALL be rejected with an error next to the type field regardless of the state of the form and of the presence of saved values; a request with the same type SHALL not be considered an error. The server SHALL also reject a change of multiplicity and requiredness of a system field. The validation of the name and the weight matches the validation of creation. After success, the user SHALL remain on the edit form of the field with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the weight of the field `visits` and saves (the form sends the same type)
- **THEN** the changes are saved, the user remains on the edit form of the field with a success message, and the form shows the new values

#### Scenario: Type of a field with values
- **WHEN** a request changes the value type of a field that has saved values
- **THEN** the changes are not saved, an error is shown next to the type field, and the values do not change

#### Scenario: Type of a field without values
- **WHEN** a request changes the value type of a field without saved values
- **THEN** the changes are not saved, an error is shown next to the type field, and the type remains the previous one

#### Scenario: Forbidden changes of title
- **WHEN** a request changes the multiplicity or requiredness of the field `title`
- **THEN** the changes are not saved, an error is shown, and the field remains the previous one

#### Scenario: Key in the request
- **WHEN** an edit request contains a different field key
- **THEN** the key does not change

### Requirement: Deleting a field
The user SHALL be able to delete a non-system field that has no saved values, through the "Delete" item of the row menu after confirmation in a dialog that names the field. The deletion SHALL be forbidden for the system field `title` and for a field that has saved values: the field remains unchanged, and the user sees a message about the reason. The dialog SHALL show the reason for the prohibition and not allow confirming, if the reason is known from the row data. The server SHALL check the prohibition by itself, regardless of the dialog. After success, the user SHALL remain on the list of fields of the type with a success message, and the field is absent from the table.

#### Scenario: Deleting a field without values
- **WHEN** the user selects "Delete" for a field without values and confirms
- **THEN** the field is deleted, it is absent from the table, and a success message is shown

#### Scenario: Field with values
- **WHEN** the user selects "Delete" for a field that has values
- **THEN** the dialog names the reason ("delete the field's values first") and has no confirm button; the field and the values remain

#### Scenario: Cancellation
- **WHEN** the user closes the dialog without confirmation
- **THEN** the field remains, and nothing changes

#### Scenario: System field
- **WHEN** a request to delete the field `title` arrives at the server
- **THEN** the field remains, and the user sees a message about the reason

#### Scenario: Request to delete a field with values
- **WHEN** a request to delete a field that has values arrives at the server (bypassing the dialog)
- **THEN** the field and the values remain, and the user sees a message about the reason

### Requirement: Changing the order of fields by drag and drop
The fields table SHALL allow changing the order of the fields by dragging the rows by the handle in the first cell (also from the keyboard). Dragging SHALL be available only when the whole list of fields is visible in its original order: without search, without column sorting, and all fields fit on one page; otherwise the handles are inactive with a hint about how to make dragging available. After the release, the row SHALL immediately take the new place, and the new order SHALL be saved on the server: the weights of all shown fields of the type are rewritten sequentially from 0 in the new order, and after reloading the page the order remains the same. If the save fails, the order in the table SHALL return to the previous one, and the user SHALL see an error. The server SHALL accept only a complete set of keys: exactly all shown fields of this type (without the panel fields), without repetitions and foreign keys. The system field `title` can also be moved.

#### Scenario: Dragging
- **WHEN** the user drags the field `body` above the field `summary`
- **THEN** `body` comes before `summary`, and after reloading the order is the same

#### Scenario: Weights after a change
- **WHEN** the order is saved for a type with three fields
- **THEN** their weights are 0, 1, 2 in the new order

#### Scenario: Search or sorting is active
- **WHEN** the user has entered a search or sorted the table by a column
- **THEN** the drag handles are inactive, and the order cannot be changed

#### Scenario: Save failure
- **WHEN** the server rejects the new order
- **THEN** the rows return to their previous places, and the user sees an error

#### Scenario: Incomplete or foreign set of keys
- **WHEN** an order without one of the fields of the type, with a repetition, or with a key that the type does not have arrives at the server
- **THEN** the order is rejected, and the weights are not changed

#### Scenario: Type of another kind
- **WHEN** a change-order request arrives for the key of a type of kind `page`
- **THEN** the server responds with 404

#### Scenario: Order does not touch panel fields
- **WHEN** the user rearranges the shown fields of the type
- **THEN** the values of the panel fields and their presence do not change, and a panel field key in the request is rejected as foreign

### Requirement: Settings of types and size for "image" and "file" fields
When the value type `image` or `file` is selected in the create or edit form of a field, the form SHALL show the settings: a choice of the allowed file types (among the types of the profile of this field type) and the maximum size in kilobytes. For the type `image` the form SHALL additionally show the maximum width and the maximum height in pixels; for the type `file` they are absent. Without a choice of types and without a size the rules of the whole profile apply, and empty width and height leave the bounds of the profile. For other types these settings SHALL NOT be shown. The server SHALL reject a type outside the profile and a size above the limit of the profile with an error next to the corresponding element, regardless of the state of the form; the maximum width and height SHALL be accepted only as positive integers, not greater than the bounds of the profile if the profile sets them, and SHALL be rejected for the field type `file`. A change of the settings SHALL be allowed also for a field that has saved values, and SHALL not change the values and files already saved.

#### Scenario: Settings appear only for file types
- **WHEN** the user selects the type `image` in the form of creating a field
- **THEN** the choice of file types from the list of types of the profile `image`, the maximum size field, the maximum width, and the maximum height appear, and for the type `string` there are none

#### Scenario: Saving the settings
- **WHEN** the user creates an `image` field, selects only PNG, and specifies the maximum size of 1024 KB
- **THEN** the field is saved, its settings contain `image/png` and `1024`, and when the edit form is opened they are selected

#### Scenario: Without settings
- **WHEN** the user creates a `file` field without selecting types and without specifying a size
- **THEN** the field is saved without restrictions beyond the profile `document`

#### Scenario: Type outside the profile
- **WHEN** the request creates an `image` field with the type `application/pdf`
- **THEN** the field is not created, and an error is shown next to the choice of types

#### Scenario: Size above the profile limit
- **WHEN** the request creates an `image` field with a size greater than the limit of the profile
- **THEN** the field is not created, and an error is shown next to the size field

#### Scenario: Narrowing for a field with values
- **WHEN** the user narrows the allowed types of a field that already has saved values
- **THEN** the changes are saved, and the existing values do not change

#### Scenario: Saving width and height
- **WHEN** the user creates an `image` field and specifies the maximum width 800 and the maximum height 600
- **THEN** the field is saved, its settings contain `800` and `600`, and they are shown when the edit form is opened

#### Scenario: Width above the profile bound
- **WHEN** the request creates an `image` field with a maximum width greater than the bound of the profile
- **THEN** the field is not created, and an error is shown next to the width field

#### Scenario: Invalid width
- **WHEN** the request creates an `image` field with the maximum width `0`, a negative one, or a non-numeric one
- **THEN** the field is not created, and an error is shown next to the width field

#### Scenario: Width for a `file` field
- **WHEN** the request creates a `file` field with a maximum width
- **THEN** the field is not created, and the settings are rejected

#### Scenario: A change of bounds does not affect existing files
- **WHEN** the user reduces the maximum width of an `image` field that already has saved values
- **THEN** the changes are saved, the existing files and values do not change, and the new bound applies to subsequent uploads

### Requirement: Short type labels in the settings of a file field
The list of allowed file types in the settings of the "image" and "file" fields SHALL show each type by the short extension label (for example, `pdf`, `docx`, `xlsx`) instead of the full MIME type. The saved settings of the field SHALL still contain the full MIME types, and the already saved fields SHALL be displayed with the same labeled types without changing the data.

#### Scenario: List of types
- **WHEN** the user opens the form of a field of type `file`
- **THEN** the list of allowed types shows `pdf`, `docx`, and `xlsx`, without long MIME strings

#### Scenario: Saving the choice
- **WHEN** the user selects the label `docx` and saves the field
- **THEN** the full MIME type of the `docx` document is stored in the settings of the field, and after reopening the form the label `docx` is checked

### Requirement: Description of a field
A content type field SHALL have an optional text description — an instruction for the node editor. The create and edit forms of a field SHALL contain the multiline field "Description" up to 1000 characters in length; an empty value (also one of spaces only) is stored as an absent description. The description SHALL be allowed to be changed also for system fields. Exceeding the length SHALL be rejected with an error next to the "Description" field without losing the entered text. The description does not affect the value of the field, its validation, or its storage. The node forms show the description after the label of the field.

#### Scenario: Creating a field with a description
- **WHEN** the user creates a field and enters a description
- **THEN** the field is saved with the description, and the edit form of the field shows it

#### Scenario: Clearing the description
- **WHEN** the user erases the description in the edit form of the field and saves
- **THEN** the field has no description

#### Scenario: Too long description
- **WHEN** the user enters a description longer than 1000 characters and saves
- **THEN** saving is rejected, an error is shown next to the "Description" field, and the entered text is kept

#### Scenario: System field
- **WHEN** the user edits the system field `title` and sets a description
- **THEN** the description is saved, and the other restrictions of the system field remain in force

#### Scenario: Field without a description
- **WHEN** a field is created without a description
- **THEN** it is saved without a description, and the node form shows no helper text

### Requirement: Maximum number of elements of a multiple field
A multiple field of a content type SHALL have the setting "Maximum number of items" — an integer from 0 to 65535; `0` (default) means no limit. The create and edit forms of a field SHALL show this setting only when the checkbox "Multiple" is enabled, with a hint that `0` means no limit. For a single-value field the setting SHALL be ignored and stored as `0`. An invalid value (negative, not a number, greater than 65535) SHALL be rejected with an error next to the field without losing the entered text. The setting SHALL be the same for all value types, including `image` and `file`.

#### Scenario: Default setting
- **WHEN** the user creates a multiple field and does not change "Maximum number of items"
- **THEN** the field is saved with `0`, and the edit form shows `0`

#### Scenario: Setting a limit
- **WHEN** the user creates a multiple `image` field with the limit 5
- **THEN** the field is saved with the limit 5, and the edit form shows 5

#### Scenario: Setting is hidden for a single-value field
- **WHEN** the checkbox "Multiple" is turned off
- **THEN** the field "Maximum number of items" is not shown

#### Scenario: Reset when multiplicity is turned off
- **WHEN** the user turns off "Multiple" in a field with the limit 5 and saves
- **THEN** the field is saved without a limit (`0`)

#### Scenario: Invalid value
- **WHEN** the user enters a negative number and saves
- **THEN** saving is rejected, an error is shown next to the field, and the entered text is kept

### Requirement: Panel fields are not configured through the "Fields" tab
The system panel fields — `meta_title`, `meta_description`, `meta_image` and `section_title`, `section_description`, `section_cover` — are configured by the system, and the user SHALL NOT configure them: they SHALL be absent from the fields table of a type of any kind, and the edit page, saving changes, and deletion of such a field SHALL respond with 404. The system field `title` remains in the table and is configured as before. The values of panel fields are edited only on the "Section" and "SEO" tabs of the type and in the "SEO" section of the node; hiding does not change the fields themselves, their values, or the possibility of using `meta_*` as a column of the node table.

#### Scenario: Page type
- **WHEN** the user opens the fields page of a type of kind `page`
- **THEN** the `meta_*` fields are not shown in the table

#### Scenario: Direct opening of the edit form
- **WHEN** the user opens `/admin/content-types/news/fields/meta_title/edit`
- **THEN** the server responds with 404

#### Scenario: Saving and deletion
- **WHEN** a change or a deletion of the field `section_title` arrives at the server
- **THEN** the server responds with 404, and the field and its values remain unchanged

#### Scenario: Column of the node table
- **WHEN** the list settings of the type refer to `meta_title`
- **THEN** the field is available as a column of the node table, as before

### Requirement: "Term" field in the field form
The create and edit forms of a field of a `content` type SHALL offer the value type "term" among the registered types. The forms of page and block types SHALL NOT offer "term", and the server SHALL reject `type = term` for fields of such types with an error next to the type selection. For this type the form SHALL show a required choice of a vocabulary (among the existing ones; if there are no vocabularies, the form SHALL explain that a vocabulary must be created first, with a link to the "Taxonomy" section). Saving a field of type "term" without a vocabulary or with a non-existent vocabulary SHALL be rejected with an error next to the choice of vocabulary. The choice of vocabulary SHALL be blocked in the edit form if the field already has values; the server SHALL reject a change of the vocabulary of such a field. For other types the choice of vocabulary SHALL not be shown, and the server SHALL reject the setting `vocabulary` for them.

#### Scenario: Creating a "term" field
- **WHEN** the user creates a field `category` of type "term" and selects the vocabulary "Categories"
- **THEN** the field is created, and in the list of fields it has the type "term"

#### Scenario: Type without a vocabulary
- **WHEN** the user selects the type "term" and saves without selecting a vocabulary
- **THEN** a validation error appears next to the vocabulary choice, and the field is not created

#### Scenario: No vocabularies
- **WHEN** the user selects the type "term", and there is no vocabulary at all
- **THEN** the form shows an explanation and a link to the "Taxonomy" section

#### Scenario: Blocked vocabulary
- **WHEN** the user opens the edit of a "term" field that has values
- **THEN** the choice of vocabulary is blocked

#### Scenario: Page or block type
- **WHEN** the user opens the form of a field of a page or block type
- **THEN** "term" is absent among the types, and a POST with `type=term` is rejected by validation

#### Scenario: Other type
- **WHEN** the user selects the type "string"
- **THEN** the choice of vocabulary is not shown

### Requirement: Settings of reference fields
The field form SHALL show for the type `type_ref` a required choice of the kind of types (`content`, `page`, `block`), and for the type `node_ref` — a required choice of the node type (a search among all types by name and key). After values appear in the field, the choice SHALL be blocked with an explanation. Server errors SHALL be shown next to the corresponding choice. These types SHALL NOT have any other settings.

#### Scenario: Creating a `type_ref` field
- **WHEN** the admin selects the type `type_ref`, the kind `block`, and saves
- **THEN** the field is created with `settings.kind = block`

#### Scenario: Creating a `node_ref` field
- **WHEN** the admin selects the type `node_ref`, the node type `article`, and saves
- **THEN** the field is created with `settings.content_type = article`

#### Scenario: Required choice
- **WHEN** the admin saves a `type_ref` / `node_ref` field without a choice
- **THEN** an error is shown next to the choice

#### Scenario: Field with values
- **WHEN** the admin opens a field that already has values
- **THEN** the choice of kind / type is blocked with an explanation

### Requirement: Hiding the label of a field
A content type field SHALL have the setting "Hide label" — a boolean, off by default. The create and edit forms of a field SHALL contain the checkbox "Hide label" with a hint that it only affects the public page: when it is on, the public templates do not print the name of the field as a label. The setting SHALL be available for every value type, including system fields, and SHALL be changeable for a field that has values. The setting does not affect the value of the field, its validation, or its storage, and the node forms in the admin panel SHALL keep showing the label of the field.

#### Scenario: Default setting
- **WHEN** the user creates a field and does not touch "Hide label"
- **THEN** the field is saved with the label shown, and the edit form shows the checkbox off

#### Scenario: Hiding the label of a new field
- **WHEN** the user creates a field with "Hide label" on
- **THEN** the field is saved with the label hidden, and the edit form shows the checkbox on

#### Scenario: Showing the label again
- **WHEN** the user turns "Hide label" off in the edit form of a field and saves
- **THEN** the field is saved with the label shown

#### Scenario: System field
- **WHEN** the user turns "Hide label" on for the system field `title`
- **THEN** the setting is saved, and the other restrictions of the system field remain in force

#### Scenario: The node form keeps the label
- **WHEN** a field has "Hide label" on and its node is edited in the admin panel
- **THEN** the node form shows the label of the field as for any other field

### Requirement: Translation of a field label
The edit page of a field SHALL follow the translation mode contract for the label of the field, the only translatable attribute of a field. In the translation mode the form SHALL keep the layout of the base form with only the label editable, prefilled with the stored translation or with the base name when none exists, with a maximum length of 255 characters. Saving SHALL store the label of that language, SHALL NOT store a label equal to the base name and SHALL remove the label of that language when it is emptied. The key, the description, the type, the flags, the weight and the settings of the field SHALL stay in place, shown disabled with their base values, and SHALL NOT be changed. Translated labels SHALL be available for every value type, including system fields. The right to translate SHALL be the right to configure the type.

#### Scenario: Translating a label
- **WHEN** the user opens a field with `?lang=en`, changes the label to "Opening hours" and saves
- **THEN** the English label is stored and the base name is unchanged

#### Scenario: Label equal to the name
- **WHEN** the user saves the English form without changing the prefilled label
- **THEN** no English label is stored

#### Scenario: Emptied label
- **WHEN** the user empties a stored English label and saves
- **THEN** the English label is removed

#### Scenario: Base form keeps labels
- **WHEN** the user saves the base form of a field that has translated labels, whatever its value type
- **THEN** the translated labels are unchanged

### Requirement: Labels are exempt from the settings restrictions of a value type
The per-type restrictions on field `settings` (which keys a type accepts, and which a type rejects) SHALL NOT apply to the translated labels, which are kept in the same settings. Every value type SHALL be able to hold translated labels, and a change of the settings of a type or the creation of a field SHALL NOT remove them. Changing only the translated labels SHALL NOT trigger the type's own settings checks.

#### Scenario: Type without settings
- **WHEN** a `string` field, which accepts no settings, gets a translated label
- **THEN** the label is stored and the field's other settings stay empty

#### Scenario: Type with settings
- **WHEN** the user changes the allowed file types of an `image` field that has translated labels
- **THEN** the translated labels are unchanged

#### Scenario: Field with stored values
- **WHEN** a translated label is saved for a `term` field that has values
- **THEN** the save succeeds and the vocabulary stays locked

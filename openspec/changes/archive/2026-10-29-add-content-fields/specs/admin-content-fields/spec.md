# Spec Delta

## Purpose

Gives the admin the management of the fields of a content type: the list of fields in a table, creation, editing and deletion, subject to the rules of the system field and of fields with values.

## ADDED Requirements

### Requirement: Fields page of a type
The admin SHALL have a page `/admin/content-types/{key}/fields`, available only to authenticated users with a confirmed email, which shows all fields of the content type of the `content` kind with this key in the data table in client mode: all fields are passed to the page, and search, sorting, pagination and page size are performed by the browser without requests to the server. The table SHALL NOT have filters. Without an explicit sorting the fields SHALL follow their weight (`weight`), and with equal weight — the creation order. The page title SHALL name the type, and above the table SHALL be a link back to the list of types. For a key that is not among the types of the `content` kind (including a key of a `page` or `block` type), the page SHALL respond 404.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/fields`
- **THEN** they see the table of fields of the type `news` and a title with the name of the type, and the table toolbar has no filter button

#### Scenario: Default order
- **WHEN** the type has the fields `title` (weight 0), `body` (weight 20) and `summary` (weight 10)
- **THEN** without sorting the table shows them in the order `title`, `summary`, `body`

#### Scenario: Search in the browser
- **WHEN** the user types text into the table search
- **THEN** the table is narrowed by the name or key of the field without a page reload and without a request to the server

#### Scenario: A type of another kind
- **WHEN** the user opens the fields page for the key of a type of the `page` kind
- **THEN** the server responds 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/fields`
- **THEN** they are redirected to the login page

### Requirement: Columns of the fields table
The table SHALL show for each field: the name, the key, the value type, the flags "multiple", "required" and "translatable", and the weight. System fields SHALL have a "System" note under the name. The name and key columns SHALL be non-hideable. The flags SHALL be displayed as "Yes"/"No" in the interface language.

#### Scenario: The title field
- **WHEN** the user looks at the row of the field `title`
- **THEN** they see the type "string", the flags "required" and "translatable" with the value "Yes", "multiple" — "No", and the "System" note

#### Scenario: An ordinary field
- **WHEN** the user looks at the row of an additional field without the system flag
- **THEN** there is no "System" note in the row

### Requirement: Create field button
Above the fields table there SHALL be an active "Create" button in the toolbar, which opens the form for creating a field of this type.

#### Scenario: Going to the form
- **WHEN** the user clicks "Create" on the fields page of the type `news`
- **THEN** `/admin/content-types/news/fields/create` opens

### Requirement: Field row action menu
Each row of the fields table SHALL have a "⋮" menu with the items "Edit" and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated). For a system field the "Delete" item SHALL be inactive; for a field that has values it stays active, but the dialog does not allow confirming the deletion (see "Deleting a field").

#### Scenario: Menu content
- **WHEN** the user opens the "⋮" menu in the row of an ordinary field
- **THEN** they see the active items "Edit" and "Delete"

#### Scenario: System field
- **WHEN** the user opens the "⋮" menu in the row of the field `title`
- **THEN** the "Delete" item is inactive, and "Edit" is active

### Requirement: Create field form
The admin SHALL have a page `/admin/content-types/{key}/fields/create` with a form: key (required), name (required), value type (a required choice among the registered types), the flags "multiple", "required", "translatable" and weight. The default weight SHALL equal the highest weight of the fields of the type plus one. The form SHALL have a save button and a "Cancel" link, which returns to the list of fields without changes. Labels, hints and error messages SHALL be displayed in the interface language.

#### Scenario: Opening the form
- **WHEN** the user opens the create-field form of a type whose highest field has weight 20
- **THEN** they see the form fields, and the weight is pre-filled with 21

#### Scenario: Cancel
- **WHEN** the user clicks "Cancel"
- **THEN** they return to the list of fields of the type, and no new field is created

### Requirement: Saving a new field
Submitting the create form SHALL create a field in this type with the given values. The key SHALL match the format of field keys (lowercase Latin letters, digits and `_`, starts with a letter, up to 64 characters) and be unique within the type (the same key in another type is allowed); the name — up to 255 characters; the value type SHALL be registered; the weight SHALL be an integer from 0 to 65535. Extra spaces at the edges of the name and key are trimmed. The created field SHALL NOT have the system flag, even if the request contains it. After success the user SHALL land on the list of fields of the type with a success message, and the new field SHALL be in the table.

#### Scenario: Successful creation
- **WHEN** the user fills in the key `views`, the name "Views", the type "integer" and saves
- **THEN** the field is created, the user is returned to the list of fields with a success message, and `views` is in the table

#### Scenario: Required values
- **WHEN** the user saves the form without a key or a name
- **THEN** the field is not created, and an error is shown next to the corresponding field

#### Scenario: Invalid key
- **WHEN** the user enters the key `My Field` or `1price`
- **THEN** the field is not created, and an error is shown next to the key field

#### Scenario: Duplicate key in a type
- **WHEN** the user enters a key that is already among the fields of this type (including `title`)
- **THEN** the field is not created, and an error is shown next to the key field

#### Scenario: Unregistered value type
- **WHEN** the request contains a value type that is not in the registry
- **THEN** the field is not created, and an error is shown next to the type field

#### Scenario: Invalid weight
- **WHEN** the user enters the weight -1 or 70000
- **THEN** the field is not created, and an error is shown next to the weight field

#### Scenario: Attempt to create a system field
- **WHEN** the create request contains the system field flag
- **THEN** the field is created as a non-system field

### Requirement: Edit field form
The admin SHALL have a page `/admin/content-types/{key}/fields/{fieldKey}/edit` with the same form, filled with the current values of the field. The key SHALL be read-only with a hint about its immutability. For a system field the value type, "multiple" and "required" SHALL be blocked; for a field that has stored values, the value type SHALL be blocked. For a field key that does not exist in this type, the page SHALL respond 404.

#### Scenario: Opening the form
- **WHEN** the user chooses "Edit" for the field `views` of the type `news`
- **THEN** `/admin/content-types/news/fields/views/edit` opens with the current values, and the key cannot be changed

#### Scenario: System field
- **WHEN** the user opens the form of the field `title`
- **THEN** the value type, "multiple" and "required" are blocked, and the name, "translatable" and weight are available

#### Scenario: Field with values
- **WHEN** the user opens the form of a field for which values exist
- **THEN** the value type is blocked, the other fields are available

#### Scenario: Unknown field
- **WHEN** the user opens the form for a key that does not exist in the type
- **THEN** the server responds 404

### Requirement: Saving changes to a field
Submitting the edit form SHALL update the name, weight, flags and value type of the field; the key and the owner type SHALL NOT change, and the values of these parameters from the request are ignored. The server SHALL reject a change of the value type, multiplicity and requiredness of a system field, and a change of the value type of a field that has stored values, with an error next to the corresponding field, regardless of the state of the form. The validation of name and weight matches the validation of creation. After success the user SHALL land on the list of fields of the type with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the weight of the field `views` and saves
- **THEN** the changes are saved, the user is returned to the list of fields with a success message, and the table shows the new values

#### Scenario: Type of a field with values
- **WHEN** the request changes the value type of a field that has stored values
- **THEN** the changes are not saved, an error is shown next to the type field, and the values do not change

#### Scenario: Type of a field without values
- **WHEN** the user changes the value type of a field without stored values
- **THEN** the new type is saved

#### Scenario: Forbidden changes to title
- **WHEN** the request changes the type, multiplicity or requiredness of the field `title`
- **THEN** the changes are not saved, an error is shown, and the field remains as it was

#### Scenario: Key in the request
- **WHEN** the edit request contains a different field key
- **THEN** the key does not change

### Requirement: Deleting a field
The user SHALL be able to delete a non-system field that has no stored values, via the "Delete" item of the row menu after confirmation in a dialog that names the field. Deletion SHALL be forbidden for the system field `title` and for a field that has stored values: the field stays unchanged, and the user sees a message with the reason. The dialog SHALL show the reason for the ban and SHALL NOT allow confirming the deletion if the reason is known from the row data. The server SHALL check the ban itself, regardless of the dialog. After success the user SHALL stay on the list of fields of the type with a success message, and the field is not in the table.

#### Scenario: Deleting a field without values
- **WHEN** the user chooses "Delete" for a field without values and confirms
- **THEN** the field is deleted, it is not in the table, and a success message is shown

#### Scenario: Field with values
- **WHEN** the user chooses "Delete" for a field that has values
- **THEN** the dialog names the reason ("delete the field's values first") and has no confirm button; the field and the values remain

#### Scenario: Cancel
- **WHEN** the user closes the dialog without confirming
- **THEN** the field remains, and nothing changes

#### Scenario: System field
- **WHEN** a request to delete the field `title` arrives at the server
- **THEN** the field remains, and the user sees a message with the reason

#### Scenario: Request to delete a field with values
- **WHEN** a request to delete a field that has values arrives at the server (bypassing the dialog)
- **THEN** the field and the values remain, and the user sees a message with the reason

### Requirement: Changing the order of fields by dragging
The fields table SHALL allow changing the order of fields by dragging the rows via the handle in the first cell (also with the keyboard). Dragging SHALL be available only when the whole list of fields is visible in its original order: without search, without sorting by a column, and all fields fit on one page; otherwise the handles are inactive with a hint on how to make dragging available. After release the row SHALL immediately take the new place, and the new order SHALL be saved on the server: the weights of all fields of the type are rewritten sequentially from 0 in the new order, and after a page reload the order remains the same. If saving fails, the order in the table SHALL return to the previous one, and the user SHALL see an error. The server SHALL accept only a complete set of keys: exactly all fields of this type, without duplicates and foreign keys. The system field `title` may also be moved.

#### Scenario: Dragging
- **WHEN** the user drags the field `body` above the field `summary`
- **THEN** `body` comes before `summary`, and after a reload the order is the same

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
- **WHEN** an order arrives at the server without one of the fields of the type, with a repeat, or with a key that does not exist in the type
- **THEN** the order is rejected, and the weights are not changed

#### Scenario: Type of another kind
- **WHEN** a request to change the order arrives for the key of a type of the `page` kind
- **THEN** the server responds 404

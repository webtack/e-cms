## MODIFIED Requirements

### Requirement: Field edit form
The admin SHALL have the page `/admin/content-types/{key}/fields/{fieldKey}/edit` with the same form, filled with the current values of the field. The key SHALL be read-only with a hint about its immutability. The value type SHALL be locked for every field with a hint about the immutability of the type; for a system field, "multiple" and "required" SHALL additionally be locked. For a field key that does not exist in this type, the page SHALL respond with 404.

#### Scenario: Opening the form
- **WHEN** the user selects "Edit" for the field `views` of the type `news`
- **THEN** `/admin/content-types/news/fields/views/edit` opens with the current values, and the key and the value type cannot be changed

#### Scenario: System field
- **WHEN** the user opens the form of the field `title`
- **THEN** the value type, "multiple" and "required" are locked, and the name, "translatable" and weight are available

#### Scenario: Field with values
- **WHEN** the user opens the form of a field for which values exist
- **THEN** the value type is locked, the other fields are available

#### Scenario: Field without values
- **WHEN** the user opens the form of a field for which there are no values
- **THEN** the value type is locked as well, the other fields (name, weight, "multiple", "required", "translatable", settings) are available

#### Scenario: Unknown field
- **WHEN** the user opens the form for a key that does not exist in the type
- **THEN** the server responds with 404

### Requirement: Saving changes of a field
Submitting the edit form SHALL update the name, the weight and the flags of the field; the key, the owner type and the value type SHALL NOT change. The values of the key and the owner type from the request are ignored. A value type different from the current one of the field SHALL be rejected with an error next to the type field regardless of the state of the form and of the presence of stored values; a request with the same type SHALL not be considered an error. The server SHALL also reject a change of multiplicity and required status of a system field. The validation of the name and the weight matches the validation of creation. After success the user SHALL land on the list of fields of the type with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name of the field `views` and the weight and saves (the form sends the same type)
- **THEN** the changes are saved, the user is returned to the list of fields with a success message, and the table shows the new values

#### Scenario: Type of a field with values
- **WHEN** the request changes the value type of a field that has stored values
- **THEN** the changes are not saved, an error is shown next to the type field, and the values do not change

#### Scenario: Type of a field without values
- **WHEN** the request changes the value type of a field without stored values
- **THEN** the changes are not saved, an error is shown next to the type field, and the type remains the previous one

#### Scenario: Forbidden changes of title
- **WHEN** the request changes the multiplicity or the required status of the field `title`
- **THEN** the changes are not saved, an error is shown, and the field remains as before

#### Scenario: Key in the request
- **WHEN** the edit request contains a different field key
- **THEN** the key does not change

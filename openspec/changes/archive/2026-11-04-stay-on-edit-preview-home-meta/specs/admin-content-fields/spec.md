# Spec Delta

## MODIFIED Requirements

### Requirement: Збереження змін поля
Submitting the edit form SHALL update the name, the weight and the flags of the field; the key, the owner type and the value type SHALL NOT change. The values of the key and of the owner type from the request are ignored. A value type different from the one of the field SHALL be rejected with an error next to the type field regardless of the state of the form and of the presence of saved values; a request with the same type SHALL not be considered an error. The server SHALL also reject a change of the multiplicity and the requiredness of a system field. The validation of the name and the weight matches the validation of the creation. After success the user SHALL stay on the edit form of the field with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name of the field `views` and the weight and saves (the form sends the same type)
- **THEN** the changes are saved, the user stayed on the edit form of the field with a success message, and the form shows the new values

#### Scenario: Type of a field with values
- **WHEN** the request changes the value type of a field that has saved values
- **THEN** the changes are not saved, an error is shown next to the type field, the values do not change

#### Scenario: Type of a field without values
- **WHEN** the request changes the value type of a field without saved values
- **THEN** the changes are not saved, an error is shown next to the type field, the type remains the previous one

#### Scenario: Forbidden changes of title
- **WHEN** the request changes the multiplicity or the requiredness of the field `title`
- **THEN** the changes are not saved, an error is shown, the field remains the previous one

#### Scenario: Key in the request
- **WHEN** the edit request contains a different key of the field
- **THEN** the key does not change

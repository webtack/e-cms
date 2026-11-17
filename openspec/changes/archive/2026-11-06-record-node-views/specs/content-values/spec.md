# Spec Delta

## MODIFIED Requirements

### Requirement: Single write mechanism
The system SHALL write the values of a node by a single mechanism by the field keys of the content type and the language, in a transaction, replacing the existing values of the specified fields in the specified language and not changing the values of other fields and other languages.

#### Scenario: Partial update
- **WHEN** only the field `title` of a node that also has the field `visits` is updated
- **THEN** the value of `title` is replaced, and the value of `visits` remains unchanged

#### Scenario: Error during writing
- **WHEN** the writing of the values of a node fails on the validation of any field
- **THEN** none of the values of this node is saved

#### Scenario: Unknown field
- **WHEN** the request contains a key that does not exist among the fields of the content type of the node
- **THEN** the write is rejected with a validation error

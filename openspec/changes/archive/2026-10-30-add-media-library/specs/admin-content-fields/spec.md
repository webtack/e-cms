# Spec Delta

## ADDED Requirements

### Requirement: Type and size settings for the fields "image" and "file"
When the value type `image` or `file` is selected in the creation or edit form of a field, the form SHALL show settings: a choice of the allowed file types (among the types of the profile of this field type) and the maximum size in kilobytes. Without a choice of types and without a size the rules of the whole profile apply. For other types these settings SHALL NOT be shown. The server SHALL reject a type outside the profile and a size above the profile's limit with an error next to the corresponding element, regardless of the state of the form. Changing the settings SHALL be allowed both for a field that has stored values and SHALL NOT change the already stored values.

#### Scenario: Settings appear only for file types
- **WHEN** the user selects the type `image` in the field creation form
- **THEN** the choice of file types from the list of types of the profile `image` and the maximum size field appear, and for the type `string` there are none

#### Scenario: Saving settings
- **WHEN** the user creates the field `image`, selects only PNG and sets the maximum size to 1024 KB
- **THEN** the field is saved, its settings contain `image/png` and `1024`, and when the edit is opened they are selected

#### Scenario: Without settings
- **WHEN** the user creates the field `file`, without selecting types and without setting a size
- **THEN** the field is saved without restrictions beyond the profile `document`

#### Scenario: Type outside the profile
- **WHEN** a request creates the field `image` with the type `application/pdf`
- **THEN** the field is not created, and an error is shown next to the type choice

#### Scenario: Size above the profile limit
- **WHEN** a request creates the field `image` with a size greater than the limit of the profile
- **THEN** the field is not created, and an error is shown next to the size field

#### Scenario: Narrowing for a field with values
- **WHEN** the user narrows the allowed types of a field that already has stored values
- **THEN** the changes are saved, the existing values do not change

### Requirement: Settings do not widen the profile
The system SHALL reject settings of an `image` or `file` field that contain a type missing from the profile of the type, or a size above the profile's limit. The settings SHALL NOT allow executable files and SVG, even if the profile was mistakenly widened.

#### Scenario: Type outside the profile
- **WHEN** for the field `image` the settings specify `application/pdf`
- **THEN** the settings are rejected with an error

#### Scenario: Size above the profile
- **WHEN** for the field `image` a `max_size` greater than the limit of the profile `image` is specified
- **THEN** the settings are rejected with an error

#### Scenario: Empty settings
- **WHEN** the field is created without settings
- **THEN** the types and the size of the whole profile of the type apply

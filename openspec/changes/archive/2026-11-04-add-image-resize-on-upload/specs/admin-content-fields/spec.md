# Spec Delta

## MODIFIED Requirements

### Requirement: Налаштування типів і розміру для полів «зображення» і «файл»
When the value type `image` or `file` is selected in the form of creating or editing a field, the form SHALL show the settings: a choice of the allowed file types (among the types of the profile of this field type) and the maximum size in kilobytes. For the type `image` the form SHALL additionally show the maximum width and the maximum height in pixels; for the type `file` there are none. Without a choice of types and without a size the rules of the whole profile apply, and empty width and height leave the limits of the profile. For the other types these settings SHALL NOT be shown. The server SHALL reject a type outside the profile and a size above the limit of the profile with an error next to the corresponding element, regardless of the state of the form; the maximum width and height SHALL accept only positive integers, not greater than the limits of the profile if the profile sets them, and SHALL reject them for a field of the type `file`. A change of the settings SHALL be allowed both for a field that has saved values and SHALL NOT change the already saved values and files.

#### Scenario: Settings appear only for file types
- **WHEN** the user selects the type `image` in the form of creating a field
- **THEN** the choice of file types from the list of types of the profile `image`, the field of the maximum size, the maximum width and the maximum height appear, and for the type `string` there are none

#### Scenario: Saving the settings
- **WHEN** the user creates a field `image`, selects only PNG and sets the maximum size 1024 KB
- **THEN** the field is saved, its settings contain `image/png` and `1024`, and when editing is opened they are selected

#### Scenario: Without settings
- **WHEN** the user creates a field `file` without selecting types and without setting a size
- **THEN** the field is saved without restrictions beyond the profile `document`

#### Scenario: Type outside the profile
- **WHEN** a request creates a field `image` with the type `application/pdf`
- **THEN** the field is not created, an error is shown next to the choice of types

#### Scenario: Size above the limit of the profile
- **WHEN** a request creates a field `image` with a size larger than the limit of the profile
- **THEN** the field is not created, an error is shown next to the field of the size

#### Scenario: Narrowing for a field with values
- **WHEN** the user narrows the allowed types of a field that already has saved values
- **THEN** the changes are saved, the existing values do not change

#### Scenario: Saving the width and height
- **WHEN** the user creates a field `image` and sets the maximum width 800 and the maximum height 600
- **THEN** the field is saved, its settings contain `800` and `600`, and when editing is opened they are shown

#### Scenario: Width above the limit of the profile
- **WHEN** a request creates a field `image` with a maximum width greater than the limit of the profile
- **THEN** the field is not created, an error is shown next to the field of the width

#### Scenario: Incorrect width
- **WHEN** a request creates a field `image` with a maximum width `0`, negative or non-numeric
- **THEN** the field is not created, an error is shown next to the field of the width

#### Scenario: Width for the field `file`
- **WHEN** a request creates a field `file` with a maximum width
- **THEN** the field is not created, the settings are rejected

#### Scenario: A change of the limits does not touch the existing files
- **WHEN** the user reduces the maximum width of a field `image` that already has saved values
- **THEN** the changes are saved, the existing files and values do not change, and the new limit applies to the next uploads

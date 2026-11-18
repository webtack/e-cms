# Spec Delta

## ADDED Requirements

### Requirement: Field types "image" and "file"
The system SHALL support the field types `image` and `file`, whose value references a file from the storage. The value SHALL be stored as the string `{uuid}.{extension}` together with the values of the string type, without a new values table. The type SHALL define the upload profile, which is the upper limit of the rules: `image` — the profile `image`, `file` — the profile `document`. A field SHALL have optional settings: `mimes` — the allowed file types (a subset of the types of the profile) and `max_size` — the maximum size in kilobytes (not more than the limit of the profile); without settings the rules of the whole profile apply. The settings SHALL only narrow the rules of the profile and SHALL never widen them. For a multiple field the system SHALL store an ordered list of such strings by the general rules of multiple values, and translations by the general rules of translations.

#### Scenario: Saving and reading an image
- **WHEN** a file of the profile `image` is written into the field `image`
- **THEN** on reading the same string `{uuid}.{extension}` is returned

#### Scenario: Saving and reading a file
- **WHEN** a file of the profile `document` is written into the field `file`
- **THEN** on reading the same string `{uuid}.{extension}` is returned

#### Scenario: Link without additional queries
- **WHEN** a set of records with the fields `image` and `file` is read
- **THEN** the links to the files are built from the read values, without additional queries to the file storage

#### Scenario: Clearing
- **WHEN** `null` is passed to the field `image` or `file`
- **THEN** the value is deleted, and the file remains in the storage

### Requirement: Validation of the value of the fields "image" and "file"
The system SHALL accept a value of the field `image` or `file` only in the format `{uuid}.{extension}`, where `uuid` is the identifier of an existing file, its profile matches the profile of the field type, the file type is among the allowed types of the field, the size does not exceed the maximum size of the field, and the extension matches the extension of the file in the storage. Other strings (in particular with path separators) SHALL be rejected with an error for this field.

#### Scenario: File of the required profile
- **WHEN** the value is a string `{uuid}.{extension}` of an existing file of the profile of the field type
- **THEN** the record is accepted

#### Scenario: Document in the image field
- **WHEN** a file of the profile `document` is written into the field `image`
- **THEN** the record is rejected with an error for this field

#### Scenario: Image in the file field
- **WHEN** a file of the profile `image` is written into the field `file`
- **THEN** the record is rejected with an error for this field

#### Scenario: Non-existing file
- **WHEN** the value has the correct format, but there is no such file
- **THEN** the record is rejected with an error for this field

#### Scenario: Wrong extension
- **WHEN** the `uuid` belongs to an existing file, and the extension in the value is different
- **THEN** the record is rejected with an error for this field

#### Scenario: Arbitrary string
- **WHEN** `../secret.png` or a string of an arbitrary form is passed to the field `image` or `file`
- **THEN** the record is rejected with an error for this field

#### Scenario: Type outside the field settings
- **WHEN** the field `image` allows only `image/png`, and a file `image/jpeg` of the profile `image` is written into it
- **THEN** the record is rejected with an error for this field

#### Scenario: Size above the field limit
- **WHEN** the field has `max_size` of 1024 KB, and a file of 2 MB is written
- **THEN** the record is rejected with an error for this field

#### Scenario: One file in different fields
- **WHEN** the same file `image/jpeg` is written into a field that allows JPEG, and into a field that allows only PNG
- **THEN** the record is accepted in the first field, and rejected in the second

#### Scenario: Changing the settings does not touch the stored values
- **WHEN** after narrowing the allowed types the field already has stored file values that now do not match the settings
- **THEN** the stored values are read without changes, and the new rules apply at the next write of a value

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

### Requirement: The types "image" and "file" do not change the general mechanism
Adding the types `image` and `file` SHALL NOT change the general mechanism of writing, reading and selecting values: fields of these types are created, written, read and filtered by the same means as other types, and the filter by value works as for a short text. The type of the value of a field that has stored values SHALL NOT change between `string`, `image` and `file`.

#### Scenario: Filter by file
- **WHEN** records are filtered by the value of the field `image` or `file` equal to a certain `{uuid}.{extension}`
- **THEN** the records that contain this file in this field are returned

#### Scenario: Changing the type of a field with values
- **WHEN** the type of a field `string` or `image` that has stored values is changed to `file`
- **THEN** the change is rejected, the values do not change

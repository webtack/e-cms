# app-settings Specification

## Purpose
Provides a single shared key–value store of application settings, which the CMS core and any future modules can use without their own settings tables and without changes to the DB schema.

## Requirements

### Requirement: Settings store by key
The system SHALL store application settings as pairs of a unique key and a value, where the value is any JSON-compatible value (a string, number, boolean, array, object, `null`), and SHALL allow reading, writing, and deleting a setting by its key without code changes and migrations.

#### Scenario: Write and read
- **WHEN** the setting `content.multilingual` is written with the value `true` and then read
- **THEN** the boolean `true` is returned

#### Scenario: Preserving structure
- **WHEN** a setting is written with the array value `{"a": [1, 2]}`
- **THEN** on reading the same structure with the same types is returned

#### Scenario: Overwriting
- **WHEN** a setting with an existing key is written again with a new value
- **THEN** there is one record with the new value

#### Scenario: Deletion
- **WHEN** a setting is deleted and then read
- **THEN** the default value specified at reading is returned (or `null`)

### Requirement: Default value
The system SHALL return the value specified by the caller as the default when a setting is absent, and SHALL distinguish an absent setting from a setting with the value `null`.

#### Scenario: Absent setting
- **WHEN** a key that does not exist is read with the default value `false`
- **THEN** `false` is returned

#### Scenario: Stored null
- **WHEN** a setting with the value `null` has been written
- **THEN** the presence of the key is confirmed, and reading returns `null`, not the default value

### Requirement: Key namespaces
The system SHALL require keys in the format `module.name` (lowercase Latin letters, digits, `_`, `-`, dots as segment separators, at least two segments, up to 191 characters in length) and SHALL reject invalid keys; the settings of the content core SHALL use the prefix `content.`.

#### Scenario: Invalid key
- **WHEN** a setting with the key `Multilingual` or `a..b` is written
- **THEN** the write is rejected with a validation error

#### Scenario: Selection by prefix
- **WHEN** all settings with the prefix `content.` are requested
- **THEN** only the settings whose keys begin with `content.` are returned, as key–value pairs

### Requirement: Consistency after changes
The system SHALL return the current value immediately after a write or deletion within the same application, regardless of internal caching.

#### Scenario: Reading after writing
- **WHEN** a setting is written and immediately read
- **THEN** the value just written is returned

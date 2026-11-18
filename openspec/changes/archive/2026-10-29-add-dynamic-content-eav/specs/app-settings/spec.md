# Spec Delta

## Purpose

Provides a single common key—value storage of application settings, which the CMS core and any future modules can use without their own settings tables and without schema changes in the DB.

## ADDED Requirements

### Requirement: Settings storage by key
The system SHALL store application settings as unique key—value pairs, where the value is any JSON-compatible value (a string, a number, a boolean, an array, an object, `null`), and SHALL allow reading, writing and deleting settings by key without code changes or migrations.

#### Scenario: Write and read
- **WHEN** the setting `content.multilingual` is written with the value `true` and then read
- **THEN** the boolean `true` is returned

#### Scenario: Preserving structure
- **WHEN** a setting is written with the array-valued `{"a": [1, 2]}`
- **THEN** on reading the same structure with the same types is returned

#### Scenario: Overwrite
- **WHEN** a setting with an existing key is written again with a new value
- **THEN** there is one record with the new value

#### Scenario: Deletion
- **WHEN** a setting is deleted and then read
- **THEN** the default value specified when reading (or `null`) is returned

### Requirement: Default value
The system SHALL return the default value specified by the caller when a setting is absent, and SHALL distinguish an absent setting from a setting with the value `null`.

#### Scenario: Absent setting
- **WHEN** a key that does not exist is read with the default value `false`
- **THEN** `false` is returned

#### Scenario: Stored null
- **WHEN** a setting with the value `null` has been written
- **THEN** the presence of the key is confirmed, and the read returns `null`, not the default value

### Requirement: Key namespaces
The system SHALL require keys in the format `module.name` (lowercase Latin letters, digits, `_`, `-`, dots as segment separators, at least two segments, up to 191 characters long) and SHALL reject invalid keys; the settings of the content core SHALL use the prefix `content.`.

#### Scenario: Invalid key
- **WHEN** a setting with the key `Multilingual` or `a..b` is written
- **THEN** the write is rejected with a validation error

#### Scenario: Selection by prefix
- **WHEN** all settings with the prefix `content.` are requested
- **THEN** only the settings whose keys start with `content.` are returned, as key—value pairs

### Requirement: Consistency after changes
The system SHALL return the current value immediately after a write or deletion within the same application, regardless of internal caching.

#### Scenario: Read after write
- **WHEN** a setting is written and immediately read
- **THEN** the value just written is returned

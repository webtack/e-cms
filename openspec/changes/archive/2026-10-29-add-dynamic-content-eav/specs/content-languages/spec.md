# Spec Delta

## Purpose

Allows managing the content languages (the list of available languages) and the global content multilingualism switch. It concerns only the content and the frontend part; the language of the admin interface does not change.

## ADDED Requirements

### Requirement: List of content languages
The system SHALL store the list of available content languages as records of the common system languages table (`app_languages`) with a unique code, a name, an activity flag (inactive by default) and a weight (`weight`), without new migrations and code changes. The code `default` SHALL be reserved for base values and SHALL NOT be added as a language. A language code SHALL have a BCP-47-like format (`uk`, `en`, `pt-BR`) up to 16 characters long. The system SHALL store the code only in normalized form (the canonical BCP-47 case: the language in lowercase, the script capitalized, the region in uppercase) and SHALL normalize the language given on writing, reading and selection of values in the same way, so that case variants of one code are one language.

#### Scenario: Code normalization
- **WHEN** a language with the code `PT-br` is added
- **THEN** the code `pt-BR` is stored; adding `pt-br` afterwards is rejected with a uniqueness error, and a value write with `lang = pt-br` is saved with `lang = pt-BR`

#### Scenario: Adding a language
- **WHEN** a language with the code `en` and the name "English" is added
- **THEN** the language is saved; it is inactive by default and becomes available for writing content values after activation

#### Scenario: Duplicate code
- **WHEN** a language with an already existing code is added
- **THEN** the save is rejected with a uniqueness error

#### Scenario: Reserved or invalid code
- **WHEN** a language with the code `default`, `!!` or longer than 16 characters is added
- **THEN** the save is rejected with a validation error

### Requirement: Deactivation and deletion of a language
The system SHALL allow deactivating a language while keeping its values, and SHALL reject deletion of a language for which values exist; a language without values SHALL be allowed to be deleted.

#### Scenario: Deactivation
- **WHEN** a language with existing values is deactivated
- **THEN** its values are kept and available for reading, but new values cannot be written in this language

#### Scenario: Deleting a language with values
- **WHEN** a language for which values exist is deleted
- **THEN** the deletion is rejected

#### Scenario: Deleting an empty language
- **WHEN** a language without values is deleted
- **THEN** it is deleted

### Requirement: Multilingualism switch
The system SHALL have a single global content multilingualism switch, stored in the common settings storage (`app-settings`) under the key `content.multilingual`, disabled by default and changeable without code changes. When disabled, the system SHALL accept, read and select values only with the language `default`. When enabled, the system SHALL additionally accept the active languages from the list. The switch SHALL NOT delete or change already stored translations.

#### Scenario: Disabled multilingualism
- **WHEN** multilingualism is disabled and a value is written, read or selected with `lang = en`
- **THEN** the operation is rejected with a validation error

#### Scenario: Enabled multilingualism
- **WHEN** multilingualism is enabled, the language `en` is active, and a translatable field is written with `lang = en`
- **THEN** the value is saved

#### Scenario: Switching off with translations preserved
- **WHEN** multilingualism is switched off after translations were written, and then switched on again
- **THEN** the translations are saved without changes

### Requirement: Independence from the admin language
The content languages and the multilingualism switch SHALL NOT affect the language of the admin interface and its switcher; and vice versa, the admin language SHALL NOT affect the language of content values.

#### Scenario: Change of content language settings
- **WHEN** a content language is added or the multilingualism switch is toggled
- **THEN** the available admin interface languages and the choice of the admin interface language remain unchanged

### Requirement: Availability of the settings for the frontend
The system SHALL provide, for use in code, the state of the multilingualism switch and the ordered list of active content languages.

#### Scenario: List of active languages
- **WHEN** multilingualism is enabled, the languages `uk` (active), `en` (active), `de` (inactive)
- **THEN** the list `uk`, `en` is returned in the order of the weight (`weight`); when disabled — an empty list of additional languages

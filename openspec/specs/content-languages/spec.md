# content-languages Specification

## Purpose
Lets administrators manage the content languages (the list of available languages) and the global content multilingual switch. It concerns only the content and the frontend part; the language of the admin interface does not change.

## Requirements

### Requirement: List of content languages
The system SHALL store the list of available content languages as records of the shared system languages table (`app_languages`) with a unique code, a name, an activity flag (inactive by default), and a weight (`weight`), without new migrations and code changes. The code `default` SHALL be reserved for base values and SHALL NOT be added as a language. A language code SHALL have a BCP-47-like format (`uk`, `en`, `pt-BR`) of up to 16 characters. The system SHALL store the code only in normalized form (canonical BCP-47 casing: the language in lowercase, the script in title case, the region in uppercase) and SHALL normalize in the same way the language specified when writing, reading, and querying values, so that case variants of one code are one language.

#### Scenario: Code normalization
- **WHEN** a language with the code `PT-br` is added
- **THEN** the code `pt-BR` is saved; adding `pt-br` afterwards is rejected with a uniqueness error, and writing a value with `lang = pt-br` is saved with `lang = pt-BR`

#### Scenario: Adding a language
- **WHEN** a language with the code `en` and the name "English" is added
- **THEN** the language is saved; it is inactive by default and becomes available for writing content values after activation

#### Scenario: Duplicate code
- **WHEN** a language with an already existing code is added
- **THEN** saving is rejected with a uniqueness error

#### Scenario: Reserved or invalid code
- **WHEN** a language with the code `default`, `!!`, or longer than 16 characters is added
- **THEN** saving is rejected with a validation error

### Requirement: Deactivation and deletion of a language
The system SHALL allow deactivating a language while keeping its values, and SHALL reject deleting a language for which any translation exists: content values, term translations, menu item translations, and the translations of any other feature that registers its stored translations with the language rules. A language without translations SHALL be allowed to be deleted.

#### Scenario: Deactivation
- **WHEN** a language with existing values is deactivated
- **THEN** its values are kept and available for reading, but new values cannot be written in this language

#### Scenario: Deleting a language with values
- **WHEN** a language for which values exist is deleted
- **THEN** the deletion is rejected

#### Scenario: Deleting a language with other translations
- **WHEN** a language for which only a term translation or a menu item translation exists is deleted
- **THEN** the deletion is rejected

#### Scenario: Deleting an empty language
- **WHEN** a language without translations is deleted
- **THEN** it is deleted

### Requirement: Multilingual switch
The system SHALL have a single global content multilingual switch, which is stored in the shared settings store (`app-settings`) under the key `content.multilingual`, is disabled by default, and is changed without code changes. When disabled, the system SHALL accept, read, and select values only in the language `default`. When enabled, the system SHALL additionally accept the active languages from the list. The switch SHALL NOT delete or change already saved translations.

#### Scenario: Multilingual disabled
- **WHEN** multilingual is disabled, and a value is written, read, or selected with `lang = en`
- **THEN** the operation is rejected with a validation error

#### Scenario: Multilingual enabled
- **WHEN** multilingual is enabled, the language `en` is active, and a translatable field is written with `lang = en`
- **THEN** the value is saved

#### Scenario: Disabling with saved translations
- **WHEN** multilingual is disabled after translations were written, and then enabled again
- **THEN** the translations are saved without changes

### Requirement: Independence from the admin language
The content languages and the multilingual switch SHALL NOT affect the interface language of the admin panel and its switcher; and vice versa, the admin language SHALL NOT affect the language of content values.

#### Scenario: Changing content language settings
- **WHEN** a content language is added or the multilingual switch is toggled
- **THEN** the available languages and the choice of the admin interface language remain unchanged

### Requirement: Availability of settings for the frontend
The system SHALL provide, for use in code, the state of the multilingual switch and an ordered list of the active content languages.

#### Scenario: List of active languages
- **WHEN** multilingual is enabled, the languages `uk` (active), `en` (active), and `de` (inactive) exist
- **THEN** the list `uk`, `en` is returned in order of weight (`weight`); when disabled — an empty list of additional languages

### Requirement: Base language code
The system SHALL store the code of the base language, the language in which the `default` values are written, in the shared settings store under the key `content.base_lang`. The code SHALL have the same format and normalization as the code of a content language. When no code is saved, the base language code SHALL read as the configured default locale of the application (`content.base_lang_default`, taken from `APP_LOCALE`), never as the interface locale of the current request. The base language code SHALL NOT change the storage key of base values, which stays `default`, and SHALL NOT change any saved value; it only names the base language for the outside world. The base language code SHALL NOT equal the code of any additional language: saving a code that equals an existing additional language, and adding an additional language whose code equals the saved base language code, SHALL be rejected with a validation error. Turning multilingual mode on SHALL be rejected while no base language code is saved.

#### Scenario: Reading without a saved code
- **WHEN** no base language code is saved and the configured default locale is `uk`
- **THEN** the base language code reads as `uk`

#### Scenario: Interface locale does not matter
- **WHEN** no base language code is saved, the configured default locale is `uk` and the request runs with the interface locale `en`
- **THEN** the base language code still reads as `uk`

#### Scenario: Saving a normalized code
- **WHEN** the code `UK` is saved as the base language
- **THEN** `uk` is stored and read back

#### Scenario: Code of an additional language
- **WHEN** the language `en` exists and `en` is saved as the base language, or the base language is `uk` and a language `uk` is added
- **THEN** the operation is rejected with a validation error

#### Scenario: Enabling multilingual mode
- **WHEN** multilingual mode is turned on without a saved base language code
- **THEN** the operation is rejected with a validation error

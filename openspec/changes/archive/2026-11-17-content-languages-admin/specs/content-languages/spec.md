# Spec Delta

## ADDED Requirements

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

## MODIFIED Requirements

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

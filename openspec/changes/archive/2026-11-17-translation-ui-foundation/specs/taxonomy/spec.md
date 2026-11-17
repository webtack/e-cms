# Spec Delta

## MODIFIED Requirements

### Requirement: Translations of terms
The system SHALL store the name and an optional description of a term separately for each language: the base language `default` and the content languages, admissible by the same rules as the translations of content values (the code is normalized; an additional language requires multilingual mode to be enabled and the language to be active). The name of a term in the language `default` SHALL be required. In any other language the name and the description SHALL each be optional: a translation MAY hold only one of them, and the other reads as missing; a translation that holds neither SHALL NOT exist. The system SHALL be able to save the translation of one language on its own: it SHALL change only that language, SHALL NOT store a value that equals the `default` value of the same field, and SHALL delete the translation when no field of it remains. For one language a term SHALL have at most one translation. A fallback to `default` when reading a missing translation SHALL NOT be applied: a missing translation is returned as missing.

#### Scenario: Name in the base language
- **WHEN** a term is created without a name in the language `default`
- **THEN** saving is rejected with a validation error

#### Scenario: Translation of a name
- **WHEN** the name "Jackets" is written for a term in the language `en`
- **THEN** there are two independent names (`default` and `en`), and changing one does not change the other

#### Scenario: Invalid language
- **WHEN** a translation is written in a language that is not in the list, or multilingual mode is disabled
- **THEN** the record is rejected with a validation error

#### Scenario: Missing translation
- **WHEN** the name of a term is read in the language `en`, and there is no English translation
- **THEN** the name is absent, and `default` is not substituted

#### Scenario: Translation with only a description
- **WHEN** a translation in `en` is saved with a description and without a name
- **THEN** the translation exists, its name reads as missing and its description is stored

#### Scenario: Value equal to the base
- **WHEN** the translation of `en` is saved with a name equal to the `default` name and a different description
- **THEN** only the description is stored for `en`

#### Scenario: Saving one language
- **WHEN** the translation of `en` is saved for a term that also has a translation in `pl`
- **THEN** the translation in `pl` and the `default` values are unchanged

#### Scenario: Nothing left to store
- **WHEN** the translation of `en` is saved with every value empty or equal to `default`
- **THEN** the `en` translation does not exist afterwards

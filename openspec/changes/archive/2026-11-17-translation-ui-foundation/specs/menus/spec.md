# Spec Delta

## MODIFIED Requirements

### Requirement: Translations of items
An item SHALL have translations of `title` (the name, up to 255 characters) and `description` (the public description, optional) for the language `default` and for the content languages admissible for writing under the content language rules. The title in `default` SHALL be required. In any other language the title and the description SHALL each be optional: a translation MAY hold only one of them, and the other reads as missing; a translation that holds neither SHALL NOT exist. The system SHALL be able to save the translation of one language on its own: it SHALL change only that language, SHALL NOT store a value that equals the `default` value of the same field, and SHALL delete the translation when no field of it remains. A fallback to `default` SHALL NOT be applied: a missing translation is returned as `null`. An empty translation of a non-base language SHALL NOT be saved, and clearing every field of an existing one SHALL delete it. Deleting an item SHALL delete its translations.

#### Scenario: Title in default
- **WHEN** an item is saved without a title in `default`
- **THEN** saving is rejected with a validation error

#### Scenario: Translation in an active language
- **WHEN** multilingual mode is enabled, the language `en` is active, and a title and a description are set for the item in `en`
- **THEN** the translation is saved

#### Scenario: Inactive language
- **WHEN** a translation is set for an item in a language that is not admissible for writing
- **THEN** saving is rejected with a validation error for the key `lang`

#### Scenario: Missing translation
- **WHEN** the title of an item is requested in `en`, and there is no translation
- **THEN** `null` is returned, not the title of `default`

#### Scenario: Translation with only a description
- **WHEN** a translation in `en` is saved with a description and without a title
- **THEN** the translation exists, its title reads as missing and its description is stored

#### Scenario: Value equal to the base
- **WHEN** the translation of `en` is saved with a title equal to the `default` title and a different description
- **THEN** only the description is stored for `en`

#### Scenario: Saving one language
- **WHEN** the translation of `en` is saved for a item that also has a translation in `pl`
- **THEN** the translation in `pl` and the `default` values are unchanged

#### Scenario: Nothing left to store
- **WHEN** the translation of `en` is saved with every value empty or equal to `default`
- **THEN** the `en` translation does not exist afterwards

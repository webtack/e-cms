# Spec Delta

## ADDED Requirements

### Requirement: Saving one language of the settings
The system SHALL provide an operation that saves the translatable settings in one additional language and clears them. It SHALL accept only the translatable settings (the name, short name, description, address, schedule, and the labels of emails, phones and socials), SHALL identify a list item by its non-translatable key (the `value` of an email or a phone; the network together with the link of a social network) and SHALL ignore labels of items that do not exist. It SHALL NOT store a value equal to the `default` value of the same setting, SHALL remove a value submitted empty, SHALL remove a setting's language key when nothing remains, and SHALL leave every other language, the `default` values, the logos, the indexing flag and the non-translatable data of the lists unchanged. A language SHALL be accepted only under the content language rules. Reading a setting SHALL keep falling back to `default`. A language in which any translatable setting is stored SHALL NOT be deletable.

#### Scenario: Equal to the base
- **WHEN** the English name equal to the base name is saved
- **THEN** no English name is stored

#### Scenario: Label of a missing item
- **WHEN** a label is submitted for a phone number that is not in the list
- **THEN** it is ignored

#### Scenario: Other languages
- **WHEN** the English translation is saved on a site that has Polish translations
- **THEN** the Polish values and the `default` values are unchanged

#### Scenario: Clearing
- **WHEN** the English translation of the settings is cleared
- **THEN** no English value remains and every reader returns the `default` values for English

#### Scenario: Deleting a language
- **WHEN** a language with a stored translated setting is deleted
- **THEN** the deletion is rejected

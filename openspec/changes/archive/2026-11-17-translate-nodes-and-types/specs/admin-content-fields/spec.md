# Spec Delta

## ADDED Requirements

### Requirement: Translation of a field label
The edit page of a field SHALL follow the translation mode contract for the label of the field, the only translatable attribute of a field. In the translation mode the form SHALL keep the layout of the base form with only the label editable, prefilled with the stored translation or with the base name when none exists, with a maximum length of 255 characters. Saving SHALL store the label of that language, SHALL NOT store a label equal to the base name and SHALL remove the label of that language when it is emptied. The key, the description, the type, the flags, the weight and the settings of the field SHALL stay in place, shown disabled with their base values, and SHALL NOT be changed. Translated labels SHALL be available for every value type, including system fields. The right to translate SHALL be the right to configure the type.

#### Scenario: Translating a label
- **WHEN** the user opens a field with `?lang=en`, changes the label to "Opening hours" and saves
- **THEN** the English label is stored and the base name is unchanged

#### Scenario: Label equal to the name
- **WHEN** the user saves the English form without changing the prefilled label
- **THEN** no English label is stored

#### Scenario: Emptied label
- **WHEN** the user empties a stored English label and saves
- **THEN** the English label is removed

#### Scenario: Base form keeps labels
- **WHEN** the user saves the base form of a field that has translated labels, whatever its value type
- **THEN** the translated labels are unchanged

### Requirement: Labels are exempt from the settings restrictions of a value type
The per-type restrictions on field `settings` (which keys a type accepts, and which a type rejects) SHALL NOT apply to the translated labels, which are kept in the same settings. Every value type SHALL be able to hold translated labels, and a change of the settings of a type or the creation of a field SHALL NOT remove them. Changing only the translated labels SHALL NOT trigger the type's own settings checks.

#### Scenario: Type without settings
- **WHEN** a `string` field, which accepts no settings, gets a translated label
- **THEN** the label is stored and the field's other settings stay empty

#### Scenario: Type with settings
- **WHEN** the user changes the allowed file types of an `image` field that has translated labels
- **THEN** the translated labels are unchanged

#### Scenario: Field with stored values
- **WHEN** a translated label is saved for a `term` field that has values
- **THEN** the save succeeds and the vocabulary stays locked

# Spec Delta

## MODIFIED Requirements

### Requirement: Page fields
The page SHALL contain one form with blocks: the name and the short name (single-line texts), an optional site description (a multiline text field), the large and the small logo (image upload), the contacts (emails, phones, and an optional multiline address text field), social networks, the working hours schedule, and the indexing checkbox. All blocks except the checkbox SHALL be optional. In the base mode the form SHALL show and edit only the values of the base language (`default`); translations are edited in the translation mode of the page and never in the base form. The address SHALL accept up to 500 characters.

#### Scenario: Empty installation
- **WHEN** the user opens the page when nothing is saved
- **THEN** the fields are empty, the lists have no rows, and the indexing checkbox is unchecked

#### Scenario: Saved values
- **WHEN** the user opens the page after saving the settings
- **THEN** the form shows the saved values of the language `default`, the logos with a preview, and the lists in the saved order

#### Scenario: Address in the Contacts block
- **WHEN** the user opens the page
- **THEN** the "Contacts" block contains an "Address" textarea, and a saved address is shown in it with its line breaks

#### Scenario: Address too long
- **WHEN** the user saves an address longer than 500 characters
- **THEN** the save is rejected with an error on the `address` field and nothing changes

#### Scenario: No language switcher
- **WHEN** multilingual mode is off or no additional language is active, and the user opens the page
- **THEN** the form shows only the values of `default`, without a language switcher

## ADDED Requirements

### Requirement: Translation mode of the settings page
The settings page SHALL follow the translation mode contract. In the translation mode of a language the form SHALL keep the layout of the base form, with only the translatable settings editable: the name, the short name, the description, the address, the working hours schedule, and the labels of emails, phones and social networks. Every other control (the logos, the indexing checkbox, email addresses, phone numbers, the networks and their links) SHALL stay in its place, shown disabled with its base value, and SHALL NOT be changed. Emails, phones and social networks SHALL appear as the rows of the base lists, each identified by its non-translatable value (the address or number; the network and its link) shown disabled, with only its label editable; rows SHALL NOT be added, removed or reordered in a translation. Each field SHALL be prefilled with its stored translation or, when none exists, with the base value; the schedule SHALL be prefilled and saved as a whole table. Saving SHALL store only values that differ from the base, SHALL remove a value that is emptied, and SHALL keep the base values, the other languages, the logos and the lists' non-translatable data unchanged. The right to the settings page SHALL be required.

#### Scenario: Fields of a translation
- **WHEN** the user opens `/admin/settings?lang=en`
- **THEN** the form has the layout of the base form, the name, short name, description, address, schedule and the labels of the existing contact and social rows are editable and prefilled from the base, and the logos and the indexing checkbox stay in place, disabled

#### Scenario: Saving a translated name
- **WHEN** the user changes the prefilled name to "My site" and saves
- **THEN** the English name is stored, the base name is unchanged, and the user stays on the English form with a success message

#### Scenario: Label of a phone
- **WHEN** the user enters the label "Sales" for a phone row in the English mode and saves
- **THEN** the English label is stored for that phone, and the phone number and its base label are unchanged

#### Scenario: Rows are fixed
- **WHEN** the user opens the English mode
- **THEN** there are no buttons to add, delete or reorder rows

#### Scenario: Schedule
- **WHEN** the user changes one cell of the prefilled schedule and saves
- **THEN** the whole English schedule table is stored, and the base schedule is unchanged

#### Scenario: Without the right
- **WHEN** a user without the right to site settings opens the page with `?lang=en` or sends a translation
- **THEN** the response is 403 and nothing changes

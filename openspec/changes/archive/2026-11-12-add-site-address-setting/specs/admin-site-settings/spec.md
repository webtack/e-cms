## MODIFIED Requirements

### Requirement: Page fields
The page SHALL contain one form with blocks: the name and the short name (single-line texts), an optional site description (a multiline text field), the large and the small logo (image upload), the contacts (emails, phones, and an optional multiline address text field), social networks, the working hours schedule, and the indexing checkbox. All blocks except the checkbox SHALL be optional. The form SHALL show and edit only the values of the base language (`default`), without a language switcher. The address SHALL accept up to 500 characters.

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
- **WHEN** several content languages are enabled on the site and the user opens the page
- **THEN** the form still shows only the values of `default`, without a language switcher

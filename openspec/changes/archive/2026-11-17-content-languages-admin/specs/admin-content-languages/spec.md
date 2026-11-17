# Spec Delta

## Purpose

Describes the admin page where an administrator turns multilingual mode on or off, names the base language and manages the list of additional content languages. It concerns content languages only, never the interface language of the admin panel.

## ADDED Requirements

### Requirement: Access to the languages page
The page `/admin/settings/languages` SHALL be available only to an authenticated verified user whose role has the system right to site settings. A user without the right SHALL receive 403 both for viewing and for every change; a guest SHALL be redirected to the login page.

#### Scenario: A role with the right
- **WHEN** a user whose role has the right to site settings opens `/admin/settings/languages`
- **THEN** the page opens with the current state

#### Scenario: A role without the right
- **WHEN** a user without the right to site settings opens the page or sends any change
- **THEN** the response is 403 and nothing changes

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens the page
- **THEN** they are redirected to `/login`

### Requirement: Content of the languages page
The page SHALL show the multilingual switch, the base language code and the table of additional languages with the columns code, name, "Active", weight and row actions. Languages SHALL be ordered by weight and then by creation. The page SHALL state that these languages concern the content of the site and do not change the language of the admin interface.

#### Scenario: Empty installation
- **WHEN** nobody has saved anything yet
- **THEN** the switch is off, the base language shows the configured default locale as an unsaved value, regardless of the interface language of the admin panel, and the table is empty

#### Scenario: Saved languages
- **WHEN** the languages `en` (active, weight 1) and `pl` (inactive, weight 2) exist
- **THEN** the table lists `en` before `pl` with their activity flags

### Requirement: Multilingual switch on the page
The user SHALL be able to turn multilingual mode on and off on the page. Turning it on SHALL be rejected with an error next to the switch while the base language code has not been saved. Turning it off SHALL NOT delete or change any translation. A success message SHALL be shown after saving.

#### Scenario: Enabling with a base language
- **WHEN** the base language is saved and the user turns the switch on
- **THEN** multilingual mode is enabled and a success message is shown

#### Scenario: Enabling without a base language
- **WHEN** the base language has never been saved and the user turns the switch on
- **THEN** the change is rejected with an error next to the switch and the mode stays off

#### Scenario: Disabling keeps translations
- **WHEN** the user turns the switch off on a site with saved translations and later turns it on again
- **THEN** all translations are intact

### Requirement: Base language code on the page
The user SHALL be able to set the base language code on the page. The code SHALL have the language code format of content languages and SHALL be saved in normalized form. A code equal to the code of an existing additional language SHALL be rejected with an error next to the field.

#### Scenario: Saving the base language
- **WHEN** the user enters `UK` and saves
- **THEN** the base language is saved as `uk`

#### Scenario: Code of an additional language
- **WHEN** the language `en` exists and the user enters `en` as the base language
- **THEN** the change is rejected with an error next to the field

#### Scenario: Invalid code
- **WHEN** the user enters `default` or `!!`
- **THEN** the change is rejected with an error next to the field

### Requirement: Adding a language
The user SHALL be able to add an additional language by a code and a name. The name SHALL be required and at most 255 characters. The language SHALL be inactive unless the user explicitly marks it active in the same form. A code that already exists, is reserved, is invalid or equals the base language code SHALL be rejected with an error next to the code field and the entered values SHALL be kept.

#### Scenario: Adding an inactive language
- **WHEN** the user adds the code `en` with the name "English" and does not mark it active
- **THEN** the language appears in the table as inactive

#### Scenario: Adding an active language
- **WHEN** the user adds the code `pl` with the name "Polski" and marks it active
- **THEN** the language appears in the table as active

#### Scenario: Duplicate or base code
- **WHEN** the user adds a code that already exists or equals the base language
- **THEN** an error appears next to the code field and nothing is added

### Requirement: Editing a language
The user SHALL be able to change the name, the activity flag and the weight of an additional language. The code SHALL NOT be editable. The weight SHALL be an integer from 0. Deactivating a language SHALL keep its translations.

#### Scenario: Activation
- **WHEN** the user marks an inactive language active
- **THEN** it becomes available for writing translations

#### Scenario: Deactivation keeps translations
- **WHEN** the user deactivates a language that has translations
- **THEN** the translations are kept and the language is no longer offered for editing

#### Scenario: Changing the order
- **WHEN** the user gives the language `pl` a lower weight than `en`
- **THEN** `pl` is listed before `en` on the page and in every language list of the admin panel

### Requirement: Deleting a language
The user SHALL be able to delete an additional language through a confirmation dialog. Deletion SHALL be rejected while any translation of the language exists (content values, term translations, menu item translations, and the translations of later features). The rejection SHALL explain that translations exist and SHALL suggest deactivating the language instead. A language without translations SHALL be deleted.

#### Scenario: Language without translations
- **WHEN** the user confirms the deletion of a language that has no translations
- **THEN** the language disappears from the table

#### Scenario: Language with translations
- **WHEN** the user tries to delete a language that has a term translation
- **THEN** the deletion is rejected with an explanation and the language remains

### Requirement: Reaching the languages page
The page SHALL be a sub-page of "Site" reached from the admin navigation next to the other settings sub-pages, and its breadcrumb or heading SHALL read "Languages". The labels SHALL be translated in the interface languages of the admin panel.

#### Scenario: Navigation
- **WHEN** a user with the right to site settings looks for the languages page
- **THEN** a link to it is available from the settings area and the page heading reads "Languages"

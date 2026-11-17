# admin-site-settings Specification

## Purpose
Describes the "Site settings" page in the admin panel: which fields it shows, how the user edits the lists, how the values are validated and saved, and who has access.

## Requirements

### Requirement: Access to the settings page
The site settings page SHALL be available at `/admin/settings` only to an authenticated verified user whose role has the system right to site settings. A user without this right SHALL receive 403 both for viewing and for saving; a guest SHALL be redirected to the login page.

#### Scenario: A role with the right
- **WHEN** a user of a role with the right to site settings opens `/admin/settings`
- **THEN** the page opens with the current values

#### Scenario: A role without the right
- **WHEN** a user without the right to site settings opens `/admin/settings` or sends a save
- **THEN** the response is 403, and the values are not changed

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/settings`
- **THEN** they are redirected to `/login`

#### Scenario: Administrator
- **WHEN** a user of the role `admin` opens `/admin/settings`
- **THEN** the page opens

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

### Requirement: Emails and phones
The email block and the phone block SHALL be lists of records, where each record has a value and an optional label. The user SHALL be able to add, delete, and change the order of records. The value of an email SHALL be a valid email address; the value of a phone — a string of digits and the symbols `+`, space, parentheses, and hyphen. The number of records in each list SHALL be limited by a reasonable bound.

#### Scenario: Adding an email with a label
- **WHEN** the user adds the email `info@example.com` with the label "General questions" and saves
- **THEN** the record is saved, and after reloading it is shown with the label

#### Scenario: Phone without a label
- **WHEN** the user adds a phone without a label
- **THEN** the record is accepted

#### Scenario: Invalid email
- **WHEN** the user enters a value that is not an email address
- **THEN** saving is rejected, and the error is shown next to the corresponding row

#### Scenario: Invalid phone
- **WHEN** the user enters letters in a phone
- **THEN** saving is rejected, and the error is shown next to the corresponding row

#### Scenario: Empty record
- **WHEN** the user adds a row, leaves the value empty, and saves
- **THEN** saving is rejected with an error next to the value; a label without a value is not saved

#### Scenario: Exceeding the limit
- **WHEN** the number of records in a list exceeds the set limit
- **THEN** saving is rejected with an error of the list

### Requirement: Social networks
The social networks block SHALL be a list of records, each consisting of a network (a choice from a fixed directory), a link, and an optional label. The add button SHALL add a new row with a network selection, a link field, and a label field. The user SHALL be able to add several records of one network, delete records, and change their order. The link SHALL be a valid `http` or `https` address.

#### Scenario: Adding a network
- **WHEN** the user clicks "Add", selects a network, enters a link, and saves
- **THEN** the record is saved, and after reloading it is shown in the same order

#### Scenario: Two pages of one network
- **WHEN** the user adds two records with the network Instagram and different links
- **THEN** both are saved

#### Scenario: Invalid link
- **WHEN** the user enters a link without an `http`/`https` scheme
- **THEN** saving is rejected, and the error is shown next to the link field

#### Scenario: Network not selected
- **WHEN** the user adds a row and saves without selecting a network
- **THEN** saving is rejected, and the error is shown next to the network selection

### Requirement: Working hours schedule
The working hours block SHALL be a list of rows, each consisting of four single-line text values (columns). The user SHALL be able to add rows, delete them, and change their order. The values of the columns SHALL be arbitrary text without any format requirements. The number of rows SHALL be limited by a reasonable bound. A row in which all columns are empty SHALL NOT be saved.

#### Scenario: Adding a row
- **WHEN** the user adds a row and enters "Mon–Fri", "9:00", "18:00", "lunch 13–14"
- **THEN** after saving the four values are shown in the same columns

#### Scenario: Partial filling
- **WHEN** the user fills in only the first column of a row
- **THEN** the row is saved, and the empty columns remain empty

#### Scenario: Empty row
- **WHEN** the user adds a row and leaves all columns empty
- **THEN** after saving there is no such row

#### Scenario: Exceeding the limit
- **WHEN** the number of rows exceeds the set limit
- **THEN** saving is rejected with an error of the block

### Requirement: Changing the order by drag and drop
In the lists of emails, phones, social networks, and the schedule, the user SHALL change the order of records by dragging the handle, and also from the keyboard. The saved order SHALL be reproduced on the next opening of the page and in the settings reader.

#### Scenario: Drag and drop
- **WHEN** the user moves the second phone to the first place and saves
- **THEN** after reloading the phones are in the new order

#### Scenario: Keyboard
- **WHEN** the user moves a record by its handle with the keyboard
- **THEN** the order changes the same way as with mouse dragging

### Requirement: Logos in the form
The logo block SHALL contain two independent uploads: the large and the small logo, both with the `image` profile and with a preview of the chosen file. The user SHALL be able to choose an uploaded file, replace it, and clear the logo. Saving SHALL reject a file that is not of the `image` profile.

#### Scenario: Uploading a logo
- **WHEN** the user uploads an image as the large logo and saves
- **THEN** after reloading the logo is shown with a preview

#### Scenario: Clearing
- **WHEN** the user clears the logo and saves
- **THEN** the logo is no longer shown, and the file remains in the media library

#### Scenario: Disallowed file
- **WHEN** the user submits a file of another profile as a logo
- **THEN** saving is rejected, and the error is shown next to the corresponding field

### Requirement: Indexing checkbox
The form SHALL contain the checkbox "the site is indexed", which is unchecked by default. The checkbox SHALL only be saved for use on the public frontend; in the admin panel it changes no behavior.

#### Scenario: Default value
- **WHEN** the user opens the page for the first time
- **THEN** the checkbox is unchecked

#### Scenario: Enabling
- **WHEN** the user checks the checkbox and saves
- **THEN** after reloading the checkbox is checked

#### Scenario: No effect on the admin panel
- **WHEN** the checkbox is checked
- **THEN** the admin panel pages behave the same as with the checkbox unchecked

### Requirement: Saving the page
The page SHALL save all settings with one action: either all values are saved or (on a validation error) nothing is changed. On success the user SHALL see a save message; on error — messages next to the corresponding fields with the entered input kept. Repeated saving of unchanged values SHALL NOT change the data. Values that are not in the form SHALL NOT be erased.

#### Scenario: Successful save
- **WHEN** the user changes the name, adds a phone, and clicks "Save"
- **THEN** both changes are saved, and a success message is shown

#### Scenario: Error in one block
- **WHEN** one block has a validation error and another is changed correctly
- **THEN** nothing is saved, the error is shown next to the field, and the entered values remain in the form

#### Scenario: Someone else's settings
- **WHEN** the storage contains settings with other prefixes
- **THEN** saving the page does not change them

#### Scenario: Clearing the name
- **WHEN** the user clears the name and saves
- **THEN** the name is empty, and the other settings are not changed

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

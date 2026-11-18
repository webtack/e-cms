# Spec Delta

## Purpose

Describes the page «Налаштування сайту» ("Site settings") in the admin: which fields it shows, how the user edits the lists, how the values are validated and saved, and who has access.

## ADDED Requirements

### Requirement: Доступ до сторінки налаштувань
The page of the site settings SHALL be available at `/admin/settings` only to an authenticated confirmed user whose role has the system right to the site settings. A user without this right SHALL receive 403 both for viewing and for saving; a guest SHALL be redirected to the login page.

#### Scenario: Role with the right
- **WHEN** a user of a role with the right to the site settings opens `/admin/settings`
- **THEN** the page opens with the current values

#### Scenario: Role without the right
- **WHEN** a user without the right to the site settings opens `/admin/settings` or sends a save
- **THEN** the response is 403, the values are not changed

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/settings`
- **THEN** he is redirected to `/login`

#### Scenario: Administrator
- **WHEN** a user of the role `admin` opens `/admin/settings`
- **THEN** the page opens

### Requirement: Поля сторінки
The page SHALL contain one form with the blocks: the name and the short name (single-line texts), an optional description of the site (a multi-line text field), the large and the small logo (image upload), emails, phones, social networks, working hours and the indexing flag. All blocks except the flag SHALL be optional. The form SHALL show and edit only the values of the base language (`default`), without a language switcher.

#### Scenario: Empty setup
- **WHEN** the user opens the page when nothing has been saved
- **THEN** the fields are empty, the lists have no rows, the indexing flag is unchecked

#### Scenario: Saved values
- **WHEN** the user opens the page after saving the settings
- **THEN** the form shows the saved values of the language `default`, the logo with a preview, and the lists in the saved order

#### Scenario: No language switcher
- **WHEN** several content languages are enabled on the site and the user opens the page
- **THEN** the form still shows only the values of `default`, without a language switcher

### Requirement: Email-и та телефони
The block of emails and the block of phones SHALL be lists of records, where each record has a value and an optional caption. The user SHALL be able to add, delete and change the order of the records. The value of an email SHALL be a valid email address, the value of a phone — a string of digits and the symbols `+`, space, brackets and hyphen. The number of records in each list SHALL be limited by a reasonable bound.

#### Scenario: Adding an email with a caption
- **WHEN** the user adds the email `info@example.com` with the caption «Загальні питання» («General questions») and saves
- **THEN** the record is saved, and after reloading it is shown with the caption

#### Scenario: Phone without a caption
- **WHEN** the user adds a phone without a caption
- **THEN** the record is accepted

#### Scenario: Invalid email
- **WHEN** the user enters a value that is not an email address
- **THEN** the saving is rejected, the error is shown next to the corresponding row

#### Scenario: Invalid phone
- **WHEN** the user enters letters into a phone
- **THEN** the saving is rejected, the error is shown next to the corresponding row

#### Scenario: Empty record
- **WHEN** the user adds a row, leaves the value empty and saves
- **THEN** the saving is rejected with an error next to the value; a caption without a value is not saved

#### Scenario: Exceeding the limit
- **WHEN** the number of records of a list exceeds the set limit
- **THEN** the saving is rejected with an error of the list

### Requirement: Соцмережі
The block of social networks SHALL be a list of records, each of which consists of a network (a choice from the fixed directory), a link and an optional caption. The add button SHALL add a new row with the choice of a network, a link field and a caption field. The user SHALL be able to add several records of the same network, delete records and change their order. The link SHALL be a valid address `http` or `https`.

#### Scenario: Adding a network
- **WHEN** the user clicks «Додати» («Add»), chooses a network, enters a link and saves
- **THEN** the record is saved, and after reloading it is shown in the same order

#### Scenario: Two pages of the same network
- **WHEN** the user adds two records with the network Instagram and different links
- **THEN** both are saved

#### Scenario: Invalid link
- **WHEN** the user enters a link without the scheme `http`/`https`
- **THEN** the saving is rejected, the error is shown next to the link field

#### Scenario: Network not chosen
- **WHEN** the user adds a row and saves without choosing a network
- **THEN** the saving is rejected, the error is shown next to the choice of the network

### Requirement: Графік роботи
The block of working hours SHALL be a list of rows, each of which consists of four single-line text values (the columns). The user SHALL be able to add rows, delete them and change their order. The values of the columns SHALL be arbitrary text without any requirements to the format. The number of rows SHALL be limited by a reasonable bound. A row in which all the columns are empty SHALL NOT be saved.

#### Scenario: Adding a row
- **WHEN** the user adds a row and enters «Пн–Пт» («Mon–Fri»), «9:00», «18:00», «обід 13–14» («lunch 13–14»)
- **THEN** after saving the four values are shown in the same columns

#### Scenario: Partial filling
- **WHEN** the user fills in only the first column of a row
- **THEN** the row is saved, the empty columns remain empty

#### Scenario: Empty row
- **WHEN** the user adds a row and leaves all the columns empty
- **THEN** after saving this row does not exist

#### Scenario: Exceeding the limit
- **WHEN** the number of rows exceeds the set limit
- **THEN** the saving is rejected with an error of the block

### Requirement: Зміна порядку перетягуванням
In the lists of emails, phones, social networks and working hours the user SHALL change the order of the records by dragging the handle, and also by the keyboard. The saved order SHALL be reproduced at the next opening of the page and in the reader of the settings.

#### Scenario: Drag-and-drop
- **WHEN** the user moves the second phone to the first place and saves
- **THEN** after reloading the phones go in the new order

#### Scenario: Keyboard
- **WHEN** the user moves a record by the handle with the keyboard
- **THEN** the order changes in the same way as with the drag by the mouse

### Requirement: Логотипи в формі
The block of logos SHALL contain two independent uploads: the large and the small logo, both with the profile `image` and with a preview of the chosen file. The user SHALL be able to choose the uploaded file, replace it and clear the logo. The saving SHALL reject a file that is not of the profile `image`.

#### Scenario: Uploading a logo
- **WHEN** the user uploads an image as the large logo and saves
- **THEN** after reloading the logo is shown with a preview

#### Scenario: Clearing
- **WHEN** the user clears the logo and saves
- **THEN** the logo is no longer shown, and the file remains in the media library

#### Scenario: Forbidden file
- **WHEN** the user sends a file of another profile as the logo
- **THEN** the saving is rejected, the error is shown next to the corresponding field

### Requirement: Прапорець індексації
The form SHALL contain the flag «сайт індексується» («the site is indexed»), which is unchecked by default. The flag SHALL only be saved for use on the public frontend; in the admin it does not change any behaviour.

#### Scenario: Default value
- **WHEN** the user opens the page for the first time
- **THEN** the flag is unchecked

#### Scenario: Enabling
- **WHEN** the user checks the flag and saves
- **THEN** after reloading the flag is set

#### Scenario: No effect on the admin
- **WHEN** the flag is set
- **THEN** the pages of the admin behave in the same way as with the flag unchecked

### Requirement: Збереження сторінки
The page SHALL save all the settings in one action: either all values are saved or (on a validation error) nothing is changed. On success the user SHALL see a message about saving, on an error — messages next to the corresponding fields with the entered data kept. A repeated saving of unchanged values SHALL not change the data. The values that are absent from the form SHALL not be wiped out.

#### Scenario: Successful saving
- **WHEN** the user changes the name, adds a phone and clicks «Зберегти» («Save»)
- **THEN** both changes are saved, a success message is shown

#### Scenario: Error in one block
- **WHEN** one block has a validation error, and another one is changed correctly
- **THEN** nothing is saved, the error is shown next to the field, the entered values remain in the form

#### Scenario: Foreign settings
- **WHEN** the storage has settings with other prefixes
- **THEN** saving the page does not change them

#### Scenario: Clearing the name
- **WHEN** the user clears the name and saves
- **THEN** the name is empty, the other settings are not changed

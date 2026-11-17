# admin-profile Specification

## Purpose
Lets every signed-in admin panel user maintain their own account: view their role, edit their name and email, change their password and choose the interface language and colour theme, without the right to manage users.

## Requirements

### Requirement: Profile page
The system SHALL provide the page `/admin/profile` in the admin shell for every authenticated user with a verified email. The page SHALL NOT require any role right. A guest SHALL be redirected to `/login`. The page SHALL show the user's name, email and role (the role read-only) and the sections "Personal data", "Password" and "Settings".

#### Scenario: Any signed-in user opens the profile
- **WHEN** an authenticated user without the right to manage users opens `/admin/profile`
- **THEN** they see their own name, email and role and the three sections

#### Scenario: Guest
- **WHEN** a guest opens `/admin/profile`
- **THEN** they are redirected to `/login`

### Requirement: Editing personal data
The system SHALL let the user change their own name and email on the profile page. The name is required (at most 255 characters). The email is required, must be a valid address of at most 255 characters and unique among users (the user's own current address is not a duplicate). A successful save SHALL keep the user on the profile page with a success message. Changing the email SHALL NOT reset the email verification, so the user keeps access to the admin panel. The role and activity of the user SHALL NOT be changed through this page, and a request that carries them SHALL NOT change them.

#### Scenario: Name and email saved
- **WHEN** the user submits a new name and a new unique email
- **THEN** both are saved, the profile page is shown with a success message, and the user stays signed in with their email still verified

#### Scenario: Own email resubmitted
- **WHEN** the user saves the form without changing their email
- **THEN** the save succeeds and no duplicate-email error appears

#### Scenario: Duplicate email
- **WHEN** the user submits an email that belongs to another user
- **THEN** nothing is saved and an error is shown next to the email field

#### Scenario: Role and activity are not editable
- **WHEN** a request to save personal data also carries `role_id` or `is_active`
- **THEN** the role and activity of the user stay unchanged

### Requirement: Changing the password
The system SHALL let the user change their own password on the profile page by entering the current password, the new password and its confirmation. The new password MUST be at least as long as the minimum password length of the users section and MUST match its confirmation. A wrong current password SHALL be rejected with an error next to the current password field and the password SHALL stay unchanged. After a successful change the user SHALL stay signed in in the current session and see a success message, and the password fields SHALL be empty.

#### Scenario: Password changed
- **WHEN** the user submits the correct current password and a valid new password with its confirmation
- **THEN** the password is replaced, the user stays signed in, and they can log in with the new password and no longer with the old one

#### Scenario: Wrong current password
- **WHEN** the user submits an incorrect current password
- **THEN** the password stays unchanged and an error is shown next to the current password field

#### Scenario: New password too short or not confirmed
- **WHEN** the new password is shorter than the minimum or differs from its confirmation
- **THEN** the password stays unchanged and an error is shown next to the new password field

### Requirement: Interface settings
The profile page SHALL contain a "Settings" section where the user can change the interface language and the colour theme. The language choice SHALL be saved for the user like the language switcher of the header does. The theme choice SHALL be remembered only in the browser, as the theme toggle of the header does.

#### Scenario: Language changed on the profile
- **WHEN** the user selects another language in the "Settings" section
- **THEN** the interface switches to that language and the choice is saved for the user

#### Scenario: Theme changed on the profile
- **WHEN** the user selects another theme in the "Settings" section
- **THEN** the theme is applied immediately and is kept in this browser

### Requirement: Avatar
The profile page SHALL let the user upload, replace and remove their own avatar. Only an image of the avatar upload profile is accepted; a file of another profile or a non-image SHALL be rejected with an error next to the avatar field and the avatar SHALL stay unchanged. A saved avatar SHALL be shown on the profile page in place of the initials; a user without an avatar SHALL see their initials. Removing the avatar SHALL restore the initials. The avatar SHALL be recorded in the media library as used by this user.

#### Scenario: Avatar uploaded
- **WHEN** the user uploads a valid image as their avatar and saves
- **THEN** the profile page shows the picture instead of the initials and the media library lists the file as used by the user

#### Scenario: Avatar replaced
- **WHEN** the user uploads another image over an existing avatar and saves
- **THEN** the new picture is shown and the media library no longer lists the previous file as used by the user

#### Scenario: Avatar removed
- **WHEN** the user removes their avatar and saves
- **THEN** the initials are shown again and the previous file is no longer listed as used by the user

#### Scenario: Wrong kind of file
- **WHEN** the request carries a file that was not uploaded with the avatar profile
- **THEN** the avatar stays unchanged and an error is shown next to the avatar field

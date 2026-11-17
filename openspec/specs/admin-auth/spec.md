# admin-auth Specification

## Purpose

Lets the administrator log in and log out, and guarantees that the `/admin` section is available only to authenticated users with a verified email address.

## Requirements

### Requirement: Login by email and password
The system SHALL provide a login page at `/login` that authenticates the user by email and password and, after success, redirects them to `/admin/dashboard` (or to the address requested earlier).

#### Scenario: Successful login
- **WHEN** a guest submits the correct email and password of a verified user to `/login`
- **THEN** a session is created, and the user is redirected to `/admin/dashboard`

#### Scenario: Wrong credentials
- **WHEN** a guest submits a wrong password
- **THEN** they stay on the login page and see an error message next to the field, and no session is created

#### Scenario: Authenticated user on the login page
- **WHEN** an already authenticated user opens `/login`
- **THEN** they are redirected to `/admin/dashboard`

### Requirement: Logout
The system SHALL allow an authenticated user to end the session with a POST request to `/logout` and SHALL then redirect to the login page.

#### Scenario: Logout
- **WHEN** an authenticated user performs the logout action from the user menu
- **THEN** the session is destroyed, and the next attempt to open `/admin/dashboard` redirects to `/login`

### Requirement: Protection of the admin section
All routes under the `/admin` prefix SHALL be available only to authenticated users with a verified email.

#### Scenario: Guest opens the admin panel
- **WHEN** an unauthenticated visitor opens `/admin/dashboard`
- **THEN** they are redirected to `/login`, and the admin content is not returned

#### Scenario: Unverified email
- **WHEN** an authenticated user without a verified email opens `/admin/dashboard`
- **THEN** access is denied, and the admin content is not returned

### Requirement: Initial administrator
After the installer (or the seeder it uses) has run, the system SHALL contain at least one user with a verified email and the system role `admin`, under whom the admin panel can be logged into. The email and the password of this user SHALL come only from explicitly provided values: the installer options, an interactive prompt, or the variables `ADMIN_EMAIL` and `ADMIN_PASSWORD`; the system SHALL NOT supply a default email or a default password in any environment. When either value is missing and cannot be asked for (the seeder, or the installer without interaction), it SHALL fail with an error that names the missing values and SHALL NOT create or change any user.

#### Scenario: Login as the initial user
- **WHEN** the administrator credentials were provided explicitly, the installer (or the seeder) has run, and the developer logs in with these credentials
- **THEN** the login succeeds, and `/admin/dashboard` opens

#### Scenario: Credentials are not provided
- **WHEN** the seeder or `ecms:install --no-interaction` is run without an email or without a password, in any environment
- **THEN** it fails with an error naming the missing values, and no user is created or updated

#### Scenario: A re-seed does not bring back a default account
- **WHEN** the seeder is run again after the account `admin@example.com` was deleted, and `ADMIN_EMAIL` is a different address
- **THEN** no user with the address `admin@example.com` is created

### Requirement: Deactivated user
A user with activity turned off SHALL be unable to log in, and their already open session SHALL be terminated on the next request.

#### Scenario: Login of a deactivated user
- **WHEN** a deactivated user submits the correct email and password to `/login`
- **THEN** the login is rejected with an error message, and no session is created

#### Scenario: Deactivation of an open session
- **WHEN** an administrator deactivates a user who has an open session, and that user makes the next request
- **THEN** the session is ended, and the user is redirected to `/login`

### Requirement: Styled login page
The login page SHALL be displayed over a full-page background image, with the login form placed in a card-coloured block centred on the page, and the admin logo (its white variant in the dark theme) and the site name from the site settings shown at the top of that block, with the application name as the fallback. The language switcher SHALL remain available. The form fields, validation errors, and behaviour of the login SHALL NOT change.

#### Scenario: Look of the login page
- **WHEN** a guest opens `/login`
- **THEN** the page shows the background image, the logo, the site name, and the email and password fields inside a card block

#### Scenario: Errors in the card
- **WHEN** a guest submits wrong credentials
- **THEN** the error message is shown inside the card block, and the page keeps its look


### Requirement: Login attempts are limited
The system SHALL limit failed login attempts in two ways: after 5 failed attempts for the same email address (compared case-insensitively) from the same IP address, further attempts for that pair SHALL be refused until the lockout ends; and after a configured number of failed attempts from one IP address across all email addresses within a configured window, further attempts from that IP address SHALL be refused until the window ends, whatever the email. A refusal SHALL show the same message next to the email field, without revealing whether the account exists or is deactivated. A successful login SHALL reset the counter of its email and IP pair and SHALL NOT reset the counter of the IP address.

#### Scenario: Same email and IP
- **WHEN** a guest fails 5 times to log in with the same email from one IP address and then submits the correct password
- **THEN** the login is refused with the throttle message

#### Scenario: Many emails from one IP
- **WHEN** a guest fails to log in with a different email each time from one IP address until the per-IP limit is reached and then submits correct credentials
- **THEN** the login is refused with the throttle message

#### Scenario: Another IP address
- **WHEN** the per-IP limit of one address is reached and a guest from another IP address logs in with correct credentials
- **THEN** the login succeeds

#### Scenario: Success does not reset the per-IP counter
- **WHEN** a guest from an IP address near the per-IP limit logs in successfully with a valid account and then fails again
- **THEN** the failures before the successful login still count toward the per-IP limit

### Requirement: Other sessions end when the password changes
The system SHALL end every session and every remember-me login of a user, except the session in which the user changed their own password, when that user's password changes, whether the user changed it on the profile page or an administrator reset it. A request from an ended session SHALL be treated as a guest request (a redirect to `/login`). The session in which the user changed their own password SHALL stay signed in. A change that does not alter the password (name, email, role, activity) SHALL NOT end any session by this rule. Users who are signed in when this behaviour is first deployed SHALL stay signed in until their password changes.

#### Scenario: Own password change
- **WHEN** a user signed in on two browsers changes the password in the first one
- **THEN** the first browser stays signed in, and the next request of the second browser redirects to `/login`

#### Scenario: Password reset by an administrator
- **WHEN** an administrator sets a new password for a signed-in user
- **THEN** the next request of that user's session redirects to `/login`, and the administrator's own session is not affected

#### Scenario: Remember-me login
- **WHEN** a user with a remember-me login (no live session) has the password changed
- **THEN** the next request with the remember-me cookie redirects to `/login`

#### Scenario: Other changes keep sessions
- **WHEN** a user's name or email changes, or an administrator changes the role of a signed-in user
- **THEN** the user's sessions stay signed in (a role change applies from the next request, as before)

#### Scenario: Existing sessions at deployment
- **WHEN** a user whose session was created before this behaviour was deployed makes the first request after it
- **THEN** the request is served and the session stays signed in

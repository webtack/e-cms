## ADDED Requirements

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

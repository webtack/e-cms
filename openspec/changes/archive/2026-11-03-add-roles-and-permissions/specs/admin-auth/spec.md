# Spec Delta

## ADDED Requirements

### Requirement: Деактивований користувач
A user with the activity switched off SHALL not be able to log in, and his already open session SHALL be terminated on the next request.

#### Scenario: Login of a deactivated user
- **WHEN** a deactivated user submits the correct email and password to `/login`
- **THEN** the login is rejected with an error message, and no session is created

#### Scenario: Deactivation of an open session
- **WHEN** the administrator deactivates a user who has an open session, and that user makes the next request
- **THEN** the session is terminated, and the user is redirected to `/login`

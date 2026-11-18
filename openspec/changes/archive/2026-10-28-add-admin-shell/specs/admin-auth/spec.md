# Spec Delta

## Purpose

Lets the administrator log in and out, and guarantees that the `/admin` section is available only to authenticated users with a confirmed email address.

## ADDED Requirements

### Requirement: Login with email and password
The system SHALL provide a login page at `/login` that authenticates the user by email and password and, after success, redirects them to `/admin/dashboard` (or to the address requested earlier).

#### Scenario: Successful login
- **WHEN** a guest submits the correct email and password of a confirmed user to `/login`
- **THEN** a session is created, and the user is redirected to `/admin/dashboard`

#### Scenario: Wrong credentials
- **WHEN** a guest submits a wrong password
- **THEN** they stay on the login page and see an error message next to the field, and no session is created

#### Scenario: Authenticated user on the login page
- **WHEN** an already authenticated user opens `/login`
- **THEN** they are redirected to `/admin/dashboard`

### Requirement: Logout
The system SHALL let an authenticated user end the session with a POST request to `/logout` and SHALL then redirect to the login page.

#### Scenario: Logout
- **WHEN** an authenticated user runs the logout action from the user menu
- **THEN** the session is destroyed, and the next attempt to open `/admin/dashboard` redirects to `/login`

### Requirement: Protection of the admin section
All routes under the `/admin` prefix SHALL be available only to authenticated users with a confirmed email.

#### Scenario: Guest opens the admin
- **WHEN** an unauthenticated visitor opens `/admin/dashboard`
- **THEN** they are redirected to `/login`, and the admin content is not returned

#### Scenario: Unconfirmed email
- **WHEN** an authenticated user without a confirmed email opens `/admin/dashboard`
- **THEN** access is denied, and the admin content is not returned

### Requirement: Initial administrator
After the seeders run, the system SHALL contain at least one user with a confirmed email, with which one can log in to the admin.

#### Scenario: Login as the initial user
- **WHEN** the migrations and seeders have been run, and the developer logs in with the credentials of the initial user
- **THEN** the login succeeds, and `/admin/dashboard` opens

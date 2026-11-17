# Spec Delta

## MODIFIED Requirements

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

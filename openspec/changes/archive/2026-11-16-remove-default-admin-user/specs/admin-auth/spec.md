## MODIFIED Requirements

### Requirement: Initial administrator
After the seeders are run, the system SHALL contain at least one user with a verified email, under whom the admin panel can be logged into. The email and the password of this user SHALL come only from the explicitly provided variables `ADMIN_EMAIL` and `ADMIN_PASSWORD`; the system SHALL NOT supply a default email or a default password in any environment. When either variable is missing, the seeder SHALL fail with an error that names the missing variables and SHALL NOT create or change any user.

#### Scenario: Login as the initial user
- **WHEN** `ADMIN_EMAIL` and `ADMIN_PASSWORD` are set, migrations and seeders have been run, and the developer logs in with these credentials
- **THEN** the login succeeds, and `/admin/dashboard` opens

#### Scenario: Credentials are not provided
- **WHEN** the seeder is run without `ADMIN_EMAIL` or without `ADMIN_PASSWORD`, in any environment
- **THEN** the seeder fails with an error naming the missing variables, and no user is created or updated

#### Scenario: A re-seed does not bring back a default account
- **WHEN** the seeder is run again after the account `admin@example.com` was deleted, and `ADMIN_EMAIL` is a different address
- **THEN** no user with the address `admin@example.com` is created

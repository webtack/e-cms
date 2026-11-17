## ADDED Requirements

### Requirement: The e2e suite has its own administrator
The e2e suite SHALL log in as a dedicated administrator account (email `E2E_ADMIN_EMAIL`, default `e2e-admin@example.com`) and SHALL NOT depend on the administrator created by `DatabaseSeeder`. Before the first test the system SHALL create this account with the administrator role, or bring an existing one back to that state, using the password from `E2E_ADMIN_PASSWORD`. The password SHALL have no default: when `E2E_ADMIN_PASSWORD` is missing, the run SHALL fail before any test with an error that names the variable. The suite SHALL NOT contain a hard-coded password for an administrator account.

#### Scenario: The account is created on the first run
- **WHEN** the developer runs `npm run test:e2e` with `E2E_ADMIN_PASSWORD` set, and no e2e administrator exists
- **THEN** the account is created with the administrator role and the given password, and the run signs it in

#### Scenario: The account already exists with another state
- **WHEN** the e2e administrator exists but has another password, is deactivated, or has another role
- **THEN** before the first test it is brought to the state described above, and the run signs it in

#### Scenario: The password is not provided
- **WHEN** the developer runs `npm run test:e2e` without `E2E_ADMIN_PASSWORD`
- **THEN** the run fails before any test with an error that names `E2E_ADMIN_PASSWORD`

#### Scenario: An existing account is reused
- **WHEN** a user with the address `E2E_ADMIN_EMAIL` already exists
- **THEN** that user is updated and signed in, and no second account is created

#### Scenario: The seeded administrator is not needed
- **WHEN** no user with the address `admin@example.com` exists
- **THEN** the e2e run works without any difference

## MODIFIED Requirements

### Requirement: An e2e run starts with the English admin interface
Before running the tests the system SHALL return the saved language of the e2e administrator account (`E2E_ADMIN_EMAIL`) to English, regardless of the state left by a previous run. The system SHALL NOT change the language of other users.

#### Scenario: A previous run was stopped with Ukrainian
- **WHEN** the e2e administrator's `users.locale` is still `uk` after an interrupted scenario, and the developer runs `npm run test:e2e`
- **THEN** before the first test the administrator's language becomes `en`, and the tests see the English interface without manual actions

#### Scenario: The language is already English
- **WHEN** the developer runs `npm run test:e2e`, and the administrator's language is already `en`
- **THEN** the reset does not change the state, and the run continues as usual

#### Scenario: The application container is unavailable
- **WHEN** the containers are stopped and the language cannot be reset
- **THEN** the run fails with a clear error about the unavailability of the application, and not with dozens of false failures caused by the language

### Requirement: The administrator logs in once per e2e run
The system SHALL sign in the e2e administrator (`E2E_ADMIN_EMAIL`) once, before the first test of a run, and SHALL give every test an authenticated administrator session without repeating the login form. This SHALL hold for a selective run of one or several specs too. A test that needs a different state (a guest, another user, or an ended session) SHALL be able to opt out and start without the saved session. A test that ends or invalidates the saved session SHALL NOT affect later tests.

#### Scenario: Tests start already signed in
- **WHEN** a test that does not opt out opens `/admin/dashboard`
- **THEN** the administrator's dashboard opens without passing through `/login`

#### Scenario: Selective run
- **WHEN** the developer runs one spec with `npm run test:e2e -- e2e/<file>.spec.js`
- **THEN** the administrator is signed in once before its first test, as in the full run

#### Scenario: A guest test
- **WHEN** a test opts out of the saved session and opens `/admin/dashboard`
- **THEN** it is redirected to `/login`

#### Scenario: A test that logs out
- **WHEN** a test logs out through the interface
- **THEN** the tests after it still start signed in as the administrator

#### Scenario: Login fails
- **WHEN** the administrator cannot be signed in before the run (wrong credentials or containers stopped)
- **THEN** the run fails with a clear error about the login, and not with dozens of failures from unauthenticated tests

# Spec Delta

## ADDED Requirements

### Requirement: The administrator logs in once per e2e run
The system SHALL sign in the seeded administrator (`ADMIN_EMAIL`) once, before the first test of a run, and SHALL give every test an authenticated administrator session without repeating the login form. This SHALL hold for a selective run of one or several specs too. A test that needs a different state (a guest, another user, or an ended session) SHALL be able to opt out and start without the saved session. A test that ends or invalidates the saved session SHALL NOT affect later tests.

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

### Requirement: Test data is not re-created between tests that do not change it
A spec SHALL prepare its test data once for a group of tests that only read it, and SHALL re-create the data before each test only when that test changes it. Every test SHALL still start from a known data state, independent of the order in which tests ran and of data left by a previous or interrupted run.

#### Scenario: Read-only tests share one preparation
- **WHEN** a group of tests only reads the seeded data
- **THEN** the data is created once for the group, not once per test

#### Scenario: A test that changes data
- **WHEN** a test creates, edits, or deletes the seeded records
- **THEN** the data is re-created before that test, so the next test does not see the change

#### Scenario: A previous run left data behind
- **WHEN** a run was interrupted and its test records remain in the database
- **THEN** the next run removes them before preparing its data, and the tests see only their own records

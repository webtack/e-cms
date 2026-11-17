## ADDED Requirements

### Requirement: An e2e run restores the data it wipes
The system SHALL save the application settings, the additional content languages, the admin menu entries, the favorite types and the relations of the site-setting files before an e2e run, and SHALL restore them after the run, so that the developer's own data survives specs that start from a clean installation. The restore SHALL happen for a full run, for a selective run of individual specs, and for a run with failed tests.

#### Scenario: A run wipes the site settings
- **WHEN** the site has a name, contacts and a logo, and the developer runs `npm run test:e2e -- e2e/admin-site-settings.spec.js`
- **THEN** after the run the name, contacts, logo relation and the other `site.*` settings have their pre-run values

#### Scenario: A run wipes the languages and the admin menu
- **WHEN** the site has additional content languages, the base language code, the multilingual switch, admin menu entries and favorite types, and a run executes `admin-content-languages` and `admin-favorites-menu`
- **THEN** after the run all of them are present as before the run

#### Scenario: Tests fail
- **WHEN** a test fails during the run
- **THEN** the data is still restored after the run

### Requirement: An interrupted run does not lose the original data
The system SHALL keep the saved state until it is restored. When the next run finds a saved state that was not restored (the previous run was interrupted), it SHALL restore it first and only then save a new state.

#### Scenario: A run was interrupted
- **WHEN** a run was stopped before the restore, leaving the database with test data and the saved state in place, and the developer starts a new run
- **THEN** before the first test the saved state is restored, and the new state is taken from the restored data, not from the test data

### Requirement: Restoring touches only the covered data
The restore SHALL replace only the covered data (settings, languages, admin menu entries, favorite types, relations of site-setting files) and SHALL NOT change other tables, including users, content types, nodes and files.

#### Scenario: Other data is untouched
- **WHEN** a run finishes and the data is restored
- **THEN** users, content types, nodes, taxonomy and files that existed before the run are unchanged

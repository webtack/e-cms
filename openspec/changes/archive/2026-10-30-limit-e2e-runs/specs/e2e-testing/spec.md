# Spec Delta

## ADDED Requirements

### Requirement: Selective run of individual e2e specs
The system SHALL allow running one or several e2e specs by the file path with the command `npm run test:e2e -- e2e/<файл>.spec.js` on the host, and SHALL perform for such a run the same preparation as for the full run (in particular, resetting the administrator's language to English).

#### Scenario: Running one spec
- **WHEN** the developer runs `npm run test:e2e -- e2e/admin-page-types.spec.js` with the containers running
- **THEN** only the tests from this file are executed, and the command finishes with the exit code of their result

#### Scenario: Preparation runs for a selective run too
- **WHEN** the administrator's `users.locale` is still `uk`, and the developer runs one spec
- **THEN** before the first test the administrator's language becomes `en`, as in the full run

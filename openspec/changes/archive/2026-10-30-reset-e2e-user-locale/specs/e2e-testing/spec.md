# Spec Delta

## ADDED Requirements

### Requirement: Запуск e2e починається з англійського інтерфейсу адміністратора
Before running the tests the system SHALL switch the stored language of the administrator account under which the e2e tests sign in (`ADMIN_EMAIL`) to English, regardless of the state left by the previous run. The system SHALL NOT change the language of other users.

#### Scenario: The previous run was stopped with Ukrainian
- **WHEN** the administrator's `users.locale` remained `uk` after an interrupted scenario, and the developer runs `npm run test:e2e`
- **THEN** before the first test the administrator's language becomes `en`, and the tests see the English interface without manual steps

#### Scenario: The language is already English
- **WHEN** the developer runs `npm run test:e2e`, and the administrator's language is already `en`
- **THEN** the reset does not change the state, and the run continues as usual

#### Scenario: The application container is unavailable
- **WHEN** the containers are stopped and the language cannot be reset
- **THEN** the run finishes with a clear error about the unavailability of the application, not with dozens of false failures caused by the language

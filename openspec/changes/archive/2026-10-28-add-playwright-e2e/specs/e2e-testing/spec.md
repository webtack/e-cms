# Spec Delta

## Purpose

Lets a developer run browser e2e tests against the application running in Docker without changing the Docker environment, and keeps the separation of PHP (container) and Node (host).

## ADDED Requirements

### Requirement: E2E tests run from the host
The system SHALL let e2e tests be run with a single command `npm run test:e2e` on the host, without `docker compose exec`.

#### Scenario: Running on the host
- **WHEN** the developer runs `npm run test:e2e` on the host while the containers are running
- **THEN** the tests run in Chromium and the command finishes with the exit code of the test results

### Requirement: Tests work against the Docker application
E2E tests SHALL address the application at `http://localhost:8080` and SHALL NOT start their own server.

#### Scenario: The application is available
- **WHEN** the containers are running and respond on `http://localhost:8080`
- **THEN** relative navigations in the tests (for example, `/`) open this application

#### Scenario: The application is unavailable
- **WHEN** the containers are stopped
- **THEN** the tests fail with a connection error, instead of starting a separate server

### Requirement: Chromium only
The system SHALL run e2e tests only in Chromium.

#### Scenario: A single browser
- **WHEN** `npm run test:e2e` is run
- **THEN** the tests run in a single Chromium project, without Firefox and WebKit

### Requirement: Separation of e2e and PHPUnit
E2E tests SHALL be stored in `e2e/` and SHALL NOT be picked up by the PHPUnit run.

#### Scenario: PHPUnit does not see e2e
- **WHEN** `docker compose exec app php artisan test` is run
- **THEN** the files from `e2e/` are not run

### Requirement: Smoke check
The system SHALL contain at least one e2e test that confirms the home page is available.

#### Scenario: The home page responds
- **WHEN** the smoke test opens `/`
- **THEN** the response is successful (status below 400)

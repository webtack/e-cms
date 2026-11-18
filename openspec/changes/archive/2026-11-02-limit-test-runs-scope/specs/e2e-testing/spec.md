# Spec Delta

## ADDED Requirements

### Requirement: Вибірковий запуск PHP-тестів у правилах розробки
The development rules SHALL require running PHP tests (PHPUnit `tests/Unit` and `tests/Feature`) only for the changed area (by the filter `--filter` or by the path to a file or a directory of tests), and not the whole set, if the full run is not necessary. The full set of PHP tests SHALL be required only for changes that touch the whole application: migrations and the DB schema, the shared test base (`tests/TestCase.php`, shared test helpers), `phpunit.xml`, `composer` dependencies, global middleware, providers, configuration, authentication. The rules SHALL NOT require a separate full run of PHP tests at the end of a change. The choice of tests SHALL NOT require confirmation before the run. At the end of a task the report SHALL name the PHP tests that were run and the reason why the rest were skipped.

#### Scenario: Change of one controller or model
- **WHEN** the code of one area is changed (for example, content types), and the corresponding Feature tests are known
- **THEN** only the tests of this area are run (`docker compose exec app php artisan test --filter=…` or the path to a file), and the rest of the set is skipped

#### Scenario: Change of the shared base
- **WHEN** a migration, `tests/TestCase.php`, `phpunit.xml` or global middleware is changed
- **THEN** a full `docker compose exec app php artisan test` is allowed

#### Scenario: Report of the run
- **WHEN** the task is finished
- **THEN** the report names the PHP tests that were run and the reason for skipping the rest (or states that PHP tests were not run, and why)

#### Scenario: Rule for e2e remains in force
- **WHEN** the edit does not touch the behaviour in the browser
- **THEN** e2e is skipped in the same way or only the spec of the changed area is run, as the requirement «Вибір e2e-спеків для змін спільного коду» ("Choice of e2e specs for changes of shared code") requires

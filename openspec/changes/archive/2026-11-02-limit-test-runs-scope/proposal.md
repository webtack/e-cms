# Proposal

## Why

The development rules already limit the e2e runs (only the spec of the changed area), but for PHP tests there is no such rule: after each edit there is a temptation to run the whole `php artisan test`, although the change touches a small part of the code. This slows the work down just as much as a full e2e run.

## What Changes

- A new rule in `CLAUDE.md`: PHP tests (PHPUnit/Feature/Unit) are not run all together unless there is a need; only the tests of the changed area are run (`--filter`, a path to a file or a directory). The rule for e2e (a selective run) remains in force and is explicitly named next to it.
- The same rule is added to `openspec/config.yaml` (`rules.tasks`), so that the tasks of future changes do not contain a full PHP test run without need.
- In `docs/e2e-testing.md` a section appears about the selective run of PHP tests (when a selective run is enough, when a full one is needed, the report format), next to the existing section «Коли запускати e2e» ("When to run e2e").
- Delta specification `e2e-testing`: a new requirement about the selective run of PHP tests in the development rules.

Assumption (recorded, because the code does not define it): a full run of PHP tests is justified only for changes that touch the whole application: migrations and the DB schema, `tests/TestCase.php` and shared test helpers, `phpunit.xml`, the dependencies `composer.json`/`composer.lock`, global middleware, providers, configuration, authentication. The choice of tests is not to be asked about before the run (as for e2e).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `e2e-testing`: a requirement about the selective run of PHP tests in the development rules is added (the existing requirement «Вибір e2e-спеків для змін спільного коду» — "Choice of e2e specs for changes of shared code" — does not change).

## Impact

- Rules: `CLAUDE.md`, `openspec/config.yaml`.
- Documentation: `docs/e2e-testing.md` is updated (a new section about PHP tests); `docs/README.md` does not change (no new files).
- The application code, the tests and `playwright.config.js` do not change.

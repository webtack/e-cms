# Tasks

## 1. Development rules

- [x] 1.1 Add to `CLAUDE.md` a section «PHP-тести» («PHP tests») (the selective run by `--filter`/path through `docker compose exec app php artisan test ...`, the list of exceptions for the full run, no questions about the choice, a report on the tests run and skipped, a mention that e2e follows the section «Playwright (e2e)»); check that the section is in the file, and the e2e rules are not changed.
- [x] 1.2 Add to `openspec/config.yaml` in `rules.tasks` a rule: the check of PHP tests in a task runs only the tests of the changed area, the full `php artisan test` — only for the exceptions, there is no separate final full run; check that `openspec status --change limit-test-runs-scope` and `openspec validate limit-test-runs-scope` pass (the YAML is valid).

## 2. Documentation

- [x] 2.1 Add to `docs/e2e-testing.md` a section «Коли запускати PHP-тести» («When to run PHP tests») (the selective run by area, the exceptions for the full run, no confirmation, the report with an example) and a cross-reference from the section «Коли запускати e2e» («When to run e2e»); check that the commands in the section match those in `CLAUDE.md`, and that `docs/README.md` needs no changes.

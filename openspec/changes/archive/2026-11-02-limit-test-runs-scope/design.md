# Design

## Context

The rules about the selective e2e are already fixed in `CLAUDE.md` (section «Playwright (e2e)»), `openspec/config.yaml` (`rules.tasks`) and `docs/e2e-testing.md` (section «Коли запускати e2e» — "When to run e2e"). For PHP tests these places contain only commands (`docker compose exec app php artisan test ...`), and there is no rule for the choice. See proposal.md — Why.

## Goals / Non-Goals

**Goals:**
- Mirror for PHP tests the structure of the e2e rule: selective by area, the full run only for the listed exceptions, without confirmation, with a report.

**Non-Goals:**
- Changing the tests, `phpunit.xml` or the run scripts.
- Introducing an automatic correspondence «area → test» (a table) for PHP; we limit ourselves to `--filter`/a path, because the names of the tests in `tests/Feature` already match the areas (see `docs/admin-*.md`).

## Decisions

- **Place of the rule: `CLAUDE.md` + `openspec/config.yaml` + `docs/e2e-testing.md`.** These are the same three places where the e2e rule lives, so they remain consistent. Alternative — a separate `docs/php-testing.md`: rejected, because the rules of the two kinds of tests must be read together, and no new index entry in `docs/README.md` is needed.
- **A separate section in `CLAUDE.md` «PHP-тести» («PHP tests»)** (next to «Docker» and «Playwright (e2e)»), not an addition to the Docker section: the rule is about the choice of tests, not about the environment.
- **The list of exceptions for the full run** (migrations/schema, `tests/TestCase.php` and shared helpers, `phpunit.xml`, `composer` dependencies, global middleware, providers, configuration, authentication) — an assumption, recorded in proposal.md; it can be refined without changing the structure of the rule.
- **Specification:** the requirement is added to the existing capability `e2e-testing` (next to the requirement about the choice of e2e specs and the requirement about separating e2e and PHPUnit), without creating a new capability.

Component documentation: `docs/e2e-testing.md` (section «Коли запускати e2e» and a new section about PHP tests).

## Risks / Trade-offs

- [A selective run will miss a regression in an adjacent area] → the exceptions for the full run cover the shared code; for the rest — run the consumers' tests (`grep` of the usages), as for e2e.

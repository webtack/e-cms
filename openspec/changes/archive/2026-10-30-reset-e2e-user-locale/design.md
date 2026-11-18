# Design

## Context

All specs run under one seeded user. `SetLocale` (`app/Http/Middleware/SetLocale.php`) determines the language in this order: `users.locale` → cookie `locale` → `Accept-Language` → `config('app.locale')`. So a `uk` left in `users.locale` overrides Playwright's English `Accept-Language`. Specs that switch the language return `English` in `afterEach`/`finally`, but this does not run when the Playwright process is stopped (Ctrl-C, a Chromium crash, a dropped connection) — the state stays in the DB until the next run. Details of the scenarios: `docs/e2e-testing.md`. The specs already call `docker compose exec -T app php artisan tinker` (`admin-content-types.spec.js`, `admin-content-fields.spec.js`), so the host-to-container connection is already acceptable for e2e.

## Goals / Non-Goals

**Goals:**
- Every run of `npm run test:e2e` starts with `locale = 'en'` for the administrator without manual steps.
- It also works after an interrupted run.

**Non-Goals:**
- A separate e2e user, a separate DB or a change of `SetLocale`.
- Removing the existing `afterEach`/`finally` (they are needed for isolation within a run).
- Resetting the language after the run finishes (see below).

## Decisions

- **Playwright `globalSetup` (`e2e/global-setup.js`), connected in `playwright.config.js`.** It runs once before all tests and on every run, so it restores the state itself regardless of how the previous run ended. The reset is performed through `execFileSync('docker', ['compose','exec','-T','app','php','artisan','tinker','--execute=…'])` — the same mechanism as in the existing specs.
- **We reset only the user with `ADMIN_EMAIL`** (by default `admin@example.com`, as in the specs), not all users: this is narrower and sufficient, because only that user is used in the tests. The email is passed to PHP as a JSON literal so as not to break the quotes.
- **Reset to `'en'`, not `null`:** a deterministic result that matches what the specs return to and what is described in the documentation.
- **An error of the reset stops the run** with a message (the containers are not running / the command failed) — otherwise there would be dozens of false failures.

Alternatives considered:
- **`globalTeardown`** — it does not run on Ctrl-C/a crash, so it does not solve the exact case that breaks; redundant next to the setup.
- **A separate e2e user / `storageState` per spec** — isolates completely, but requires seeding, changes in all specs and a login strategy; excessive for this problem.
- **`beforeAll` in each spec** — duplication, and easy to forget in a new spec; the global setup covers everything centrally.
- **Forcing the language in requests (cookie/header)** — does not help: `users.locale` has a higher priority than both.

## Risks / Trade-offs

- [An extra ~1–2 s per run through `docker compose exec`] → acceptable, it runs once.
- [The reset touches the real local DB] → limited to one user and one column; e2e already run against the dev DB.
- [A failure in the middle of a run between specs is still possible] → `afterEach`/`finally` remain; `workers: 1` keeps the order.

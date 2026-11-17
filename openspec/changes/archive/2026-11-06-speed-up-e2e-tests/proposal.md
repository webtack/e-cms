# Proposal

## Why

The e2e suite is slow: about 343 tests run on one worker (`workers: 1`), and `admin-content-nodes.spec.js` alone takes about 3.2 minutes for 108 tests. Narrowing which specs run (already documented in `docs/e2e-testing.md`) does not make a full run or a single large spec faster. Measurements show a floor of about 0.8 s even for trivial tests, because every test repeats the same preparation:

- **Login in every test.** About 130 tests call `login(page)` (UI form, redirect, dashboard render) instead of reusing a session.
- **Data seeding in every test.** 13 specs re-create their data in `beforeEach` through `docker compose exec app php artisan tinker`, although most of their tests only read that data.

Both costs can be removed without touching what the tests verify.

## What Changes

- The administrator logs in **once per run**, in `globalSetup`, and the session is saved and reused by all tests through a Playwright storage state. Specs that need a guest, a different user, or that end the session (logout) opt out explicitly.
- Specs whose tests only read their seeded data prepare it **once per group** (`beforeAll` in a `describe`), and only tests that change the data keep a per-test `beforeEach` seed. Each group still starts from a known state.
- The duplicated `tinker` and `login` helpers move into one shared module under `e2e/`, so the new session and seeding rules live in one place.
- `docs/e2e-testing.md` describes the saved session, which specs opt out, and the seeding rule for new specs.
- No test scenario is removed or weakened; coverage stays the same.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `e2e-testing`: adds requirements for a single administrator login per run (with explicit opt-out for specs that need another state) and for data isolation that does not re-seed between tests that do not change that data.

## Impact

- Code: `playwright.config.js`, `e2e/global-setup.js`, a new shared helper module in `e2e/`, and all 19 spec files (login and seeding structure only; assertions are unchanged).
- Docs: `docs/e2e-testing.md` is updated (structure, session and seeding rules, per-spec notes where they mention `beforeEach`/login). `docs/README.md` needs no new link.
- Rules: `playwright.config.js` and `e2e/global-setup.js` change, so the verification of that task is the one case where the project rules allow a full `npm run test:e2e`.
- No application code, database schema, or dependencies change. A generated session file under `e2e/` is added to `.gitignore`.

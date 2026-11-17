# Design

## Context

See `proposal.md` for motivation. Facts observed in the code and measured:

- `playwright.config.js`: `workers: 1` (the seeded administrator and its `users.locale` are shared), `globalSetup` resets that locale through `docker compose exec -T app php artisan tinker`.
- 18 specs define their own copy of `login(page)` (UI form) and call it per test (38 times in `admin-content-nodes`); 13 specs define their own `tinker(code)` and run `test.beforeEach(() => tinker(seed))`.
- `docker compose exec ... tinker --execute='echo 1;'` takes about 0.35 s; a spec seed (`cleanup` + creating types, fields, nodes) costs more. In `admin-content-nodes` the trivial tests (a row menu, a 404, a counter) take 0.8–1.0 s each, so about 85 s of its 193 s is preparation, not assertions. `admin-menus` runs 15 tests in 24 s.
- Sessions that must not be shared: `admin-shell` (guest redirect, login form, logout), `admin-i18n` (guest login page, language persisting after logout), `admin-users` (logs in as created users in `browser.newContext`), `admin-roles` and `admin-favorites-menu` (limited users), and every `guest cannot open ...` test.
- Logout invalidates the server-side session, so a saved session reused after a logout test would be dead for all later tests.

## Goals / Non-Goals

**Goals:**
- Cut the time of a full run and of single large specs by removing repeated login and repeated seeding, with the same assertions.
- Keep each test independent of order and of leftovers from an interrupted run.
- Keep the changes mechanical and reviewable per spec.

**Non-Goals:**
- Running tests in parallel (needs per-worker users and data; separate change).
- Replacing `tinker` as the way to prepare data (for example, with a test-only HTTP endpoint or a persistent PHP process); this adds application surface and is not needed to reach the goal.
- Rewriting, merging, or removing scenarios; changing application code.
- Changing which specs the development rules ask to run (already done in `CLAUDE.md` and `docs/e2e-testing.md`).

## Decisions

### 1. Log in once in `globalSetup` and reuse the storage state

`globalSetup` (after resetting the locale) opens Chromium, submits the real login form with `ADMIN_EMAIL` / `ADMIN_PASSWORD`, and saves `storageState` to `e2e/.auth/admin.json`. `playwright.config.js` sets `use.storageState` to that file, so every test starts signed in.

- **Why a real UI login in setup:** it exercises the same path as today and avoids reproducing CSRF/XSRF handling in a raw request; it costs one login per run.
- **Alternative: raw `request.post('/login')`.** Faster setup, but depends on Laravel's XSRF cookie flow; rejected for robustness.
- **Alternative: a Playwright "setup project" with dependencies.** Cleaner in reports but changes the project layout and how `npm run test:e2e -- e2e/x.spec.js` selects tests; rejected because `globalSetup` already exists and runs for selective runs too.
- A failing login throws from `globalSetup` with a message naming the login, as the locale reset does.

### 2. Opt-out is explicit, per spec or per test

Specs and tests that need another state call `test.use({ storageState: { cookies: [], origins: [] } })` (the shared helper exports it as a constant `noSession`) and keep logging in themselves where they need a session.

- `admin-shell`: whole file opts out (guest redirect, login, logout, theme and sidebar state start clean).
- `admin-i18n`: whole file opts out (starts as a guest on `/login`, tests logout).
- `admin-users`, `admin-roles`, `admin-favorites-menu`: the tests that log in as another user use their own context or opt out; tests acting as the administrator use the saved session.
- Every `guest cannot open ...` test opts out (a `describe` or per-test `test.use`).

Because a logout test would kill the shared server-side session, no test that uses the saved session may log out; the logout tests are exactly the ones that opt out and log in again.

### 3. Shared helper module `e2e/support.js`

One module exports `tinker(code)` (the same `execFileSync` call as today), `login(page)` for the opt-out tests, `noSession`, and the admin credentials. Specs import it and delete their local copies. Selectors for the login form differ between specs (`getByLabel` vs `#email`), so the shared `login` uses the stable ids (`#email`, `#password`, `button[type="submit"]`) that are language independent, which also helps the Ukrainian scenarios.

- **Why:** the session and seeding rules then live in one place, and the 18 near-identical copies stop drifting.
- **Alternative: leave helpers per spec.** Less diff, but the opt-out and later changes would be repeated 18 times.

### 4. Seed per group, not per test

For each spec with `beforeEach(() => tinker(seed))`:

1. Classify each test as read-only (opens, filters, sorts, validates in a form without saving) or mutating (creates, edits, deletes, reorders, uploads, or changes the language-independent state that other tests read).
2. Put read-only tests in a `test.describe` with `test.beforeAll(() => tinker(seed))`; keep mutating tests where `beforeEach` still seeds.
3. Keep the existing `afterAll` cleanup. The seed already begins with the cleanup, so leftovers from interrupted runs are removed.

Playwright runs a `describe`'s `beforeAll` right before its first test, in file order, so a read-only group always starts after any mutating test and re-creates fresh data. With `workers: 1` no other worker interferes; with a retry or a failed test Playwright restarts the worker and runs `beforeAll` again.

- **Alternative: one `beforeAll` for the whole file.** Largest gain, but any mutating test would leak into the others; rejected.
- **Alternative: snapshot/restore with a database transaction.** Not possible across separate PHP processes.
- A test that is uncertain (it mutates only an `e2e-*` record it created itself, never the seeded ones) counts as read-only; the rule is "does not change seeded records".

### 5. Measure before and after, per spec

The first task records the current per-spec durations (a full run with the `list` reporter) in the change notes. The login change is verified by the full run its rules allow (it changes `playwright.config.js` and `global-setup`). Seeding changes are verified per spec against its recorded baseline. The target is a clear reduction of the full-run time (at least 25%) with the same number of passing tests; it is a check, not a spec requirement.

## Risks / Trade-offs

- [A test silently depends on being the first after a fresh seed] → each read-only group starts from a fresh seed, each converted spec runs fully, and each group is also run alone with `-g` (its describe title) to prove it does not rely on tests that ran before it.
- [A "read-only" test actually mutates and breaks the next one] → classification is reviewed per test; any failure after conversion is first treated as a misclassification and the test moves to the per-test group.
- [Saved session expires or is invalidated mid-run] → the file is rebuilt at every run start; the default session lifetime is far longer than a run, and logout tests opt out. A failing opt-out is caught by the guest scenarios.
- [Locale persists on the user, not in the session] → unchanged: `admin-i18n` and Ukrainian scenarios still return `English` in `finally`, and `globalSetup` still resets the locale before logging in.
- [Browser-local state in the saved file] → the state is captured right after login and copied per test; tests that toggle theme or sidebar (localStorage) do not write back into the file.
- [The seed in a `beforeAll` runs only if the group is selected] → expected: a selective run with `-g` seeds only the groups it reaches.

## Migration Plan

Land in this order, each step runnable on its own: shared helper (no behavior change), saved session (full run), then spec-by-spec seeding conversion starting with the largest (`admin-content-nodes`, `admin-content-types`, `admin-content-fields`). Rollback is per step: remove `storageState` from the config (tests still work with their own `login`), or revert a spec to `beforeEach` seeding.

## Open Questions

- None that change the specs or tasks. The exact read-only/mutating split of each spec is decided while converting it (task group 3).

## Result

Measured with the `list` reporter on the full suite (349 tests, `workers: 1`):

| | Before | After |
| --- | --- | --- |
| Full run | 9.4 min (sum of tests 551 s) | 8.9 min (sum of tests 514 s), measured after the saved session; the `admin-content-nodes` split came later and changed that spec by a few seconds |
| `admin-content-nodes` | 193 s | about 160 s (2.7 min alone) |

The reduction is about 6-7%, below the 25% target. Reason: a `tinker` call costs about 0.35 s and almost all of that is PHP start-up (a no-op call takes 0.32 s, the whole seed 0.35 s), and most tests change data or seed more themselves. Only `admin-content-nodes` was split into read-only and mutating groups (12 of 108 tests are read-only); converting the other 13 specs was estimated at 25-45 s for a large rewrite and was dropped by decision of the owner. Further speed-up would have to cut the fixed `tinker` cost (for example a persistent PHP process or a test-only endpoint), which stays a non-goal here.

The baseline had 5 failing tests unrelated to this change (SEO tabs of content and page types, keyboard reorder in site settings, clearing a reference choice); they fail the same way after it.

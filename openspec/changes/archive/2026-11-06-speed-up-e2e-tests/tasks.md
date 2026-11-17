# Tasks

## 1. Baseline and shared helper

- [x] 1.1 Record the baseline: run the full `npm run test:e2e -- --reporter=list` once (allowed here because the change touches the whole suite), save per-spec durations and the total to `docs/e2e-testing.md` as a short "Baseline" note for this change (removed after the final comparison in 4.1), and verify the numbers are in the note
- [x] 1.2 Create `e2e/support.js` exporting `tinker(code)`, `login(page)` (stable ids `#email`, `#password`, `button[type="submit"]`), `noSession` and the admin credentials, with English JSDoc and `@example`; verify with `npx playwright test --list` that it loads
- [x] 1.3 Replace the local `tinker`/`login`/credential copies in all 19 specs with imports from `e2e/support.js` (no other change); verify by running `smoke`, `admin-shell` and `admin-roles` (one with login-only, one with seeding) and by `grep -L "e2e/support\|./support" e2e/*.spec.js` listing only specs that need no helper

## 2. Single login per run

- [x] 2.1 Extend `e2e/global-setup.js`: after the locale reset, log the administrator in through Chromium and save the state to `e2e/.auth/admin.json`; a failure throws an error that names the login (and the credentials/containers); add `e2e/.auth/` to `.gitignore`; verify by running `npx playwright test e2e/smoke.spec.js` and checking the file exists, then by running with a wrong `ADMIN_PASSWORD` and seeing the clear login error
- [x] 2.2 Set `use.storageState` in `playwright.config.js` and add the opt-out (`noSession`) to `admin-shell`, `admin-i18n`, every `guest cannot open ...` test, and the tests of `admin-users`, `admin-roles` and `admin-favorites-menu` that act as another user; remove `login(page)` calls from tests that act as the administrator; verify by the full `npm run test:e2e` (allowed: this task changes `playwright.config.js` and `global-setup`), same number of passing tests as the baseline, and no test that logs out uses the saved session
- [x] 2.3 Document the saved session in `docs/e2e-testing.md` (Structure: the `.auth` file, `globalSetup` login, `noSession` opt-out, why logout tests must opt out) and update the "Multilingual scenario" and "Admin shell scenario" notes that mention logging in per test; verify the documented opt-out example matches `e2e/support.js`

## 3. Seed once per read-only group

- [x] 3.1 Convert `admin-content-nodes.spec.js` (largest): read-only tests (12) in a `reading the seeded data` group with a `beforeAll` seed, the rest (96, including the `for`-generated tests) in a `changing the data` group with a `beforeEach` seed; `afterAll` cleanup and the separate `tinker` seeds are kept. Verified: the spec passes fully (108 passed, 2.7 min) and the read-only group alone (11 passed)
- [x] 3.2 Not converting the other specs (decision of the owner after measurement): one `tinker` call costs about 0.35 s, almost all of it PHP start-up, and most tests in the other specs save data or seed more themselves, so the gain would be about 25-45 s of a 530 s run for a rewrite of 13 files
- [x] 3.3 Document the seeding rule in `docs/e2e-testing.md` ("Seeding rule" section) and update the `admin-content-nodes` scenario wording; the `beforeEach` mentions of the unconverted specs stay accurate

## 4. Result

- [x] 4.1 Compare times with the baseline, record the result in `design.md` (Result), remove the temporary "Baseline" note from `docs/e2e-testing.md`; the 25% target is not met and the shortfall is explained in `design.md`

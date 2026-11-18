# Tasks

## 1. Playwright on the host

- [x] 1.1 Run on the host `npm i -D @playwright/test` and `npx playwright install chromium`; check: `npx playwright --version` works, the dependency is in `package.json`, `package-lock.json` is updated
- [x] 1.2 Create `playwright.config.js` with `testDir: './e2e'`, `baseURL: 'http://localhost:8080'`, one Chromium project and no `webServer`, with JSDoc in English; check: `npx playwright test --list` returns no config errors
- [x] 1.3 Add the `test:e2e` script to `package.json` and the Playwright artifacts (`test-results/`, `playwright-report/`) to `.gitignore`; check: `npm run test:e2e` runs Playwright

## 2. Smoke test and test documentation

- [x] 2.1 Add `e2e/smoke.spec.js`, which opens `/` and checks that the status is below 400, with comments in English; check: `npm run test:e2e` passes with the containers running
- [x] 2.2 Check the separation from PHPUnit; check: `docker compose exec app php artisan test` does not run the files from `e2e/`
- [x] 2.3 Create `docs/e2e-testing.md` in Ukrainian (preconditions, commands, the structure of `e2e/`, the error when the containers are stopped) and `docs/README.md` with the index; check: all relative links work, the commands from the document work as written

## 3. Project rules

- [x] 3.1 Add a section on Playwright to `CLAUDE.md` (running from the host, the base `http://localhost:8080`, tests in `e2e/`, Chromium only); check: the section does not duplicate the Docker rules and refers to `docs/e2e-testing.md`
- [x] 3.2 Add the `context` field to `openspec/config.yaml` (Laravel in Docker, the URL `http://localhost:8080`, npm only on the host, Playwright) and a rule for `tasks` about PHP commands via `docker compose exec app`; check: `openspec context --json` returns no errors, the YAML parses
- [x] 3.3 Change `APP_URL` in `.env.example` to `http://localhost:8080`; check: `grep APP_URL .env.example` shows the new value

## 4. Integration check

- [x] 4.1 Full run: `docker compose ps` shows the running containers, `npm run test:e2e` passes, `openspec validate add-playwright-e2e` without errors

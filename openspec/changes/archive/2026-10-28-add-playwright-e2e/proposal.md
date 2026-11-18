# Proposal

## Why

The project runs in Docker (nginx at `http://localhost:8080`), but there is no browser testing in it: PHPUnit in `tests/` does not check the UI. We need an e2e tool that works with the already running application without breaking the rule "npm only on the host".

## What Changes

- Add `@playwright/test` as a dev dependency on the host and install only the Chromium browser.
- Add `playwright.config.js` with `baseURL` `http://localhost:8080`, without its own `webServer` (the application is already running in Docker).
- The e2e tests live in `e2e/`, separate from PHPUnit in `tests/`; add a `test:e2e` script in `package.json` and a minimal smoke test.
- Add a section about Playwright to `CLAUDE.md` (running from the host, the base `http://localhost:8080`, tests in `e2e/`).
- Add `context` to `openspec/config.yaml` (Laravel in Docker, the URL, npm on the host, Playwright) and a rule for `tasks`: PHP commands go through `docker compose exec app`.
- Change `APP_URL` in `.env.example` to `http://localhost:8080`.
- Add documentation in Ukrainian: `docs/e2e-testing.md` and the index `docs/README.md`.

## Capabilities

### New Capabilities
- `e2e-testing`: running browser e2e tests with Chromium from the host against the application running in Docker at `http://localhost:8080`.

### Modified Capabilities

## Impact

- Files: `package.json`, `package-lock.json`, `playwright.config.js`, `e2e/`, `CLAUDE.md`, `openspec/config.yaml`, `.env.example`, `.gitignore` (Playwright artifacts).
- Documentation: `docs/e2e-testing.md` and `docs/README.md` are created.
- Dependencies: `@playwright/test` (dev) and Chromium downloaded to the host.
- The Docker configuration does not change.

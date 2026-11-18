# Design

## Context

The application runs in Docker: `ecms_nginx` publishes port 8080, `ecms_app` contains PHP. There is no PHP on the host, and Node/npm run only on the host (see `CLAUDE.md`, the Docker section). The PHPUnit tests are in `tests/`. Playwright is absent. The motivation is in proposal.md.

## Goals / Non-Goals

**Goals:**
- A minimal e2e environment that uses the already running Docker stack.
- Clear rules in `CLAUDE.md` and `openspec/config.yaml`, so that the agent does not run PHP/npm in the wrong place.

**Non-Goals:**
- Playwright in a Docker container and changes to `docker-compose.yml`.
- Firefox/WebKit, CI configuration, scenarios beyond the smoke test.
- Automatic preparation of the DB (seed/migrations) for e2e.

## Decisions

- **Playwright on the host, not in the container.** It is consistent with the rule "npm only on the host"; the browser sees `localhost:8080` directly. Alternative: a separate Playwright service in compose, but that complicates the stack without need.
- **No `webServer` in the config.** The application is already started in Docker, so Playwright only connects via `baseURL`. Alternative: `webServer` with `docker compose up`, but that duplicates the management of containers.
- **One Chromium project** in `playwright.config.js` and `npx playwright install chromium`. Less space and faster; the decision was confirmed by the owner.
- **Tests in `e2e/`**, `testDir: './e2e'`. This separates them from PHPUnit (`tests/`), which does not need them.
- **Config in JS** (`playwright.config.js`), because `package.json` has `"type": "module"` and the project does not use TypeScript; JSDoc in English according to the project rules.
- **Rules and context** are added to `CLAUDE.md` and `openspec/config.yaml` (the `context` field and a rule for `tasks`), so that future changes automatically take Docker and the URL into account.
- **`.env.example`**: `APP_URL=http://localhost:8080`, consistent with `.env` and the nginx port.
- **Documentation**: `docs/e2e-testing.md` in Ukrainian and `docs/README.md` as the index (there is no `docs/` directory yet).

## Risks / Trade-offs

- [Tests fail if the containers are not running] → state the precondition `docker compose up -d` and the expected connection error in the documentation.
- [Chromium is downloaded to the host and takes space] → install only Chromium; add the artifacts (`test-results/`, `playwright-report/`) to `.gitignore`.
- [The DB state affects e2e] → for now we limit ourselves to a smoke test without a dependency on data.

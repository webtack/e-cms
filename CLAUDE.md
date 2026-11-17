# E-CMS package: working rules

`webtack/e-cms` is a Laravel package (namespace `Webtack\Ecms`, sources in `src/`, admin frontend in `resources/js`, compiled bundle in `dist/`). These rules apply to contributors and to AI assistants working in this repository. The documentation index is `docs/README.md`; the contributor guide is `CONTRIBUTING.md`.

## Running PHP

There are two ways; use whichever the environment has.

- **A clone of the package alone** (PHP 8.3+ and Composer available): run `composer install`, then `vendor/bin/phpunit`, `vendor/bin/pint` and `composer test` from the package root.
- **The demo application's container** (the package is mounted at `/var/www/e-cms-package`, the service is `app`): run every PHP command non-interactively with the working directory set, for example `docker compose exec -w /var/www/e-cms-package app vendor/bin/phpunit --filter=<Name>`, `docker compose exec -w /var/www/e-cms-package app vendor/bin/pint --dirty --format agent`, `docker compose exec -w /var/www/e-cms-package app composer ...`. Run these from the demo repository, where `docker-compose.yml` lives. There is no PHP on the host.

Node and npm always run on the host, in the package directory.

## Tests

- The suite uses Orchestra Testbench on in-memory SQLite (`docs/package-testing.md`). Test classes live in `tests/Feature` and `tests/Unit` under the namespace `Webtack\Ecms\Tests`.
- Run only the tests of the area you changed: a path (`vendor/bin/phpunit tests/Feature/Theme`) or `--filter=<Name>`. Test names follow the areas of `docs/`; for shared code find the consumers with `grep`.
- Run the whole suite only for changes that touch the whole package: migrations and the schema, `tests/TestCase.php`, shared helpers and fixtures, `phpunit.xml`, `composer.json` dependencies, global middleware, service providers, configuration, authentication.
- At the end of a task, report which tests ran and why the rest was skipped.
- Browser (Playwright) tests belong to the demo application, not to this package.

## Frontend and `dist/`

- Changes in `resources/js`, `resources/css`, `themes/default/assets` or the Vite configuration require rebuilding `dist/` and committing it with the sources: `npm run build:package`, `npm run build:themes`, then `npm run check:dist` (it must pass).
- A new publishable file needs its publish tag in `EcmsServiceProvider` and a line in `docs/package-frontend.md`.

## Documentation

- All documentation is English: `docs/`, OpenSpec artifacts (proposal, design, tasks, delta and main specs), code comments and JSDoc. Chat with the owner keeps its own language; it is not documentation. Literal strings that code or tests match verbatim keep their original form inside quotes.
- One file per topic in `docs/`, cross-linked, every file listed in `docs/README.md`. A change to public behaviour updates its page in the same commit; a new key in `config/*.php` adds a row to `docs/configuration.md`.
- Before committing documentation run `node scripts/check-docs.mjs` and `node scripts/check-config-docs.mjs`; after archiving an OpenSpec change run `node scripts/history.mjs`.

## OpenSpec

- Behaviour is specified with OpenSpec: proposal, specs, design and tasks per change; main specs in `openspec/specs/`, archived changes in `openspec/changes/archive/`. The slash commands are in `.claude/commands/opsx`, the skills in `.claude/skills`.
- A task that adds or changes public code includes English comments and JSDoc, with `@example` where usage is not obvious, and states it in its verification.
- Specs describe behaviour only; code-comment and documentation conventions are not spec requirements.
- Archiving a change syncs its delta specs to the main specs and commits the result without asking for confirmation.

## Git

- Commit only when the user explicitly asks. Do not stage or commit automatically.
- Use Conventional Commits, matching the history (`feat(files): ...`, `docs(openspec): ...`, `build(dist): ...`).
- Never add `Co-Authored-By` or any other attribution or "Generated with" line to commit messages or pull request descriptions.

## No assumptions

- Research first (the code, `docs/`, the OpenSpec specs), and only then ask the owner.
- A statement about behaviour rests on code or documentation that was read; mark anything unconfirmed as such.
- Ask the owner only about decisions that cannot be derived from the code (product behaviour, priorities).

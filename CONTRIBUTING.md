# Contributing

How to work on the `webtack/e-cms` package. The code is PHP (the `Webtack\Ecms` namespace in `src/`), the admin frontend is React and TypeScript in `resources/js`, and the compiled result is committed to `dist/`.

## Ways to run the code

### In a clone of the package alone

The package has its own suite on Orchestra Testbench and in-memory SQLite, so it needs PHP 8.3 or newer (with `gd` or `imagick`) and Composer, and nothing else:

```bash
composer install
composer test              # vendor/bin/phpunit, the whole suite
composer lint              # vendor/bin/pint --test
vendor/bin/phpunit tests/Feature/Theme            # one directory
vendor/bin/phpunit --filter=ThemeManagerTest      # by name
```

Run the tests of the area you changed. A full run is for changes that touch the whole package: migrations, `tests/TestCase.php` and shared helpers, `phpunit.xml`, `composer.json` dependencies, global middleware, providers, configuration and authentication ([docs/package-testing.md](docs/package-testing.md)).

### In the demo application's container

The demo application mounts the package at `/var/www/e-cms-package` (`../e-cms-package` in its `docker-compose.yml`) and requires it through a Composer `path` repository, so a change in the package is live in the demo. PHP runs in the container; there is no PHP on the host:

```bash
docker compose exec -w /var/www/e-cms-package app composer install
docker compose exec -w /var/www/e-cms-package app vendor/bin/phpunit --filter=ThemeManagerTest
docker compose exec -w /var/www/e-cms-package app vendor/bin/pint --dirty --format agent
```

Node and npm run on the host, in the package directory.

## The frontend and `dist/`

Composer cannot run a build, so the compiled admin is committed. Whenever you change `resources/js`, `resources/css`, `themes/default/assets` or the Vite configuration, rebuild and commit `dist/` together with the sources:

```bash
npm install --legacy-peer-deps
npm run build:package      # tsc --noEmit + vite build, writes dist/build
npm run build:themes       # the theme default, writes dist/themes/default
npm run check:dist         # fails when dist/ does not match the sources
```

`check:dist` runs in CI. The details: [docs/package-frontend.md](docs/package-frontend.md).

## Documentation

All documentation is English: `docs/`, the OpenSpec artifacts, code comments and JSDoc. One file per topic in `docs/`, cross-linked, with every file listed in [docs/README.md](docs/README.md). Check the documents before you commit:

```bash
node scripts/check-docs.mjs          # relative links and heading anchors
node scripts/check-config-docs.mjs   # every config key and env variable is in docs/configuration.md
node scripts/history.mjs             # regenerates docs/HISTORY.md from the OpenSpec archive
```

A change to public behaviour updates its topic page in the same commit; a new `config/*.php` key adds a row to [docs/configuration.md](docs/configuration.md).

## Licences of dependencies

The package is MIT. The production Composer dependencies are MIT, BSD-3-Clause or Apache-2.0 (`nette/schema` and `nette/utils` are offered under BSD-3-Clause or GPL; the package uses them under BSD-3-Clause). The npm dependencies that end up in `dist/` are MIT, ISC, BSD or Apache-2.0, plus the permissive BlueOak-1.0.0 and the data licence CC-BY-4.0 of `caniuse-lite`, which build tooling reads and which is not shipped. Re-check after adding a dependency: `composer licenses --no-dev` and the `license` field of the new npm packages.

## OpenSpec flow

Behaviour is specified with [OpenSpec](https://github.com/Fission-AI/OpenSpec): a change has a `proposal.md` (why), `specs/` (deltas of requirements), `design.md` (decisions) and `tasks.md` (the checklist). The main specifications live in `openspec/specs/`, finished changes in `openspec/changes/archive/` ([docs/HISTORY.md](docs/HISTORY.md) indexes them).

```bash
openspec list                 # active changes
openspec list --specs         # capabilities
openspec status --change <name>
```

The slash commands `/opsx:propose`, `/opsx:apply`, `/opsx:archive`, `/opsx:sync`, `/opsx:explore` and `/opsx:update` (in `.claude/commands/opsx`) drive the flow in Claude Code. Specs describe behaviour only; documentation conventions are not requirements.

## Commits and pull requests

- Conventional Commits, matching the history (`feat(files): …`, `fix(theme): …`, `docs(openspec): …`, `build(dist): …`).
- Commit only what you were asked to; no attribution or "Generated with" lines in commit messages or pull request descriptions.
- The PHPUnit suite, `pint --test` and `check:dist` pass in CI on PHP 8.3 and 8.4.

## Releasing

1. Move the `Unreleased` entries of [CHANGELOG.md](CHANGELOG.md) under the new version and describe breaking changes in [UPGRADING.md](UPGRADING.md).
2. Rebuild `dist/` and run `npm run check:dist`, the whole PHPUnit suite and the documentation checks.
3. Regenerate the history: `node scripts/history.mjs`.
4. Tag the commit; the tag carries `dist/`.

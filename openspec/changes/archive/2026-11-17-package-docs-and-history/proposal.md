# Proposal

## Why

A package is only usable if a stranger can install, configure and extend it from its documentation, and the maintainers can see why it is built the way it is. The project already has detailed English docs (one per topic) and a long OpenSpec history (main specs and archived changes) that explain exactly that, but they are written for the demo application and live only here. This is step 6 of 7 (series in `package-scaffold-and-namespace`).

## What Changes

- Finish the documentation that step 1 copied into the package (`docs/*.md`, all except the four demo-only files `e2e-testing.md`, `legacy-import.md`, `theme-dcculture.md` and `deployment.md`, with `App\` already renamed and steps 2 to 5 already edited in place): replace the remaining `docker compose exec app …` commands and demo paths with package equivalents. The copy is not repeated, so the edits of earlier steps are kept.
- Write the user-facing documents the package needs:
  - `README.md` — what it is, features, requirements, quick start, links;
  - `docs/installation.md` — requirements, `composer require`, `ecms:install`, first login, publishing assets, uninstall notes;
  - `docs/configuration.md` — every `ecms.*`, `content.*`, `files.*`, `theme.*`, `social_networks.*` key, env variables, switches (`public_site`, `auth_routes`);
  - `docs/theming.md` — how to write an application theme: directories, cascade, hooks, directives, assets, building a theme with the published tooling (`ecms-theme-tooling`), using the published `default` (`ecms-theme`) as the model for a hand-written theme, refreshing it after a package update, overriding `default`;
  - `docs/integration.md` — the host user model contract and trait, host routes vs. the catch-all, roles for existing users, custom guard/login, mail/queue/storage expectations, interface translations and the override rule for `auth.php`/`validation.php`; language codes are reserved first segments for the host (a host route such as `/en/contact` conflicts with an active language `en`), the interface languages come from `ecms.locales` and the host needs no `available_locales`;
  - `UPGRADING.md` — for an application that ran the CMS as its own code: the namespace change and the `ThemeHooks` import, the interface language list moving from `config/app.php` `available_locales` to `ecms.locales`, the admin translation files now shipped by the package, the language-prefix middleware registered by the provider, the new publish tags (`ecms-theme`, `ecms-theme-tooling`); then per-version notes (starting with 0.x);
  - `CHANGELOG.md`, `LICENSE`, `CONTRIBUTING.md` (how to work on the package: Docker mount, tests, `dist/`, OpenSpec flow).
- Copy the OpenSpec history into the package: `openspec/specs/` (all capabilities except the demo-specific `e2e-testing`, `legacy-import`, `dcculture-theme`, `dcculture-accessibility`, `dcculture-events`, plus the new package capabilities), the **complete** `openspec/changes/archive/` (every archived change, including this series), and `openspec/config.yaml` adapted to the package. Add `docs/HISTORY.md`, a chronological index generated from the archive (date, change, one-line why) by a repeatable script, and a note on how to read OpenSpec.
- Port the working rules (`CLAUDE.md`, `AGENTS.md`, `.claude/commands`, `.claude/skills` of the OpenSpec workflow) to the package, adapted to its commands; not `settings.local.json`.
- Create the package git repository history policy: a fresh repository (initial commit = the package at that point), no `git filter-repo`; the development history is carried by OpenSpec.
- The demo **keeps** its own `openspec/` (config, the five demo-specific specs and the series of changes) — a deliberate revision of the earlier plan to remove it, because e2e, legacy import and the `dcculture` theme remain demo concerns and the series itself must be archived somewhere. The package receives a final re-copy of the archive after step 7.

## Capabilities

### New Capabilities

- `package-documentation`: what documentation and project history a release of the package must contain and how its accuracy is kept.

### Modified Capabilities

<!-- None: no runtime behaviour changes. -->

## Impact

- New/changed in the package: `README.md`, `UPGRADING.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `LICENSE`, `docs/` (the copied topic docs plus installation, configuration, theming, integration, history and the package-* docs from steps 1–5), `openspec/`, `CLAUDE.md`, `AGENTS.md`, `.claude/`, `scripts/history.mjs`.
- Docs index: `docs/README.md` rewritten for the package.
- No change to the demo except nothing yet; the demo's `README.md` is replaced in step 7.

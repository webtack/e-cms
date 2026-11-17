# Design

## Context

Observed: the demo has the topic docs in `docs/` (English, one topic per file, indexed in `docs/README.md`; the counts change with every feature and are not recorded here), `README.md` (stack-oriented, mentions Docker), `openspec/` with its main specs and archived changes, `CLAUDE.md`/`AGENTS.md` (the `CLAUDE.md` holds Docker, PHP/e2e test rules, documentation-language rules), `.claude/commands` and `.claude/skills` (OpenSpec workflow), and `openspec/config.yaml` whose `rules` mention `docs/e2e-testing.md`. Demo-specific topics: e2e (`docs/e2e-testing.md`, spec `e2e-testing`), legacy import (`docs/legacy-import.md`, spec `legacy-import`), the `dcculture` theme (`docs/theme-dcculture.md`, spec `dcculture-theme`). Specs of steps 1–5 add package capabilities. The docs repeatedly contain `docker compose exec app …` and `App\…` names. See proposal.md for motivation.

## Goals / Non-Goals

**Goals:** a package a stranger can adopt from its docs; full history preserved; reproducible copy.

**Non-Goals:** a documentation website, translations of docs (English only, per project rule), a git-history import.

## Decisions

**Docs are copied once (in step 1) and then edited, not regenerated.** Step 1 copies `docs/*.md` minus the four demo-only topics (`e2e-testing`, `legacy-import`, `theme-dcculture`, `deployment`) with the `App\` rename, and steps 2 to 5 edit those copies in place. This step does not copy again, because a second copy would overwrite the edits; it runs a cleanup pass that flags and rewrites `docker compose exec app` lines and demo paths. A link checker (script) validates every relative link and heading anchor. Alternative: rewrite all docs — rejected, the existing ones are detailed and verified.

**New guides are written from the code, not from memory.** `configuration.md` is built by reading each `config/*.php` file; a check script lists config keys and greps them in the guide to catch omissions. `integration.md` and `installation.md` are validated by following them in a clean Testbench-independent Laravel skeleton (task 4.1), which also proves the quick start.

**History = OpenSpec archive + generated index.** `scripts/history.mjs` (Node, runs on the host) reads `openspec/changes/archive/<date>-<name>/proposal.md`, extracts the `## Why` first sentence and writes `docs/HISTORY.md` (date, name, why, link). Re-running is idempotent. Alternative: import git history with `filter-repo` — rejected, every commit touches paths that no longer exist and the result is noisy; the archive already holds the reasoning.

**What is copied.** `openspec/specs` minus `e2e-testing`, `legacy-import`, `dcculture-theme`, `dcculture-accessibility`, `dcculture-events`; the whole `archive/` (including demo-only changes, they are history), `config.yaml` rewritten (context: package, Docker via mounted path; rules: drop the e2e rules, keep documentation rules). `.claude/commands` and `.claude/skills` are copied as they are; `.claude/settings.local.json` is not.

**The demo keeps `openspec/`.** The earlier plan to delete it from the demo is revised: the demo still owns three specs and must be able to archive steps 6–7 of this series. After step 7, a one-time re-copy of `archive/` into the package completes the history; later package work is done in the package repository.

**Working rules.** `CLAUDE.md` for the package says: PHP runs in the container with the package mounted (`docker compose exec -w /var/www/e-cms-package app …`), `dist/` must be rebuilt on frontend changes, tests are run by area, documentation in English, Conventional Commits, no attribution lines (carried over from the demo). `AGENTS.md` is carried over. The e2e rules stay with the demo.

**License.** MIT, decided by the owner (Ruslan Bilyshko): it is the standard of the Laravel/Composer ecosystem and the main dependencies (Laravel, Inertia, Intervention Image, Symfony, React, Tiptap, shadcn) are MIT as well, so no licence conflict is expected; task 3.3 verifies this with a licence check instead of assuming. `LICENSE` names the owner as copyright holder with the year 2026 and carries no email; `composer.json` `authors` lists the name. Alternatives considered: Apache-2.0 (patent grant), GPL/AGPL (copyleft, would limit adoption in client sites), proprietary (would need a private registry); rejected by the owner's choice of MIT.

**Versioning.** Start at `0.1.0` (pre-1.0: the API and the fixed admin prefix may change); `CHANGELOG.md` follows Keep a Changelog.

Docs touched/created: all of `docs/` plus the root files listed in the proposal.

## Risks / Trade-offs

- [Docs keep demo wording (`localhost:8080`, Docker commands) after the copy] → The checklist script greps for `localhost:8080`, `docker compose exec app`, `App\\`, `e2e` and fails on leftovers outside `CONTRIBUTING.md`.
- [Documentation drifts from the config] → The config-key check script runs in CI (added to the workflow from step 5).
- [Duplicate history between the demo and the package] → The demo's archive is the source until step 7; the final re-copy is scripted (`scripts/sync-history.sh` kept in the demo repository's scratch tooling, not shipped).
- [The README promises a quick start that fails] → Task 4.1 executes it on a clean skeleton before this change is done.

## Open Questions

### Audit 2026-11-17 (series deferred), resolved

- **Demo-only specs are five.** `e2e-testing`, `legacy-import`, `dcculture-theme`, `dcculture-accessibility`, `dcculture-events` are excluded from the copy (task 4.1 and "What is copied").
- **Counts.** They change with every feature and are no longer recorded in the artifacts.
- **Order of copying (resolved with the owner, 2026-11-17).** The docs are copied in step 1, not here (see the decision above). The copy of `openspec/specs` (task 4.1) needs the package capabilities in the demo's main specs, so steps 1 to 5 must be archived (their delta specs synced) before it.
- **Contributors without the demo.** `CONTRIBUTING.md` and the package `CLAUDE.md` describe two ways to run PHP: the demo's container with the mounted package and plain `composer install` / `vendor/bin/phpunit` in a clone of the package alone.

#### Decision (2026-11-17): deployment material

Owner decision: **split**. `docs/deployment.md` (a server checklist for the demo: nginx, PHP-FPM, `.env`, `ADMIN_*`), the demo's `.env.example` keys (`SESSION_SECURE_COOKIE`, `LIMIT_*`) and the project skill `.claude/skills/auth-audit` stay in the demo; they are the host's business. The package documents only what it relies on, in a "Production notes" section of `docs/installation.md` (task 2.1): trusted proxies for the per-IP limits, `SESSION_SECURE_COOKIE`, and the `LIMIT_*` keys with defaults from `config/limits.php`. Demo-only docs are therefore four: `e2e-testing.md`, `legacy-import.md`, `theme-dcculture.md`, `deployment.md`.

#### Pre-start audit (2026-11-17, task 0.1)

Baseline commit: `45d2095`. `git diff --stat 124812e..45d2095` outside `openspec/` is empty: no code, config, route, middleware or docs changed since the security-hardening addendum, so the facts and counts of this series stay valid (532 files reference `App\` in `app/`, `config/`, `database/`, `routes/`, `themes/`, `resources/`, `tests/` and `bootstrap/` (564 with `docs/`); the next audit starts from this commit).

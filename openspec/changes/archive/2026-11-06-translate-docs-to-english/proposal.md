# Proposal

## Why

The project documentation (`docs/`) and the main OpenSpec specs (`openspec/specs/`) are written in Ukrainian. The project needs an English documentation baseline so that the docs are readable for English-speaking contributors and AI agents, while chat communication with the owner stays in Ukrainian. The root `README.md` is still the stock Laravel readme and does not point to the project documentation or describe the system.

## What Changes

- Translate every Ukrainian document in `docs/` to English, keeping the file names, structure, code identifiers, routes, and relative cross-links intact. `docs/README.md` index entries are translated too, and the index links stay valid.
- Translate the main specs in `openspec/specs/*/spec.md` to English. Requirement and scenario wording is preserved; no behavior changes.
- The archived changes in `openspec/changes/archive/` are not translated and stay in Ukrainian. They are historical records, and this change does not modify them.
- Exception: Cyrillic text stays where code needs it, meaning literal strings that the application or tests match verbatim (for example, UI labels or seeded values quoted in a scenario). Each kept string is checked against the repository before it is left in place.
- Add a project rule to `CLAUDE.md`: all project documentation (`docs/`), OpenSpec artifacts (proposal, specs, design, tasks, and the main specs), code comments, and JSDoc are written in English. Chat communication with the owner keeps its current language (Ukrainian).
- Update `openspec/config.yaml` so that its `context` and `rules`/`operations` guidance says documentation is written in English. The current text instructs writing `docs/` in Ukrainian and requiring Ukrainian docs; it becomes obsolete with this change.
- Replace the stock Laravel content of the root `README.md` with a short English project description of the system (what e-cms is, main parts, stack, how to run it in Docker) and a link to [`docs/README.md`](docs/README.md) as the documentation index.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

None. Translation does not change any requirement or scenario behavior.

## Impact

- `docs/*.md` (all Ukrainian files, including `docs/README.md`): translated in place.
- `openspec/specs/*/spec.md` (33 main specs): translated in place, no requirement changes.
- `openspec/changes/archive/**`: not changed. Archived changes stay in Ukrainian.
- `openspec/changes/translate-docs-to-english/`: the change's own artifacts are in English.
- `openspec/config.yaml`: `context` and guidance text updated to English documentation rules.
- `CLAUDE.md`: new "documentation language" rule under "Project rules".
- `README.md` (root): replaced Laravel boilerplate with an English project overview and a link to `docs/README.md`.
- Code is not changed. No PHP, JS, or e2e tests are affected, so no test runs are required beyond a link check of `docs/README.md` and the root `README.md`.
- Assumption: "docs/openspec" in the request is read as `docs/` plus everything under `openspec/`. Code-required Cyrillic (literal strings matched by code or tests) is the only exception.

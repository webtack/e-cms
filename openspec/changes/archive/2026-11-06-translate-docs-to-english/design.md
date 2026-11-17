# Design

## Context

- `docs/` holds 33 Ukrainian Markdown files (plus `docs/README.md`, the index). Each file has a fixed structure: headings, tables, route and config keys in backticks, code blocks, and relative cross-links such as `[E2E testing](e2e-testing.md)` or links to sections (`#anchor`).
- `openspec/specs/*/spec.md` holds 33 main specs in Ukrainian. They use the OpenSpec format (`### Requirement:` blocks with `#### Scenario:` entries, SHALL/MUST wording). Parsing depends on those markers, so only the prose may change.
- `openspec/config.yaml` and `CLAUDE.md` contain rules that currently require Ukrainian documentation. They must change together with the translation so that the rules do not contradict the files.
- The root `README.md` is the Laravel default. The new content replaces it entirely.
- See `proposal.md` (Why / What Changes) for motivation and scope.

## Goals / Non-Goals

**Goals:**
- Every file under `docs/` and every file under `openspec/` (main specs, archived changes, config) is English, with the same meaning, headings, and anchors that other documents link to.
- Cyrillic is kept only where code or tests need it: literal strings that are matched verbatim. Every kept string is listed in the change's notes with the file that matches it.
- Links (`docs/README.md` index, cross-links between docs, root README link) resolve to existing files and sections after the translation.
- The new English-only rule is written where agents read it first (`CLAUDE.md`) and in `openspec/config.yaml`.

**Non-Goals:**
- No changes to code, routes, config keys, table names, or test files. Identifiers stay exactly as written.
- No rewriting of the content beyond translation (no new sections, no fixes to technical claims). If a translated doc is found to be wrong, the issue is recorded, not silently changed.
- No translation of chat messages or the owner's Ukrainian conversation.

## Decisions

- **Translate in place, file by file, keeping file names.** Renaming would break existing links and the `docs/README.md` index. Alternative considered: separate `docs/en/` tree. Rejected: duplicates content and two sets of rules to keep in sync.
- **Keep anchors stable by translating headings and then fixing anchor links in the same pass.** Anchors generated from Ukrainian headings change when headings are translated, so every `#anchor` link that targets a translated heading is updated to match. Alternative: keep Ukrainian anchors via explicit `<a id>` tags. Rejected: adds markup noise to every file.
- **Keep identifiers and code verbatim.** Routes, class names, config keys, SQL, and CLI commands are not translated, including inside prose. Terms with a fixed project meaning (for example "node", "content type", "page type", "block type", "taxonomy", "term") use one consistent English term across all files; the glossary is built from the first translated file and reused.
- **Translate archived changes in place, in date batches.** The 58 archive directories are translated batch by batch (by date prefix) so each batch can be checked with the same grep. Directory names, file names, and the `.openspec.yaml` `created` field stay unchanged. Alternative: leave the archive in Ukrainian as history. Rejected by the owner's request to translate everything under `openspec/`.
- **Keep Cyrillic that code matches.** Before a Cyrillic string is kept, it is searched in `app/`, `resources/`, `tests/`, `e2e/`, and `lang/`. If the code matches it, the string stays as written inside its quotes and is marked in the notes. Everything else is translated.
- **Translate main specs with the same mechanical care.** Keywords `SHALL`, `MUST`, `SHALL NOT`, and the `### Requirement:` / `#### Scenario:` / `**WHEN**` / `**THEN**` markers stay as they are so the OpenSpec parser keeps working. Validation is run after the translation (`openspec validate` on the main specs where the CLI supports it, otherwise `openspec list --specs`).
- **Mark the change `skip_specs: true`.** Translation changes no requirement. The main-spec translation is applied directly, without delta specs, because archiving a zero-delta change does not sync anything.
- **Root README:** a short English overview (what e-cms is, its main parts, stack, Docker startup, link to `docs/README.md`). The Laravel boilerplate is removed because it describes the framework, not this project.

## Risks / Trade-offs

- [Broken anchors or relative links after translation] → Mitigation: after translating, grep every `](...)` link in `docs/` and `README.md` and check that the target file and heading exist.
- [Inconsistent terminology across 33 files] → Mitigation: a single glossary built in the first task group and reused; a final grep for mixed Ukrainian terms.
- [Accidental change of technical meaning] → Mitigation: translation only, and each file is compared with its source before it is replaced; no content is added or removed.
- [Spec parser breakage in main specs] → Mitigation: keep all markers and keywords unchanged; run the OpenSpec validation after the spec translation.
- [Translating a literal the code matches (e.g., a Ukrainian UI label) breaks a test] → Mitigation: the code-match search runs before each kept or changed literal, and the affected test spec is run after the batch.
- [Large volume: about 392 archive files] → Mitigation: batches by date prefix, each verified with a grep for Cyrillic and a count check.

## Migration Plan

1. Update `CLAUDE.md` and `openspec/config.yaml` rules first, so that new work follows English rules.
2. Translate `docs/` files, then `docs/README.md`, then fix anchors and cross-links.
3. Translate `openspec/specs/*/spec.md`, then validate.
4. Replace the root `README.md`, add the link to `docs/README.md`, then check links.

Rollback: revert the commit. No data, schema, or runtime behavior is involved.

## Open Questions

None. Scope reading ("docs/openspec" = `docs/` plus `openspec/specs/`) is recorded in `proposal.md`.

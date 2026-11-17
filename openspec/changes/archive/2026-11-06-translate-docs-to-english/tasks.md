# Tasks

## 1. Language rules

- [x] 1.1 Add the "documentation language" rule to `CLAUDE.md` under "Project rules": `docs/`, OpenSpec artifacts and main specs, code comments, and JSDoc are in English; chat stays as is. Verify with `grep -n "English" CLAUDE.md`.
- [x] 1.2 Update `openspec/config.yaml` (`context`, `rules`, `operations.apply.guidance`) to require English documentation and remove the Ukrainian `docs/` requirement. Verify the file parses with `python3 -c "import yaml,sys; yaml.safe_load(open('openspec/config.yaml'))"` and that `openspec list --json` still returns a root.

## 2. Translate docs/

- [x] 2.1 Create the terminology glossary (project terms and their fixed English forms) in the working notes of this change and use it for all files. Verify it covers at least: node, content type, page type, block type, taxonomy, term, content field, media, menu, theme, locale.
- [x] 2.2 Translate `docs/e2e-testing.md`, `docs/admin-shell.md`, `docs/admin-i18n.md`, `docs/admin-auth.md`, `docs/access-control.md`, `docs/admin-users.md`, `docs/admin-roles.md` to English, keeping headings' anchor targets consistent with their links. Verify that no Cyrillic characters remain in these files with `grep -nP "[\x{0400}-\x{04FF}]"` and that their relative links resolve to existing files.
- [x] 2.3 Translate `docs/content-model.md`, `docs/app-settings.md`, `docs/admin-site-settings.md`, `docs/content-languages.md`, `docs/content-values.md`, `docs/content-querying.md` to English. Verify no Cyrillic remains and every `](file.md#anchor)` link target exists.
- [x] 2.4 Translate `docs/admin-data-table.md`, `docs/admin-content-types.md`, `docs/admin-page-types.md`, `docs/admin-block-types.md`, `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/admin-taxonomy.md` to English. Verify no Cyrillic remains and every link target exists.
- [x] 2.5 Translate `docs/file-storage.md`, `docs/media-library.md`, `docs/image-styles.md`, `docs/taxonomy.md`, `docs/menus.md`, `docs/admin-menus.md`, `docs/admin-favorites.md`, `docs/admin-navigation-menu.md` to English. Verify no Cyrillic remains and every link target exists.
- [x] 2.6 Translate `docs/site-themes.md`, `docs/public-site.md`, `docs/theme-templates.md` to English. Verify no Cyrillic remains and every link target exists.
- [x] 2.7 Translate `docs/README.md` (the index) to English: title and one-line descriptions, with all links unchanged in target. Verify every link in the index points to an existing file and that the file list matches `ls docs/*.md`.

## 3. Translate main specs

- [x] 3.1 Translate the prose of `openspec/specs/*/spec.md` (the first 17 capability directories in alphabetical order: `access-control` through `admin-users`) to English, keeping `### Requirement:`, `#### Scenario:`, `**WHEN**`/`**THEN**`/`**AND**`, and SHALL/MUST keywords unchanged. Verify no Cyrillic remains in those files and that the requirement and scenario counts match the pre-translation counts (`grep -c "### Requirement:"`, `grep -c "#### Scenario:"`).
- [x] 3.2 Translate the remaining main specs (`app-settings` through `theme-rendering`) with the same rules. Verify no Cyrillic remains and requirement and scenario counts match the pre-translation counts.

## 4. Root README

- [x] 4.1 Replace the Laravel boilerplate in the root `README.md` with an English project overview: what e-cms is, its main parts (content types, pages, blocks, taxonomy, media, menus, themes, admin panel on Inertia/React), stack, Docker startup (`docker compose up`, `http://localhost:8080`), and a link to `docs/README.md` as the documentation index. Verify that `docs/README.md` is linked and that the link target exists with `grep -n "docs/README.md" README.md`.

## 5. Integration check

- [x] 5.1 Check all links across `README.md`, `docs/*.md`, and `CLAUDE.md` resolve to existing files and headings. Verify with a link-check script run from the project root; report any mismatches.
- [x] 5.2 Verify that no Cyrillic characters remain in `docs/`, `openspec/specs/`, `openspec/config.yaml`, or the root `README.md`, except literal strings that code or tests match verbatim (for example, `image-styles.md` and the scenarios in `openspec/specs/`). Verify with `grep -rnP "[\x{0400}-\x{04FF}]"` and confirm each remaining match is such a literal.
- [x] 5.3 Run `openspec list --specs` and `openspec validate` on the current change to confirm the OpenSpec tooling still reads the translated files. Verify both commands exit 0.

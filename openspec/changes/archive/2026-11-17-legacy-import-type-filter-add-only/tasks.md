# Tasks

## 1. Scope option and source queries

- [x] 1.1 Add the `--type` option to `LegacyImportCommand` (signature, docblock): parse the comma-separated keys, stop with a message naming an unknown key (checked against `LegacySource::types('content')` before anything is written), and stop with a message when combined with `--fresh`. JSDoc/PHPDoc in English with `@example`. Verify: `docker compose exec app php artisan test --compact --filter=LegacyImportCommandTest`.
- [x] 1.2 Add `types` (selected keys) to `ImportContext` and a `selects()` lookup the steps share. Verify: `--filter=LegacyImportCommandTest`.
- [x] 1.3 In `LegacySource`: add `referencedBlockSlugs(array $types)`; add an optional type filter to `nodeChunks()` (the limit counts filtered rows); filter `ownedFiles()` (including its limit subquery), `imageReferences()` and `orphanFiles()` by the selected nodes and blocks. Verify: `--filter=LegacySourceTest`.
- [x] 1.4 `ImportUsers`: an existing account (by email) is counted `skipped` and not updated; its id is still remembered for node authors. Verify: `--filter=ImportUsersTest`.

## 2. Add-only steps

- [x] 2.1 `ImportNodes`: look up the `uuid` first, count an existing node as `skipped` before mapping values or importing files, create only missing nodes, stop writing the `node` hash to `legacy_import_map`; with a type scope import the referenced blocks, then the nodes of the selected types, and skip pages. Verify: `--filter=ImportNodesTest`.
- [x] 2.2 `ImportTypes`: with a type scope handle only the selected types, their parents, the types of the referenced blocks and their fields; do not update an existing type or field (`unchanged`), still add a missing field to an existing type; write type values and the page-field list only for types created in this run. Verify: `--filter=ImportTypesTest`.
- [x] 2.3 `ImportFiles`: copy only the files of the selected nodes and blocks when a type scope is set. Verify: `--filter=ImportFilesTest`.

## 3. Tests

- [x] 3.1 Update the existing tests that expect a node, type or field to be rewritten (second run, view counter, hidden label, type values, files added later) to the add-only behavior. Verify: `--filter=Legacy` (the directory `tests/Feature/Legacy`).
- [x] 3.2 Add tests for the scenarios of "Type filter" and "Existing types and fields are not changed": one type, referenced blocks, other kinds left out, unknown type, `--type` with `--fresh`, existing news kept, edited type and field kept, new field in an existing type, `--limit` with `--type`, dry run with `--type`. Verify: `docker compose exec app php artisan test --compact tests/Feature/Legacy`.
- [x] 3.3 Run `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 4. Documentation

- [x] 4.1 Update `docs/legacy-import.md` (in English): the `--type` option and its scope, add-only behavior of nodes, types and fields, the changed "repeated run" and "files added later" notes, `--type` with `--fresh`, the recommended order of runs. Keep the links in `docs/README.md` valid.

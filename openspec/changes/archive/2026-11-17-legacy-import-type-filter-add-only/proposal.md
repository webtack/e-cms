# Proposal

## Why

`legacy:import` always walks the whole legacy site and rewrites every node that already exists on the new site, so editors' later changes are lost on a re-run, and there is no way to bring over one content type (for example `news`) on its own. The owner wants a safe, additive import that can be limited to one type.

## What Changes

- Add the option `--type=<key>[,<key>]` to `legacy:import`: only the nodes of the listed content types are imported, together with what they need (their types and parent types, the blocks they refer to and those blocks' types, and the files of these nodes).
- **BREAKING**: the import becomes add-only, always (with and without `--type`). A node whose `uuid` already exists is skipped (counted as `skipped`) and is no longer rewritten from the legacy data. Existing types and fields are no longer updated (name, description, required flag, weight, type values); missing types and missing fields of existing types are still added.
- The `users` step is add-only too: an account that already exists (matched by email) is not touched and is counted `skipped`; only missing accounts are created. Nodes still take it as their author.
- `--fresh` stays the only way to delete; it cannot be combined with `--type`.
- The report counts skipped nodes, types and fields; the files step copies only the files of the selected nodes.
- Update `docs/legacy-import.md` and the tests of the command.

## Capabilities

### New Capabilities

### Modified Capabilities
- `legacy-import`: new option `--type`; existing nodes, types, fields and users are skipped instead of updated; the repeat-run and "files added later" behavior changes.

## Impact

- Code: `app/Console/Commands/LegacyImportCommand.php`, `app/Legacy/ImportContext.php`, `LegacySource.php`, `ImportTypes.php`, `ImportNodes.php`, `ImportFiles.php`, `ImportUsers.php`.
- Tests: `tests/Feature/Legacy/*`.
- Docs: `docs/legacy-import.md` updated (options, repeat run, fresh start, recommended order); `docs/README.md` index unchanged.
- No migrations, no UI, no e2e.

# Design

## Context

`legacy:import` runs four steps (`users`, `types`, `files`, `nodes`) through `ImportContext`, which carries the options and the lookups. `LegacySource` is the only class that reads the legacy database. Today `ImportNodes` rewrites an existing node (matched by `uuid`) and `ImportTypes` updates names, descriptions, flags, weights and type values of existing types and fields. The node's legacy type key is the `type_id` column of `node`, `block` and `page` rows; blocks are referenced from nodes by slug through `field_reference_block`. The command and its steps are described in `docs/legacy-import.md`.

## Goals / Non-Goals

**Goals:**
- Select the nodes of given types, and bring along exactly what they need.
- Make every run add-only: nothing that exists (nodes, types, fields, users) is rewritten.

**Non-Goals:**
- Importing child types of a selected type, or pages, with `--type`.
- Changing `--fresh`, the dry-run report or the value mapping.
- Backfilling values into nodes imported earlier.

## Decisions

1. **The scope is computed once, in `ImportContext`.** `--type` is parsed into a list of keys, validated against `LegacySource::types('content')` before anything is written (an unknown key stops the command with exit code 1), and `ImportContext` gets `types` (the selected keys, empty = all). `ImportContext::selects(kind, typeKey)` is the one place steps ask. Alternative: each step reads the option itself; rejected because the three steps must agree on the same scope.

2. **What `--type` pulls in.** `LegacySource` gets `referencedBlockSlugs(array $types)`: the distinct `field_reference_block.value` of rows owned by nodes of the selected types. From it come the block rows (by slug) and their type keys. The scope for `types` is: the selected content types, their parents (by `parent_type`, parents first as now), the types of the referenced blocks and all fields of these types. The scope for `nodes` is: the referenced blocks, then the content nodes of the selected types; pages are skipped. The scope for `files` is the same nodes (a `type_id in (...)` / `slug in (...)` filter added next to the existing `--limit` subquery in `ownedFiles()`, in `imageReferences()` and in the orphan tally). Alternative: import all blocks and types; rejected because the owner wants only the nodes of the type and what they refer to. Blocks that refer to other blocks are not followed (the legacy data has block references only from content nodes).

3. **`--limit` with `--type`.** `nodeChunks()` gets an optional list of type keys (`where type_id in (...)`) and the limit counts the filtered rows; the `ownedFiles()` limit subquery gets the same filter so that the files match the limited nodes.

4. **Add-only nodes.** `ImportNodes` looks up the `uuid` first; an existing node is counted `skipped` and returns before the values are mapped, so no file is imported for it either. The `legacy_import_map` rows of kind `node` (the content hash) no longer decide anything and are no longer written; existing rows are left to `--fresh`. Dry run reports `created` or `skipped` the same way. Alternative: keep the hash and skip only unchanged nodes; rejected, because the owner wants editors' changes to survive.

5. **Add-only types and fields.** `ImportTypes::importType()` returns `unchanged` for an existing type (no `fill`), `saveField()` returns `unchanged` for an existing field, and `importTypeValues()` and `importPageFields()` run only for types created in this run (tracked in a `created` set kept by the step). A field missing from an existing type is still created, and does not touch that type's page-field list. The manager rights stay as they are: they only raise a missing level and already keep `any`.

6. **`--fresh` with `--type` is refused** by the command before it asks for confirmation, because `--fresh` deletes all content types and nodes while `--type` promises a partial, additive run.

7. **Report.** `skipped` already exists in `ImportReport::OUTCOMES`; nodes, types and fields use it. No new report key.

8. **Add-only users.** `ImportUsers` counts an existing account (matched by email) as `skipped` and remembers its id, so it still becomes the author of nodes; its name, role, password and state are not updated. The author of a node is resolved by `ImportContext::authorId()`: the mapped account, otherwise the account found by email (also when the `users` step did not run), otherwise the first administrator of `legacy.users`. No code change is needed for an author that is missing in the database.

## Risks / Trade-offs

- [Nodes imported earlier never get later fixes from the legacy data (files copied later, edited legacy text)] → the owner accepted this; `--fresh` stays for a full redo, and the docs say so.
- [A node imported while a referenced block was missing keeps an empty reference] → run order puts blocks first, so within one run it cannot happen; across runs the owner can `--fresh`.
- [Existing types never get legacy changes] → intended; documented.
- [`legacy_import_map` rows of kind `node` become dead data] → harmless and cleaned by `--fresh`.

## Migration Plan

No schema change and no deploy step. Rollback is reverting the code; imported data is not affected. Docs: `docs/legacy-import.md` (options table, repeated runs, fresh start, recommended order).

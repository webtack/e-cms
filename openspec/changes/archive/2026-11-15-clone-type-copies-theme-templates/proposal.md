# Proposal

## Why

Cloning a type copies its fields and settings, but a theme may override how the source type looks on the public site with templates bound to its key (`themes/<theme>/views/news/…`, `views/blocks/block-<key>.blade.php`). The clone starts without them and falls back to the generic templates, so the editor has to copy the files by hand for every theme and every clone.

## What Changes

- Cloning a type also copies the type-level theme templates of the source to the new type key, in every theme under `themes/` that has them:
  - the type directory `views/{old}/` becomes `views/{new}/`, without the node-bound files `*--{id}` (for example `preview--7`, `details--7`);
  - the block template `views/blocks/block-{old}.blade.php` becomes `views/blocks/block-{new}.blade.php`, without the node-bound `block-{old}--{slug}.blade.php`.
- Files are copied as they are: their content is not read or rewritten.
- The copy is part of the atomic clone: if the target path already exists or a file cannot be written, the whole clone fails, no type is created and the files created so far are removed. Existing files are never overwritten.
- Out of scope: page templates `pages/page-{old}` (and `--{id}`), field templates other than those inside the type directory, and type keys hard-coded in a theme's `hooks/*.php`.

## Capabilities

### New Capabilities

### Modified Capabilities
- `admin-content-types`: the requirement "Result of cloning" also covers the copy of the theme templates bound to the type key and the failure rules for it.

## Impact

- Code: `app/Content/ContentTypeCloner.php` (copy step and cleanup), `app/Http/Controllers/Admin/ContentTypeController.php` (error shown to the user), PHP Feature test `tests/Feature/Admin/ContentTypeCloneTest.php`.
- Docs: `docs/admin-content-types.md` (section "Cloning"), `docs/site-themes.md` (note that clone copies type-level templates).
- No migrations, no new routes, no UI change in the clone dialog; no e2e change.

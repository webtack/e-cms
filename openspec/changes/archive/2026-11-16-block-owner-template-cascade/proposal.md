# Proposal

## Why

A block looks the same wherever it is placed: its template is chosen by the block's own type and slug (`blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`), and the type of the page that references it plays no part. The `dcculture` theme needs the `contacts` blocks referenced from the pages of the type `institutions_sources` (13 records, one block each) to have their own markup, while the same block type stays unchanged for `institutions` and the `collectives_*` types. Today that is only possible with one `block-contacts--{slug}` file per block (no new record is picked up) or with CSS tricks that cannot change the markup. The existing cascade for fields already lets a template depend on the record type (`{t}/field-...`); blocks have no such level.

## What Changes

- When a block is rendered as the `content` of a `node_ref` item, the template cascade gets two more candidates in front of the existing ones, taken from the directory of the **owner type**: `{owner}/block-{t}--{slug}` and `{owner}/block-{t}`. The owner is the type of the node whose field holds the reference (also for a reference nested in another target).
- A block placed by the directive `@block('slug')` has no owner; its cascade does not change.
- The block template additionally receives `$owner` (the owner node) when it is rendered through a reference; it is `null` for `@block`.
- Cards (`preview`) are **not** changed: the owner context applies to blocks only.
- Theme `dcculture`: a template `views/institutions_sources/block-contacts.blade.php` for the contacts blocks of `institutions_sources` pages, with its own markup (written by the owner of the project, outside the task list).
- Docs: the block cascade and the node-reference content sections are updated.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `theme-rendering`: the requirement "The content of a referenced node" (block target: owner-type candidates and `$owner`) and the requirement "Blocks in templates" (the direct placement keeps the old cascade and has no owner).
- `dcculture-theme`: a new requirement for the contacts block on `institutions_sources` pages.

## Impact

- Code: `app/Theme/BlockRenderer.php` (candidates and the owner parameter), `app/Theme/NodeContentRenderer.php` (passes the owner type of `$current`).
- Theme: `themes/dcculture/views/institutions_sources/block-contacts.blade.php`, `themes/dcculture/assets/components/contacts.css`. No tests for the theme markup (project rule for site themes).
- Tests: `tests/Feature/Theme/BlockRendererTest.php`, `tests/Feature/Theme/NodeContentRendererTest.php`.
- Docs: `docs/theme-templates.md` (sections "Blocks" and "Node reference content"), `docs/theme-dcculture.md` (section "Node references"), `docs/site-themes.md` (the note on cloning a type: files `block-*` in `views/{old}/` are copied like any other file of that directory).
- Compatibility: nothing existing is renamed or removed. A theme without `views/{owner}/block-*` files renders exactly as before.

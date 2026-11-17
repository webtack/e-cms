# Proposal

## Why

A `node_ref` field on a public page prints only a link to the target node: `present()` returns `title` and `url`, and the field template has no access to the target's other fields. A site that wants the referenced record shown inside the page (a contact block, a related article, a department) has to rebuild the target's markup by hand in every theme.

The core already has what is needed to render a record's content (`DisplayFields::pageFields()`, `ContentValueReader`, `@fields`, the `BlockRenderer` pattern), but nothing that does it for a referenced node, and nothing that guards against reference cycles (A -> B -> A is allowed in the database).

## What Changes

- New core renderer `App\Theme\NodeContentRenderer` (scoped): for a visible node it renders the node's page fields (`DisplayFields::pageFields()`) with the same field templates a page uses, in the context of the target (so the candidates are `{target type}/field-{ftype}--{key}`, ..., `fields/field`). There is NO separate "embed" template and NO new directive: how the content is wrapped is decided by the `node_ref` field template.
- `FieldRenderer` adds the rendered content to every presented `node_ref` item: `$items[]` elements get `content` (an HTML string, `''` when nothing can be rendered).
- Cycle and depth guard: a node that is already being rendered (including the node whose page or field is being rendered) and any nesting deeper than a fixed limit give `content` = `''`.
- Invisible, deleted, or missing target nodes give no item at all, same as `present()` today.
- `NodeRefType::present()` additionally returns the target's `uuid` (additive: `title` and `url` stay).
- Theme `default`: `fields/field-node_ref` prints the target's title as a link and its `content`, and falls back to the plain link when `content` is empty (cycle, depth limit).
- Theme `dcculture` keeps the plain link through its own `fields/field-node_ref` template, so the cascade does not make it embed.
- `type_ref` is NOT changed: a type reference has no "content".

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `theme-rendering`: `node_ref` is presented with the uuid and the rendered content of the target (guards included); the default theme's `node_ref` template prints that content.

## Impact

- Code: `app/Theme/NodeContentRenderer.php` (new), `app/Theme/FieldRenderer.php` (`content` for `node_ref` items), `app/Providers/AppServiceProvider.php` (scoped service), `app/Content/PresentContext.php` and `app/Content/FieldTypes/NodeRefType.php` (`uuid`), `themes/default/views/fields/field-node_ref.blade.php`, `themes/dcculture/views/fields/field-node_ref.blade.php`.
- Tests: PHP Feature tests for the renderer, the guards, `present()` and the default templates (`tests/Feature/Theme`). No tests for `dcculture` (project rule for theme frontends). No e2e: no browser behavior changes.
- Docs: `docs/theme-templates.md` (new "Node reference content" section, updated `node_ref` template), `docs/content-values.md` (the `node_ref` presentation table), `docs/theme-dcculture.md`, `docs/README.md` only if a file is added.
- No migrations, no admin UI, no API changes.

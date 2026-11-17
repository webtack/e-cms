# Design

## Context

`NodeContentRenderer::content()` (see `docs/theme-templates.md#node-reference-content`) loads the target, reads the values of its `page_fields` and returns `FieldRenderer::renderManyInScope(...)`, a glued string. `FieldRenderer::render()` puts it into every `node_ref` item as `content`. The guard is a stack of uuids kept for the request and popped in `finally`.

The theme already has two units that render one record:

- `BlockRenderer::render($slug)` finds a block by slug and renders `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block` with `$node`, `$type`, `$values`, `$fields` (`pageFields`).
- `PreviewRenderer::render($node, $type, $fields)` renders `{t}/preview--{id}`, `{t}/preview`, `content/preview` with `$node`, `$type`, `$values`, `$fields`, `$url`. It reads `$values` from the loaded `values` relation of the node (empty when the relation is not loaded). `list_fields` exist only for the kind `content` (`PublicDisplaySettings`); a `page` type has none.

`dcculture` has its own `content/preview`, `news/preview`, `events/preview`, `video/preview`, and a `node_ref` template that prints the title link only, except `field-node_ref--contacts_reference` which prints `content`.

## Goals / Non-Goals

**Goals:**
- `content` of a `node_ref` item is the output of the target's block template or card, so the look of a target is defined in one place and overridden by existing files.
- The cycle and depth guards keep covering everything rendered inside those templates.

**Non-Goals:**
- No new template names, variables, directives, settings on the reference field or admin UI.
- No per-reference override (a card that differs by the referencing field); the card may be overridden per type or per node only. This can be added later without changing this change.
- No tests for theme front-end markup.

## Decisions

**1. Branch by the target's type kind inside `NodeContentRenderer::content()`.** Kind `block` goes to the block template, every other kind to the card. The stack push, `try/finally` and the visibility query stay where they are, so the template is rendered inside the chain and a reference inside a block or a card is guarded exactly as before. Alternative: the field renderer calls `BlockRenderer` / `PreviewRenderer` directly; rejected, because the chain would not be set while the template renders.

**2. Add a method to each renderer that takes an already found node.** `BlockRenderer` gets a method that renders a given node and its type (the lookup by slug and the `kind` check stay in `render($slug)`, which calls it), so `@block` and the reference give the same output. `PreviewRenderer::render()` gets an optional `$values` argument that is used instead of the `values` relation, and an optional `$url`; the list keeps calling it as now. Alternative: set the `values` relation on the node and reuse `render()` unchanged; rejected as a hidden side effect on a shared model, though it is a possible fallback.

**3. Card fields.** For a kind with `list_fields` (`content`), the keys are `list_fields` without `title`, as the list does, taken through `DisplayFields::settings()` / `fields()`. For a kind without `list_fields` (`page`), the card uses `DisplayFields::pageFields()`. `title` is always read, because the card prints it. The values are read with `ContentValueReader::load` in the reading language, followed by `FieldRenderer::preload`, as today. Blocks keep `pageFields()`.

**4. `$url` of the card.** Taken from the presented item (`NodeRefType::present` already gives the address, `null` for a block), not rebuilt from the type slug, so a page and a content node get their own addresses. The value is passed to the content renderer with the uuid.

**5. `default/field-node_ref` prints `content` alone** and falls back to the title link when `content` is empty. `default/content/preview` already prints the title as a link, so printing the title in the field template would repeat it. `dcculture/field-node_ref--contacts_reference` keeps printing `content`; with this change that is the output of the `contact` type card (`content/preview` of the theme when the type has no own one), which is checked in the tasks.

**6. Cost.** The work per target is the same order as before (the node, its values, the references inside); the card reads `list_fields`, which are usually fewer than `page_fields`. A block reads its page fields as before.

## Risks / Trade-offs

- [The output of every `node_ref` field that prints `content` changes] → listed as **BREAKING** in the proposal; `dcculture` is the only theme with such a template and it is checked; a theme that wants the old output prints the fields itself.
- [A card is designed for a list and may look wrong when embedded] → it is overridden per type or per node with the existing files; a per-reference override is left for a later change.
- [The card template of a type that has none falls back to `content/preview`] → acceptable, same as in a list.
- [A `page` type has no `list_fields`] → the card falls back to its page fields, so an embedded page is not a bare title.

## Migration Plan

No data change. Deploy with the code; to roll back, revert the change. After deploy, review the pages that show a `node_ref` field with `content` in the active theme.

## Documentation

`docs/theme-templates.md` (section "Node reference content" and the `field-node_ref` note in "Field templates"), `docs/theme-dcculture.md` (section "Node references"), `docs/content-values.md` (the `node_ref` row of the public presentation).

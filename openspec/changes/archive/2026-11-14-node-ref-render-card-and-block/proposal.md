# Proposal

## Why

The `content` of a `node_ref` item is today one glued string of the target's `page_fields`. A theme cannot lay the target out: `dcculture` can only print the string whole, and the look of a referenced record is not in one place. The theme already has the right units for this: a block has its own template (`blocks/block-{t}--{slug}`, ...) and a node has a card (`{t}/preview--{id}`, `{t}/preview`, `content/preview`). A reference should reuse them, so the look of a block or a node is the same wherever it is placed, and it is overridden with the files the theme already has.

## What Changes

- `content` of a `node_ref` item becomes the output of the target's own template instead of the glued page fields:
  - a **block** target: the block template cascade (`blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`), the same output as `@block('block-slug')`, with the type's `page_fields`;
  - a **node** target: the card cascade (`{t}/preview--{id}`, `{t}/preview`, `content/preview`) with the card fields of the target's type and the address `$url`.
- The guards stay as they are: a node already being rendered, a cycle and depth over 3 give `content` equal to `''`; the template is rendered inside the chain, so references inside a block or a card are guarded too.
- `default/fields/field-node_ref` stops repeating the title: the card already prints it. The title link remains the fallback when `content` is empty.
- **BREAKING**: `content` no longer contains the glued page fields of a node target, so the output of every `node_ref` field in a theme that prints `content` changes (a theme that prints only the link, such as `dcculture` for most fields, is not affected; `dcculture/field-node_ref--contacts_reference` prints `content` and starts showing the card).
- No new template names, no new template variables, no new directive.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `theme-rendering`: the requirements "The content of a referenced node" (what `content` is and which templates produce it) and "A node reference in the default theme" (no repeated title).

## Impact

- Code: `app/Theme/NodeContentRenderer.php` (picks the block or card template), `app/Theme/BlockRenderer.php` and `app/Theme/PreviewRenderer.php` (render an already found node), `themes/default/views/fields/field-node_ref.blade.php`, `themes/dcculture/views/fields/field-node_ref--contacts_reference.blade.php` (check the output).
- Tests: `tests/Feature/Theme/NodeContentRendererTest.php`, `tests/Feature/Theme/DefaultFieldTemplatesTest.php`; no tests for theme front-end markup.
- Docs updated: `docs/theme-templates.md` (section "Node reference content", the `field-node_ref` note), `docs/theme-dcculture.md` (section "Node references"), `docs/content-values.md` (the `node_ref` row of the public presentation), and the field template comments in `themes/default/views/fields/field-node_ref.blade.php`.

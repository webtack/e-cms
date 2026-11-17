# Design

## Context

`NodeRefType::present()` returns `['title', 'url']` from `PresentContext::node()` (one batched query for visible nodes, their types and titles). Field templates are context-free: they get `$items`, `$field`, `$node`, `$type`, `$values` of the node that owns the field, never the target's values. See proposal.md for the motivation.

Existing pieces to reuse: `DisplayFields::pageFields()` (the fields a page shows), `ContentValueReader::load()`, `FieldRenderer` (`renderManyInScope()`, `preload()` for batched references, the candidates of field templates). Public pages read values in the `default` language; `PresentContext::lang` is the reading language.

Cascade: a `dcculture` page without its own `field-node_ref` template falls back to `default`'s, so any change to the default template reaches `dcculture`. The legacy import creates `node_ref` fields (`field_reference_block`), so `dcculture` data can contain them. Documented in [theme-templates.md](../../../docs/theme-templates.md) and [site-themes.md](../../../docs/site-themes.md).

## Goals / Non-Goals

**Goals:**
- The full public content of a referenced node reaches the `node_ref` field template, with the core owning the guards (visibility, cycles, depth).
- The fields of the target are output by field templates chosen by the SAME candidates as on the target's own page (`{target type}/field-{ftype}--{key}`, ..., `fields/field`); no new template family.
- `default` shows the target's content for `node_ref`.

**Non-Goals:**
- No separate "embed" template, no hook of its own and no Blade directive: the wrapper is the `node_ref` field template.
- No per-field setting for the display mode (link or content); themes decide in their templates.
- No change for `type_ref` and no admin UI changes.
- No caching layer for the rendered content.

## Decisions

**1. A core renderer called by `FieldRenderer`, not logic in the template.** `App\Theme\NodeContentRenderer` (scoped) with `render(string $uuid, ?ContentNode $current): string` returns the HTML of the target's page fields (or `''`). `FieldRenderer::render()` calls it for each item of a field of type `node_ref` and adds the result to the item as `content`. Alternatives: (a) put the content into `present()`: it has no render context and cannot apply field templates or guards; (b) a directive `@embed($uuid)` with its own `{t}/embed` template family (the first version of this change): a second naming principle next to field templates, which the theme author would have to learn; (c) a setting `display: link|full` on the field: needs admin form and validation for a rendering choice that belongs to a theme. Chosen: the guards live in one place, the fields use the existing cascade, and the `node_ref` field template stays the single place where the markup is chosen. `FieldRenderer` resolves the renderer lazily (`app()`), because the renderer itself calls `FieldRenderer`.

**2. `present()` adds `uuid`, nothing else.** The renderer needs the key of the target; `PresentContext::node()` stores the `uuid` it already has in hand, so no extra query. Additive: `title` and `url` keep their meaning.

**3. The target's fields use the cascade of the TARGET's type.** The renderer calls `FieldRenderer::renderManyInScope()` with a scope of `$node` (the target), `$type` (its type) and `$fields` (`DisplayFields::pageFields()`), so `{t}` in the candidates is the key of the target's type. A theme restyles a field of the target everywhere it is shown with `news/field-string--body`, not per place of use. Hooks `preprocess('field')` apply as on a page.

**4. Visibility, language, values.** The target is found with `ContentNode::query()->visible()` by uuid; a missing one gives `''`. Values are read with `ContentValueReader::load([$node], keys of $fields, $context->lang)`, then `FieldRenderer::preload($fields, [$values])` batches nested references. No view counting: only the controller counts views.

**5. Guards: an in-render stack and a depth limit of 3.** The renderer keeps a stack of uuids. `render($uuid, $current)` seeds it with the uuid of `$current` (the node that owns the field being rendered) when it is empty, for the duration of the call. Before rendering a target: if its uuid is on the stack, or `count($stack) - 1 >= 3`, return `''`; otherwise push, render, pop in `finally`. Because the stack is per-request state of a scoped service and balanced with `finally`, a failed render cannot leave it dirty. Alternative: only a depth limit: A -> B -> A would then show A inside B, repeated up to the limit; the stack removes it. The limit 3 is a constant (an assumption: three nested levels cover realistic pages; raise it by a constant change if needed).

**6. The default field template falls back to the link.** `fields/field-node_ref` prints, per item, the title (a link when `url` is set, plain text for a block) and, when `$item['content']` is not empty, the content raw (`{!! !!}`: it is already-rendered, escaped output of field templates). With an empty `content` (cycle, depth) only the title is printed, so the value is not lost. The label and `hide_label` handling stay as in the other default templates.

**7. `dcculture` keeps its look.** `dcculture` inherits `fields/field-node_ref` from `default` through the cascade, so without action it would start printing content. `dcculture` is a design theme where each field is placed by hand, so full content in a field list is unlikely to be wanted. The change adds `themes/dcculture/views/fields/field-node_ref.blade.php` that keeps today's link markup. No tests for it (project rule: theme frontends are not tested). A site that wants the content there removes that file or prints `$item['content']`.

## Risks / Trade-offs

- [Each rendered target costs queries: node, values, nested references] -> bounded by the depth limit and by batching inside a node; the content is produced for each item even if a custom template ignores it. Documented in theme-templates.md.
- [Content is produced for every `node_ref` item, also for a theme that prints only links] -> that is the price of having no directive; a theme that never prints content (`dcculture`) still pays it. Accepted: the batches keep it small, and a lazy value would need a new template mechanism.
- [Wide or heavy target types make pages large] -> the type's `page_fields` setting already controls what shows.
- [A `default` markup change affects every theme that relies on the old link] -> only `dcculture` exists in the repo; it gets the explicit override (decision 7).
- [Raw output in the field template] -> the content comes from Blade templates that escape their data; `richtext` is sanitized by its own `present()`.

## Migration Plan

No data or schema changes. Deploy = code and theme files. Rollback = revert the commit; the added `uuid` and `content` keys in the items are harmless to older templates.

## Docs

`docs/theme-templates.md` (the new "Node reference content" section next to Blocks, the `node_ref` template description), `docs/content-values.md` (the `node_ref` row of the presentation table), `docs/theme-dcculture.md` (the `node_ref` override).

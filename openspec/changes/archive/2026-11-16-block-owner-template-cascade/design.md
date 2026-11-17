## Context

- `BlockRenderer::candidates($typeKey, $slug)` builds `blocks/block-{t}--{name}`, `blocks/block-{t}`, `blocks/block`. Both entry points end in `BlockRenderer::renderNode()`: the directive `@block('slug')` (through `render()`) and `NodeContentRenderer` for a `node_ref` target of kind `block`.
- `FieldRenderer` calls `NodeContentRenderer::render($uuid, $node, $url)` with `$node` = the node whose field is rendered. `NodeContentRenderer` only uses it to seed the cycle guard, but it is exactly the owner. For a reference placed inside a block or card template, the field template of that target is rendered with the target as `$node`, so the same parameter gives the nearest owner, not the page.
- Templates bound to a type already live in `views/{t}/` (`{t}/preview`, `{t}/field-*`, `{t}/list`, `{t}/details`). `ContentNode::$content_type` holds the type key, so the owner key needs no extra query.
- `ThemeTemplateCopier` (cloning a type) copies `views/{old}/` recursively and skips only files whose names end in `--{digits}`. Owner block templates (`block-contacts.blade.php`, `block-contacts--{slug}.blade.php`) therefore are copied to the clone like any other file of that directory.
- Docs describing the components: `docs/theme-templates.md` (Blocks, Node reference content), `docs/theme-dcculture.md` (Node references), `docs/site-themes.md` (Cloning a type).

## Goals / Non-Goals

**Goals:**
- A theme can give a block type a template that depends on the type of the page that references it, by placing a file in that type's directory.
- No effect on themes without such files, on `@block`, and on the cycle/depth guards.

**Non-Goals:**
- Owner context for cards (`preview`): the name `{owner}/preview` is already taken by the card of the owner's own records, so it needs a separate naming decision and is not part of this change.
- A generic `{owner}/block` (without the block type): it would restyle every block of the owner. Not added.
- Owner context for a block placed by `@block('slug')`: the page template can place its own markup or call a different block.
- Any change to field templates, the `node_ref` item shape, or the admin.

## Decisions

**1. The owner is passed as a parameter, not stored in a request-wide state.** `NodeContentRenderer::content()` receives `$current` from `render()` and passes it to `BlockRenderer::renderNode($node, $type, $values, $fields, $owner)`. `renderNode` gets a new last optional parameter `?ContentNode $owner = null`; `@block` leaves it `null`.
*Alternative:* derive the owner from the top of the `NodeContentRenderer` chain stack. Rejected: the stack holds only uuids of the whole chain, the top one is the TARGET, and the owner of a nested block is the previous element, which would need a second stack of types. The parameter is the same value the cycle guard already receives.

**2. `candidates()` gets an optional owner type key.** `candidates(string $typeKey, string $slug, ?string $ownerKey = null)` returns, when the owner key is not null, `["{owner}/block-{t}--{name}", "{owner}/block-{t}"]` followed by the existing three. With `null` the result is identical to today's, so the public signature stays compatible and the existing test of the candidates passes untouched.

**3. Owner templates go first.** The order follows the other cascades ("a type's directory before the shared one", as `{t}/field-x--{key}` before `fields/field-x--{key}`). So `institutions_sources/block-contacts` wins over `blocks/block-contacts--{slug}`; a per-slug file in `blocks/` does not leak into one owner context. A per-block exception inside an owner context is `{owner}/block-{t}--{slug}`.

**4. `$owner` is a template variable.** The block template receives `owner` (the owner node, `null` for `@block`). It is added to the data that `ThemeManager::render` passes and is therefore visible to the `block` preprocess hook. Cheap, and it lets a shared template branch on the owner without a second file. Existing templates ignore it.

**5. Nothing changes for the guard and cost.** The owner is a node that is already loaded; no query is added. The cycle and depth guards are untouched because the parameter does not enter the chain.

**6. Cloning a type needs no code change.** Owner block templates are ordinary files of `views/{old}/`; the clone keeps its owner look. A `block-{t}--{slug}` file bound to a block slug is copied as well (it is not node-bound by the digit rule), which is harmless: the clone's references point to other blocks and the file simply never matches. The note is added to `docs/site-themes.md`.

**7. Theme template.** `themes/dcculture/views/institutions_sources/block-contacts.blade.php` is a standalone template, so its markup is free. In the delivered version it prints only the social row, labelled with the block's `title`, with the extra class `institutions-sources` on the `.contacts` section; the style rule for it is in `assets/components/contacts.css`. It was written by the project owner after the core part, so this change has no task for it.

## Risks / Trade-offs

- [Duplicated data preparation in two Blade files] → Accepted for free markup; if more variants appear, extract the preparation into a theme hook (`preprocess('block')`), which now also sees `$owner`.
- [A `{owner}/block-*` file name could clash with a future template of the owner type] → The `block-` prefix is reserved by the block cascade only; no existing candidate uses it in a type directory.
- [A theme author expects `@block` to honour owner files] → Documented explicitly in `theme-templates.md` and covered by a test.

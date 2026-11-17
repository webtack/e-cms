# Tasks

## 1. Render a found node by its own template

- [x] 1.1 Add a method to `BlockRenderer` that renders an already found block node and its type (`render($slug)` keeps the lookup and the `kind` check and calls it), with English PHPDoc and `@example`; verify `docker compose exec app php artisan test --compact --filter=BlockRendererTest` still passes.
- [x] 1.2 Let `PreviewRenderer::render()` take optional `$values` and `$url` (the `values` relation and `/{type.slug}/{slug}` stay the defaults), with English PHPDoc and `@example`; verify `docker compose exec app php artisan test --compact --filter=CandidateCascadeTest` and `--filter=PublicRoutesTest` still pass.

## 2. Use them in the node reference content

- [x] 2.1 In `NodeContentRenderer::content()` branch by the target's kind: a block renders its block template with `pageFields`; any other kind loads the card fields (`list_fields` without `title`, `pageFields` for a kind without `list_fields`, `title` always read) and renders the card with the address of the presented item; keep the stack, the depth limit and `finally`. `FieldRenderer::render()` passes the item address to the renderer. Update the class and method PHPDoc and the `@example`.
- [x] 2.2 Rewrite `tests/Feature/Theme/NodeContentRendererTest.php` for the new behavior (card cascade `{t}/preview--{id}`, `{t}/preview`, `content/preview`; card fields and the page-fields fallback; a block with and without its own template; self-reference, a cycle through a card and through a block, the depth limit, an invisible node, the reading language, no view counted); verify `docker compose exec app php artisan test --compact --filter=NodeContentRendererTest` passes.

## 3. Default theme and dcculture

- [x] 3.1 Change `themes/default/views/fields/field-node_ref.blade.php` to print `content` alone, with the title link only when `content` is empty, and update its header comment; update `tests/Feature/Theme/DefaultFieldTemplatesTest.php` (the title appears once, the link fallback, multiple values, `hide_label`); verify `docker compose exec app php artisan test --compact --filter=DefaultFieldTemplatesTest` passes.
- [x] 3.2 Check what `dcculture/field-node_ref--contacts_reference` now prints (the card of the target type through `content/preview` of the theme) and adjust the template or add a card for the target type only if the output is wrong; verify with `docker compose exec app php artisan theme:check` and by viewing a page of the `department` type that has the reference. No tests for the theme front-end.

## 4. Documentation

- [x] 4.1 Update `docs/theme-templates.md` (the section "Node reference content": block template and card as the content, card fields, the guards inside templates, the `field-node_ref` note), `docs/theme-dcculture.md` (the section "Node references"), and the `node_ref` row in `docs/content-values.md`; verify the links in `docs/README.md` stay valid and the documented template names match `PreviewRenderer` and `BlockRenderer`.
- [x] 4.2 Run Pint on the changed PHP files with `docker compose exec app vendor/bin/pint --dirty --format agent`; the set of PHP tests run in this change is `BlockRendererTest`, `CandidateCascadeTest`, `PublicRoutesTest`, `NodeContentRendererTest`, `DefaultFieldTemplatesTest`; e2e is skipped because no admin or browser behavior changes (the public theme front-end has no e2e).

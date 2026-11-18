## Why

The skeleton of the themes (`add-theme-skeleton`) renders only the heading of a record: the templates `default` show neither the fields of the records, nor the blocks, nor the menus. For the public site to become usable, templates of the fields of all types, the pages, the blocks, the records and the menus are needed, as well as a mechanism that turns the raw values (the uuid of a file, the uuid of a term, the composite values) into data ready for output.

## What Changes

- The method `present()` in the contract `FieldType`: converts the value read by `ContentValueReader` into data ready for the template (the URL of a file instead of `{uuid}.{ext}`, the name of a term, the heading and the address of the target record, the embed address of YouTube, etc.). The default implementation is in `AbstractFieldType`, overrides are in the types with references and files.
- Rendering of a field: a service that collects the 8 candidates `field` (see `site-themes`), calls the hooks `preprocess('field')`/`field.{ftype}` and always passes `$items` to the template (an array, even for a non-multiple field). The Blade directive `@field($values, 'key')` for outputting a field from a template.
- Templates `default/views/fields/field-{ftype}` for all 15 field types (`string`, `text`, `richtext`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `image`, `file`, `youtube`, `term`, `url`, `type_ref`, `node_ref`) and the general `fields/field`.
- Pages: `pages/page` outputs `title` and the fields by `settings.public.page_fields` of the type (without the setting — all added fields), through `@field`.
- Records `content`: `content/list` outputs the cards through `content/preview` (candidates `{t}/preview--{id}`, `{t}/preview`, `content/preview`) by `list_fields`, with sorting, the page size and the paginator from `settings.public`; `content/details` outputs the fields by `page_fields`.
- Blocks: the directive `@block('slug')` renders a visible record of the kind `block` through the candidates `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`; a block that does not exist or is invisible outputs nothing.
- Menus: the directive `@menu('key')` renders the tree `MenuTree` through `menus/menu--{key}`, `menus/menu`; `layout` shows the menu `main` if it exists.
- The hooks `preprocess`/`suggest` apply to the fields, the blocks, the menus and the preview.

Out of scope: the languages of the site, the regions of blocks, switching the theme in the admin, new themes except `default`.

## Capabilities

### New Capabilities
- `theme-rendering`: the ready values of fields (`present`), the rendering of fields, blocks and menus in the theme: the candidates of templates, the directives, the hooks, the templates `default`.

### Modified Capabilities
- `public-site`: the pages, the list and the details of records output the fields by the public settings of the type; the header of the site shows the menu `main`.

## Impact

- Code: `app/Content/FieldTypes/*` (the contract `FieldType`, `AbstractFieldType` and the types with references/files), a new `app/Theme/FieldRenderer.php` (and the related classes of blocks/menus), `app/Providers/AppServiceProvider.php` (the Blade directives), `app/Http/Controllers/Public/PublicNodeController.php`, `themes/default/views/**`, `themes/default/hooks.php`.
- Tests: `tests/Feature/Theme/*`, `tests/Feature/Public/*`; e2e `e2e/public-site.spec.js`.
- Docs: `docs/theme-templates.md` is created (fields, blocks, menus, the full list of candidates); `docs/site-themes.md`, `docs/public-site.md`, `docs/content-values.md` (the contract `present`), `docs/menus.md` (a reference), `docs/README.md`, `docs/e2e-testing.md` are updated.

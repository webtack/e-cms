## Context

The skeleton of the themes already exists: `ThemeManager::render($candidates, $data, $hook)` (the cascade «candidate → theme», the hooks `preprocess`/`suggest`, the Blade namespace `theme`), the public controllers in `app/Http/Controllers/Public/`, `PublicData` (the shared variables), `themes/default/views/{html,layout,home,errors/404,pages/page,content/list,content/details}`. Reading values: `ContentValueReader::load()` returns the values typed (`cast`), but the references remain raw (`{uuid}.{ext}` for files, the uuid of a term and of a record, the key of a type, the composite value of youtube/url as an array). The public settings of a type are read by `PublicDisplaySettings::resolve()` (`list_fields`, `page_fields`, `sort`, `per_page`, `pagination`); the menu — `MenuTree::tree($key, $lang)`; the URL of a file — `FileStorage::urlForValue()`. The decisions agreed earlier about the naming, the candidates and the hooks — in `docs/site-themes.md`; here only what is not there yet. The motivation — `proposal.md`.

The documents that describe the components: `docs/site-themes.md` (the cascade, the hooks), `docs/public-site.md` (the routes, the variables), `docs/content-values.md` (reading values, the contract `FieldType`), `docs/menus.md` (`MenuTree`); the new `docs/theme-templates.md` describes the fields, the blocks, the menus and the full list of candidates.

## Goals / Non-Goals

**Goals:**
- A template never makes requests and does not know about the storage format: it receives ready data.
- Adding a new field type = a class of the type (with `present`) + the template `fields/field-{ftype}`; the core of the rendering does not change.
- One point of rendering of fields/blocks/menus for all the templates of the theme, with hooks.

**Non-Goals:**
- The languages of the site (the rendering only in the language `default`), the regions of blocks, switching the theme in the admin, new themes except `default`.
- Caching of rendered fragments.
- Changing the storage formats or the API of reading values for the admin.

## Decisions

### 1. `present()` in `FieldType`, not a separate formatter
`FieldType::present(mixed $value, ContentField $field, PresentContext $context): mixed` returns the ready value for ONE element. `AbstractFieldType` returns `$value` as it is, so the types without references (`string`, `integer`, `boolean`, `date`, `datetime`, `decimal`, `text`) override nothing. Override `image`/`file` (the URL), `richtext` (the cleaned HTML, once again through `HtmlSanitizer`), `youtube`, `url`, `term`, `node_ref`, `type_ref`. The type knows its format, so there is no `switch` by the key of the type; the new types from `config/content.php` are picked up automatically.

*Alternative*: a separate registry of formatters in the theme. Rejected: it duplicates the registry of types, and the theme would have to know the structure of the stored values.

### 2. Batch resolution of references
So that there are no requests per element, `FieldRenderer` before rendering collects the values of the fields of the page/card and resolves the references in a batch (`present` receives a `PresentContext` with a cache: terms by uuid + translations in one selection, the target records by uuid in one selection with the value of `title`, the types by key). A list of N cards makes a fixed number of requests, not N. `PresentContext` lives for the duration of one request (a scoped service).

*Alternative*: a request in `present()` for each value. Rejected: N+1 on lists.

### 3. `FieldRenderer` in `app/Theme/`, the directives as thin wrappers
`FieldRenderer::render(ContentField $field, mixed $raw, ContentNode $node, ContentType $type): string` builds the list of eight candidates (the order from `docs/site-themes.md`), prepares `$items` (always an array, having filtered out the values that `present` returned as `null`), and calls `ThemeManager::render($candidates, $vars, "field.{$ftype}")`. The hook name `field.{ftype}` runs first `field`. An empty `$items` gives an empty string without rendering. The fields needed by the template are taken from `$type` + `ContentField` (the types and the settings are already loaded; `PublicDisplaySettings` gives the list of keys).

The Blade directives are registered in `AppServiceProvider::boot()` (next to the existing registration of the namespace `theme`): `@field($values, 'key')` (requires `$node`, `$type`, `$fields` in the template, which the controller puts into the variables), `@fields($keys)` for a group, `@block('slug')`, `@menu('key')`. The directives only call the services (`FieldRenderer`, `BlockRenderer`, `MenuRenderer`) and output their HTML unescaped (it has already been assembled by Blade). All the logic of choosing the candidates is in the services, not in the directives, so it is covered by Feature tests without Blade.

*Alternative*: the component `<x-field>`. Rejected: it requires a class/anonymous component and a binding to a path; the directive does not depend on the theme.

### 4. Blocks and menus — separate simple services
`BlockRenderer::render(string $slug)`: finds the record `kind = block` by slug (`ContentNode::findBySlug($slug, onlyVisible: true)`, checks `contentType->kind === 'block'`), reads the values `ContentValueReader::load()`, the candidates `blocks/block-{t}--{slug-without-block-}`, `blocks/block-{t}`, `blocks/block`, the hook `block`. Invisible/missing → `''`. `MenuRenderer::render(string $key)`: `Menu::findByKey` + `MenuTree::tree($key, 'default')`, the candidates `menus/menu--{key}`, `menus/menu`, the hook `menu`; a missing menu → `''`. The template `menus/menu` renders the tree recursively through `@include` of an anonymous nested list (`menus/item` is not made a separate candidate: one template per menu is simpler to override).

The data of one request `layout` + the menu `main` add one request of the tree; the value of a block — the standard requests of the reader.

### 5. The pages/content controller gives the template the fields and the settings
`PublicNodeController` is extended: besides `node/type/values`, it passes `fields` (key → `ContentField` for those shown: from `PublicDisplaySettings::resolve()['page_fields']` or `list_fields`) and prepares the list of records for the card (`ContentValueReader::load()` over the page of the paginator, not one by one). For the list `ContentNodeQuery::paginate()` is called with `perPage`, `sort` from the settings of the type (`NodeQueryParams`); `pagination = false` hides `links()` in `content/list`. The templates `default` compose the fields so: `@foreach ($fields as $key => $field) @field($key) @endforeach`.

The card of the list is rendered by a separate call of `ThemeManager::render` with the candidates `{t}/preview--{id}`, `{t}/preview`, `content/preview` (the hook `content.preview`) — through the directive `@preview($item)` in `content/list`.

### 6. The metadata of the document
`meta_title`, `meta_description`, `meta_image` are added to `PublicData::make()` as the variables `$meta` (if there is a value), and `themes/default/views/html.blade.php` outputs `<title>`, `<meta name="description">`, `og:image`. `meta_title` overrides the heading of the record in `$title`. This is the behaviour of `default`; another theme may not output it.

### 7. Safety of the output
Everything except `richtext` is output through `{{ }}` (escaped). The value of `richtext` is taken from the DB, where it has already been cleaned by `HtmlSanitizer` at the writing, and `present()` cleans it again (protection against data written outside the API); in the template `{!! !!}` is output only for it. The URL of a `url` field that does not pass `UrlType::isValid` is not output as a link. The YouTube embed: only `https://www.youtube-nocookie.com/embed/{id}` from an identifier that already matches `[A-Za-z0-9_-]{11}`, and not from an arbitrary string.

## Risks / Trade-offs

- [The complex contract of `present` + the batch `PresentContext`] → makes the types with references receive a cache; we minimize it: the types without references do not change, and the tests of the number of requests (`DB::getQueryLog`) guard against N+1.
- [The Blade directives depend on the variables that the controller puts (`$node`, `$type`)] → `FieldRenderer` does not read a global state; a directive without variables throws `ThemeException` with an explanation, rather than staying silent.
- [The templates `default` for 15 types — many files] → a shared structure (`<div class="field field-{ftype} field--{key}">`); the check `theme:check` is extended: `default` must have `fields/field-{ftype}` for each type of the registry (so that a new type without a template is caught by the command).
- [`meta_*` change `<title>`] → only when the value is set; otherwise the behaviour of the skeleton does not change.
- [The menu `main` in `layout` — an assumption about the key] → the key `main` becomes a convention of the theme `default`, described in `docs/theme-templates.md`; without such a menu the header does not break.

## Migration Plan

No DB migrations and no changes in the data. Step by step: the contract `present` → the renderer of fields and the templates → the pages/list/details → blocks → menus → `theme:check`. The rollback is an ordinary `git revert`; `themes/default` without these files returns to the skeleton.

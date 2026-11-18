## 1. Ready values of fields (`present`)

- [x] 1.1 Add `present(mixed $value, ContentField $field, PresentContext $context)` to `FieldType`, the default implementation in `AbstractFieldType` and the class `PresentContext` (the batch caches of terms, target records, types), with JSDoc/PHPDoc in English and `@example`; check `docker compose exec app php artisan test --compact --filter=FieldTypeRegistry`
- [x] 1.2 Implement `present` for `image`/`file` (the URL through `FileStorage::urlForValue`), `richtext` (the repeated cleaning), `youtube` (id, watch-URL, embed-URL `youtube-nocookie.com`), `url` (url+title, only valid ones), `term` (the name in the reading language), `node_ref` (title+address of a visible record, invisible → `null`), `type_ref` (key, name, address for `content`); check with the test `tests/Feature/Theme/PresentTest.php` for each type (`--filter=PresentTest`), including the check of the number of requests for a list of values
- [x] 1.3 Describe the contract `present` in `docs/content-values.md` (the section «Публічне подання» — "Public representation") with a cross-reference to `docs/theme-templates.md`; check that the example in the document matches `PresentTest`

## 2. Rendering of fields

- [x] 2.1 Create `App\Theme\FieldRenderer` (8 candidates, `$items` is always an array, an empty field → `''`, the hooks `field`/`field.{ftype}`) and the directives `@field`, `@fields` in `AppServiceProvider`; check `tests/Feature/Theme/FieldRendererTest.php` (`--filter=FieldRendererTest`): the cascade of candidates, `$items` for a non-multiple field, an empty field, preprocess/suggest
- [x] 2.2 Add the templates `themes/default/views/fields/field-{ftype}.blade.php` for the 15 types and `fields/field.blade.php`; escape the output, `{!! !!}` only for `richtext`; check with a test that for each type from `config('content.field_types')` a non-empty HTML is rendered for a typical value (`--filter=DefaultFieldTemplatesTest`)
- [x] 2.3 Extend `theme:check`: `default` must have `fields/field-{ftype}` for each type of the registry; check `--filter=ThemeCheckCommandTest` (the new scenario of a missing template of a type)
- [x] 2.4 Create `docs/theme-templates.md` (the sections «Поля» — "Fields", the candidates, the directives, the examples `preprocess('field.youtube')`), update `docs/site-themes.md` (the references, `theme:check`), `docs/README.md` (the index) and check the validity of the links

## 3. Pages, list and details of records

- [x] 3.1 Extend `PublicNodeController`: pass to the templates `fields` by `page_fields`/`list_fields` from `PublicDisplaySettings`, apply `sort`, `per_page`, `pagination` to `ContentNodeQuery::paginate`, read the values of the page of the paginator by one `ContentValueReader::load()`; check `tests/Feature/Public/PublicRoutesTest.php` (`--filter=PublicRoutesTest`) for the configured and absent settings
- [x] 3.2 Update the templates `pages/page`, `content/details` (the fields through `@fields`), `content/list` (the cards through `@preview`), add `content/preview` and the directive `@preview` with the candidates `{t}/preview--{id}`, `{t}/preview`, `content/preview` and the hook `content.preview`; check with the tests of the cascade of preview (`--filter=PublicRoutesTest`, `--filter=CandidateCascadeTest`)
- [x] 3.3 Add `$meta` (`meta_title`, `meta_description`, `meta_image`) to `PublicData` and the output in `html.blade.php`; check with a test of the heading/meta tags (`--filter=PublicRoutesTest`)
- [x] 3.4 Update `docs/public-site.md` (the variables `$fields`, `$meta`, the candidates of preview) and `docs/theme-templates.md` (pages, list, details); e2e: extend `e2e/public-site.spec.js` with scenarios of the fields of the page and of the card of the list, run `npm run test:e2e -- e2e/public-site.spec.js`

## 4. Blocks

- [x] 4.1 Create `App\Theme\BlockRenderer` and the directive `@block('slug')` (the candidates `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`; invisible/missing → `''`), add `themes/default/views/blocks/block.blade.php`; check `tests/Feature/Theme/BlockRendererTest.php` (`--filter=BlockRendererTest`): a visible, an invisible, a non-existent block, the cascade, `/block-footer` remains 404
- [x] 4.2 Describe the blocks in `docs/theme-templates.md` and update `docs/public-site.md` (the blocks are not routed, the placement by the directive); check that the examples match the tests

## 5. Menus

- [x] 5.1 Create `App\Theme\MenuRenderer` and the directive `@menu('key')` (the candidates `menus/menu--{key}`, `menus/menu`, the hook `menu`; a missing menu → `''`), add `themes/default/views/menus/menu.blade.php` (a recursive nested list, external links with `rel`/`target`); check `tests/Feature/Theme/MenuRendererTest.php` (`--filter=MenuRendererTest`): nested items, hidden branches, a missing menu, a separate template `menu--footer`
- [x] 5.2 Include `@menu('main')` in `themes/default/views/layout.blade.php` (a missing menu does not break the page); check `--filter=PublicRoutesTest` (with a menu and without) and `npm run test:e2e -- e2e/public-site.spec.js`
- [x] 5.3 Describe the menus in `docs/theme-templates.md`, add a reference in `docs/menus.md`; update the tables in `docs/e2e-testing.md` for the changes in `e2e/public-site.spec.js` (the rows «Область → спек» and «Спільний код → спеки»), check the validity of the links in `docs/README.md`

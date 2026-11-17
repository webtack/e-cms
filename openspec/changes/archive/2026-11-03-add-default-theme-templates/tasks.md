## 1. Готові значення полів (`present`)

- [x] 1.1 Додати `present(mixed $value, ContentField $field, PresentContext $context)` до `FieldType`, реалізацію за замовчуванням у `AbstractFieldType` і клас `PresentContext` (пакетні кеші термів, цільових записів, типів), з JSDoc/PHPDoc англійською та `@example`; перевірити `docker compose exec app php artisan test --compact --filter=FieldTypeRegistry`
- [x] 1.2 Реалізувати `present` для `image`/`file` (URL через `FileStorage::urlForValue`), `richtext` (повторне очищення), `youtube` (id, watch-URL, embed-URL `youtube-nocookie.com`), `url` (url+title, лише валідні), `term` (назва мовою читання), `node_ref` (title+адреса видимого запису, невидимий → `null`), `type_ref` (key, name, адреса для `content`); перевірити тестом `tests/Feature/Theme/PresentTest.php` для кожного типу (`--filter=PresentTest`), включно з перевіркою кількості запитів для списку значень
- [x] 1.3 Описати контракт `present` у `docs/content-values.md` (розділ «Публічне подання») з перехресним посиланням на `docs/theme-templates.md`; перевірити, що приклад у документі збігається з `PresentTest`

## 2. Рендер полів

- [x] 2.1 Створити `App\Theme\FieldRenderer` (8 кандидатів, `$items` завжди масив, порожнє поле → `''`, хуки `field`/`field.{ftype}`) і директиви `@field`, `@fields` в `AppServiceProvider`; перевірити `tests/Feature/Theme/FieldRendererTest.php` (`--filter=FieldRendererTest`): каскад кандидатів, `$items` для немножинного поля, порожнє поле, preprocess/suggest
- [x] 2.2 Додати шаблони `themes/default/views/fields/field-{ftype}.blade.php` для 15 типів і `fields/field.blade.php`; екранувати вивід, `{!! !!}` лише для `richtext`; перевірити тестом, що для кожного типу з `config('content.field_types')` рендериться непорожній HTML для типового значення (`--filter=DefaultFieldTemplatesTest`)
- [x] 2.3 Розширити `theme:check`: у `default` має бути `fields/field-{ftype}` для кожного типу реєстру; перевірити `--filter=ThemeCheckCommandTest` (новий сценарій відсутнього шаблона типу)
- [x] 2.4 Створити `docs/theme-templates.md` (розділи «Поля», кандидати, директиви, приклади `preprocess('field.youtube')`), оновити `docs/site-themes.md` (посилання, `theme:check`), `docs/README.md` (індекс) і перевірити валідність посилань

## 3. Сторінки, список і деталі записів

- [x] 3.1 Розширити `PublicNodeController`: передавати в шаблони `fields` за `page_fields`/`list_fields` з `PublicDisplaySettings`, застосовувати `sort`, `per_page`, `pagination` до `ContentNodeQuery::paginate`, читати значення сторінки пагінатора одним `ContentValueReader::load()`; перевірити `tests/Feature/Public/PublicRoutesTest.php` (`--filter=PublicRoutesTest`) для налаштованих і відсутніх налаштувань
- [x] 3.2 Оновити шаблони `pages/page`, `content/details` (поля через `@fields`), `content/list` (картки через `@preview`), додати `content/preview` і директиву `@preview` з кандидатами `{t}/preview--{id}`, `{t}/preview`, `content/preview` та хуком `content.preview`; перевірити тестами каскаду preview (`--filter=PublicRoutesTest`, `--filter=CandidateCascadeTest`)
- [x] 3.3 Додати `$meta` (`meta_title`, `meta_description`, `meta_image`) до `PublicData` і вивід у `html.blade.php`; перевірити тестом заголовка/мета-тегів (`--filter=PublicRoutesTest`)
- [x] 3.4 Оновити `docs/public-site.md` (змінні `$fields`, `$meta`, кандидати preview) і `docs/theme-templates.md` (сторінки, список, деталі); e2e: розширити `e2e/public-site.spec.js` сценаріями полів сторінки й картки списку, запустити `npm run test:e2e -- e2e/public-site.spec.js`

## 4. Блоки

- [x] 4.1 Створити `App\Theme\BlockRenderer` і директиву `@block('slug')` (кандидати `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`; невидимий/відсутній → `''`), додати `themes/default/views/blocks/block.blade.php`; перевірити `tests/Feature/Theme/BlockRendererTest.php` (`--filter=BlockRendererTest`): видимий, невидимий, неіснуючий блок, каскад, `/block-footer` лишається 404
- [x] 4.2 Описати блоки в `docs/theme-templates.md` і оновити `docs/public-site.md` (блоки не маршрутизуються, розміщення директивою); перевірити, що приклади збігаються з тестами

## 5. Меню

- [x] 5.1 Створити `App\Theme\MenuRenderer` і директиву `@menu('key')` (кандидати `menus/menu--{key}`, `menus/menu`, хук `menu`; відсутнє меню → `''`), додати `themes/default/views/menus/menu.blade.php` (рекурсивний вкладений список, зовнішні посилання з `rel`/`target`); перевірити `tests/Feature/Theme/MenuRendererTest.php` (`--filter=MenuRendererTest`): вкладені пункти, приховані гілки, відсутнє меню, окремий шаблон `menu--footer`
- [x] 5.2 Підключити `@menu('main')` у `themes/default/views/layout.blade.php` (відсутнє меню не ламає сторінку); перевірити `--filter=PublicRoutesTest` (з меню і без) та `npm run test:e2e -- e2e/public-site.spec.js`
- [x] 5.3 Описати меню в `docs/theme-templates.md`, додати посилання в `docs/menus.md`; оновити таблиці в `docs/e2e-testing.md` для змін у `e2e/public-site.spec.js` (рядки «Область → спек» і «Спільний код → спеки»), перевірити валідність посилань у `docs/README.md`

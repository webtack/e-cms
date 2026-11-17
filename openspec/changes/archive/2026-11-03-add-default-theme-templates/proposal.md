## Why

Каркас тем (`add-theme-skeleton`) віддає лише заголовок запису: шаблони `default` не показують ні полів записів, ні блоків, ні меню. Щоб публічний сайт став придатним до використання, потрібні шаблони полів усіх типів, сторінок, блоків, записів і меню та механізм, що перетворює сирі значення (uuid файла, uuid терма, складені значення) на готові до виводу дані.

## What Changes

- Метод `present()` у контракті `FieldType`: перетворює значення, прочитане `ContentValueReader`, на готові для шаблона дані (URL файла замість `{uuid}.{ext}`, назва терма, заголовок і адреса цільового запису, embed-адреса YouTube тощо). Реалізація за замовчуванням у `AbstractFieldType`, перевизначення в типах із посиланнями й файлами.
- Рендер поля: сервіс, що збирає 8 кандидатів `field` (див. `site-themes`), викликає хуки `preprocess('field')`/`field.{ftype}` і завжди передає шаблону `$items` (масив, навіть для немножинного поля). Директива Blade `@field($values, 'key')` для виводу поля з шаблона.
- Шаблони `default/views/fields/field-{ftype}` для всіх 15 типів полів (`string`, `text`, `richtext`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `image`, `file`, `youtube`, `term`, `url`, `type_ref`, `node_ref`) і загальні `fields/field`.
- Сторінки: `pages/page` виводить `title` і поля за `settings.public.page_fields` типу (за відсутності налаштування — усі додані поля), через `@field`.
- Записи `content`: `content/list` виводить картки через `content/preview` (кандидати `{t}/preview--{id}`, `{t}/preview`, `content/preview`) за `list_fields`, із сортуванням, розміром сторінки й пагінатором із `settings.public`; `content/details` виводить поля за `page_fields`.
- Блоки: директива `@block('slug')` рендерить видимий запис виду `block` через кандидати `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`; блок, якого немає чи який невидимий, нічого не виводить.
- Меню: директива `@menu('key')` рендерить дерево `MenuTree` через `menus/menu--{key}`, `menus/menu`; `layout` показує меню `main`, якщо воно існує.
- Хуки `preprocess`/`suggest` діють для полів, блоків, меню й preview.

Поза межами: мови сайту, регіони блоків, зміна теми в адмінці, нові теми окрім `default`.

## Capabilities

### New Capabilities
- `theme-rendering`: готові значення полів (`present`), рендер полів, блоків і меню в темі: кандидати шаблонів, директиви, хуки, шаблони `default`.

### Modified Capabilities
- `public-site`: сторінки, список і деталі записів виводять поля за публічними налаштуваннями типу; шапка сайту показує меню `main`.

## Impact

- Код: `app/Content/FieldTypes/*` (контракт `FieldType`, `AbstractFieldType` і типи з посиланнями/файлами), новий `app/Theme/FieldRenderer.php` (та пов'язані класи блоків/меню), `app/Providers/AppServiceProvider.php` (директиви Blade), `app/Http/Controllers/Public/PublicNodeController.php`, `themes/default/views/**`, `themes/default/hooks.php`.
- Тести: `tests/Feature/Theme/*`, `tests/Feature/Public/*`; e2e `e2e/public-site.spec.js`.
- Docs: створюється `docs/theme-templates.md` (поля, блоки, меню, повний перелік кандидатів); оновлюються `docs/site-themes.md`, `docs/public-site.md`, `docs/content-values.md` (контракт `present`), `docs/menus.md` (посилання), `docs/README.md`, `docs/e2e-testing.md`.

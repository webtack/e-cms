# Proposal

## Why

Бекенд вузлів контенту вже є (`ContentNodeCreator`, `ContentValueWriter`, `ContentValueReader`, `ContentNodeQuery`, 7 типів полів), але в адмінці немає жодної сторінки для роботи з ними: можна створювати типи й поля, а самі записи (`content_nodes`) — ні. Без цього типи `kind = content` не можна наповнити контентом.

## What Changes

Адмінка для вузлів типів із видом `content` лише в мові `default`. Вузли живуть під типом у межах розділу «Content Types» (рішення власника: варіант A).

- Маршрути `/admin/content-types/{contentType}/nodes` (список), `/create`, `/{node}/edit`, `PUT`, `DELETE`. Вузол адресується за `uuid` (незмінний ідентифікатор); `slug` — публічний ідентифікатор для фронтенду сайту й в адресах адмінки не використовується; вузол іншого типу або іншого виду → 404 (як для `{field}`).
- `ContentNodeController` + `StoreContentNodeRequest` / `UpdateContentNodeRequest`: правила значень будує `FieldTypeRegistry` за `content_fields`, запис — `ContentNodeCreator::create()` і `ContentValueWriter::write()`.
- Список: DataTable у серверному режимі. Колонки — з `settings.list.visible` типу (за замовчуванням `title`), пошук і сортування через `ContentNodeQuery` / `NodeQueryParams`. Потрібен адаптер між `TableRequest` і `NodeQueryParams`, бо `TablePage::fromCollection` працює з колекцією в пам'яті.
- Форма вузла: динамічні віджети за типом поля (`string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`), редактор списку для `is_multiple`, обов'язковість і `settings` (min/max) з поля; помилки валідації за ключами (`tags.2`).
- Slug: необов'язковий при створенні (порожній → автогенерація з `title`, явний → перевірка формату й унікальності). Slug показується й редагується у формі редагування (рішення за замовчуванням; зміна slug не змінює `uuid`).
- Видалення вузла з діалогом підтвердження.
- Зв'язок із типами: дія «Вузли» в меню рядка типу, третій пункт «Вузли» у `ContentTypeNav` (тільки для `kind = content`), лічильник `nodes_count` стає посиланням на список; breadcrumbs і переклади uk.
- Документація, Feature-тести, новий e2e-спек.

## Non-goals

- **Переклади** (вкладки мов, перемикач `content.multilingual`, блокування неперекладних полів) — окрема наступна зміна. Тут лише `default`.
- Вузли видів `page` і `block` (їхні розділи — окремо; контролер проєктувати так, щоб `kind` потім не заважав).
- Фільтри за значеннями полів у таблиці (оператори `between`, `contains` тощо): бекенд їх вміє, UI — на потім.
- Публічний сайт (`content_types.slug` як публічний шлях).

## Capabilities

### New Capabilities
- `admin-content-nodes`: список, створення, редагування й видалення вузлів типу `content` в адмінці (мова `default`).

### Modified Capabilities
- `content-model`: фіксується призначення `slug` вузла (публічний/фронтенд-ідентифікатор) і що внутрішні та адмінські посилання на вузол йдуть через `uuid`; раніше це ніде не було записано.
- `admin-content-types`: меню рядка й горизонтальне меню розділу типу отримують пункт «Вузли»; лічильник вузлів у таблиці стає посиланням.

## Impact

- Код: `routes/web.php` (route bind `{node}` і маршрути вузлів), `app/Http/Controllers/Admin/ContentNodeController.php`, `app/Http/Requests/Admin/*ContentNodeRequest.php`, адаптер таблиці, `resources/js/Pages/Admin/ContentNodes/{Index,Create,Edit}.tsx`, `Components/Admin/ContentNodes/*` (форма, віджети полів, row actions), `ContentTypeNav.tsx`, `ContentTypeRowActions.tsx`, `navigation.ts`, переклади uk.
- Docs: створити `docs/admin-content-nodes.md`; оновити `docs/README.md` (індекс), `docs/content-model.md` (призначення `slug` і `uuid`), `docs/admin-content-types.md` (пункт «Вузли»), `docs/e2e-testing.md` (спек у «Область → спек» і «Спільний код → спеки»).
- e2e: новий `e2e/admin-content-nodes.spec.js`; зі спільного коду зачіпаються `ContentTypeNav` і row actions → ще `admin-content-types`.

## Нотатки з дослідження (для design / tasks)

- Наявний бекенд: `ContentNodeCreator::create($type, $values, ?$slug, $lang, ?$uuid)`; `ContentValueWriter::write($node, $values, $lang)` замінює лише передані поля; `ContentNodeQuery::paginate()` бере видимі поля з `settings.list`; `ContentValueReader::load()` — один запит на таблицю значень.
- Окремого шляху видалення вузла немає (лише каскад БД) — достатньо `$node->delete()` у контролері; вирішити, чи потрібен сервісний метод.
- Зміна slug існуючого вузла модельно дозволена (`ContentNode::assertValidSlug`); унікальність при оновленні віддана індексу БД, тому FormRequest перевіряє її сам.
- У `ContentTypeController::destroy` тип із вузлами не видаляється — після цієї зміни повідомлення «видаліть вузли спершу» стає виконуваним.
- З'ясовано: серверний `DataTable` отримує колонки з пропсів сторінки, рядки — через `TablePage::make()`; див. design.md.
- Наступна зміна (переклади): вкладки мов, перемикач `content.multilingual` у UI (зараз лише `AppSettings`), `ContentLanguages::isMultilingual()`.

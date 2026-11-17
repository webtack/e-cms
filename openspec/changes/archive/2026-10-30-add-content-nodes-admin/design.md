# Design

## Context

Мотивація й обсяг — у [proposal.md](proposal.md), вимоги — у `specs/`. Що вже є й на що спираємось (усе прочитано в коді):

- Бекенд вузлів готовий: `ContentNodeCreator::create()`, `ContentValueWriter::write()/validate()`, `ContentValueReader::load()`, `ContentNodeQuery::paginate()`.
- Розділ типів віддається циклом маршрутів у `routes/web.php`; `kind` береться з `defaults('kind')` маршруту (трейт `ResolvesTypeSection`), `{contentType}` прив'язується за `key` у межах виду, `{field}` — за парою (тип, `key`).
- Серверний `DataTable` живиться від `TableRequest::from()` + `TablePage::make($rows, $total, $table)`: стан таблиці парситься з адреси, а рядки контролер бере з власного джерела. Колонки `Column<Row>` оголошуються на клієнті (React), серверні `TableColumn` — для валідації `sort` і `q`.
- `ContentNodeQuery::paginate()` сам пагінує й кидає `ValidationException` для невідомих полів, а номер сторінки поза діапазоном повертає порожньо.
- Налаштування поля (`settings`: `min`, `max`) у формах полів не редагуються (їх немає в `sharedRules`), тож задаються лише кодом.
- Видалення поля не чистить `settings.list` типу (`ContentField` блокує лише видалення системного поля), тому в `settings.list.visible` можуть лишитися ключі неіснуючих полів.
- Документація: [content-values.md](../../../docs/content-values.md), [content-querying.md](../../../docs/content-querying.md), [admin-data-table.md](../../../docs/admin-data-table.md), [admin-content-types.md](../../../docs/admin-content-types.md), [admin-content-fields.md](../../../docs/admin-content-fields.md), [e2e-testing.md](../../../docs/e2e-testing.md).

## Goals / Non-Goals

**Goals:**
- Один контролер і одна пара запитів для вузлів `content`, без дублювання логіки значень: вся валідація й запис — через наявні `ContentNodeCreator` / `ContentValueWriter`.
- Форма будується даними (поля типу), без жорстко закладених полів.
- Контролер не залежить від того, що вид завжди `content` більше, ніж потрібно: вид береться з маршруту, щоб розділи `page`/`block` могли підключитися окремою зміною.

**Non-Goals:**
- Мови, переклади, перемикач багатомовності (окрема зміна). Усе читається й пишеться з `lang = default`, мова у формі не обирається.
- Фільтри за значеннями полів у таблиці вузлів.
- Редагування `settings` поля чи `settings.list` типу в адмінці.

## Decisions

### 1. Адреси й прив'язка вузла

Маршрути вузлів оголошуються окремо від циклу типів у `routes/web.php`, лише для `content-types` (`->defaults('kind', 'content')`):

```
GET    /admin/content-types/{contentType}/nodes                  nodes.index
GET    /admin/content-types/{contentType}/nodes/create           nodes.create
POST   /admin/content-types/{contentType}/nodes                  nodes.store
GET    /admin/content-types/{contentType}/nodes/{node}/edit      nodes.edit
PUT    /admin/content-types/{contentType}/nodes/{node}           nodes.update
DELETE /admin/content-types/{contentType}/nodes/{node}           nodes.destroy
```

`Route::bind('node', …)` шукає `ContentNode` за парою (`content_type = contentType.key`, `uuid`), як `{field}` за парою (тип, `key`): невідомий `uuid` або `uuid` вузла іншого типу дає 404. Рядок таблиці несе `uuid` як ідентифікатор рядка (`getRowId`) і для адрес редагування/видалення.

`slug` — публічний ідентифікатор для фронтенду сайту (рішення власника); в адмінських адресах він не використовується, тож його зміна не ламає відкриті форми та посилання. Це призначення `slug` раніше ніде не було записано, тому фіксується в `content-model` (дельта) і в `docs/content-model.md`.

*Альтернатива:* адресувати за `slug` — читабельніше, але slug змінюваний (відкрита друга вкладка отримала б 404 після зміни), а `uuid` створений саме як стабільний ідентифікатор вузла.

### 2. Список: `ContentNodeQuery` за `TableRequest`

Контролер будує `TableRequest::from($request, $columns)`, де колонки — серверний опис того ж набору, що піде на клієнт:

| Колонка | Тип таблиці | sortable | searchable |
| --- | --- | --- | --- |
| `title` та видимі поля | за типом поля (`string`/`text` → `text`, `integer`/`decimal` → `number`, `boolean` → `boolean`, `date`/`datetime` → `date`) | немножинні поля | рішення за `ContentNodeQuery` |
| `slug` | `text` | ні | ні |

`TableRequest` дає `search`, `sort`/`dir`, `page`, `perPage` без викидання помилок. Далі контролер викликає `ContentNodeQuery::paginate($type, new NodeQueryParams(fields: $visible, search: …, sort: …, page: …, perPage: …))` і обгортає результат у `TablePage::make($rows, $total, $table)`.

Номер сторінки за межами діапазону: `paginate()` повертає порожню сторінку, тому контролер після першого виклику порівнює `page` із `lastPage()` і за потреби повторює запит для останньої сторінки (`TablePage::make` сама бере `withTotal`, але рядки мають відповідати). Один зайвий запит лише в рідкісному випадку.

`$visible` = `settings.list.visible` ∩ наявні поля типу, з обов'язковим `title` на першому місці (див. Ризики). Рядок таблиці: `uuid` (id рядка), `slug`, ключ → значення для кожного видимого поля (множинне — рядок через кому, дата/час — рядок, булеве — булеве), плюс службове `title` для діалогу видалення (це значення поля `title`).

*Альтернатива:* `TablePage::fromCollection` над усіма вузлами — відхилено: не масштабується й дублює пошук/сортування, які вже є в `ContentNodeQuery`.

### 3. Форма: дані поля йдуть на клієнт, віджет обирає клієнт

Контролер передає `fields` (за вагою): `key`, `name`, `type`, `is_multiple`, `is_required`, `settings` (лише `min`/`max` для підказок атрибутів `min`/`max`/`maxLength`; сервер лишається арбітром) і `values` (ключ → значення, множинні — список). Значення форматуються у рядки для HTML-елементів: `date` → `Y-m-d`, `datetime` → `Y-m-d\TH:i` (формат `datetime-local`), `decimal` вже рядок, `boolean` — булеве. Мапа «тип поля → віджет» живе на клієнті в одному місці (`NodeFieldInput`): `string` → `Input`, `text` → `Textarea`, `integer` → `Input type=number step=1`, `decimal` → `Input inputMode=decimal` (текст, щоб не губити точність), `boolean` → `Checkbox`, `date` → `Input type=date`, `datetime` → `Input type=datetime-local`.

Множинне поле — `NodeFieldList` поверх того ж віджета: кнопки «Додати» і «Видалити елемент» та перестановка елементів. Порядок збережених значень = `weight` = індекс у списку (`ContentValueWriter` записує список за індексом, `ContentValueReader` читає за `weight`), тож бекенд не змінюється: форма надсилає список у новому порядку. Перестановка — перетягуванням ручкою через `@dnd-kit/core` + `@dnd-kit/sortable` (уже є в проєкті для `DataTable`) із клавіатурною підтримкою (пробіл, стрілки), як у перетягуванні рядків таблиці. Елементи в стані форми мають клієнтські ідентифікатори (стабільний `id` на елемент), бо значення можуть повторюватися й не годяться як ключі; ідентифікатори до сервера не надсилаються.

Новий тип поля в реєстрі, якого немає в мапі віджетів, показується звичайним текстовим полем (запасний варіант), щоб розширення реєстру не ламало форму.

### 4. Контракт форми й помилки

Форма надсилає `{ slug, values: { <key>: <value|list> } }`. Значення — під окремим ключем `values`, бо ключ поля може збігатися з `slug` (формат ключа це дозволяє).

`StoreContentNodeRequest` / `UpdateContentNodeRequest` перевіряють лише `slug` (необов'язковий при створенні; формат; унікальність через `Rule::unique('content_nodes', 'slug')`, при зміні — з `ignore($node)`, бо унікальність у моделі віддана індексу БД, а `UniqueConstraintViolationException` на оновленні дала б 500) і те, що `values` — масив. Решту значень перевіряє `ContentValueWriter::validate()` (єдине джерело правил).

Контролер викликає `ContentNodeCreator::create()` / `ContentValueWriter::write()` у `try`; `ValidationException` від них має ключі без префікса (`title`, `scores.1`), тому контролер перекидає її з префіксом `values.` (`slug` лишається як є), щоб ключі збігалися з вкладеністю в `useForm` і відображалися біля потрібних полів (`values.scores.1`). Введене лишається у формі, бо Inertia повертає назад.

*Альтернатива:* плоскі поля без `values` — відхилено через колізію з `slug`; окрема валідація кожного типу у FormRequest — відхилено як дубль правил реєстру.

### 5. Оновлення

`update` у транзакції: якщо `slug` змінився — `$node->update(['slug' => …])` (модель перевіряє формат/префікс), далі `ContentValueWriter::write($node, $allFieldValues, 'default')` зі значеннями **всіх** полів форми, щоб очищене поле втратило значення (`''`/`[]` очищають). Незмінений slug не передається в `update`. `uuid` і `content_type` не чіпаються (модель їх і так блокує). Інші мови не зачіпаються, бо `write()` замінює лише вказану мову.

Поле, якого форма не показала (наприклад, додане між відкриттям форми і збереженням), не надсилається й не змінюється: оновлюються тільки ключі з `values`.

### 6. Видалення

`$node->delete()` у контролері, без нового сервісу: значення прибирає каскад БД ([content-model.md](../../../docs/content-model.md)). Редірект на список типу з `success`. Назва вузла в діалозі — з рядка таблиці (`title`), запасний варіант — `slug`.

### 7. Навігація й компоненти

- `ContentTypeNav`: `active` розширюється значенням `'nodes'`; пункт «Вузли» додається лише при `section.kind === 'content'`.
- `ContentTypeRowActions`: у меню «⋮» пункт «Вузли» між «Поля» і «Видалити» лише для `content`; колонка `nodes_count` у `Pages/Admin/ContentTypes/Index.tsx` отримує `cell` із `Link` лише для `content`.
- `navigation.ts`: `patternTitles` для `/admin/content-types/{key}/nodes` («Nodes»), `/nodes/create` («Create node»), `/nodes/{uuid}/edit` («Edit node»); перший сегмент `content-types` дає проміжний пункт «Content Types» (за `isActive`).
- Нові файли: `Pages/Admin/ContentNodes/{Index,Create,Edit}.tsx`, `Components/Admin/ContentNodes/{ContentNodeForm,NodeFieldInput,NodeFieldList,ContentNodeRowActions}.tsx`.
- Переклади: рядки в `lang/uk.json` і `lang/en.json`.

## Risks / Trade-offs

- [Застарілі ключі в `settings.list.visible` після видалення поля → `ContentNodeQuery` кидає `ValidationException`] → контролер перетинає `visible`/`search`/`sort` з наявними полями, а `title` додає завжди; покрити Feature-тестом.
- [Назва вузла для діалогу — це значення `title`, яке не обов'язково серед видимих полів] → `title` завжди входить у `$visible` (перша колонка).
- [Зміна slug ламає зовнішні посилання на нього] → у специфікації це допустима явна дія; `uuid` лишається стабільним. Попередження в UI не додаємо (відкладено).
- [Булеве поле без значення неможливо відрізнити від «Ні» у формі з прапорцем] → неочищений прапорець зберігається як «Ні». Допустимо для першої версії; потреба в трьох станах — окреме рішення.
- [Дата й час: часові пояси] → `datetime` зберігається як `Y-m-d H:i:s` без пояса ([DateTimeType]); форма передає значення «як є» без перетворення поясів. Поведінка збігається з бекендом, UI її не змінює.
- [Номер сторінки поза діапазоном → зайвий запит] → лише в крайньому випадку.

## Migration Plan

Міграцій БД немає: змін схеми не потрібно. Розгортання — звичайна збірка фронтенду (`npm run build`). Відкат — прибрати маршрути й сторінки; дані не зачіпаються.

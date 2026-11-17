# Design

## Context

Записи (`content_nodes`) мають `published_at` (NOT NULL), `is_active` і `ContentNode::scopeVisible()`: `is_active AND published_at <= now()`. Меню вже розширило цю модель дією «зняти за розкладом»: `menu_items.unpublished_at`, видимість на читанні, статус `expired` і перевірка інтервалу в моделі та запиті (`MenuItem::status()`, `scopeVisible()`, `StoreMenuItemRequest::withValidator`). Мотивацію див. у [proposal.md](proposal.md), вимоги — у [specs/](specs/).

Де сьогодні живе логіка стану запису: `ContentNodeController::status()` (PHP, для таблиці), `nodeStatus()` у `resources/js/Components/Admin/ContentNodes/status.ts` (клієнт, для бейджа форми до збереження) і `NodeStatusBadge` у `NodeServicePanel.tsx`. Форма й панель «Деталі» спільні для записів контенту, сторінок і блоків. Описують компоненти: `docs/content-model.md` (службові атрибути), `docs/content-querying.md` (видимість), `docs/admin-content-nodes.md` (таблиця, контракт форми, блок «Деталі»), `docs/menus.md` (перший застосунок моделі).

## Goals / Non-Goals

**Goals:**
- Та сама модель видимості, що в меню: інтервал `[published_at, unpublished_at)`, обчислення на читанні, без cron, черг і подій.
- Нуль змін для наявних записів і для коду, що не знає про нову дату: `unpublished_at = NULL` означає «без обмеження».
- Один обчислювач стану на сервері (замість копії в контролері) і дзеркало в клієнті для бейджа до збереження.

**Non-Goals:**
- Дата зняття для самих типів контенту (`content_types`): за уточненням власника зміна стосується записів.
- Зарезервований ключ `unpublished_at` для користувацьких полів: це не колонка таблиці записів у списку, тож `ContentField::RESERVED_KEYS` не чіпаємо.
- Колонка й сортування за `unpublished_at` у таблиці; фільтр за станом.
- Зробити `published_at` необов'язковою (як у меню): це змінило б наявну поведінку записів і не потрібно для зняття.
- Спільна абстракція «розклад видимості» для `ContentNode` і `MenuItem`: два вживання не виправдовують трейт.

## Decisions

### 1. Колонка `content_nodes.unpublished_at`, nullable, без індексу

Нова міграція додає `dateTime('unpublished_at')->nullable()` після `published_at`; бекфілу немає (наявні записи лишаються без дати зняття), відкат видаляє колонку. Індекс `(content_type, published_at)` лишається: умова на `unpublished_at` — це `IS NULL OR > now`, вона відсікає мало рядків і працює поверх уже вибраного діапазону, тож додатковий індекс не потрібен, доки немає вимірюваної проблеми.

**Альтернативи:** окрема таблиця розкладів (навіть для багатьох інтервалів) — надмірно для однієї пари дат; `status`-колонка, яку перемикає планувальник — стан розходиться з розкладом при збоях і стирає намір редактора (те саме відхилено в меню).

### 2. Видимість і стан — на читанні, за зразком `MenuItem`

```
visible(node) = is_active
                AND published_at <= now
                AND (unpublished_at IS NULL OR unpublished_at > now)

status(node)  = disabled   якщо !is_active
                scheduled  якщо published_at > now
                expired    якщо unpublished_at IS NOT NULL AND unpublished_at <= now
                published  інакше
```

`ContentNode::scopeVisible()` додає третю умову (`orWhere`-група, як у `MenuItem::scopeVisible()`), тож `ContentNodeQuery` (режим «лише видимі», лічильник), `ContentNode::findBySlug(onlyVisible: true)` та інші споживачі скоупу отримують нову поведінку без змін свого коду. Межа: рівно в момент `unpublished_at` запис невидимий (`> now`, строго), а в момент `published_at` уже видимий (`<= now`): інтервал напіввідкритий і без перекриття. Додається `ContentNode::status(?CarbonInterface $now = null)`, що повертає `disabled | scheduled | expired | published`; параметр `now` робить метод детермінованим у тестах, як у `MenuItem`. `ContentNodeController::status()` стає тонкою обгорткою над ним (або видаляється, а контролер викликає модель): правило стану живе в одному місці PHP.

### 3. Інтервал перевіряється в трьох місцях, арбітр — модель

- `ContentNode::assertValid()` (хук `saving`, остання межа для коду й імпорту): коли обидві дати задані, `unpublished_at` має бути пізніше за `published_at`, інакше `ValidationException` для ключа `unpublished_at`.
- `StoreContentNodeRequest` і `UpdateContentNodeRequest`: `unpublished_at` — `nullable|date_format:Y-m-d\TH:i` (той самий `PUBLISHED_AT_FORMAT`, UTC без перетворення) і перевірка порядку в `withValidator` (після базових правил), щоб помилка стояла біля поля. Для створення порожня `published_at` означає «зараз»: порівняння ведеться з поточною хвилиною, тож дата зняття в минулому без дати публікації відхиляється (сценарій у специфікації).
- `ContentNodeCreator::create()` отримує параметр `?CarbonInterface $unpublishedAt = null` (передається в `new ContentNode([...])`); перевірку виконує модель на `save()`.

Порівняння ведеться за хвилиною (`startOfMinute()`), як у `StoreMenuItemRequest`, бо форма працює з точністю до хвилини.

**Альтернатива:** лише правило `after:published_at` у запиті — не захищає код і імпорт та не працює, коли дата публікації підставляється сервером; відхилено.

### 4. Контракт форми й контролера

`ContentNodeController` додає `unpublished_at` у `SERVICE_KEYS`, у пропси `node` сторінок `Create` (`null`) і `Edit` (`Y-m-d\TH:i` або `null`), у `store()` (`unpublishedAt: $this->publishedAt($request->validated('unpublished_at'))`; існуючий `publishedAt()` уже повертає `null` для порожнього значення) і в `update()`. У `update()` порожнє значення означає «очистити» (`null`), а відсутній ключ лишає значення як є (`$request->has('unpublished_at')`), так форма (яка надсилає ключ завжди) може очистити дату, а код/тест, що не знає про поле, її не стирає. Помилки службових атрибутів уже повертаються без префікса `values.`, тож ключ `unpublished_at` потрапляє біля поля без змін у `forForm()`.

### 5. Клієнт: дзеркало стану й нове поле

- `status.ts`: `NodeStatus` отримує `'expired'`; `nodeStatus(isActive, publishedAt, unpublishedAt, now)` зберігає порядок серверу (вимкнено, заплановано, завершено, опубліковано); некоректна або порожня `unpublishedAt` рахується як «без дати», а порожня `publishedAt` — як «зараз» (як зараз). Порівняння текстом `YYYY-MM-DDTHH:mm`, як у наявній функції.
- `NodeServicePanel.tsx`: рядок «Показувати до» (`datetime-local`, `id="unpublished_at"`, підпис — наявний ключ перекладу `Show until`) одразу після «Дати публікації»; `STATUS_LABEL` додає `expired: 'Expired'` (ключ уже є в `lang/*.json`); бейдж `expired` рендериться як `outline`, відмінність — `data-status="expired"`. Помилка показується тим самим `message('unpublished_at')`.
- `ContentNodeForm.tsx` і `types.ts`: `unpublished_at` у `useForm`, пропсах `node` та переліку ключів помилок, що запалюють червону крапку кнопки «Деталі» на вузьких екранах.
- `Pages/Admin/ContentNodes/Index.tsx`: нових колонок немає; `NodeStatus` розширюється, тож бейдж «Завершено» з'являється сам. Змін у `DataTable` нема.

Новий переклад не потрібен: підписи `Show until` і `Expired` вже є в `lang/en.json` і `lang/uk.json`. Це перевіряється задачею, а не припускається.

### 6. Документація й e2e

Службові атрибути, видимість і контракт форми описують `docs/content-model.md`, `docs/content-querying.md`, `docs/admin-content-nodes.md`; `docs/menus.md` лишається джерелом опису меню (посилання на нього не потрібне, лише коротка примітка, що логіка збігається, у `content-model.md`). Спільна форма записів покриває три розділи, тому, крім `admin-content-nodes`, перевіряються `admin-page-types` і `admin-block-types` за таблицею «Спільний код → спеки» (`docs/e2e-testing.md`, рядок про форму записів); нових спеків і нових рядків таблиць не потрібно, лише оновлення опису блоку «службова інформація» спеку записів.

## Risks / Trade-offs

- [Запис невидимий для сайту, поки редактор не помітить, що минула дата зняття] → стан «Завершено» видно в таблиці записів і в бейджі форми; поле «Показувати до» стоїть поруч із датою публікації.
- [Зміна `scopeVisible()` зачіпає всіх споживачів скоупу] → умова додається лише відсіканням (`unpublished_at` NULL або в майбутньому), тож наявні записи з `NULL` видимі як раніше; покривається тестами `ContentNodeTest` і `ContentNodeQueryTest`, включно з межею в момент `unpublished_at`.
- [Розбіжність серверного й клієнтського обчислення стану] → обидва в одному порядку перевірок; сервер — арбітр, клієнт показує лише попередній бейдж до збереження (так само, як зараз).
- [Час без пояса: UTC, як `published_at`] → поведінка збігається з наявною; редактор бачить час у тому ж вигляді.
- [Дублювання логіки розкладу з `MenuItem`] → навмисно (див. Non-Goals); третє вживання стане підставою для спільної абстракції.

## Migration Plan

1. Нова міграція додає nullable-колонку; даних для перенесення немає, `php artisan migrate` швидка (додавання nullable-колонки).
2. Розгортання: `migrate`, звичайна збірка фронтенду (`npm run build`). Нових змінних середовища й залежностей немає.
3. Відкат: `migrate:rollback` видаляє колонку разом із датами зняття; записи лишаються й знову видимі за `is_active` та `published_at`.

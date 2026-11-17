# Design

## Context

Модель `ContentType` (`key`, `name`, `description`, `kind`, `parent_type`) і зв'язки `fields()` / `nodes()` описано в [docs/content-model.md](../../../docs/content-model.md). Таблиця даних ([docs/admin-data-table.md](../../../docs/admin-data-table.md)): сервер парсить адресу через `TableRequest`, а `TablePage` будує проп `table`; `DataTable` рендерить його. Поточний `DataTable` (`DataTable.tsx`, `DataTableRow.tsx`, `DataTableToolbar.tsx`) не має ні колонки дій рядка, ні слота в панелі інструментів. Навігація — `resources/js/components/Admin/navigation.ts` (пункт `Content Types` зараз `mock: true`), див. [docs/admin-shell.md](../../../docs/admin-shell.md). Мотивацію див. у proposal.md.

## Goals / Non-Goals

**Goals:**
- Серверна таблиця типів на наявних `TableRequest`/`TablePage` без зміни їхнього контракту.
- Загальні, не прив'язані до домену розширення `DataTable`: дії рядка й слот панелі інструментів, придатні для всіх майбутніх розділів.
- Лічильники без N+1.

**Non-Goals:**
- Форми створення/редагування, сторінка полів, видалення типів, дерево за `parent_type` (окремі зміни; тут лише неактивний каркас).
- Нова авторизація: доступ як у решти адмінки (`auth` + `verified`).
- Зміни схеми БД, масові дії, вибір рядків.

## Decisions

1. **Серверний режим, дані через `TablePage::fromCollection()`.** Таблиця працює в `mode="server"`: усе виконує сервер за адресою. Джерело — один запит `ContentType::withCount(['fields','nodes'])->get()`, перетворений на рядки; типів десятки, тому наявна, протестована логіка колекцій (однакова з клієнтським рушієм) краща за дублювання операторів у SQL. Альтернатива — `TablePage::make()` з Eloquent-запитом: потребує окремих реалізацій операторів і сортування за лічильниками в SQL. Контракт сторінки від вибору не залежить, тож перехід на `make()` можливий пізніше.
2. **Типове сортування — у контролері.** Без `sort` `TableRequest` не сортує, тому рядки заздалегідь впорядковуються за назвою (сортування `fromCollection` стабільне); явний `sort` з адреси перебиває його.
3. **Колонки.** `name`, `key`, `description` — `text`; `kind` — `select` з опціями з `config('content.kinds')`; `parent_type` — `text`, `searchable: false`; `fields_count`, `nodes_count` — `number`. Усі, крім опису, сортовані; усі фільтровані. Ключі рядків — snake_case, як атрибути моделі. Опис `defaultVisible: false`; `name`, `key` — `hideable: false`.
4. **Дії рядка як необов'язковий проп `rowActions?: (row) => ReactNode` у `DataTableBaseProps`.** `DataTableView` додає останню колонку (порожній заголовок з `sr-only` підписом «Actions»), а `DataTableRow` рендерить у ній результат `rowActions(row)`. Колонка не є `Column`, тому не потрапляє у видимість, пошук, фільтри й сортування. `colSpan` порожнього стану враховує її. Альтернатива — `actions` усередині `Column.cell`: порушила б вибір колонок (її можна приховати) і дублювала б розкладку.
5. **Слот панелі інструментів: проп `toolbarActions?: ReactNode`** передається в `DataTableToolbar` і рендериться наприкінці ряду (`ml-auto`, переноситься на вузьких екранах). Альтернатива — сторінка поза таблицею: не вирівнялась би з пошуком/фільтрами й дублювала б flex-розкладку.
6. **Меню рядка — компонент `ContentTypeRowActions`** (`resources/js/components/Admin/ContentTypes/`) на `DropdownMenu` shadcn: тригер — `Button variant="ghost" size="icon"` з іконкою `MoreHorizontal` і `aria-label` «Actions»; пункти Edit / Fields / Delete (`variant="destructive"` для видалення, `DropdownMenuSeparator` перед ним). **Каркас:** пункти й кнопка «Створити» рендеряться з `disabled`; опис дій зібрано в одному місці (масив `{ key, label, icon, destructive }`), тож підключення маршрутів і діалогу видалення — зміна в одному файлі.
7. **Маршрут і контролер.** `GET /admin/content-types` → `Admin\ContentTypeController@index` (ресурсне ім'я, щоб `create/store/edit/destroy` лягли поряд), ім'я `admin.content-types.index`, у групі `auth`+`verified`. Сторінка `Admin/ContentTypes/Index.tsx` з `AdminLayout`, `tableId="content-types"`.
8. **Навігація.** У `navigation.ts` прибрати `mock: true` у пункту `Content Types`; `isActive` і `getBreadcrumbs` працюють без змін.
9. **Демонстрація.** Демо-сторінка `DataTableDemo` отримує `rowActions` і `toolbarActions` в серверній і клієнтській таблицях, щоб e2e таблиці даних покривали розширення незалежно від домену.

## Risks / Trade-offs

- [Усі типи завантажуються в пам'ять на кожен запит] → прийнятно для очікуваного обсягу; шлях міграції на `TablePage::make()` описано вище.
- [`fields_count` включає системне поле `title`] → показуємо реальну кількість, зафіксовано в специфікації.
- [Неактивні пункти можуть вводити в оману] → стандартний disabled-стиль shadcn і `aria-disabled`; неактивність зафіксована в специфікації й знімається наступними змінами.
- [Зміна спільного `DataTable` може зачепити наявні сторінки] → нові props необов'язкові; наявні e2e й Feature-тести таблиці мають лишитися зеленими.
- [Нові ключі перекладу можна забути] → завдання з перевіркою обох `lang/*.json` та e2e у двох мовах.

## Документація

`docs/admin-content-types.md` (новий), `docs/admin-data-table.md` (розділ «Дії рядка й панелі інструментів»), `docs/admin-shell.md`, індекс `docs/README.md`.

# Proposal

## Why

Адмінка керує типами контенту видів `content` і `page`, але типи виду `block` у ній недоступні: маршрути `/admin/content-types/**` і `/admin/page-types/**` для них відповідають 404, а в навігації немає розділу. Щоб описувати структуру блоків (повторно використовуваних наборів полів), адміністратору потрібні такі самі сторінки для `kind=block`.

## What Changes

- Новий розділ «Типи блоків» за адресою `/admin/block-types` з тими самими сторінками, що й для типів сторінок: список (серверна таблиця), створення, редагування, видалення типу, а також список, створення, редагування, видалення й впорядкування полів типу (`/admin/block-types/{key}/fields/**`).
- Відмінності від типів контенту такі ж, як у `page`: у формі та таблиці немає `slug` (модель забороняє slug для `block`); батьківським типом можна обрати лише тип виду `block`; `kind` завжди `block`.
- Горизонтальне меню розділу («Налаштування» / «Поля») працює і на сторінках типу блоку; посилання назад веде до списку типів блоків.
- Пункт «Типи блоків» у бічній навігації (після «Типи сторінок») та відповідні хлібні крихти.
- Сторінки `/admin/content-types/**` і `/admin/page-types/**` і далі відповідають 404 для типів `block`; вузли блоків (префікс slug `block-`, розміщення) лишаються поза цією зміною.

## Capabilities

### New Capabilities
- `admin-block-types`: сторінки адмінки для керування типами виду `block` та їхніми полями.

### Modified Capabilities
- `admin-shell`: у бічній навігації з'являється робочий пункт «Типи блоків».

## Impact

- Бекенд: `routes/web.php` (третя пара `block-types` => `block` у циклі реєстрації); трейт `Concerns\ResolvesTypeSection` (сегмент розділу для `block`); контролери й form requests вже залежать від виду з маршруту, тож змін логіки не потребують (перевірити коментарі/PHPDoc, що згадують лише `content`/`page`).
- Фронтенд: `Components/Admin/ContentTypes/section.ts` (тип `TypeSection`, `sectionLabels('block')`); `navigation.ts` (пункт меню, `extraTitles`/`patternTitles`); перевірка, що компоненти й сторінки не припускають лише `content`/`page`.
- Переклади `lang/uk.json`, `lang/en.json` («Block Types», «Create block type», «Edit block type», «Delete block type», «Block type sections», підтвердження видалення).
- Тести: `tests/Feature/Admin/BlockTypeTest.php`, e2e `e2e/admin-block-types.spec.js`.
- Docs: новий `docs/admin-block-types.md`; оновити `docs/admin-page-types.md` (згадка про спільний механізм), `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`, `docs/e2e-testing.md` (рядок таблиці «область → спек») і індекс `docs/README.md`.

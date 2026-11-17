# Proposal

## Why

Типи сторінок і типи блоків уже можна створювати та наповнювати полями, але створити з них самі записи (сторінки й блоки) неможливо: форма, список і збереження записів існують лише в розділі «Content Types». Без цього типи виду `page` і `block` залишаються порожніми описами структури.

## What Changes

- Розділи `/admin/page-types/{key}/nodes…` і `/admin/block-types/{key}/nodes…` отримують ті самі шість маршрутів записів (список, створення, збереження, редагування, оновлення, видалення), що й `/admin/content-types/{key}/nodes…`. Контролер, сторінки React, форми та віджети полів лишаються спільними; вид визначає маршрут.
- Запити до записів типу іншого виду (наприклад, ключ типу `block` у `/admin/page-types/**/nodes`) і далі відповідають 404.
- Горизонтальне меню розділу типу (`ContentTypeNav`) та меню дій рядка типу (`ContentTypeRowActions`) показують пункт «Записи» для всіх трьох видів; у таблиці типів сторінок і блоків кількість записів стає посиланням на їхній список, як у типів контенту.
- Хлібні крихти сторінок записів розділів «Page Types» і «Block Types» ведуть через відповідний пункт sidebar.
- Посилання на запис у медіатеці (`ContentNodeOwner`) будується для запису будь-якого виду, а не лише `content`.
- Правила slug на сервері лишаються як у моделі: для записів сторінок slug береться із заголовка, для блоків автоматично генерується з префіксом `block-`, а явний slug блоку без префікса відхиляється.
- Форма запису блоку показує префікс `block-` як незмінну частину поля slug (поле вводить лише решту), а надсилає завжди повний slug із префіксом, якщо користувач не вказав його явно; порожнє поле, як і раніше, дає згенерований slug.
- Тести: Feature-тести маршрутів записів для `page` і `block` (замість нинішніх очікувань 404), e2e-сценарії в `admin-page-types` та `admin-block-types`.

**Не входить:** дерево сторінок, публічна маршрутизація й рендер, розміщення блоків на сторінках, оновлення макетного пункту «Pages» у sidebar.

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `admin-content-nodes`: розділ записів перестає бути лише для виду `content`: записи доступні в розділах типів контенту, сторінок і блоків (вимога про 404 для `page`/`block` замінюється вимогою про 404 лише для типу іншого виду); список записів, меню розділу й хлібні крихти описано для трьох видів; посилання на запис у медіатеці працює для всіх видів.
- `admin-page-types`: меню розділу типу й меню дій рядка отримують пункт «Записи», кількість записів у таблиці — посилання.
- `admin-block-types`: те саме для типів блоків.

## Impact

- **Код:** `routes/web.php` (цикл маршрутів записів по трьох розділах), `app/Http/Controllers/Admin/ContentNodeController.php` (коментар класу; перевірка, що редіректи та `section` береться з маршруту), `app/Files/Owners/ContentNodeOwner.php` (URL за видом), `resources/js/Components/Admin/ContentNodes/NodeServicePanel.tsx` і `ContentNodeForm.tsx` (префікс slug), `resources/js/Components/Admin/ContentTypes/ContentTypeNav.tsx`, `ContentTypeRowActions.tsx`, `resources/js/Pages/Admin/ContentTypes/Index.tsx`, `resources/js/Components/Admin/navigation.ts` (шаблони крихт), за потреби переклади в `lang/en.json` і `lang/uk.json`.
- **Тести:** `tests/Feature/Admin/ContentNodeIndexTest.php`, `ContentNodeWriteTest.php`, `PageTypeTest.php`, `BlockTypeTest.php`, `MediaLibraryTest.php`; `e2e/admin-page-types.spec.js`, `e2e/admin-block-types.spec.js`.
- **Документація (docs/, українською):** оновити `admin-content-nodes.md`, `admin-page-types.md`, `admin-block-types.md`, `admin-content-types.md`, `admin-shell.md` (крихти), `media-library.md` (посилання власника), `content-model.md` (згадка, що поведінка видів «майбутня»), `e2e-testing.md` (таблиці «Область → спек» і «Спільний код → спеки»); індекс `docs/README.md` без нових файлів лишається чинним.
- Міграцій БД і нових залежностей немає.

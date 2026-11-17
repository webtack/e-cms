# Tasks

## 1. Примітиви та типи

- [x] 1.1 Додати через shadcn CLI на хості `table`, `select`, `popover`, `checkbox`, `badge`; перевірити, що `grep -rn "@/components" resources/js` порожній і `npm run build` проходить
- [x] 1.2 Створити `Components/Admin/DataTable/types.ts` (`Column`, `TableState`, `TableMeta`, `TablePageProps`, `FilterOperator`, проп `mode: 'server' | 'client'`, `getRowId`, `rowHandle`) і `params.ts` (розбір та серіалізація адреси: `q`, `filters[N]`, `sort`, `dir`, `page`, `per_page`; таблиця операторів за типом колонки); JSDoc англійською з `@example`; перевірити `npx tsc --noEmit` і приклади з `@example` у консолі браузера на демо-сторінці (група 4)

## 2. Серверний хелпер

- [x] 2.1 Реалізувати `App\Support\Table\TableRequest` (опис колонок, нормалізація пошуку, фільтрів, сортування, `page`, `per_page`; некоректне ігнорується і потрапляє в `ignored`) і `App\Support\Table\TablePage` (відповідь `rows/meta/query/ignored/operators`, обмеження сторінки за межами діапазону, застосування до колекції); PHPDoc з `@example`; перевірити тестами `tests/Unit/Support/Table/TableRequestTest.php` і `TablePageTest.php` (кожен оператор за типом, `between` з двома межами, невідома колонка, неприпустимий оператор, `per_page` понад максимум, сторінка за межами, порожній результат) через `docker compose exec app php artisan test --filter=Table`
- [x] 2.2 Створити `docs/admin-data-table.md` розділами «Контракт адреси», «Оператори за типами», «Серверний хелпер» і додати рядок до `docs/README.md`; перевірити, що імена параметрів і оператори в документі збігаються з тестами 2.1

## 3. Компонент DataTable

- [x] 3.1 Хук `useTableState` і `DataTable` із сортуванням (цикл asc → desc → none, `aria-sort`), пагінацією (`DataTablePagination`: навігація, підсумок, розмір сторінки, вимкнені межі), станом завантаження (`aria-busy`) та порожнім станом; `push` для сторінки/сортування, `replace` для пошуку; JSDoc з `@example`; перевірити `npm run build` і вручну на демо-сторінці (група 4)
- [x] 3.2 Панель інструментів: поле пошуку з debounce 300 мс, кнопка скидання, повідомлення про проігноровані параметри (`DataTableToolbar`); перевірити `npm run build` та сценарії пошуку на демо-сторінці
- [x] 3.3 Фільтри (`DataTableFilters`): додавання/видалення рядків колонка → оператор → значення, елемент введення за типом (текст, число, дата, так/ні, вибір), два поля для `between`, чіпи активних фільтрів, застосування лише повних фільтрів; перевірити `npm run build` та вручну на демо-сторінці всі типи колонок
- [x] 3.4 Видимість колонок (`DataTableColumnMenu` + `useColumnVisibility`): `localStorage` за `tableId` з `try/catch`, `defaultVisible`, `hideable: false`, приховані колонки залишаються доступними для фільтрів і сортування; перевірити `npm run build` і вручну (перезавантаження, дві таблиці, заблоковане сховище)
- [x] 3.5 Клієнтський режим: чиста функція `clientEngine.ts` (пошук, фільтри всіх операторів, сортування з порожніми в кінці й стабільністю, пагінація, `meta`), хук `useClientTableState`, вибір джерела за `mode` у `DataTable`; `getRowId`, необов'язковий слот `rowHandle` і окремий `DataTableRow` (точки розширення під drag-and-drop, без самого перетягування); JSDoc з `@example`; перевірити `npm run build` і вручну на клієнтській демо-таблиці (група 4)
- [x] 3.6 Переклади всіх рядків компонента в `lang/en.json` і `lang/uk.json` (із замінниками `:from`, `:to`, `:total`); перевірити `docker compose exec app php artisan test --filter=TranslationParity`
- [x] 3.7 Доповнити `docs/admin-data-table.md` розділами «Опис колонок», «Режими server і client» (орієнтир, коли який обирати), «Використання в сторінці» (приклад контролера й `.tsx`), «Видимість колонок», «Підготовка до drag-and-drop» (точки розширення, умови доступності перетягування, явне зауваження, що серверний режим його не підтримує); перевірити, що приклади з документа збігаються з кодом демо-сторінки

## 4. Демонстраційна сторінка та e2e

- [x] 4.1 Контролер `Admin\Ui\DataTableDemoController` (404 у production), маршрут `admin.ui.data-table`, ~42 детермінованих рядки в пам'яті з колонками всіх типів, сторінка `Pages/Admin/Ui/DataTableDemo.tsx` з двома таблицями — серверною (≈42 рядки) і клієнтською (≈8 рядків, на кшталт мов), різні `tableId`, без пункту в навігації; Feature-тест `tests/Feature/Admin/DataTableDemoTest.php` (гість → login, автентифікований → 200 і пропси, production → 404, сортування/фільтр/пошук через параметри, некоректні параметри не дають 500) через `docker compose exec app php artisan test --filter=DataTableDemo`
- [x] 4.2 Playwright `e2e/admin-data-table.spec.js`: пошук, фільтр (числовий, булевий, діапазон), цикл сортування з `aria-sort`, пагінація і розмір сторінки, відновлення стану після перезавантаження, приховування колонки зі збереженням, порожній стан, 375 px без горизонтальної прокрутки сторінки, українська локалізація; для клієнтської таблиці — відсутність мережевих запитів при зміні стану, однаковий результат із серверною на спільних даних, незалежність видимості колонок від серверної таблиці; перевірити `npm run test:e2e`
- [x] 4.3 Оновити `docs/e2e-testing.md` (новий файл сценаріїв) і `docs/admin-shell.md` (структура `Components/Admin/DataTable`); перевірити, що перелік файлів у документах збігається з каталогами

## 5. Наскрізна перевірка

- [x] 5.1 Повний прогін: `docker compose exec app php artisan test`, `docker compose exec app vendor/bin/pint --dirty --format agent`, `npm run build`, `npm run test:e2e` — усе зелене; якщо щось падає, виправити до завершення

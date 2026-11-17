# Tasks

## 1. DataTable: дії рядка й слот панелі інструментів

- [x] 1.1 У `types.ts` додати до `DataTableBaseProps` необов'язкові `rowActions?: (row) => ReactNode` та `toolbarActions?: ReactNode` з JSDoc англійською та `@example`; перевірити `npm run build` без помилок типів
- [x] 1.2 У `DataTable.tsx`/`DataTableRow.tsx` додати останню колонку дій (порожній заголовок із `sr-only` «Actions», не входить у видимість колонок, `colSpan` порожнього стану її враховує); перевірити, що без `rowActions` розмітка таблиці не змінилась (наявні e2e таблиці зелені)
- [x] 1.3 У `DataTableToolbar.tsx` вивести `toolbarActions` наприкінці ряду з переносом на вузьких екранах; перевірити візуально на 375px і десктопі
- [x] 1.4 Підключити `rowActions` і `toolbarActions` до `DataTableDemo` (обидві таблиці) і додати переклади; розширити `e2e/admin-data-table.spec.js` (меню «⋮» відкривається клавіатурою, колонки дій немає у виборі колонок, кнопка в панелі переживає зміну пошуку); `npm run test:e2e -- admin-data-table` проходить
- [x] 1.5 Оновити `docs/admin-data-table.md` (розділ про дії рядка й слот панелі, приклад коду); перевірити посилання

## 2. Бекенд: маршрут і контролер

- [x] 2.1 Додати `App\Http\Controllers\Admin\ContentTypeController::index` (`ContentType::withCount(['fields','nodes'])`, рядки за назвою, колонки за дизайном, `TablePage::fromCollection`, `Inertia::render('Admin/ContentTypes/Index')`) із PHPDoc англійською та `@example`
- [x] 2.2 Зареєструвати `GET /admin/content-types` (`admin.content-types.index`) у групі `auth`+`verified` у `routes/web.php`; перевірити `docker compose exec app php artisan route:list --name=content-types`
- [x] 2.3 Написати `tests/Feature/Admin/ContentTypeIndexTest.php`: редирект гостя, компонент і пропси, лічильники, порядок за назвою, пошук за ключем, фільтр за видом і за лічильником, сортування за лічильником, пагінація, ігнорований параметр, порожній стан; `docker compose exec app php artisan test --filter=ContentTypeIndexTest` проходить
- [x] 2.4 `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень

## 3. Сторінка, меню рядка, навігація

- [x] 3.1 Створити `resources/js/components/Admin/ContentTypes/ContentTypeRowActions.tsx` (`DropdownMenu`, тригер «⋮» з `aria-label`, пункти Edit/Fields/Delete неактивні, Delete деструктивний за роздільником) із JSDoc англійською; перевірити в браузері
- [x] 3.2 Створити `resources/js/pages/Admin/ContentTypes/Index.tsx` (`DataTable` серверний режим, `tableId="content-types"`, колонки за дизайном, `cell`/`options` видів через `t()`, неактивна кнопка «Створити» в `toolbarActions`, `rowActions`) із JSDoc; `npm run build` проходить
- [x] 3.3 Прибрати `mock: true` у пункту `Content Types` в `navigation.ts`; перевірити, що пункт — активне посилання, підсвічений на `/admin/content-types`, крихти показують «Content Types»
- [x] 3.4 Додати ключі перекладу (колонки, види, «Create», «Edit», «Fields», «Delete», «Actions») у `lang/uk.json` і `lang/en.json` в алфавітному порядку; перевірити сторінку українською
- [x] 3.5 Створити `docs/admin-content-types.md` (маршрут, колонки, джерело даних, каркас дій і як їх підключати), оновити `docs/admin-shell.md` та `docs/README.md`; перевірити посилання

## 4. E2E та інтеграційна перевірка

- [x] 4.1 Додати `e2e/admin-content-types.spec.js`: перехід через sidebar, пошук і фільтр оновлюють адресу, сортування, пагінація, видимість колонок, меню «⋮» з трьома пунктами, неактивна кнопка «Створити», uk/en; `npm run test:e2e -- admin-content-types` проходить
- [x] 4.2 Перевірити, що наявні e2e оболонки не вважають Content Types макетом; повний `npm run test:e2e` проходить
- [x] 4.3 Повний `docker compose exec app php artisan test` і `openspec validate add-content-types-table` без помилок

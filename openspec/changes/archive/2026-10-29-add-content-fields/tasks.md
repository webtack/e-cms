# Tasks

## 1. Бекенд: маршрути, контролер, валідація

- [x] 1.1 Додати в `routes/web.php` маршрути `admin.content-types.fields.{index,create,store,edit,update,destroy}` під `content-types/{contentType}` і `PUT …/fields/order` (оголосити перед `{field}`) і `Route::bind('field', …)` (пара тип + ключ, 404 для чужого/відсутнього ключа); перевірити `docker compose exec app php artisan route:list --path=fields`
- [x] 1.2 Створити `StoreContentFieldRequest` (з публічним статичним `sharedRules()`), `UpdateContentFieldRequest` (без `key`, `is_system` не приймається) і `ReorderContentFieldsRequest` (`order`: масив унікальних рядків; у `after`-хуку множина ключів дорівнює множині ключів полів типу) з PHPDoc англійською та `@example`; спільні правила: `name`, `type` (`Rule::in` ключів реєстру), три `boolean`, `weight` (`integer`, 0–65535)
- [x] 1.3 Створити `App\Http\Controllers\Admin\ContentFieldController`: `index` (рядки зі `has_values`, згруповані запити по таблицях значень), `create` (`typeOptions`, `defaultWeight`), `store`, `edit`, `update`, `destroy` (системне поле або поле з `has_values` → `back()` з `error`; інакше видалення й `success`), `reorder` (у транзакції `weight = індекс`, редірект назад); англійський PHPDoc з `@example` на класі й нетривіальних методах
- [x] 1.4 Додати переклади серверних повідомлень («Field created.», «Field updated.», «Field deleted.», «A system field cannot be deleted.», «Cannot delete a field that has values.», «Field order updated.») у `lang/uk.json` та `lang/en.json`
- [x] 1.5 Pest-тести в `tests/Feature/Admin/ContentField{Index,Create,Edit,Destroy}Test.php`: гість/неверифікований; список віддає поля за `weight`; 404 для `page`/`block` і чужого ключа поля; створення (успіх, дублікат ключа, формат ключа, незареєстрований тип, вага поза діапазоном, `is_system` ігнорується); редагування (зміна назви/ваги, ключ ігнорується, тип поля зі значеннями відхилено, тип без значень змінено, заборонені зміни `title`); видалення (звичайне; поле зі значеннями відхилено й значення лишаються; `title` відхилено); порядок (ваги стають 0…n-1, неповний набір / повтор / чужий ключ відхилено, 404 для `page`); `docker compose exec app php artisan test --filter=ContentField` проходить
- [x] 1.6 Створити `docs/admin-content-fields.md` (маршрути, відповідь `index`, правила валідації, обмеження системного поля й полів зі значеннями) українською з перехресними посиланнями на [docs/content-model.md](../../../docs/content-model.md) і [docs/content-values.md](../../../docs/content-values.md); додати рядок у `docs/README.md`; `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень

## 2. Фронтенд: список полів

- [x] 2.1 Створити `resources/js/Pages/Admin/ContentFields/Index.tsx`: заголовок із назвою типу, посилання назад до типів, `DataTable mode="client"` (без `filterable`) з колонками з design.md (п. 4), кнопка «Створити» → `/fields/create`; JSDoc англійською
- [x] 2.2 Створити `Components/Admin/ContentFields/ContentFieldRowActions.tsx` (меню «Редагувати»/«Видалити»; діалог видалення, що для `has_values` показує причину без кнопки підтвердження; «Видалити» неактивний для системного поля) за зразком `ContentTypeRowActions`; JSDoc з `@example`
- [x] 2.3 Активувати пункт «Поля» в `ContentTypeRowActions.tsx` (`Link` на `/admin/content-types/{key}/fields`), оновити його JSDoc
- [x] 2.4 Додати шаблони заголовків і крихт для `…/fields`, `…/fields/create`, `…/fields/*/edit` у `navigation.ts` (і оновити його JSDoc/`@example`); додати переклади UI в `lang/uk.json` і `lang/en.json`
- [x] 2.5 Оновити `docs/admin-content-fields.md` (сторінка списку, колонки, меню) і `docs/admin-content-types.md` (пункт «Поля» активний) з перехресними посиланнями; `npm run build` без помилок

## 3. Перетягування рядків (`reorder` у DataTable)

- [x] 3.1 Додати залежності `@dnd-kit/core` і `@dnd-kit/sortable` на хості (`npm install`), перевірити `npm run build`
- [x] 3.2 Додати в `DataTable` (типи `ClientProps` у `types.ts`/`DataTable.tsx`) необов'язковий проп `reorder` з `onReorder(orderedIds)`; з ним тіло таблиці обгорнуто в `DndContext`/`SortableContext` (миша й клавіатура), `DataTableRow` стає sortable і виводить ручку через `rowHandle`; ручки неактивні з підказкою, коли діє пошук/сортування або `meta.total > perPage`; без пропа розмітка не змінюється. JSDoc англійською з `@example`
- [x] 3.3 Підключити `reorder` у демо-таблицю клієнтського режиму (`DataTableDemo`) для перевірки; додати e2e-перевірки в `e2e/admin-data-table.spec.js` (перетягування, клавіатура, неактивні ручки при пошуку/сортуванні, відсутність ручок без пропа)
- [x] 3.4 Оновити `docs/admin-data-table.md` (проп `reorder`, слот `rowHandle`, умови активності, доступність) і перевірити посилання

## 4. Фронтенд: форми створення й редагування

- [x] 4.1 Додати shadcn `Checkbox` у `Components/ui`, якщо його ще немає
- [x] 4.2 Створити `Components/Admin/ContentFields/ContentFieldForm.tsx` (режими `create`/`edit`, блокування ключа, типу для системного поля/поля зі значеннями, «множинне»/«обов'язкове» для системного) і сторінки `Create.tsx`, `Edit.tsx` (біля ваги підказка про перетягування); JSDoc з `@example`
- [x] 4.3 Оновити `docs/admin-content-fields.md` (форма, блокування полів) і `docs/content-model.md` (посилання на адмін-сторінку полів); перевірити валідність посилань у `docs/README.md`

## 5. Порядок полів на сторінці

- [x] 5.1 У `Pages/Admin/ContentFields/Index.tsx` підключити `reorder`: локальна копія `fields`, оптимістичне оновлення, `router.put('/admin/content-types/{key}/fields/order', { order }, { preserveScroll: true, only: ['fields'] })`, відкіт і тост при `errors.order`
- [x] 5.2 Оновити `docs/admin-content-fields.md` (порядок: умови, ваги 0…n-1, відкіт при помилці)

## 6. Перевірка в браузері

- [x] 6.1 Додати `e2e/admin-content-fields.spec.js` (підготовка даних через tinker, як в `admin-content-types.spec.js`): відкриття з меню типу, відсутність кнопки фільтрів, клієнтський пошук, створення, редагування, видалення з діалогом, поле зі значеннями (діалог без підтвердження), `title` без видалення, перетягування зі збереженням після перезавантаження; `npm run test:e2e -- admin-content-fields` проходить
- [x] 6.2 Прогнати наявні тести (`docker compose exec app php artisan test`, `npm run test:e2e`) і Pint; оновити `docs/e2e-testing.md`, якщо з'явилися нові обмеження

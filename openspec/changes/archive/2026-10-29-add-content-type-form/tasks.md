# Tasks

## 1. Модель: slug типу

- [x] 1.1 Додати міграцію `add_slug_to_content_types_table` (`string('slug', 190)->nullable()->unique()` після `name`, `down()` знімає індекс і колонку); перевірити `docker compose exec app php artisan migrate` і `migrate:rollback --step=1` без помилок
- [x] 1.2 У `App\Models\ContentType` додати `slug` до `#[Fillable]`, `@property`, константу `SLUG_PATTERN`; у `assertValid()` — правила slug (nullable, формат, `max:190`, унікальність з виключенням себе, заборона для `kind !== 'content'`); додати `descendantKeys()` (ключі всіх нащадків) з PHPDoc англійською та `@example`
- [x] 1.3 Оновити `ContentTypeFactory` (`slug = null` за замовчуванням), щоб наявні тести лишилися зеленими
- [x] 1.4 Додати тести в `tests/Feature/Content/ContentTypeTest.php`: тип зі slug, без slug, дублікат (у т.ч. інший регістр), недопустимий формат, slug для `page` і `block`, зміна й очищення slug лишають `key`/`kind`, `descendantKeys()` на дереві з кількох рівнів; `docker compose exec app php artisan test --filter=ContentTypeTest` проходить
- [x] 1.5 Оновити `docs/content-model.md` (розділ про slug типу: формат, унікальність, лише `content`, порожній = немає публічного списку, `descendantKeys()`) і перевірити посилання; `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень

## 2. Список лише для content, опис під назвою

- [x] 2.1 У `ContentTypeController::index` застосувати `ofKind('content')`, прибрати `kind` з рядків і `columns()`, додати колонку `slug` (text, sortable) і службовий `children_count` у рядки; зробити всі колонки не `filterable`; пошук лишити по `name`, `description`, `slug`, `key` (`description` — серверна колонка без клієнтського відповідника); оновити PHPDoc-приклад
- [x] 2.2 Оновити `tests/Feature/Admin/ContentTypeIndexTest.php`: лише `content`, колонка `slug`, `children_count`, пошук за назвою, описом, slug і ключем (не за батьківським типом), будь-який фільтр в адресі (`kind`, `nodes_count`, `slug`) ігнорується; прибрати перевірки колонки/фільтра виду, фільтрів за лічильником і окремої колонки опису; `php artisan test --filter=ContentTypeIndexTest` проходить
- [x] 2.3 У `Pages/Admin/ContentTypes/Index.tsx` прибрати `kindLabels`, колонки `kind` і `description` та всі `filterable`, додати колонку `slug`, дати колонці `name` `cell` з описом другим рядком (`text-xs text-muted-foreground`, `line-clamp-2`, `title` з повним текстом; порожній опис — без рядка), оновити тип `ContentTypeRow`; `npm run build` проходить
- [x] 2.4 Додати до `DataTableBaseProps` необов'язковий проп `filterable?: boolean` (за замовчуванням `false`) з JSDoc англійською та `@example`; `DataTableToolbar` виводить `DataTableFilters` лише з ним; передати `filterable` усім наявним таблицям (обидві в `DataTableDemo`); `npm run build` і `npm run test:e2e -- admin-data-table` проходять без змін у наявних тестах, а на `/admin/content-types` кнопки фільтрів немає
- [x] 2.5 Оновити розділи «Колонки» й «Джерело даних» у `docs/admin-content-types.md` (лише `content`, опис під назвою, пошук за назвою/описом/slug/ключем, без фільтрів) і `docs/admin-data-table.md` (проп `filterable` таблиці, за замовчуванням вимкнений; приклад); перевірити посилання

## 3. Створення типу

- [x] 3.1 Додати `App\Http\Requests\Admin\StoreContentTypeRequest` (правила за design.md, п. 6; `kind` не приймається) зі спільними правилами для Store/Update і PHPDoc англійською та `@example`
- [x] 3.2 Додати `ContentTypeController::create` (передає `parentOptions` — типи `content` за назвою) і `store` (`ContentType::create([... , 'kind' => 'content'])`, redirect на список із `success`); зареєструвати `admin.content-types.create` і `admin.content-types.store` у групі `auth`+`verified`; перевірити `docker compose exec app php artisan route:list --name=content-types`
- [x] 3.3 Feature-тести `tests/Feature/Admin/ContentTypeCreateTest.php`: редирект гостя (GET і POST), пропси форми й склад `parentOptions` (без `page`/`block`), успішне створення (вид `content`, системне поле `title`, flash), мінімальне заповнення, обов'язкові поля, зайнятий/недопустимий ключ, зайнятий/недопустимий slug, батьківський тип чужого виду та неіснуючий, `kind` у запиті ігнорується; `php artisan test --filter=ContentTypeCreateTest` проходить
- [x] 3.4 Створити спільний `Components/Admin/ContentTypes/ContentTypeForm.tsx` (`useForm`, режими `create`/`edit`, поля назва/ключ/slug/батьківський тип/опис, помилки біля полів, «Зберегти» з `disabled` під час відправлення, «Скасувати»-посилання) і `Pages/Admin/ContentTypes/Create.tsx`, із JSDoc англійською та `@example`; `npm run build` проходить
- [x] 3.5 У `Index.tsx` зробити «Створити» активним посиланням (`Button asChild` + `Link`); у `navigation.ts` додати заголовок `/admin/content-types/create` («Create content type»); перевірити перехід і хлібні крихти в браузері
- [x] 3.6 Додати ключі перекладу (поля, підказки про формат ключа й slug, «Save», «Cancel», «Create content type», «Content type created.», «No parent type») у `lang/uk.json` і `lang/en.json` в алфавітному порядку; перевірити форму українською та англійською
- [x] 3.7 Додати в `docs/admin-content-types.md` розділ «Створення» (маршрути, поля, правила, `kind` завжди `content`); перевірити посилання

## 4. Редагування типу

- [x] 4.1 Додати `UpdateContentTypeRequest` (без `key`/`kind`; `slug` з `ignore($type)`; `parent_type` не дорівнює собі й не з `descendantKeys()`) із PHPDoc англійською
- [x] 4.2 Додати в контролер `edit` (404 для неіснуючого ключа й типів не `content`; `parentOptions` без себе й нащадків) і `update` (лише `name`, `slug`, `parent_type`, `description`; redirect на список із `success`); зареєструвати `admin.content-types.edit` / `.update` з binding за `key`; перевірити `route:list`
- [x] 4.3 Feature-тести `tests/Feature/Admin/ContentTypeEditTest.php`: гість, пропси форми й `parentOptions` без себе й нащадків, 404 для неіснуючого й `page`/`block`, успішна зміна, власний slug не конфліктує, slug іншого типу, очищення slug, `key`/`kind` у запиті ігноруються, цикл у батьківському типі; `php artisan test --filter=ContentTypeEditTest` проходить
- [x] 4.4 Створити `Pages/Admin/ContentTypes/Edit.tsx` на `ContentTypeForm` (ключ — `readOnly` із підказкою, `form.put`); у `navigation.ts`/`getBreadcrumbs` забезпечити крихту для `/admin/content-types/{key}/edit` (за потреби розширити функцію й її тест); `npm run build` проходить
- [x] 4.5 У `ContentTypeRowActions` передати `row`, зробити «Редагувати» посиланням на `edit` (пункт «Поля» лишити `disabled`); додати переклади (`Edit content type`, `The key cannot be changed.`, `Content type updated.`) в обидва `lang/*.json`; перевірити в браузері
- [x] 4.6 Додати розділ «Редагування» в `docs/admin-content-types.md` (маршрути, що можна змінювати, виключення нащадків); перевірити посилання

## 5. Видалення типу

- [x] 5.1 Додати shadcn `alert-dialog` (`Components/ui/alert-dialog.tsx` у стилі наявних ui-компонентів, на `radix-ui`); `npm run build` проходить
- [x] 5.2 Додати `ContentTypeController::destroy` (404 для не-`content`; заборона за наявності вузлів або дочірніх типів → `back()->with('error', ...)` без змін; інакше `delete()` і redirect на список із `success`); зареєструвати `admin.content-types.destroy`; перевірити `route:list`
- [x] 5.3 Feature-тести `tests/Feature/Admin/ContentTypeDestroyTest.php`: гість, успішне видалення (типу й його полів немає), тип із вузлами, тип із дочірніми, 404 для `page`/`block` і неіснуючого; `php artisan test --filter=ContentTypeDestroyTest` проходить
- [x] 5.4 У `ContentTypeRowActions` підключити діалог видалення (стан у батьківському компоненті; причина заборони з `nodes_count`/`children_count`; підтвердження через `router.delete`); додати переклади діалогу, причин і повідомлень (`Content type deleted.`, `Cannot delete a content type that has nodes.`, `Cannot delete a content type that has child types.`) в обидва `lang/*.json`; перевірити в браузері
- [x] 5.5 Додати розділ «Видалення» в `docs/admin-content-types.md` (правила заборони, діалог, каскад полів) і оновити розділ про дії рядка (замість «каркасу»); перевірити посилання

## 6. E2E та інтеграційна перевірка

- [x] 6.1 Оновити `e2e/admin-content-types.spec.js`: прибрати перевірки колонок виду й опису, фільтрів і неактивних «Створити»/«Редагувати»/«Видалити»; додати опис під назвою, відсутність кнопки фільтрів, пошук за назвою/описом/slug/ключем, створення зі slug (тип з'являється в списку), помилки валідації (порожня форма, дубль ключа/slug), редагування (ключ readOnly, зміна назви й slug), видалення з діалогом (скасування, успіх, заборона для типу з вузлами й дітьми), uk/en, вузький екран; `npm run test:e2e -- admin-content-types` проходить
- [x] 6.2 Повний `npm run test:e2e` і `docker compose exec app php artisan test` проходять; `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень; за потреби оновити `docs/e2e-testing.md`
- [x] 6.3 `openspec validate add-content-type-form` без помилок

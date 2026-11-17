# Design

## Context

Модель `ContentType` валідує себе в `saving` (`assertValid`): формат `key`, `kind` з `config('content.kinds')`, незмінність `key`/`kind`, правила `parent_type` (той самий вид, без циклів) і `settings.list`; при створенні додає системне поле `title` (див. [docs/content-model.md](../../../docs/content-model.md)). У БД поля й вузли типу видаляються каскадом, дочірні типи — `restrictOnDelete`. Сторінка списку — `Admin\ContentTypeController::index` на `TablePage::fromCollection()` ([docs/admin-content-types.md](../../../docs/admin-content-types.md), [docs/admin-data-table.md](../../../docs/admin-data-table.md)); рядки віддаються цілком, тож у них можуть бути ключі поза колонками. `Column` має необов'язковий `cell`. Меню рядка — `ContentTypeRowActions` (усі пункти `disabled`). Мотивацію див. у proposal.md.

Спільне в інтерфейсі: Inertia (`useForm`, `router`), shadcn `Input`/`Label`/`Select`/`Button`, тости з `flash.success`/`flash.error` (`FlashToaster`), переклади `t()` з `lang/*.json`. `FormRequest`-ів у проєкті ще немає; діалогового компонента в `Components/ui` немає, `radix-ui` у залежностях є.

## Goals / Non-Goals

**Goals:**
- Робочі створення, редагування й видалення типів виду `content` з валідацією на сервері та помилками біля полів.
- Збереження публічного `slug` типу (лише `content`) з гарантіями формату й унікальності в моделі.
- Список лише `content`, без колонок виду й опису; опис — під назвою.

**Non-Goals:**
- Сторінка полів, форми для `page`/`block`, публічний маршрут сайту за `slug`, резервування службових шляхів.
- Зміна `key`/`kind`, каскадне видалення вузлів з адмінки, зміна контракту `DataTable`/`TablePage`.

## Decisions

1. **Валідація slug — у моделі.** `assertValid()` отримує правила `slug`: `nullable`, `regex:SLUG_PATTERN` (`/^[a-z0-9]+(-[a-z0-9]+)*$/`), `max:190`, `unique` по `content_types.slug` з виключенням самого запису, і заборона slug для `kind !== 'content'`. Правило діє для сидерів, тинкера й форм. Альтернатива — лише `FormRequest`: пропустила б інші шляхи створення.
2. **Унікальність без урахування регістру — через колацію.** Колонка `string(190)` з типовою ci-колацією MySQL; `NULL` дозволяє багато типів без slug. Формат вимагає малих літер, тож у cs-колації дублікатів за регістром і так не буде.
3. **Міграція** `add_slug_to_content_types_table`: `string('slug', 190)->nullable()->unique()` після `name`; наявні рядки отримують `NULL`; `down()` знімає індекс і колонку.
4. **Тип береться за ключем.** Параметр маршруту `{contentType:key}` (binding за `key`, а не `id`) і той самий `ContentTypeController`: `create`, `store`, `edit`, `update`, `destroy`. Типи виду, відмінного від `content`, у цих маршрутах дають 404: або `scopeBindings`-подібне обмеження через `Route::bind`, або явна `abort_unless($type->kind === 'content', 404)` в одному приватному методі `resolve()`. Імена: `admin.content-types.create|store|edit|update|destroy`.
5. **`kind` і `key` задає/береже сервер.** `store` викликає `ContentType::create([...validated, 'kind' => 'content'])`; `update` оновлює лише `name`, `slug`, `parent_type`, `description` (з `validated()`, без `key`/`kind`). Поля `kind` і (в `update`) `key` не входять у правила, тож значення з запиту відкидаються.
6. **`FormRequest`-и.** `StoreContentTypeRequest` (`name` required/max:255; `key` required/`KEY_PATTERN`/max:64/`unique`; `slug` nullable + правила п. 1; `parent_type` nullable + `Rule::exists('content_types','key')->where('kind','content')`; `description` nullable/string) і `UpdateContentTypeRequest` (без `key`; `slug` з `Rule::unique(...)->ignore($type)`; `parent_type` додатково не дорівнює ключу типу й не належить до його нащадків). Спільні правила — в одному місці (трейт чи статичний метод), щоб Store і Update не розходилися. Правила дублюють модельні навмисно: форма повертає помилки по полях, модель лишається останнім бар'єром. Список нащадків дає `ContentType::descendantKeys()`: допоміжний метод у моделі (обхід `children` у ширину), що також живить `parentOptions` у `edit`.
7. **Видалення: заборона для вузлів і дітей — на рівні контролера.** `destroy` перевіряє `$type->nodes()->exists() || $type->children()->exists()` і тоді повертає `back()->with('error', ...)` без змін; інакше `$type->delete()` (поля йдуть каскадом) і redirect на список із `success`. Модельна вимога каскаду (видалення типу видаляє його вузли) не змінюється: заборона діє на рівні адмінки, а не моделі, бо інші сценарії (сидери, майбутні операції) можуть свідомо видаляти тип разом з даними. Альтернатива — `deleting`-хук у моделі: змінила б спільну поведінку моделі, що виходить за межі цієї зміни.
8. **Діалог видалення.** Додати shadcn `alert-dialog` (`Components/ui/alert-dialog.tsx` на `radix-ui`). Пункт «Видалити» відкриває діалог з назвою типу. Рядки віддають `nodes_count` і новий службовий `children_count` (не колонка; `TablePage` рядки не обрізає). Якщо будь-який із них > 0, діалог показує причину й не має активної кнопки підтвердження; інакше підтвердження виконує `router.delete(route)`. Сервер перевіряє заборону самостійно (п. 7), тож діалог — лише зручність.
9. **Меню рядка.** `ContentTypeRowActions` отримує `row`; «Редагувати» — `DropdownMenuItem asChild` з Inertia `Link` на `edit`, «Видалити» відкриває діалог (стан у батьківському компоненті, щоб діалог не закривався разом із меню), «Поля» лишається `disabled`.
10. **Список лише `content`, без фільтрів.** `index` додає `->ofKind('content')`, прибирає `kind` з рядків і `columns()`, додає `slug` та `children_count` (службовий). Жодна колонка не `filterable`: `TableRequest` відхиляє фільтри з причиною `not_filterable`, тож фільтри в адресі (зокрема застарілий `kind`) ігноруються. Пошук `q` іде по `text`-колонках: `name`, `description`, `slug`, `key`; `description` лишається серверною колонкою без клієнтського відповідника, а `parent_type` та лічильники в пошуку не беруть участі (`searchable: false` / тип `number`).
11. **Опис під назвою.** Колонка `name` у `Index.tsx` отримує `cell`: назва, а під нею (якщо опис не порожній) `<div className="text-xs text-muted-foreground">` з описом, обрізаний до двох рядків (`line-clamp-2`), повний текст у `title`. Сортування колонки `name` лишається за назвою.
12. **Фільтри вмикає проп таблиці `filterable`.** Зараз `DataTableToolbar` завжди рендерить `DataTableFilters`. Додається необов'язковий проп `filterable?: boolean` у `DataTableBaseProps` (за замовчуванням `false`): лише з `filterable` тулбар виводить кнопку фільтрів, інакше її немає. Колонковий `filterable` лишається й визначає, за якими колонками можна фільтрувати. Це зміна поведінки за замовчуванням, тому наявні таблиці (демо `DataTableDemo`, серверна й клієнтська) отримують `filterable` явно, і їхня розмітка не змінюється; нова таблиця типів проп не передає. Альтернатива — автоприховування за відсутності фільтровних колонок: неявно й не дає вимкнути фільтри на таблиці, де колонки фільтровні.
13. **Форми.** Спільний компонент `ContentTypeForm` (`useForm`; режим `create`/`edit`) і сторінки `Create.tsx`, `Edit.tsx`. У режимі `edit` ключ — `Input` з `readOnly` та підказкою про незмінність; `PUT` через `form.put`. `Select` батьківського типу з варіантом «без батьківського типу» (порожнє значення → `null`). Ключ з назви автоматично не генерується: його незмінність робить мовчазну підстановку ризикованою; поле має підказку про формат. Хлібні крихти: `extraTitles` у `navigation.ts` отримує заголовок для `/create`; для `/{key}/edit` крихта береться з патерну `/admin/content-types/*/edit` (перевірити, чи `getBreadcrumbs` це підтримує, інакше розширити).
14. **Документація.** Оновити [docs/admin-content-types.md](../../../docs/admin-content-types.md), [docs/content-model.md](../../../docs/content-model.md) і [docs/admin-data-table.md](../../../docs/admin-data-table.md); [docs/e2e-testing.md](../../../docs/e2e-testing.md) — якщо з'являться нові обмеження.

## Risks / Trade-offs

- [Slug типу може збігтися зі slug вузла чи службовим шляхом (`admin`, `login`)] → маршрутизації за slug ще немає; перевірка конфліктів і резервування — у зміні з публічним маршрутом (Non-Goal).
- [`key` не змінюється, помилку в ключі не виправити] → підказка біля поля; після помилки тип можна видалити й створити заново, якщо він порожній.
- [Видалення типу без вузлів усе одно знищує його поля] → діалог підтвердження називає тип; видалення з вузлами заборонено.
- [Заборона видалення лише в контролері: інші шляхи можуть каскадом видалити вузли] → це вже поведінка моделі за поточною специфікацією `content-model`; змінювати її тут не плануємо.
- [Зміна батька в `update` може дати цикл] → виключення себе й нащадків у формі, перевірка в `UpdateContentTypeRequest` і модельна перевірка циклів як останній бар'єр.
- [Дублювання правил у `FormRequest` і моделі] → спільні константи форматів, Feature-тести на обох шляхах.
- [Прибрання `kind` зі списку ховає наявні `page`/`block`] → очікувано; їхні сторінки — окремі зміни.

## Migration Plan

`docker compose exec app php artisan migrate`; відкат — `migrate:rollback --step=1`. Дані не змінюються, наявні типи отримують `slug = NULL`.

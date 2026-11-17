# Design

## Context

Адмінка — Inertia + React + TypeScript. Розділи типів будуються за одним шаблоном: контролер повертає `Inertia::render`, список — `DataTable` (серверний режим через `TableRequest`/`TablePage`, або клієнтський з `reorder`), форми — `useForm` з `FormActionBar`, меню в `navigation.ts` (одне джерело для sidebar і хлібних крихт), підписи — англійські ключі, переклади в `lang/uk.json`. Поле типу в адмінці створюється формою `ContentFieldForm`; значення запису вводить `NodeFieldInput`, єдине місце відображення типу значення на віджет; список записів форматує `ContentNodeController::cell()/text()`. Запис редагує лише базову мову `default`.

Ця зміна залежить від [add-taxonomy-core](../add-taxonomy-core/design.md): моделі `Vocabulary`/`Term`, сервіси `TermTree`, `TermWriter`, `TermUsage`, тип `term`. Вимоги — у [specs/](specs/); мотивація — у [proposal.md](proposal.md). Описують компоненти: новий `docs/admin-taxonomy.md`, а також `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/admin-shell.md`, `docs/taxonomy.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- Розділ «Таксономія» за тими самими патернами, що й типи/поля (без нових бібліотек).
- Інваріанти не дублюються в UI: контролери й запити перетворюють `ValidationException` ядра на помилки форми й повідомлення.

**Non-Goals:**
- Автокомпліт/створення терміна у формі запису; перетягування між батьками; публічні сторінки; права доступу.

## Decisions

### 1. Маршрути й прив'язка

```
/admin/taxonomy                              GET   vocabularies index      admin.taxonomy.index
/admin/taxonomy/create                       GET   form                    admin.taxonomy.create
/admin/taxonomy                              POST                          admin.taxonomy.store
/admin/taxonomy/{vocabulary}/edit            GET                           admin.taxonomy.edit
/admin/taxonomy/{vocabulary}                 PUT / DELETE                  admin.taxonomy.update / destroy
/admin/taxonomy/{vocabulary}/terms           GET   tree table              admin.taxonomy.terms.index
/admin/taxonomy/{vocabulary}/terms/create    GET                           admin.taxonomy.terms.create
/admin/taxonomy/{vocabulary}/terms           POST                          admin.taxonomy.terms.store
/admin/taxonomy/{vocabulary}/terms/order     PUT                           admin.taxonomy.terms.reorder
/admin/taxonomy/{vocabulary}/terms/{term}/edit | PUT | DELETE              ...edit / update / destroy
```

`Route::bind('vocabulary')` за `key` (єдиний ідентифікатор словника: `uuid` у словника немає, тож `key` стоїть в URL, у `settings.vocabulary` і в `Select` форми поля); `Route::bind('term')` за `uuid` лише в межах словника з маршруту (за прикладом `field`, який шукається за парою). `terms/order` оголошується перед `{term}`, як `fields/order`. Контролери `VocabularyController` і `TermController`, запити `Store/Update` для кожного й `ReorderTermsRequest`.

### 2. Помилки ядра → UX

Моделі й `TermWriter` кидають `ValidationException` з ключами полів; запити дублюють формат-правила, щоб форма мала помилки по полях (як `StoreContentFieldRequest`), а ядро лишається останнім бар'єром. Видалення (`destroy`) ловить `ValidationException` і повертає `back()` з flash-повідомленням про помилку (існуючий `FlashToaster`), для використаного терміна повідомлення складається з `TermUsage` (поле, тип, кількість).

### 3. Дерево в таблиці і порядок

Сторінка термінів віддає **усі** терміни словника рядками в порядку обходу дерева з `depth`, `children_count`, `usage_count` (з `TermUsage` ядра: один згрупований запит за `content_values_string` для полів типу `term`, для діалогу видалення), `uuid` і базовою назвою; `DataTable` `mode="client"` з `reorder`, назва з відступом за `depth` (клітинка `cell`). Дерево збирає `TermTree::tree`; рядки сплющує контролер.
**Перетягування змінює лише порядок.** Клієнт шле `order[]` — `uuid` усіх термінів у новому порядку рядків; сервер переписує `weight = позиція` (прямим `UPDATE`, інваріанти дерева від ваги не залежать) і вимагає рівно весь набір `uuid` словника. Оскільки братні сортуються за `weight`, їхній відносний порядок збігається з порядком рядків; рядок, скинутий у чужу гілку, лише змінює порядок серед братів після оновлення. Батька змінює форма.
**Альтернативи:** вкладене перетягування з перепідпорядкуванням (потрібен власний tree-віджет, `@dnd-kit` у проєкті лише плоский сортований список) — відкладено; кнопки «вгору/вниз» — незручно при багатьох термінах.

### 4. Перелік можливих батьків

Сервер рахує і передає форму `parentOptions: {uuid, name, depth}[]` у порядку дерева: усі терміни словника, крім (на редагуванні) самого терміна й його нащадків, і лише ті, для яких `depth(батька) + 1 + висота піддерева ≤ max_depth` (для нового терміна висота піддерева 0). Клієнт не рахує глибину сам.

### 5. Форма терміна і переклади

`TermForm`: `name`, `description`, `slug`, `parent_uuid`, `weight`, `image` (вибір або завантаження файлу профілю `image` тим самим механізмом, що й `NodeFieldInput` для поля `image`; сервер перевіряє правилом ядра й зберігає разом із зв'язком у `media_relations` через `TermWriter`) та, коли `ContentLanguages::isMultilingual()`, блок `translations[lang][name|description]` для кожної активної мови (`ContentLanguages::active()`; сервер передає `languages`). Збереження викликає `TermWriter` (ядро) у транзакції; порожній переклад видаляється. Вага за замовчуванням: найбільша вага серед братів + 1. Мова інтерфейсу адміністратора й мови контенту незалежні; мови контенту беруться лише з `ContentLanguages`.

### 6. Поле `term` у формі поля типу

`ContentFieldController` перестає ховати `term` (`admin_hidden_field_types` видаляється з `config/content.php` разом із фільтром), але `term` лишається в `typeOptions` лише для секції `content`; для `page` і `block` його прибрано, а `StoreContentFieldRequest`/`UpdateContentFieldRequest` відхиляють `type = term`, коли тип-власник не `content` (помилка біля `type`). Форми отримують `vocabularies: {key, name}[]`. `ContentFieldForm` показує `Select` словника для типу `term`, вибір блокується, коли `has_values`. `StoreContentFieldRequest::settingsRules` отримує гілку для `term`: `settings` — масив лише з `vocabulary`, `settings.vocabulary` обов'язковий і `exists:taxonomy_vocabularies,key`; для решти не-файлових типів лишається `prohibited`. `UpdateContentFieldRequest` використовує ті самі правила, а заборону зміни словника зі значеннями забезпечує ядро (`settings.vocabulary`).

### 7. Поле `term` у формі й списку запису

`PresentsFormFields::formFieldData` для `term` додає `settings.terms: {uuid, name, depth}[]` (дерево словника, базові назви) — один запит на поле. `NodeFieldInput` отримує гілку `term`: для немножинного поля `Select` з порожнім варіантом, для множинного — список прапорців з відступами (множинність і `max_items` вже обробляє `NodeFieldList`). Стан форми — рядок із `uuid`, сервер перевіряє його правилом типу `term` (формат UUID, термін існує в словнику поля). У списку `ContentNodeController::text` для `term` бере назву з карти `termNames` (один запит на сторінку за всіма `uuid`, як `fileNames`), `sortable()` повертає `false` для `term`.
**Обмеження:** список термінів передається цілком, без автокомпліту; для словників на тисячі термінів знадобиться окремий пошуковий віджет (поза зміною).

### 8. Навігація

`navigation.ts` отримує `{ title: 'Taxonomy', href: '/admin/taxonomy', icon: Tags }` між Block Types і Pages; `extraTitles` і `patternTitles` — «Create vocabulary», «Edit vocabulary», «Terms», «Create term», «Edit term». Українські переклади — у `lang/uk.json`.

## Risks / Trade-offs

- [Перетягування «чужої» гілки поводиться неочевидно] → тільки порядок змінюється, документувати в `docs/admin-taxonomy.md`, підказка в UI; батька міняє форма.
- [Великі словники важкі для форми запису і сторінки термінів] → пагінація клієнтської таблиці, вибір у формі запису без автокомпліту; ліміт задокументовано, автокомпліт — окрема зміна.
- [Клієнтське сортування таблиці ламає дерево] → `reorder` і так вимкнено при сортуванні/пошуку; показ дерева без сортування за замовчуванням.
- [Кілька спільних компонентів змінено (`ContentFieldForm`, `NodeFieldInput`, `navigation.ts`)] → запускаємо залежні e2e-спеки з таблиці «Спільний код → спеки», повний прогін не потрібен.
- [Гонки: термін видалено в іншій вкладці, поки відкрита форма запису] → сервер відхиляє неіснуючий термін помилкою біля поля.

## Migration Plan

Міграцій немає. Розгортання після `add-taxonomy-core`; відкат — прибрати маршрути/сторінки й повернути `admin_hidden_field_types => ['term']` (для page/block `term` відфільтровано за секцією окремо). Дані таксономії не зачіпаються.

## Open Questions

- Підписи й переклади нових фраз (точні тексти) — уточнюються під час реалізації, на специфікації не впливають.

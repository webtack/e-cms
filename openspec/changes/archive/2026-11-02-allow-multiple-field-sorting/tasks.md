# Tasks

## 1. Сортування за першим значенням у вибірці

- [x] 1.1 У `app/Content/ContentNodeQuery.php` прибрати з `applySort` помилку «Sorting by a multiple field is not supported» (`LEFT JOIN` рядка `weight = 0` не змінювати), додати другорядний порядок за `title` (`LEFT JOIN` рядка `weight = 0` системного поля `title` у вказаній мові, alias `title_value`; `(title_value.value IS NULL)`, `title_value.value ASC`, далі `content_nodes.id`) для явного сортування за полем і за `published_at`, без fallback на мову `default`; оновити докблок класу (рядок про сортування одного немножинного поля) англійською з `@example` множинного поля. Перевірка: `grep` не знаходить старого рядка помилки.
- [x] 1.2 Додати тести в `tests/Feature/Content/ContentNodeQueryTest.php` за сценаріями спеки `content-querying`: множинне поле (`asc` і `desc`, перше значення за вагою), фільтр не змінює ключ, запис без значень, відсутність дублікатів у вибірці й `total()`, сортування за полем `image`/`youtube` без помилки, другорядний порядок за `title` (рівні ключі, `ASC` за обох напрямків, запис без `title` у вказаній мові після тих, що його мають без fallback, однакові ключ і `title` — за `id`, те саме для `published_at`), незмінний порядок без явного сортування; замінити старий тест, що очікував помилку для множинного поля. Перевірка: `docker compose exec app php artisan test --filter=ContentNodeQueryTest` зелений.

## 2. Сортування в адмінці

- [x] 2.1 У `app/Http/Controllers/Admin/ContentNodeController.php` залишити метод `sortable()` і його виклики, але прибрати з нього умову `! $field->is_multiple` (лишається `! isFileField && type !== 'youtube'`); у `listSettings` замінити перевірку `! is_multiple` для `settings.list.sort` на `sortable()` поля. Оновити докблоки `listSettings` і `tableColumns` (несортованими лишаються файлові поля й `youtube`, а не множинні).
- [x] 2.2 Оновити тести в `tests/Feature/Admin/ContentNodeIndexTest.php`: колонка множинного поля має `sortable: true`, а колонки `image`, `file` і `youtube` — `sortable: false`, `sort=tags` впорядковує за першим значенням, `sort=slug` і `sort=image` ігноруються, `settings.list.sort` на множинне поле зберігається, а на `image` відкидається. Перевірка: `docker compose exec app php artisan test --filter=ContentNodeIndexTest` зелений.

## 3. Фільтри: оператор `in`

- [x] 3.1 У `ContentNodeQuery::filterValue` додати гілку `in` (непорожній масив, кожен елемент проходить правила типу й `prepare`; інакше `ValidationException` для `filters.N.value`), а у `valueCondition` — `whereIn("{table}.value", $значення)`; додати `in` в `operators()` типів `string`, `text`, `richtext`, `integer`, `decimal`, `date`, `datetime`; оновити докблоки (`FieldType::operators`, клас `ContentNodeQuery`).
- [x] 3.2 Додати тести в `tests/Feature/Content/ContentNodeQueryTest.php`: `in` для рядкового й числового поля, `in` для множинного поля (запис входить, якщо будь-яке значення в переліку), порожній масив і не масив → помилка, `in` для `boolean`/`youtube`/файлового поля → помилка оператора, кілька фільтрів по одному множинному полю виконуються різними значеннями (запис входить). Перевірка: `docker compose exec app php artisan test --filter=ContentNodeQueryTest` зелений.

## 4. Документація й e2e

- [x] 4.1 Оновити `docs/content-querying.md` (розділ «Сортування»: правило «перше значення», множинні поля, вибірка сортує за будь-яким типом (зокрема файловим і `youtube`, за збереженим рядком), наслідок для афіші, другорядний порядок за `title` за зростанням і далі за `id` (рядок 73) без fallback на `default`; розділ «Оператори за типами»: `in` і правило OR по значеннях), `docs/admin-content-nodes.md` (опис `columns.sortable` у рядку 33 — `false` для файлових полів і `youtube`, а не для множинних; `settings.list.sort` у рядку 43 — наявне поле з сортованою колонкою; розділ «Сортування» у рядку 64 — колонки полів, крім файлових і `youtube`, зокрема множинні; slug, стан, файлові поля й `youtube` не сортуються, такий `sort` ігнорується; за рівних ключів і для `published_at` порядок за `title`, далі за `id`); перевірити перехресні посилання й що `docs/README.md` не потребує змін.
- [x] 4.2 У `e2e/admin-content-nodes.spec.js` замінити перевірку `?sort=tags` (ігнорування множинного поля) сценарієм сортування за множинною колонкою `tags` за першим значенням, рівні значення за `title` та ігнорування `sort=slug` і `sort=image` (колонка файлового поля не сортується, сторінка відкривається); оновити опис спека в `docs/e2e-testing.md`, якщо змінюється перелік перевірок. Запустити лише `npm run test:e2e -- e2e/admin-content-nodes.spec.js` (за таблицею «Область → спек»).

## 5. Завершення

- [x] 5.1 Запустити `docker compose exec app vendor/bin/pint --dirty --format agent` і `docker compose exec app php artisan test`; у звіті назвати запущений e2e-спек (`admin-content-nodes`) і пропущені з причиною.

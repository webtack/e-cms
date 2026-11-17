# Tasks

## 1. Конфігурація й режим «усі» у вибірці

- [x] 1.1 Додати `content.max_all` (1000) у `config/content.php` і режим `per_page = 0` у `ContentNodeQuery::paginate()` (`limit(max_all)`, результат — один `LengthAwarePaginator`; `max_per_page` лише для `> 0`), оновити JSDoc/PHPDoc `NodeQueryParams`. Перевірка: нові тести в `tests/Feature/Content` (усі записи, стеля, звичайна кількість незмінна) — `docker compose exec app php artisan test --compact --filter=ContentNodeQuery`; у задачі є коментарі з `@example`.
- [x] 1.2 Оновити `docs/content-querying.md` (розділи про пагінацію/параметри: `per_page = 0`, `max_all`). Перевірка: посилання в документі валідні.

## 2. Модель і читання публічних налаштувань

- [x] 2.1 Додати `App\Content\PublicDisplaySettings` (`clean()`, `formOptions()`, `resolve()`): допустимі лише додані поля, `title` завжди, `page_fields` відсутній = усі, сортування за замовчуванням `published_at desc`, `pagination` ефективно «ні» при `per_page = 0`; PHPDoc англійською з `@example`. Перевірка: unit/feature-тести значень за замовчуванням, розгортання «усіх», відкидання застарілих ключів — `docker compose exec app php artisan test --compact --filter=PublicDisplaySettings`.
- [x] 2.2 Додати `ContentType::assertValidPublicSettings()` (поля типу, лише додані, сортовні, напрямок, `per_page` у `[0, max_per_page]`, обмеження за видом `content`) і викликати її там само, де `assertValidListSettings()`. Перевірка: тести `ContentType`/налаштувань типу на сценарії зі спеку content-model — `docker compose exec app php artisan test --compact --filter=ContentType`.
- [x] 2.3 Оновити `docs/content-model.md` (розділ «Налаштування типу (`settings`)»: ключ `public`, значення за замовчуванням, обмеження за видом) і `docs/content-querying.md` (читання публічних налаштувань). Перевірка: перехресні посилання працюють.

## 3. Збереження через форму типу

- [x] 3.1 Узагальнити `ContentTypeController::mergeListSettings()` до заміни одного ключа (`list`, `public`), додати `publicOptions` в `edit()`, розширити `StoreContentTypeRequest` (`array:list,public` і правила `settings.public.*`; `page_fields` відсутній, коли обрано «Усі»). Перевірка: Feature-тести збереження (сценарії admin-content-types, `list` не затирається, `page`/`block` відхиляють список-ключі) — `docker compose exec app php artisan test --compact --filter=ContentType`; `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 4. Секція «Публічне відображення» в адмінці

- [x] 4.1 Додати компонент `PublicDisplaySettingsSection` (перемикач «Усі додані поля» + чекбокси для сторінки, чекбокси для картки, сортування, кількість, перемикач пагінатора; для `page`/`block` лише поля сторінки; пагінатор вимкнений при `0`) і підключити в `ContentTypeForm` (режим `edit`) та `Edit.tsx`; JSDoc англійською з `@example`; ключі в `lang/uk.json` і `lang/en.json`. Перевірка: `npm run build` без помилок, секція видна у браузері.
- [x] 4.2 Додати сценарій до `e2e/admin-content-types.spec.js` (значення за замовчуванням, явний вибір полів сторінки, повернення до «Усі», `0` вимикає пагінатор, збереження й повернення стану, `page` без полів списку) і запустити лише його: `npm run test:e2e -- e2e/admin-content-types.spec.js`.
- [x] 4.3 Оновити `docs/admin-content-types.md` та опис спека в `docs/e2e-testing.md` (таблиці «область → спек» не змінюються, нового спека немає). Перевірка: опис збігається зі сценарієм.

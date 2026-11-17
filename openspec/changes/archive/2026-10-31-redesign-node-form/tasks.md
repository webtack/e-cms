# Tasks

## 1. Схема, модель та автор

- [x] 1.1 Додати міграцію `content_nodes`: `published_at` (datetime), `is_active` (boolean, default true), `user_id` (FK на `users`, `restrictOnDelete`), індекс `(content_type, published_at)`; бекфіл `published_at = created_at` та `user_id` адміністратором з `ADMIN_EMAIL` (як у `DatabaseSeeder`), явна помилка для непорожньої таблиці без адміністратора, пропуск бекфілу для порожньої; після бекфілу колонки `published_at` і `user_id` стають NOT NULL. Перевірка: `docker compose exec app php artisan migrate` та `migrate:rollback` проходять на БД з даними й на порожній, на БД із записами без адміністратора міграція падає з поясненням; JSDoc/PHPDoc англійською.
- [x] 1.2 Оновити `App\Models\ContentNode`: каст `published_at` (datetime), `is_active` (boolean), `published_at` у `#[Fillable]`, `user_id` — не масово призначуваний з HTTP, зв'язок `author()` (`BelongsTo User`), `scopeVisible()` і `findBySlug($slug, bool $onlyVisible = false)`; PHPDoc з `@example`. Перевірка: `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень.
- [x] 1.3 Оновити `ContentNodeFactory` (`user_id => User::factory()`, `published_at => now()`, `is_active => true`) і `ContentNodeCreator::create()` (параметри `?int $authorId`, `?CarbonInterface $publishedAt`, `bool $isActive`; автор за замовчуванням `auth()->id()`, без автора — `LogicException`; порожня дата → `now()`). Перевірка: оновлені наявні тести, що створюють записи, проходять (`docker compose exec app php artisan test --compact`).
- [x] 1.4 Додати тести моделі (`tests/Feature/Content/ContentNodeTest.php` та нові): значення за замовчуванням, автор з поточного користувача, помилка без автора, заборона видалення автора, `published_at` не змінюється при вимкненні, міграція бекфілу (з адміністратором і без). Перевірка: тести зелені.
- [x] 1.5 Задокументувати службові атрибути в `docs/content-model.md` (колонки, правила, міграція, `ADMIN_EMAIL`), з посиланням на `docs/admin-content-nodes.md`. Перевірка: посилання в docs відкриваються, `docs/README.md` чинний.

- [x] 1.6 Зарезервувати ключі полів: `ContentField::RESERVED_KEYS = ['uuid', 'slug', 'status', 'published_at']`; правило `not_in` у `StoreContentFieldRequest` (повідомлення про зарезервований ключ, переклад у `lang/uk.json` і `lang/en.json`) і перевірка в `ContentField::assertValid()` лише для нових полів (`! $this->exists`), щоб наявні поля не ламалися при оновленні. Перевірка: тести `ContentFieldTest`/`ContentFieldStoreTest` — створення з кожним із чотирьох ключів відхиляється, `views` проходить, оновлення назви наявного поля з ключем `status` проходить, `TranslationParityTest` зелений; перед міграцією виконати у `docker compose exec app php artisan tinker` запит по `content_fields.key` і зафіксувати результат у звіті.
- [x] 1.7 Описати резервування у `docs/content-model.md` і `docs/admin-content-fields.md` (перелік, причина, поведінка для наявних полів, як додати новий ключ). Перевірка: перелік у docs збігається з константою.

## 2. Видимість у вибірці

- [x] 2.1 Додати `onlyVisible` у `NodeQueryParams` і застосувати `scopeVisible()` у `ContentNodeQuery` до списку й лічильника; адмінка поводиться як раніше. Перевірка: тести в `tests/Feature/Content/ContentNodeQueryTest.php` — опублікований, відкладений, вимкнений, поєднання з фільтрами/пошуком/сортуванням, без режиму видно все.
- [x] 2.2 Тести `findBySlug(..., onlyVisible: true)` для вимкненого й відкладеного запису. Перевірка: тести зелені.
- [x] 2.3 Описати видимість у `docs/content-querying.md` (параметр, формула, приклад). Перевірка: приклад у docs виконується в `docker compose exec app php artisan tinker`.

## 3. Адмінка: збереження та передача даних

- [x] 3.1 Оновити `StoreContentNodeRequest`/`UpdateContentNodeRequest`: правила `is_active` (boolean), `published_at` (`date_format:Y-m-d\TH:i`; `nullable` у store, `required` в update), без `user_id`. Перевірка: тести запитів (некоректна дата, очищена дата в update, ігнорований `user_id`).
- [x] 3.2 Оновити `ContentNodeController`: передавати в `create`/`edit` об'єкт `node` з `slug`, `is_active`, `published_at`, `updated_at`, `author`; у `store` — службові атрибути й автор з `auth()`; у `update` — `is_active`, `published_at`, `touch()` після запису значень і метаданих файлів, `user_id` не змінюється; `forForm()` не додає префікса `values.` для службових помилок; `listSettings()` бере перелік зарезервованих ключів із `ContentField::RESERVED_KEYS` (див. 1.6), а власну константу контролера прибрати. Перевірка: Feature-тести `ContentNodeWriteTest` — відкладена публікація, вимкнення зберігає дату, автор незмінний у `PUT`, автор підміна ігнорується у `POST`, `updated_at` змінюється при зміні лише значення.
- [x] 3.3 Додати в таблицю записів колонки `status` (похідний стан) і `published_at` (сортування за датою, вторинно за `id`) у `row()`/`tableColumns()`/`index()`. Перевірка: `ContentNodeIndexTest` — три стани, сортування за датою, колонки поряд зі `settings.list.visible`, поле типу з ключем `status` чи `published_at` (створене до резервування) не дублює службову колонку.
- [x] 3.4 Оновити `docs/admin-content-nodes.md`: службові поля, пропси сторінок, правила збереження, колонки таблиці. Перевірка: розділи й посилання узгоджені з кодом.

## 4. Фронтенд: розкладка та службова панель

- [x] 4.1 Додати хук `useMediaQuery` (або узагальнити `use-mobile`) і функцію `nodeStatus()` з тестованим форматом `Y-m-d\TH:i`; JSDoc англійською з `@example`. Перевірка: `npm run build` без помилок типів.
- [x] 4.2 Створити компонент `NodeServicePanel` (перемикач «Показувати на сайті», бейдж, дата публікації, slug, «Оновлено», «Автор»; помилки всередині) і додати типи в `types.ts`. Перевірка: компонент рендериться у формі, `npm run build` без помилок.
- [x] 4.3 Переробити `ContentNodeForm`: дворядна сітка 70/30 від `lg`, sticky `aside`, службові поля в `useForm` (`is_active`, `published_at`), прибрати slug fieldset з основної колонки, один рендер панелі (aside на `lg`, `Sheet` нижче), липка нижня панель дій з кнопками «Службова інформація» (бейдж і крапка помилки) та «Зберегти», `pb-24` на вузьких. Оновити `Create.tsx`/`Edit.tsx` під нові пропси. Перевірка: `npm run build`, ручна перевірка на 1280 px і 390 px.
- [x] 4.4 Колонки стану й дати публікації в `Pages/Admin/ContentNodes/Index.tsx` (бейдж, сортування). Перевірка: сторінка списку показує бейджі та сортує за датою.
- [x] 4.5 Додати переклади в `lang/uk.json` і `lang/en.json`. Перевірка: `docker compose exec app php artisan test --compact --filter=TranslationParityTest`.
- [x] 4.6 Оновити e2e `e2e/admin-content-nodes.spec.js`: дворядна розкладка на десктопі, мобільна панель (кнопка, відкриття, значення переживають закриття, індикатор помилки slug, відсутність дубля `#slug`), створення з відкладеною датою й бейдж «Заплановано», вимкнення, дата створення відсутня; адаптувати наявні локатори slug до нового розташування. Запустити лише `npm run test:e2e -- e2e/admin-content-nodes.spec.js` (за потреби — `e2e/admin-data-table.spec.js`, якщо чіпали спільну таблицю); у `docs/e2e-testing.md` оновити таблиці «Область → спек» та «Спільний код → спеки», якщо з'явились нові споживачі. Перевірка: спеки проходять, у звіті названо запущені й пропущені спеки.
- [x] 4.7 Оновити `docs/admin-content-nodes.md` розділом про розкладку (десктоп/мобільні, компоненти `NodeServicePanel`, `useMediaQuery`, `nodeStatus`) і обґрунтуванням. Перевірка: опис збігається з реалізацією; `vendor/bin/pint --dirty --format agent` без зауважень.
- [x] 4.8 Виправити перекриття службової колонки полем `youtube`: додати `min-w-0` до `FRAME` у `ContentNodeForm.tsx` (`fieldset` розпирається нерозривним вмістом). Додати в `e2e/admin-content-nodes.spec.js` перевірку, що права межа картки YouTube не перевищує лівої межі `aside` при 1280 px; запустити лише `npm run test:e2e -- e2e/admin-content-nodes.spec.js`; оновити розділ про розкладку в `docs/admin-content-nodes.md`. Перевірка: спек зелений, `npm run build` без помилок.

## 5. Спільна липка панель дій форм

- [x] 5.1 Створити компонент `FormActionBar` у `resources/js/Components/Admin/` (липкий `bottom-0`, `-mx-4 md:-mx-6`, safe-area, «Скасувати» + «Зберегти», слот `children`); JSDoc англійською з `@example`. Перевірка: `npm run build` без помилок.
- [x] 5.2 Перевести `ContentNodeForm` на `FormActionBar`: прибрати кнопки з потоку й `hidden lg:inline-flex`, «Службова інформація» передавати в слот лише на `< lg`, панель поза сіткою колонок. Перевірка: ручна перевірка на 1280 px і 390 px.
- [x] 5.3 Застосувати `FormActionBar` у `ContentTypeForm` і `ContentFieldForm`. Перевірка: форми створення/редагування типу й поля мають липку панель.
- [x] 5.4 Оновити e2e `admin-content-nodes`, `admin-content-types`, `admin-content-fields`: панель видима без прокрутки на довгій формі, «Скасувати» повертає до списку, «Зберегти» працює. Запускати лише ці спеки.
- [x] 5.5 Оновити `docs/admin-content-nodes.md` та, за потреби, таблиці в `docs/e2e-testing.md` («Спільний код → спеки» для `FormActionBar`).
- [x] 5.6 Фінальна перевірка: `npm run build`, `openspec validate redesign-node-form`.

## 6. Доопрацювання за відгуком

- [x] 6.1 `ContentNodeForm`: кнопку параметрів в слоті `FormActionBar` зробити іконкою без тексту й без бейджа стану, з `aria-label`/`title` «Службова інформація»; індикатор помилки лишити. Перевірка: `npm run build`, ручна перевірка на 390 px.
- [x] 6.2 `ContentTypeForm` і `ContentFieldForm`: внутрішній `div` з `max-w-xl` обгорнути в `rounded-md border` (з внутрішнім відступом); панель дій лишається поза рамкою. Перевірка: `npm run build`.
- [x] 6.3 Додати невеликий відступ між вмістом форм (запис, тип, поле) і `FormActionBar`. Перевірка: форма не прилипає до панелі після прокрутки до кінця.
- [x] 6.4 Оновити e2e `admin-content-nodes`, `admin-content-types`, `admin-content-fields`: кнопку параметрів шукати за доступною назвою, прибрати перевірки бейджа на кнопці. Запускати лише ці три спеки.
- [x] 6.5 Оновити `docs/admin-content-nodes.md` і `docs/admin-shell.md` (кнопка-іконка, рамка форм типу й поля, відступ).
- [x] 6.6 Фінальна перевірка: `npm run build`, `openspec validate redesign-node-form`.

## 7. Доопрацювання за відгуком (2)

- [x] 7.1 Перейменувати «Службова інформація» на «Деталі» (en: «Details», той самий ключ `Details`, що й у медіатеці; його український переклад змінити з «Докладніше» на «Деталі», оновити e2e `admin-media` і `docs/media-library.md`): `NodeServicePanel`, `ContentNodeForm`, `lang/uk.json`, `lang/en.json` (прибрати ключ `Service information`). Перевірка: `TranslationParityTest`, `npm run build`.
- [x] 7.2 Множинне поле `image`/`file` при досягненні ліміту не показує зону завантаження (є пояснення про ліміт), після видалення елемента зона повертається; список відхилених файлів виводить `NodeFieldList` (віджет віддає його через `onFailures`), щоб він пережив зникнення зони. Перевірка: код `NodeFieldList` відповідає вимозі «Ліміт елементів множинного поля у формі запису».
- [x] 7.3 Оновити e2e `admin-content-nodes`: кнопку шукати за доступною назвою «Details», тест ліміту зображень (`galleryInput` на ліміті відсутній, а не `disabled`; після видалення повертається). Запускати лише цей спек.
- [x] 7.4 Оновити `docs/admin-content-nodes.md`, `docs/admin-shell.md` (назва блоку) і `docs/file-storage.md` (зона ховається на ліміті).
- [x] 7.5 Фінальна перевірка: `npm run build`, `openspec validate redesign-node-form`.

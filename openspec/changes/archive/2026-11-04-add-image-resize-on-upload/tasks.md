# Tasks

## 1. Залежності й конфігурація

- [x] 1.1 Виконати `docker compose exec app composer require intervention/image` і перевірити, що пакет з'явився в `composer.json`/`composer.lock`, а `docker compose exec app php -r 'echo class_exists(Intervention\Image\ImageManager::class) ? "ok" : "no";'` виводить `ok`; оскільки змінюються залежності `composer`, запустити повний `docker compose exec app php artisan test --compact` і зафіксувати результат
- [x] 1.2 Додати в `config/files.php` блок `image` (`driver`, `quality` для `jpeg` і `webp`, `max_pixels`) та `max_width`/`max_height` профілю `image`, усе через `env()` зі значеннями за замовчуванням (`FILES_IMAGE_MAX_WIDTH`/`FILES_IMAGE_MAX_HEIGHT` = 1920, `FILES_IMAGE_DRIVER` = `imagick`, `FILES_IMAGE_JPEG_QUALITY`/`FILES_IMAGE_WEBP_QUALITY` = 85, `FILES_IMAGE_MAX_PIXELS` = 100000000, а також `FILES_IMAGE_MAX_SIZE` = 5120 і `FILES_DOCUMENT_MAX_SIZE` = 20480 КБ для `max_size` профілів `image` і `document`), з коментарями англійською; додати ці змінні в `.env.example`; оновити phpstan-тип `Profile` у `FileProfiles`. Перевірити: `docker compose exec app php artisan test --compact --filter=FileProfilesTest` проходить, `FileProfiles::get('image')` повертає нові ключі, `FILES_IMAGE_MAX_WIDTH=null` дає `max_width === null`, а `FILES_IMAGE_MAX_SIZE=1024` змінює `max_size` профілю `image`
- [x] 1.3 Оновити `docs/file-storage.md` (таблиця «Профілі»: `max_width`, `max_height`; блок налаштувань зображень `files.image`; таблиця змінних `.env` `FILES_IMAGE_*`) і перевірити, що ключі в документі збігаються з `config/files.php`

## 2. Обробка зображення

- [x] 2.1 Звірити з документацією `intervention/image` 4.x (Context7) методи орієнтації за EXIF, безпечного зменшення та перевірки анімації, зафіксувати вибір у коментарі класу. Перевірити: коротка вставка в `php artisan tinker` на тестовому JPEG/PNG виконується без помилок
- [x] 2.2 Створити `App\Files\ImageResizer` (обчислення `scale = min(maxW/w, maxH/h, 1)`, пропуск анімованих GIF/WebP, повернення `null` для зображень у межах, орієнтація, збереження формату й прозорості, якість з конфігурації), з JSDoc-стилем PHPDoc англійською та `@example`. Перевірити: `Tests\Unit\Files\ImageResizerTest` покриває сценарії специфікації (ширина, висота, різні межі, одна межа, у межах, без збільшення, прозорість PNG, орієнтація, анімований GIF, формат WebP) і проходить через `--filter=ImageResizerTest`
- [x] 2.3 Задокументувати `ImageResizer` у `docs/file-storage.md` (розділ про обробку зображень: алгоритм, винятки, EXIF) і перевірити, що кожен сценарій таблиці в документі має відповідний тест

## 3. Правила завантаження

- [x] 3.1 Додати `maxWidth`/`maxHeight` до `UploadRules`: `narrowed()` бере менше з профілю й поля, ігнорує некоректні значення, `forField()` читає `settings.max_width`/`max_height`. Перевірити: нові тести в `tests/Unit/Files/UploadRulesTest.php` (без меж, межі профілю, звуження полем, поле не розширює профіль, профіль без меж + межа поля, некоректні значення) проходять через `--filter=UploadRulesTest`
- [x] 3.2 Описати межі у правилах у `docs/file-storage.md` (розділ про `UploadRules` і звуження полем) та `docs/content-values.md` (зображення і файли); перевірити посилання між розділами

## 4. Інтеграція в `FileStorage::store()`

- [x] 4.1 Додати в `store()` перевірку `max_pixels` після `getimagesize`, виклик `ImageResizer`, запис оброблених байтів та метадані (`size`, `hash`, `width`, `height`) збереженого файлу; обробку винятків як `ValidationException` під ключем `file` з перекладеними повідомленнями; компенсацію диска при помилці вставки зберегти. Перевірити: нові тести в `tests/Feature/Files/FileStorageTest.php` (зменшення з метаданими збереженого, зображення в межах зберігається з тим самим хешем, ліміт `max_size` за оригіналом, надто багато пікселів, пошкоджене зображення, нічого не створено на диску й у БД при відмові) проходять через `--filter=FileStorageTest`
- [x] 4.2 Додати переклади нових повідомлень у `lang/uk.json` і `lang/en.json` (ліміт пікселів, не вдалося обробити зображення). Перевірити: `docker compose exec app php artisan test --compact --filter=FileStorageTest` і відсутність ключів без перекладу в обох файлах
- [x] 4.3 Перевірити ендпоінт завантаження: тест у `tests/Feature/Files/FileUploadEndpointTest.php` — завантаження за профілем і за полем із межами повертає ширину, висоту й розмір збереженого файлу. Проганяти `--filter=FileUploadEndpointTest`; решта споживачів профілю `image` перевіряється `--filter=ContentNodeFilesTest`
- [x] 4.4 Оновити `docs/file-storage.md` (розділ `store()`: нові кроки, порядок перевірок, метадані збереженого файлу, розділ «Завантаження файлу» з відповіддю) і перевірити, що перелік кроків збігається з кодом

## 5. Налаштування поля `image`

- [x] 5.1 Розширити `StoreContentFieldRequest::settingsRules()` ключами `settings.max_width`/`max_height` (`nullable|integer|min:1`, `max` = межа профілю, якщо задана) лише для типів, профіль яких має `image/*`; `ContentFieldController` зводить порожні значення до `null` і віддає межі профілю у `fileTypes`. Перевірити: нові тести в `tests/Feature/Admin/ContentFieldFileSettingsTest.php` (збереження, понад межу профілю, `0`, нечислове, відхилення для поля `file`, порожні значення дають `settings = null`) проходять через `--filter=ContentFieldFileSettingsTest`
- [x] 5.2 Оновити `ContentFieldForm.tsx`: тип `FileSettings` і профіль отримують `max_width`/`max_height`, два числові поля (з `id`, `aria-invalid`, підказкою про межу профілю) показуються лише коли профіль типу має зображення, помилки біля полів, `transform` надсилає значення; англійською коментарі/JSDoc; додати переклади `lang/uk.json`/`lang/en.json`. Перевірити: `npm run build` на хості проходить без помилок типів
- [x] 5.3 Додати в `e2e/admin-content-fields.spec.js` сценарій: для `image` видно ширину й висоту, для `file` їх немає, значення зберігаються й відновлюються при редагуванні, понад межу профілю показується помилка. Перевірити: `npm run test:e2e -- e2e/admin-content-fields.spec.js` проходить; спеки залежних споживачів `ContentFieldForm` (`admin-content-types`, `content-reference-fields`) запускати лише якщо змінено спільні частини форми
- [x] 5.4 Оновити `docs/admin-content-fields.md` (розділ «Налаштування типів і розміру»: таблиця ключів з `settings.max_width`/`max_height`, опис форми) і `docs/e2e-testing.md`, якщо змінюється відповідність спеків; перевірити валідність посилань у `docs/README.md`

## 6. Завершальна перевірка

- [x] 6.1 Запустити тести, що торкаються зображень: `docker compose exec app php artisan test --compact tests/Feature/Files tests/Unit/Files tests/Feature/Admin/ContentFieldFileSettingsTest.php tests/Feature/Admin/ContentNodeFilesTest.php`, форматування `docker compose exec app vendor/bin/pint --dirty --format agent`, а `openspec validate add-image-resize-on-upload` проходить. Повний прогін `php artisan test` потрібен лише через зміну залежностей `composer` — запустити його один раз після завдання 1.1, якщо змін у залежностях лишається більше за додавання пакета

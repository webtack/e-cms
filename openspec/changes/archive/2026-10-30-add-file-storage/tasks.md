# Tasks

## 1. Інфраструктура і таблиця `files`

- [x] 1.1 Додати `docker/php/uploads.ini` (`upload_max_filesize=25M`, `post_max_size=30M`), підключити в `docker/php/Dockerfile` (копіювання в `$PHP_INI_DIR/conf.d/`), перебудувати образ `docker compose up -d --build app`; перевірка: `docker compose exec app php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size");'` виводить `25M 30M`
- [x] 1.2 Міграція `files` (колонки й індекс за design D1: `uuid`, `profile`, `disk`, `path`, `original_name`, `mime`, `size`, `hash`, `width`, `height`, `timestamps`, індекс по `created_at`) і модель `App\Models\File` з JSDoc/PHPDoc англійською та `@example`; перевірка: `docker compose exec app php artisan migrate`, тест міграції/моделі проходить (`docker compose exec app php artisan test --filter=File`), Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`
- [x] 1.3 Створити `docs/file-storage.md` (українською) з розділом про модель `files` і ліміти PHP/nginx, додати рядок у `docs/README.md`; перевірка: посилання в індексі відкриваються, опис збігається з міграцією

## 2. Профілі та `FileStorage`

- [x] 2.1 `config/files.php` (профілі `image` і `document`, `references` = `[]`, `prune_after_hours` = 24) і `App\Files\FileProfiles` (читання профілю, помилка для невідомого ключа) з коментарями англійською; перевірка: Unit-тест профілів (існуючий, невідомий, валідність дисків усіх профілів у `config('filesystems.disks')`)
- [x] 2.2 `App\Files\FileStorage` (`store`, `url`, `delete`, singleton у контейнері) за design D3: перевірка розміру й типу за вмістом, чорний список виконуваних файлів і SVG, згенероване ім'я на диску, метадані зображення, компенсація при збої вставки, `delete` спершу рядок, потім диск, відсутній файл не помилка; перевірка: Feature-тести на `Storage::fake` покривають усі сценарії вимог «Облік файлів», «Профілі», «Перевірка файлу за профілем», «Безпека збережених файлів», «Посилання на файл» і «Захист файлів, що використовуються», зокрема OOXML-файли (DOCX/XLSX), справжні та з підробленим розширенням
- [x] 2.3 Перевірити на образі `app`, який mime повертає `finfo` для згенерованих DOCX і XLSX; якщо це `application/zip`, застосувати рішення з design «Risks» (дозвіл `application/zip` лише з розширенням `docx`/`xlsx`) і зафіксувати результат тестом; перевірка: тест з реальними DOCX/XLSX проходить
- [x] 2.4 Розділи «Профілі», «FileStorage» у `docs/file-storage.md` (приклади `store`/`url`/`delete`, як додати профіль); перевірка: приклади збігаються з кодом

## 3. Ендпоінт і правило валідації

- [x] 3.1 `StoreFileRequest`, `Admin\FileController::store` і маршрут `POST /admin/files` (`admin.files.store`) у групі `auth`+`verified`; відповідь 201 з полями за design D4; перевірка: Feature-тести (успіх, гість, відсутній файл/профіль, невідомий профіль, недозволений тип, завеликий файл) проходять
- [x] 3.2 `App\Files\Rules\FileOfProfile` і переклади повідомлень у `lang/`; перевірка: Unit-тест (потрібний профіль, інший профіль, неіснуючий файл, нечислове значення) проходить, ключі перекладів є в `lang/en.json` і `lang/uk.json`
- [x] 3.3 Розділи «Завантаження файлу» і «Правило FileOfProfile» у `docs/file-storage.md` (запит, відповідь, помилки, як споживач підключає правило і FK `restrictOnDelete`); перевірка: приклади збігаються з кодом

## 4. Життєвий цикл: `files:prune`

- [x] 4.1 Команда `files:prune` за design D6 (вибірка старших за `prune_after_hours` без посилань за `config('files.references')`, чанки по 500, пропуск і лог при помилці, підсумок) і запис `Schedule::command('files:prune')->daily()` у `routes/console.php`; перевірка: Feature-тести — осиротілий, свіжий, використовуваний (тимчасова тестова таблиця з FK `RESTRICT` і записом у `references`), відсутній на диску, помилка одного файлу не зупиняє решту; `docker compose exec app php artisan schedule:list` показує команду
- [x] 4.2 Розділ «Життєвий цикл і очищення» в `docs/file-storage.md` (правило споживача: не видаляти файл самому, додати рядок у `references`, потрібен `schedule:run`); перевірка: розділ збігається з кодом команди

## 5. Віджет і демо-сторінка

- [x] 5.1 Компонент `Components/Admin/FileUpload/FileUpload.tsx` (вибір і перетягування, індикація, помилки валідації, перегляд зображення, очищення, виклик `onChange`) з JSDoc англійською та `@example`, переклади підписів у `lang/en.json` і `lang/uk.json`; перевірка: `npm run build` без помилок, JSDoc присутній
- [x] 5.2 Демо-сторінка: `Ui\FileUploadDemoController` (404 у production), маршрут `admin.ui.file-upload` (`/admin/ui/file-upload`), `Pages/Admin/Ui/FileUploadDemo.tsx` із двома віджетами (`image`, `document`), рядок у `navigation.ts` для заголовка; перевірка: Feature-тест (гість, автентифікований, production → 404) проходить
- [x] 5.3 E2E `e2e/admin-file-upload.spec.js` (допустимий файл, недозволений тип, завеликий файл, очищення, гість) і рядки в таблицях «Область → спек» та «Спільний код → спеки» у `docs/e2e-testing.md`; перевірка: `npm run test:e2e -- e2e/admin-file-upload.spec.js` проходить
- [x] 5.4 Розділ «Віджет і демо-сторінка» в `docs/file-storage.md`, посилання з `docs/admin-shell.md`; перевірка: посилання в обидва боки працюють

## 6. Інтеграційна перевірка

- [x] 6.1 `docker compose exec app php artisan test` (PHPUnit повністю) і Pint проходять без помилок; `openspec validate add-file-storage` проходить

# Design

## Context

Мотивацію й обсяг див. у [proposal.md](proposal.md), вимоги — у [specs/file-storage/spec.md](specs/file-storage/spec.md).

Що є в проєкті (перевірено в коді):

- `config/filesystems.php`: диски `local` (`storage/app/private`), `public` (`storage/app/public`, URL `APP_URL/storage`, посилання `public/storage` через `storage:link`) і `s3`. Файлового коду немає.
- Значення полів — EAV: `content_values_{тип}` з колонкою `value` і складеними FK; новий тип поля = клас + міграція + рядок у `config/content.php`. Запис іде через `ContentValueWriter`, який працює з простими значеннями. Тип `file` (значення = `files.id`) додасть наступна зміна; ядро запису від цієї зміни не залежить.
- Адмін-маршрути — у групі `auth`+`verified` з префіксом `admin` (`routes/web.php`). Форми — Inertia `useForm` (JSON). Є зразок демо-сторінки компонента: `Ui/DataTableDemoController` (404 у production).
- Scheduler: `routes/console.php` містить лише `inspire`.
- PHP-розширення `gd`, `fileinfo`, `exif` є; `imagick` і пакетів обробки зображень немає. Ліміти PHP у контейнері — `upload_max_filesize=2M`, `post_max_size=8M` (в `docker/php/Dockerfile` ini не задається); `client_max_body_size` nginx — 64M.
- Тести: PHPUnit на SQLite in-memory; зовнішні ключі в SQLite у Laravel увімкнені.

## Goals / Non-Goals

**Goals:**
- Один сервіс, через який усі споживачі зберігають, адресують і видаляють файли; споживач знає лише `file_id`.
- Правила (диск, каталог, типи, розмір) описує розробник у конфігурації; користувач їх не обирає й не обходить.
- Цілісність забезпечує БД (FK `RESTRICT`), а очищення безпечне навіть при помилці в конфігурації.

**Non-Goals:**
- Приватні файли й маршрут віддачі з перевіркою прав: сервіс `url()` залишає для цього місце, але маршруту немає.
- Мініатюри, похідні розміри, обробка зображень, дедуплікація, медіатека, ролі та права.
- Тип поля `file`, аватар, логотип — окремі зміни.

## Decisions

### D1. Таблиця `files`, споживачі зберігають `file_id`

Колонки: `id`, `uuid` (UUID v7, як у `content_nodes`), `profile` (string 64), `disk` (string 64), `path` (string 512), `original_name` (string 255), `mime` (string 127), `size` (unsigned bigint), `hash` (char 64, sha256), `width`/`height` (nullable unsigned int), `timestamps`. Індекс по `created_at` (для `files:prune`). Модель `App\Models\File`.

Альтернатива — лише шлях у колонці споживача: відкинуто (метадані розмазані, немає єдиного очищення, повторне використання неможливе). Дедуплікація за `hash` не робиться: хеш зберігається, але рядок створюється завжди — це спрощує життєвий цикл (кожен рядок має власний шлях і незалежне видалення). Унікального індексу на `hash` немає.

### D2. Профілі в `config/files.php`

```php
'profiles' => [
    'image' => ['disk' => 'public', 'directory' => 'images', 'mimes' => [image/jpeg, image/png, image/webp, image/gif], 'max_size' => 5 * 1024],   // КБ
    'document' => ['disk' => 'public', 'directory' => 'documents', 'mimes' => [application/pdf, ...docx, ...xlsx], 'max_size' => 20 * 1024],
],
'references' => [],   // [['table' => ..., 'column' => ...], ...]
'prune_after_hours' => 24,
```

`FileProfiles` (клас-читач конфігурації) повертає профіль за ключем або кидає `InvalidArgumentException`; ендпоінт перед цим перевіряє ключ правилом `Rule::in(array_keys(...))`. Диск і каталог беруться з профілю, а не із запиту. Новий профіль — один блок у конфігурації.

Альтернатива: правила (mimes, max) з клієнта — відкинуто, клієнту довіряти не можна. Диск в одному місці для всього застосунку — відкинуто: різні споживачі можуть потребувати різних дисків, а `files.disk` уже є в кожному рядку.

### D3. `FileStorage` — єдина точка

```
store(UploadedFile $file, string $profile): File
url(File $file): string
delete(File $file): void
```

`store`:
1. Профіль зі `FileProfiles`.
2. Перевірка до запису на диск: розмір ≤ `max_size`; mime за вмістом (`finfo` через `UploadedFile::getMimeType()`, який читає вміст) входить у `mimes` профілю; **глобальний чорний список**: mime/розширення виконуваних файлів (`php`, `phtml`, `phar`, `sh`, `exe` тощо) і `image/svg+xml` відхиляються навіть якщо профіль їх дозволив; невдача → `ValidationException` з ключем `file`.
3. Для зображень `getimagesize` дає ширину/висоту; якщо вміст не читається як зображення при mime `image/*` → відхилення.
4. Ім'я на диску: `{directory}/{uuid}.{розширення з mime}`; розширення береться з відповідності дозволеного mime, а не з оригінального імені. Запис на диск — `Storage::disk($disk)->putFileAs(...)`.
5. Рядок у `files`. Якщо вставка впала — файл на диску видаляється (компенсація, бо транзакція БД диск не відкотить).

`url`: `Storage::disk($file->disk)->url($file->path)` — для `public` це `/storage/...`, для `s3` — URL диска. Приватний режим (`temporaryUrl`/маршрут) додається пізніше в цьому ж методі. `delete`: спершу рядок у БД (FK може відмовити → виняток, диск не чіпаємо), потім файл на диску; відсутній на диску файл — не помилка.

Сервіс — singleton у контейнері, без фасада (за зразком `AppSettings`).

### D4. Ендпоінт `POST /admin/files`

`App\Http\Controllers\Admin\FileController::store` + `StoreFileRequest` (`file` обов'язковий + `profile` з `Rule::in`). Маршрут у наявній групі `auth`+`verified`, ім'я `admin.files.store`. Відповідь JSON 201: `id`, `uuid`, `profile`, `name` (оригінальне ім'я), `mime`, `size`, `width`, `height`, `url`. Помилки — стандартні 422 Laravel для AJAX. Ендпоінт нічого не знає про споживача; належність файлу профілю поля перевіряє споживач правилом з D5.

Throttle не додається: доступ мають лише автентифіковані користувачі адмінки, окрема політика ліміту — не в цій зміні.

### D5. Правило валідації для споживачів

`App\Files\Rules\FileOfProfile` (`ValidationRule`): значення має бути цілим, рядок `files.id` має існувати, `files.profile` має збігатися з очікуваним. Використання: `new FileOfProfile('image')`. Саме це робить подальший тип поля `file` і аватар. Повідомлення — через `lang/`.

### D6. Цілісність і очищення

Споживач заводить колонку `file_id` із `->constrained('files')->restrictOnDelete()` (FK `RESTRICT`) і додає свій `table.column` до `config('files.references')`. Споживач ніколи не викликає `FileStorage::delete` при зміні свого значення.

`php artisan files:prune` (`App\Console\Commands\PruneFiles`): вибирає `files` старші за `prune_after_hours` (за `created_at`), для яких для кожного елемента `references` не існує рядка з таким `file_id` (`whereNotExists`, чанками по 500); для кожного викликає `FileStorage::delete`. Помилка однієї спроби (`QueryException` через FK — збій конфігурації `references`; виняток диска) логується, файл пропускається, решта продовжується. Виводить кількість видалених. Розклад: `Schedule::command('files:prune')->daily()` у `routes/console.php`.

Подвійний захист навмисний: `references` потрібен для ефективної вибірки (не пробувати видаляти всі використовувані файли щоразу), FK `RESTRICT` — щоб помилка в конфігурації не призвела до втрати даних.

Альтернатива (відкинута): споживач видаляє файл одразу — при повторному використанні потребує лічильника посилань і перевірки в кожному споживачі. Прив'язка «тимчасовий → підтверджений» (поле `confirmed_at`) — відкинута: не покриває відпущення файлу після прив'язки.

Поки `references` порожній (споживачів ще немає), `files:prune` видаляє будь-який файл старший за 24 години — це очікувано: використовувати його ніхто не може.

### D7. Віджет `FileUpload` і демо-сторінка

`resources/js/Components/Admin/FileUpload/FileUpload.tsx`: props — `profile`, `value` (метадані файлу або `null`), `onChange(file | null)`, `accept`/`label`/`error` для відображення. Завантаження — `POST` multipart. У `resources/js` власних CSRF-запитів поза Inertia немає (пошук `axios`/`csrf`/`XSRF` порожній), тож спосіб передачі CSRF-токена (заголовок `X-XSRF-TOKEN` із cookie чи Inertia-запит) обирається в реалізації за перевіркою версії Inertia та поведінки Laravel; прогрес не обов'язковий понад індикатор «завантаження». Для `image/*` — перегляд за `url`. Форма отримує `file.id` через `onChange` і далі надсилає JSON. Компонент не знає про Inertia-форму: працює і з `useForm`, і з будь-яким станом.

Демо: `GET /admin/ui/file-upload` (`admin.ui.file-upload`), контролер `Ui\FileUploadDemoController`, сторінка `Pages/Admin/Ui/FileUploadDemo.tsx`; поза production, не в навігації; два екземпляри віджета (`image`, `document`). Хлібні крихти/заголовок — за зразком демо таблиці (`navigation.ts`).

### D8. Docker

`docker/php/uploads.ini` (`upload_max_filesize=25M`, `post_max_size=30M`) копіюється в `$PHP_INI_DIR/conf.d/` у `Dockerfile`; значення з запасом над найбільшим профілем (20 МБ). Потрібно перебудувати образ: `docker compose up -d --build app`. Реальні ліміти для кожного профілю все одно забезпечує `FileStorage`; PHP-ліміт лише верхня межа транспорту.

## Risks / Trade-offs

- **OOXML через `finfo`**: DOCX/XLSX — ZIP-контейнери; залежно від версії libmagic `finfo` може повернути `application/zip` замість `application/vnd.openxmlformats-officedocument.*`. → Тест з реальними згенерованими DOCX/XLSX на образі `app`; якщо потрібно, для `document` додається дозволений `application/zip` лише за умови, що розширення оригіналу `docx`/`xlsx` (рішення в межах цієї ж реалізації; специфікація не змінюється).
- **Файл і БД не атомарні**: диск не відкотиться разом із транзакцією. → Компенсація в `store` (D3) і `files:prune` як страховка від залишків; файл на диску без рядка в БД (збій між кроками) цей механізм не знаходить — ризик прийнято, малоймовірний.
- **Осиротілі файли живуть до доби** (рішення власника): диск може тимчасово містити неприв'язані файли.
- **Забутий `references`**: новий споживач без запису в `config('files.references')` не втрачає даних (FK `RESTRICT` + пропуск у `files:prune`), але його файли щоразу потрапляють у спробу видалення й лог → документація споживача вимагає додати рядок.
- **Піднімати PHP-ліміти ширше за потреби** збільшує поверхню для завеликих запитів → значення з запасом лише над найбільшим профілем.
- **`config/files.php` vs `config/filesystems.php`**: профілі посилаються на диски за ім'ям; непідтримуваний диск виявиться при першому завантаженні → тест на валідність конфігурації профілів.

## Migration Plan

1. Міграція `files` (нова таблиця, наявні дані не зачіпаються); відкат — `dropIfExists`.
2. Перебудувати образ `app` (ліміти PHP) і виконати `php artisan storage:link` (якщо ще не виконано).
3. Розклад `files:prune` вмикається самим кодом; у середовищі має працювати `schedule:run` (cron/воркер) — якщо ні, команду запускають вручну.

Відкат: прибрати маршрут/розклад, міграція `down`. Збережені файли на диску видаляються вручну (споживачів ще немає).

## Documentation

Опис — у новому `docs/file-storage.md` (модель, профілі, потік завантаження, `FileStorage`, правило `FileOfProfile`, `files:prune`, як підключити нового споживача, ліміти PHP/nginx); індекс — `docs/README.md`; демо-сторінка й віджет — `docs/file-storage.md` з посиланням із `docs/admin-shell.md`; спек e2e — `docs/e2e-testing.md`.

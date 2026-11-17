# Tasks

## 1. Короткі позначення типів файлів

- [x] 1.1 Додати `app/Files/FileTypes.php` (`label()`, `labels()`, `options()`, `mimesFor()`) на основі `config/files.php`, з PHPDoc і `@example`; перевірка: Feature-тест `FileTypes` (`docker compose exec app php artisan test --filter=FileTypes`) покриває тип з профілю, `image/jpeg` → `jpg`, запасний варіант `text/plain` → `plain` і розширення, спільне для кількох MIME.
- [x] 1.2 `MediaController`: додати `type` у `row()` і `show()`; перевірка: Feature-тест медіатеки (`--filter=Media`) бачить `type: 'docx'` у рядку й деталях, а `mime` у відповіді лишається повним.
- [x] 1.3 `ContentFieldController::fileTypes()`: додати `labels` (mime → позначення) поруч із `mimes`; перевірка: Feature-тест полів (`--filter=ContentField`) бачить `labels` у пропі `fileTypes`, а збережені `settings.mimes` — повні MIME.
- [x] 1.4 Фронтенд: колонка «Тип» у `Pages/Admin/Media/Index.tsx` і підзаголовок `MediaDetails.tsx` показують `type`; чекбокси в `ContentFieldForm.tsx` показують позначення, а значення форми лишаються повними MIME; типи пропів оновлено з JSDoc; перевірка: `npm run build` без помилок типів і `npm run test:e2e -- e2e/admin-media.spec.js e2e/admin-content-fields.spec.js` проходять (спеки оновлено: очікують `docx` / `pdf`, а не довгий MIME).
- [x] 1.5 Документація: оновити `docs/file-storage.md` (розділ про позначення типів), `docs/media-library.md` (колонка «Тип»), `docs/admin-content-fields.md` (`fileTypes.labels`); перевірка: у цих файлах немає згадок про показ повного MIME і всі перехресні посилання працюють.

## 2. Фільтр медіатеки за типом (швидкий фільтр DataTable)

- [x] 2.1 `MediaController::index`: колонка `mime` описується як `select` з `filterable` і `options` з `FileTypes::options()`, фільтр застосовується як `whereIn('mime', FileTypes::mimesFor($value))` до підрахунку `total` і до сторінки; у відповідь додається `typeOptions`; перевірка: Feature-тест перевіряє фільтр за `pdf`, фільтр разом із пошуком, пагінацію з фільтром і ігнорування невідомого значення (`docker compose exec app php artisan test --filter=Media`).
- [x] 2.2 `DataTable`: у `types.ts` додати `quickFilter?: boolean` у `Column` (лише для `select`), у `DataTableToolbar.tsx` вивести для таких колонок `Select` поруч із полем пошуку з варіантом «All types»/«Усі»; значення читається зі `state.filters`, вибір пише `eq`-фільтр через `controller.setFilters`, «Усі» його знімає; переконатися, що `DataTableFilterChips` і «Скинути» працюють для таких колонок; JSDoc з `@example`; перевірка: `npm run build` без помилок типів.
- [x] 2.3 `Pages/Admin/Media/Index.tsx`: колонка `mime` — `type: 'select'`, `quickFilter: true`, `options` з `typeOptions`, клітинка показує `row.type`; `filterable` самого `DataTable` не вмикати; нові підписи — у `lang/uk.json` і `lang/en.json`; перевірка: на `http://localhost:8080/admin/media` селект стоїть поруч із пошуком, вибір дає чіп, хрестик чіпа й «Скинути» повертають «Усі типи».
- [x] 2.4 e2e: у `e2e/admin-data-table.spec.js` (або `admin-media.spec.js`, якщо демо-таблиця не має `select`-колонки) додати сценарії швидкого фільтра: вибір → рядки й чіп, «Усі», зняття чіпа, «Скинути», адреса з фільтром, поєднання з пошуком; запустити `npm run test:e2e -- e2e/admin-media.spec.js e2e/admin-data-table.spec.js` (змінено спільний `DataTable` з вимкненим за замовчуванням прапорцем: решту залежних спеків за таблицею «Спільний код → спеки» не запускати, перевіривши `grep` використань `quickFilter` — лише медіатека); оновити опис сценаріїв у `docs/e2e-testing.md`.
- [x] 2.5 Документація: описати `quickFilter` у `docs/admin-data-table.md`, фільтр у `docs/media-library.md` («Сторінка медіатеки»); перевірка: посилання між файлами працюють.

## 3. Контейнер кожного поля форми запису

- [x] 3.1 `ContentNodeForm.tsx`: slug і кожне поле типу — у власному `fieldset` з рамкою (`space-y-2 rounded-md border p-4`) і `legend` — назвою поля з позначкою обов'язковості; булеве поле — прапорець з підписом у рамці без легенди; підказки й помилки всередині рамки; кнопки поза рамками; JSDoc компонента оновлено; перевірка: `npm run build` і `npm run test:e2e -- e2e/admin-content-nodes.spec.js` проходить (оновити селектори, що залежали від `role=group`/розмітки; додати перевірку, що поля лежать у `fieldset`, а кнопка збереження — ні).
- [x] 3.2 Документація: описати контейнери в `docs/admin-content-nodes.md` (розділ про форму запису); перевірка: опис збігається з розміткою, посилання працюють.

## 4. Множинне завантаження файлів

- [x] 4.1 `FileUpload.tsx`: винести завантаження одного файлу в `uploadFile()`, додати режим `multiple` (`<input multiple>`, перетягування кількох файлів, послідовний цикл окремих запитів, швидкі перевірки `accept` / `maxSize` для кожного файлу, перебіг «оброблено N із M», список помилок з іменами файлів, `onUploadedMany(files)`); одиночний режим працює як раніше; JSDoc з `@example`; перевірка: `npm run build` без помилок типів.
- [x] 4.2 `NodeFieldList.tsx` / `NodeFieldInput.tsx` / `ContentNodeForm.tsx`: для множинних полів `image` і `file` показати multi-зону завантаження, дописувати успішні файли в кінець списку (значення й клієнтські ключі), запам'ятовувати метадані файлів у `known`; кнопка «Додати елемент» лишається; нові підписи — у `lang/uk.json` і `lang/en.json`; перевірка: `npm run build` і ручна перевірка завантаження трьох файлів у `http://localhost:8080`.
- [x] 4.3 e2e: у `e2e/admin-file-upload.spec.js` (демо-сторінка у режимі `multiple`) і `e2e/admin-content-nodes.spec.js` додати сценарії: три допустимі файли → три елементи й три запити; один недозволений файл → два елементи й помилка з іменем; додавання до наявних; одиночне поле не приймає кілька файлів; запустити `npm run test:e2e -- e2e/admin-file-upload.spec.js e2e/admin-content-nodes.spec.js`; тестові файли `e2e-*` прибираються як у наявних спеках.
- [x] 4.4 Якщо демо-сторінка `Pages/Admin/Ui/FileUploadDemo.tsx` не має множинного віджета, додати його (лише поза production); перевірка: сторінка показує віджет у режимі `multiple`, а в production відповідає 404, як і раніше.
- [x] 4.5 Документація: `docs/file-storage.md` (розділ про віджет: режим `multiple`, послідовні запити, обробка помилок), `docs/admin-content-nodes.md` (множинні поля файлів), `docs/e2e-testing.md` (сценарії `admin-file-upload` і `admin-content-nodes`; таблиця «Спільний код → спеки» залишається чинною, перевірити); перевірка: `docs/README.md` без битих посилань.

## 5. Завершальна перевірка

- [x] 5.1 `docker compose exec app vendor/bin/pint --dirty --format agent` і `docker compose exec app php artisan test` проходять; у звіті названо запущені й пропущені e2e-спеки з причиною.

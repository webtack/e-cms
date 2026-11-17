# Tasks

## 1. Бекенд: `max_items`

- [x] 1.1 Міграція `add_max_items_to_content_fields_table` (`unsignedSmallInteger`, `default 0`); `ContentField`: `max_items` у `Fillable`, касті `integer` і PHPDoc (англійською, з `@example`); перевірка: `docker compose exec app php artisan migrate` проходить, `migrate:rollback --step=1` і повторний `migrate` теж.
- [x] 1.2 `StoreContentFieldRequest::sharedRules()` (`max_items`: `nullable|integer|between:0,65535`) з PHPDoc; `ContentFieldController` віддає й зберігає `max_items` (відповідь і форма), скидає в `0` для немножинного поля; перевірка: Feature-тести (`docker compose exec app php artisan test --filter=ContentField`) — збереження ліміту при створенні й редагуванні, скидання при вимкненні «Множинне», помилки для `-1`, `65536`, `abc`.
- [x] 1.3 `ContentValueWriter::validate()`: `max:{max_items}` для множинного поля з `max_items > 0`; `ContentNodeController::formFields()` віддає `max_items`; перевірка: Feature-тести — у межах ліміту, понад ліміт (помилка `values.{key}`), без ліміту, порожні елементи не рахуються; `formFields` у відповіді create/edit містить `max_items`.
- [x] 1.4 Документація: `docs/admin-content-fields.md` (налаштування, правила, форма) і `docs/admin-content-nodes.md` (`formFields()`, серверна перевірка ліміту); перевірка: перехресні посилання працюють, `docs/README.md` без змін.

## 2. Форма поля: «Максимальна кількість елементів»

- [x] 2.1 `ContentFieldForm.tsx`: числове поле «Максимальна кількість елементів» (лише при ввімкненому «Множинне», підказка «0 — без обмеження», скидання в `0` при вимкненні, помилка біля поля, введене не губиться); підписи в `lang/uk.json` і `lang/en.json`; JSDoc; перевірка: `npm run build` без помилок типів.
- [x] 2.2 e2e `admin-content-fields.spec.js`: поле видиме лише для множинного, збереження й відображення в редагуванні, помилка недопустимого значення; запустити `npm run test:e2e -- e2e/admin-content-fields.spec.js` (форми поля — спільний код: додати залежні спеки з рядка таблиці «Спільний код → спеки», якщо змінено спільні компоненти); перевірка: спеки проходять.
- [x] 2.3 Документація: `docs/admin-content-fields.md` (розділ «Форма»), `docs/e2e-testing.md` (сценарії `admin-content-fields`); перевірка: посилання працюють.

## 3. Форма запису: ліміт елементів

- [x] 3.1 `types.ts` (`NodeField.max_items`), `NodeFieldList.tsx`: «Додати елемент» вимкнено за `full`; зона множинного завантаження вимкнена з підказкою; пакет обрізається до вільних місць до завантаження, пропущені файли названо в повідомленні про ліміт; JSDoc; нові підписи в `lang/uk.json` і `lang/en.json`; перевірка: `npm run build`.
- [x] 3.2 `ContentNodeForm.tsx`: лічильник «n / max» біля підпису множинного поля з лімітом; помилка сервера `values.{key}` показується біля поля; JSDoc; перевірка: `npm run build`, ручна перевірка на `http://localhost:8080` — рядкове й `image` поле з лімітом 2–3.
- [x] 3.3 e2e `admin-content-nodes.spec.js`: лічильник, вимкнене додавання при досягненні ліміту (рядкове й файлове поле), обрізання пакета, видалення звільняє місце, поле без ліміту без лічильника; запустити `npm run test:e2e -- e2e/admin-content-nodes.spec.js` (плюс залежні спеки за таблицею для `ContentNodes/*`); перевірка: спеки проходять.
- [x] 3.4 Документація: `docs/admin-content-nodes.md` (розділи «Множинні поля» та «Поля `image` і `file`»), `docs/e2e-testing.md`; перевірка: посилання працюють.

## 4. Модальне вікно даних файлу

- [x] 4.1 `Components/ui/dialog.tsx` (оболонка над `radix-ui` `Dialog` у стилі `alert-dialog.tsx`); `FileUpload.tsx`: необов'язковий слот `actions` у рядку кнопок блоку файлу, JSDoc з `@example`; перевірка: `npm run build`, `npm run test:e2e -- e2e/admin-file-upload.spec.js` проходить без змін спеку (поведінка за замовчуванням не змінилася).
- [x] 4.2 `NodeFieldInput.tsx`: `FileMetaFields` → `FileMetaDialog` (кнопка-олівець «Редагувати дані файлу» у `actions`, чернетка «Назва»/«Опис», «Готово» застосовує, «Скасувати»/Escape відкидає, помилки `file_meta.{uuid}.*` під блоком і у вікні); `NodeFieldList.tsx` — без змін контракту (`removable={false}` у рядку); підписи в `lang/uk.json` і `lang/en.json`; JSDoc; перевірка: `npm run build`, ручна перевірка створення й редагування запису на `http://localhost:8080`.
- [x] 4.3 e2e `admin-content-nodes.spec.js` і `admin-media.spec.js`: адаптувати наявні сценарії зміни назви/опису під вікно (відкрити → змінити → «Готово» → зберегти), додати скасування, перевірку відсутності полів у блоці, кнопки на місці хрестика в рядку множинного поля та помилку порожньої назви; запустити `npm run test:e2e -- e2e/admin-content-nodes.spec.js e2e/admin-media.spec.js e2e/admin-file-upload.spec.js` (залежні спеки для `FileUpload` і `ContentNodes/*`); перевірка: спеки проходять.
- [x] 4.4 Документація: `docs/admin-content-nodes.md` (розділ «Поля `image` і `file`»: вікно замість полів у блоці), `docs/file-storage.md` (слот `actions`), `docs/e2e-testing.md` (сценарії; нових спеків немає — таблиці «Область → спек» і «Спільний код → спеки» перевірити); перевірка: `docs/README.md` без битих посилань.

## 5. Завершення

- [x] 5.1 `docker compose exec app vendor/bin/pint --dirty --format agent` і `docker compose exec app php artisan test` проходять; у звіті названо запущені й пропущені e2e-спеки з причиною.

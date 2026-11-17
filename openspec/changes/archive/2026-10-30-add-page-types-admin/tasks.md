# Tasks

## 1. Бекенд: вид із маршруту

- [x] 1.1 Зареєструвати маршрути `/admin/page-types/**` (типи й поля, імена `admin.page-types.*`) поряд із наявними з `defaults('kind', …)` і прив'язати `contentType` за видом із маршруту; перевірка: `docker compose exec app php artisan route:list --name=page-types` показує всі маршрути, а `--name=content-types` — без змін
- [x] 1.2 Узагальнити `ContentTypeController` (список, створення, редагування, видалення, `parentOptions`, redirect-імена) і `ContentFieldController` за видом, з JSDoc/PHPDoc англійською та `@example`; перевірка: наявні `tests/Feature/Admin/ContentType*Test.php` та `ContentField*Test.php` проходять без змін
- [x] 1.3 Узагальнити `StoreContentTypeRequest::sharedRules()` і `UpdateContentTypeRequest` (батько того ж виду, `slug` лише для `content`); перевірка: Feature-тест відхиляє батька іншого виду й ігнорує `slug`/`kind` для `page`
- [x] 1.4 Feature-тести для `page` у `tests/Feature/Admin/PageType*Test.php` (список лише `page`, гість, створення з видом `page`, редагування, видалення з вузлами/дітьми, 404 для `content`/`block` і навпаки, поля: список, створення, видалення `title`, порядок); перевірка: `docker compose exec app php artisan test --filter=PageType` проходить
- [x] 1.5 Pint для змінених PHP-файлів; перевірка: `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень

## 2. Фронтенд: спільні сторінки з `basePath`

- [x] 2.1 Передавати з контролерів `basePath` і `kind` у Inertia-пропси та замінити жорсткі шляхи `/admin/content-types` у `ContentTypeForm`, `ContentTypeRowActions`, `ContentTypeNav`, `ContentFieldForm`, `ContentFieldRowActions`, `Pages/Admin/ContentTypes/*`, `Pages/Admin/ContentFields/*`; перевірка: `grep -rn "/admin/content-types" resources/js` не знаходить шляхів поза `navigation.ts`, `npm run build` без помилок типів
- [x] 2.2 Приховати поле й колонку slug для `kind !== 'content'` та підставити заголовки/діалоги «Page Types», «Create page type», «Delete page type» за видом; додати ключі в `lang/uk.json` і `lang/en.json`; перевірка: у браузері `/admin/page-types` без колонки slug, діалог видалення називає «тип сторінки»
- [x] 2.3 Додати пункт «Page Types» у `navigation.ts` та узагальнити `patternTitles`/`extraTitles` для `page-types`; перевірка: sidebar веде на `/admin/page-types`, хлібні крихти коректні на create/edit/fields

## 3. E2E і документація

- [x] 3.1 Додати `e2e/admin-page-types.spec.js` (список, створення, редагування, меню розділу з `aria-current`, поля: створення/видалення/порядок, видалення типу, пункт sidebar) і переконатися, що наявні e2e для `content` проходять; перевірка: `npm run test:e2e` на хості зелений
- [x] 3.2 Створити `docs/admin-page-types.md` (маршрути, відмінності від `content`, механізм виду з маршруту, компоненти з `basePath`), оновити `docs/admin-content-types.md`, `docs/admin-content-fields.md` (узагальнення за видом), `docs/admin-shell.md` (новий пункт) і індекс `docs/README.md`; перевірка: усі відносні посилання валідні

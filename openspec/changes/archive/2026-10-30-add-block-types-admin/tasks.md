# Tasks

## 1. Бекенд: вид `block` із маршруту

- [x] 1.1 Додати `'block-types' => 'block'` до циклу реєстрації маршрутів у `routes/web.php` і замінити тернарний вираз у `ResolvesTypeSection::segment()` відображенням видів на сегменти; оновити PHPDoc/коментарі, що згадують лише `content`/`page`; перевірка: `docker compose exec app php artisan route:list --name=block-types` показує повний набір маршрутів, `--name=page-types` і `--name=content-types` без змін
- [x] 1.2 Feature-тести в `tests/Feature/Admin/BlockTypeTest.php` за зразком `PageTypeTest.php` (гість, список лише `block` без slug, створення з видом `block`, батько іншого виду, редагування без зміни `key`/`kind`, нащадок як батько, 404 між `content-types`/`page-types`/`block-types`, видалення з вузлами/дітьми, поля: список, створення, заборона видалення `title`, порядок, чужий ключ); перевірка: `docker compose exec app php artisan test --filter=BlockType` проходить, а `--filter=PageType` і `--filter=ContentType` не зламано
- [x] 1.3 Pint для змінених PHP-файлів; перевірка: `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень

## 2. Фронтенд: вид `block` у спільних сторінках

- [x] 2.1 Розширити `TypeSection['kind']` до `'block'` і додати запис `block` у `sectionLabels` (`section.ts`, JSDoc англійською з `@example`); додати ключі в `lang/uk.json` і `lang/en.json`; перевірка: `npm run build` без помилок типів, `grep -rn "kind === 'page'" resources/js` не знаходить перевірок, що виключають `block`
- [x] 2.2 Додати пункт «Block Types» (іконка `Blocks`) у `navigation.ts` після Page Types, записи в `extraTitles`/`patternTitles` і узагальнити шаблони полів до `(content|page|block)-types`; перевірка: sidebar веде на `/admin/block-types`, хлібні крихти коректні на create/edit/fields

## 3. E2E і документація

- [x] 3.1 Додати `e2e/admin-block-types.spec.js` за зразком `admin-page-types.spec.js` (список, створення, редагування, меню розділу з `aria-current`, поля: створення/видалення/порядок, видалення типу, пункт sidebar) і рядок «Типи блоків» у таблицю «область → спек» у `docs/e2e-testing.md`; перевірка: `npm run test:e2e -- e2e/admin-block-types.spec.js` зелений
- [x] 3.2 Оскільки змінюється спільна навігація (sidebar), запустити повний `npm run test:e2e`; перевірка: усі спеки зелені
- [x] 3.3 Створити `docs/admin-block-types.md` (маршрути, відмінності, посилання на механізм виду в `docs/admin-page-types.md`), оновити `docs/admin-page-types.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md` (новий пункт) і індекс `docs/README.md`; перевірка: усі відносні посилання валідні

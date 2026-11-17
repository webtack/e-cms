# Tasks

## 1. Схема, моделі та сервіс прав

- [x] 1.1 Міграція: таблиці `roles`, `role_permissions`, колонки `users.role_id` і `users.is_active`, створення ролі `admin` і призначення її наявним користувачам, потім `role_id` NOT NULL. Перевірка: `docker compose exec app php artisan test --compact --filter=RolesMigration` (тест на БД із користувачами до міграції і без них).
- [x] 1.2 Моделі `Role`, `RolePermission`, зв'язок `User::role()`, поле `is_active` у `User`, фабрики; сидер створює адміністратора з роллю `admin`. Перевірка: Feature-тест `tests/Feature/Auth` (сидер) проходить; код має JSDoc/PHPDoc англійською з `@example` там, де неочевидно.
- [x] 1.3 Сервіс `AccessResolver` (системні права, `level('node:{key}')`, медіа, `canEditAnyContent()`, `admin` = усе дозволено), `Gate::define` для системних прав. Перевірка: unit/Feature-тести `tests/Feature/Access/AccessResolverTest` для кожного рівня й для `admin`.
- [x] 1.4 Policies `ContentNodePolicy` і `MediaPolicy` (`viewAny`, `create`, `update`, `delete`, правило `own` за автором, файл без власника лише `any`). Перевірка: `--filter=ContentNodePolicy` і `--filter=MediaPolicy`.
- [x] 1.5 Очищення рядків `node:{key}` при видаленні типу в тій самій транзакції. Перевірка: тест видалення типу.
- [x] 1.6 Документація: створити `docs/access-control.md` (модель, схема, рівні, ризик ролі `roles`), додати в `docs/README.md`; оновити `docs/content-model.md` (зв'язок автора з правами). Перевірка: посилання в `docs/README.md` валідні.

## 2. Активність користувача та вхід

- [x] 2.1 Middleware `EnsureUserIsActive` у групі `web`, відмова входу деактивованого в `LoginRequest`. Перевірка: `--filter=Auth` (вхід деактивованого, розлогін відкритої сесії).
- [x] 2.2 Документація: оновити `docs/admin-auth.md`. Перевірка: розділ про деактивацію присутній.

## 3. Захист маршрутів і права на клієнті

- [x] 3.1 Захистити маршрути меню, таксономії, типів/полів (всі три види) системними правами через `can:` middleware. Перевірка: Feature-тести кожного розділу (`--filter=Menu`, `--filter=Taxonomy`, `--filter=ContentType`) з ролями без прав і з правами.
- [x] 3.2 Авторизація записів (`ContentNodeController`: index, create, store, edit, update, destroy) за рівнем типу і правилом `own`; список передає ознаку «можна змінювати» для кожного запису. Перевірка: `--filter=ContentNode` (рівні none/own/any, чужий запис).
- [x] 3.3 Авторизація медіатеки (`MediaController`) і `files.store`/`youtube/lookup` через `canEditAnyContent()`; `lookup/*` без змін. Перевірка: `--filter=Media`, `--filter=File`, `--filter=Lookup`.
- [x] 3.4 Тест-огляд усіх маршрутів `admin.*`: кожен, крім allow-list (dashboard, profile, lookup), дає 403 ролі без прав. Перевірка: `--filter=RouteCoverage`.
- [x] 3.5 `HandleInertiaRequests` передає `auth.user.can`; `navigation.ts`/`AppSidebar` фільтрують пункти; кнопки списку записів користуються ознакою з сервера. Перевірка: Feature-тест спільних пропсів + `npm run test:e2e -- e2e/admin-shell.spec.js`.
- [x] 3.6 Документація: оновити `docs/admin-shell.md`, `docs/admin-content-nodes.md`, `docs/media-library.md`, `docs/admin-menus.md`, `docs/admin-taxonomy.md`, `docs/admin-content-types.md` (прибрати «відкрито всім»). Перевірка: пошук `grep -n "відкрит" docs` не знаходить застарілих тверджень про доступ.

## 4. Керування ролями

- [x] 4.1 Контролер і запити ролей (`index`, `create`, `store`, `edit`, `update`, `destroy`), матриця прав на сервері (динамічно з `content_types`, групування за видом), захист `admin` і ролей із користувачами. Перевірка: `--filter=Role`.
- [x] 4.2 React-сторінки `Admin/Roles` (список на DataTable, форма з матрицею), пункти sidebar і переклади `uk`/`en`. Перевірка: сторінка рендериться, `npm run build` проходить.
- [x] 4.3 e2e-спек `e2e/admin-roles.spec.js` (створення ролі, зміна матриці, спроба видалити роль із користувачами). Перевірка: `npm run test:e2e -- e2e/admin-roles.spec.js`; додати рядок у таблиці «Область → спек» у `docs/e2e-testing.md`.
- [x] 4.4 Документація: створити `docs/admin-roles.md`, оновити `docs/README.md` і таблицю «Спільний код → спеки», якщо спек залежить від DataTable. Перевірка: посилання валідні.

## 5. Керування користувачами

- [x] 5.1 Контролер і запити користувачів (CRUD, пароль необов'язковий при зміні, `is_active`, `email_verified_at` при створенні), видалення лише без записів, заборона самодеактивації та зміни власної ролі, захист останнього адміністратора з блокуванням у транзакції. Перевірка: `--filter=User` (включно з паралельним сценарієм останнього адміна).
- [x] 5.2 React-сторінки `Admin/Users` (список на DataTable, форма з вибором ролі й перемикачем активності), переклади. Перевірка: `npm run build` проходить.
- [x] 5.3 e2e-спек `e2e/admin-users.spec.js` (створення користувача з роллю, деактивація, вхід деактивованого, видалення автора відхиляється). Перевірка: `npm run test:e2e -- e2e/admin-users.spec.js`; додати рядок у `docs/e2e-testing.md`.
- [x] 5.4 Документація: створити `docs/admin-users.md`, оновити `docs/README.md`. Перевірка: посилання валідні.

## 6. Інтеграційна перевірка

- [x] 6.1 Повний `docker compose exec app php artisan test` (змінені міграції, схема БД, глобальний middleware і автентифікація) та `openspec validate add-roles-and-permissions`. Перевірка: обидві команди без помилок; `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень.
- [x] 6.2 Повний `npm run test:e2e` (змінено автентифікацію та sidebar). Перевірка: усі спеки зелені.

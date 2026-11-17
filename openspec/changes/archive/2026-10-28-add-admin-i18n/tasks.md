# Tasks

## 1. Конфігурація мов і зберігання вибору

- [x] 1.1 Додати `available_locales` (`en` → English, `uk` → Українська) у `config/app.php`; перевірка: `docker compose exec app php artisan tinker --execute="dump(config('app.available_locales'));"` повертає обидві мови
- [x] 1.2 Створити міграцію `add_locale_to_users_table` (`locale` string(5) nullable) і виконати її; перевірка: `docker compose exec app php artisan migrate` проходить, `migrate:rollback` та повторний `migrate` теж
- [x] 1.3 Оновити `App\Models\User` (поле `locale` не масово-присвоюване, доступне для запису через контролер) із PHPDoc; перевірка: `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень
- [x] 1.4 Створити `docs/admin-i18n.md` (доступні мови, пріоритет визначення, `users.locale`, cookie) і додати посилання в `docs/README.md`; перевірка: усі лінки в індексі відкриваються

## 2. Визначення та перемикання мови на сервері

- [x] 2.1 Створити middleware `App\Http\Middleware\SetLocale` (user → cookie → `Accept-Language` → `APP_LOCALE`, ігнорувати недопустимі значення) і зареєструвати в групі `web` перед `HandleInertiaRequests` у `bootstrap/app.php`; перевірка: PHPUnit-тести пріоритету та `uk-UA` → `uk` проходять
- [x] 2.2 Створити `LocaleController` + `POST /locale` (`locale.update`, `throttle:30,1`, валідація `Rule::in`, збереження в `users.locale` для авторизованого та cookie `locale` для всіх, `back()`); перевірка: feature-тести для гостя, авторизованого й невалідного значення
- [x] 2.3 Додати спільний проп `locale` (`current`, `available`, `messages`) у `HandleInertiaRequests::share` і тип у `resources/js/types/index.d.ts`; перевірка: `assertInertia` тест на структуру пропса, `<html lang>` збігається з поточною мовою
- [x] 2.4 Додати JSDoc/PHPDoc англійською до публічних класів і методів із `@example`, де використання неочевидне; оновити `docs/admin-i18n.md` (middleware, `POST /locale`, спільний проп); перевірка: опис збігається з `docker compose exec app php artisan route:list --name=locale`

## 3. Переклади сервера

- [x] 3.1 Опублікувати англійські файли: `docker compose exec app php artisan lang:publish`; залишити лише потрібні (`auth.php`, `validation.php`, за потреби `passwords.php`/`pagination.php` не додавати); перевірка: `__('auth.failed')` повертає англійський текст
- [x] 3.2 Створити `lang/uk/auth.php` (повний переклад `failed`, `password`, `throttle`) і `lang/uk/validation.php` (правила, що використовуються, + `attributes` для `email`, `password`); перевірка: feature-тест — неправильний пароль для користувача з `uk` повертає українське повідомлення в `errors.email`
- [x] 3.3 Оновити `docs/admin-i18n.md` (де лежать серверні переклади, як додати мову); перевірка: кроки з документа відтворюються

## 4. Клієнтський механізм перекладів

- [x] 4.1 Створити `lang/en.json` і `lang/uk.json` (однаковий набір ключів) і PHPUnit-тест паритету ключів; перевірка: тест проходить, а видалення ключа з `uk.json` робить його червоним
- [x] 4.2 Створити `resources/js/lib/i18n.ts` (`translate(messages, key, replace?)`: fallback на ключ, плейсхолдери `:name`, довші імена першими) із JSDoc і `@example`; перевірка: `npm run build` без помилок TypeScript, поведінка перевірена в e2e (задача 7.2)
- [x] 4.3 Створити `resources/js/hooks/useTranslation.ts` (`{ t, locale, locales }` з `usePage().props.locale`) із JSDoc і `@example`; перевірка: `npm run build` проходить
- [x] 4.4 Оновити `docs/admin-i18n.md` (як користуватися `t()`, плейсхолдери, правило «кожен видимий рядок через `t()`», ключі = англійські фрази); перевірка: приклад із документа компілюється в `npm run build`

## 5. Перемикач мови та переклад інтерфейсу

- [x] 5.1 Створити `Components/Admin/LocaleSwitcher.tsx` (DropdownMenu, власні назви мов, позначка поточної, `router.post('/locale', …, { preserveScroll: true, preserveState: true })`) із JSDoc; додати в `AdminHeader` і на `Pages/Auth/Login.tsx`; перевірка: перемикання працює вручну на `http://localhost:8080/login` та в адмінці, стан sidebar зберігається
- [x] 5.2 Перевести `navigation.ts` на ключі та застосувати `t()` в `AppSidebar`, `Breadcrumbs` (включно із заголовком «Admin»); перевірка: `getBreadcrumbs` лишається чистою функцією, `npm run build` проходить
- [x] 5.3 Застосувати `t()` у `Login`, `VerifyEmail`, `Dashboard`, `Profile`, `UserMenu`, `ThemeToggle`, `AdminHeader` (включно з `aria-label` та `<Head title>`) і додати всі ключі в `lang/en.json` та `lang/uk.json`; перевірка: `grep` по JSX не знаходить англійських літералів поза `t()`, паритет-тест проходить
- [x] 5.4 Оновити `docs/admin-shell.md` (навігація як ключі перекладу, перемикач у шапці) і `docs/admin-auth.md` (перемикач на сторінці входу); перевірка: описані компоненти існують у `resources/js/`

## 6. Автоматичні тести PHP

- [x] 6.1 Додати `tests/Feature/Locale/SetLocaleTest.php`, `LocaleControllerTest.php`, `SharedLocalePropsTest.php` для сценаріїв зі специфікації `admin-i18n`; перевірка: `docker compose exec app php artisan test --compact` повністю зелений
- [x] 6.2 Прогнати Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`; перевірка: без змін після запуску

## 7. E2E та фінальна перевірка

- [x] 7.1 Переконатись, що наявні `e2e/admin-shell.spec.js` і `smoke.spec.js` лишаються зеленими (мова за замовчуванням `en`); перевірка: `npm run test:e2e`
- [x] 7.2 Додати `e2e/admin-i18n.spec.js`: перемикання на логіні, перемикання в адмінці зі збереженням стану sidebar, збереження мови після logout, `afterEach` повертає `en` для seeded-користувача; перевірка: `npm run test:e2e` зелений двічі поспіль
- [x] 7.3 Оновити `docs/e2e-testing.md` (ізоляція стану `users.locale`, чому тест повертає `en`); перевірка: опис збігається зі спеком
- [x] 7.4 Зібрати фронтенд `npm run build` і пройтись по всіх екранах двома мовами; перевірка: немає помилок збірки, у консолі браузера немає помилок

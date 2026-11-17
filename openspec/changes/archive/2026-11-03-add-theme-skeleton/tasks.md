# Tasks

## 1. Конфігурація й каркас каталогу теми

- [x] 1.1 Додати `config/theme.php` (`active` з `APP_THEME`, `path`, `fallback = default`) і `APP_THEME=default` у `.env.example` (та локальний `.env`), коментарі англійською; перевірити `docker compose exec app php artisan tinker --execute="echo config('theme.active');"` → `default`.
- [x] 1.2 Створити `themes/default/theme.json`, `themes/default/hooks.php` (порожня функція реєстру), `themes/default/assets/css/app.css` і мінімальні Blade-шаблони `views/{html,layout,home}.blade.php`, `views/errors/404.blade.php`, `views/pages/page.blade.php`, `views/content/list.blade.php`, `views/content/details.blade.php` (заглушки; `layout` виводить назву сайту/логотип/контакти з `$site`); видалити `resources/views/welcome.blade.php`; перевірити, що файли існують (`ls themes/default/views`) і `welcome` більше ніде не згадується (`grep -rn "welcome" app routes resources tests e2e`).

## 2. `ThemeManager`, кандидати, виняток

- [x] 2.1 Створити `App\Theme\ThemeException` і `App\Theme\ThemeManager` (singleton у `AppServiceProvider`): `active()`, `viewPaths()` (`[active/views, default/views]`), реєстрація namespace Blade `theme`, відкладена перевірка активної теми лише на публічному запиті (адмінка й консоль не падають), PHPDoc англійською з `@example`; перевірити `tests/Feature/Theme/ThemeManagerTest.php` (тема за замовчуванням, перевизначення частини шаблонів, відсутня тема → виняток теми, адмінка живе при зіпсованій `APP_THEME`): `docker compose exec app php artisan test --compact --filter=ThemeManagerTest`. Тести використовують fixture-тему в tmp-каталозі, а не змінюють `themes/`.
- [x] 2.2 Реалізувати `ThemeManager::render(array $candidates, array $data)` (`view()->first()` по namespace `theme`, порядок «кандидат → тема», `ThemeException` зі списком спробуваних імен, debug-коментар кандидатів під `app.debug`, без нього коментаря немає), PHPDoc з `@example`; перевірити `tests/Feature/Theme/CandidateCascadeTest.php` (перемагає конкретніший кандидат; кандидат у `default` проти загальнішого кандидата активної теми; відсутність усіх кандидатів; debug-коментар є/нема): `docker compose exec app php artisan test --compact --filter=CandidateCascadeTest`.
- [x] 2.3 Оформити документацію `docs/site-themes.md` (структура теми, `APP_THEME`, каскад кандидатів і порядок «кандидат → тема», `ThemeException`, debug-коментар) українською, додати рядок у `docs/README.md`; перевірити, що відносні посилання й розділи існують.

## 3. Хуки теми

- [x] 3.1 Створити `App\Theme\ThemeHooks` (`preprocess`, `suggest`, порядок за крапковим іменем від загального до конкретного, спершу `default`, потім активна тема, завантаження `hooks.php` один раз, виняток `ThemeException` для `hooks.php`, що повертає не функцію), інтегрувати в `ThemeManager::render()` (`suggest` перед резолвом, `preprocess` перед рендером); PHPDoc з `@example`; перевірити `tests/Feature/Theme/ThemeHooksTest.php` (порядок `field` → `field.youtube`, `default` → активна, `suggest` додає кандидата, некоректний `hooks.php`): `docker compose exec app php artisan test --compact --filter=ThemeHooksTest`.
- [x] 3.2 Описати хуки в `docs/site-themes.md` (формат `hooks.php`, імена хуків, приклад `preprocess('field.youtube')` і `suggest`); перевірити, що приклад із документа працює в тесті `ThemeHooksTest`.

## 4. Публічні маршрути й оболонка

- [x] 4.1 Створити `App\Http\Controllers\Public\HomeController`, `PublicNodeController` (`show` для `/{first}`, `details` для `/{first}/{second}`), 404-обробник і збирач спільних даних шаблона (`$site`, `$theme`, `$title`, `$node`/`$type`/`$paginator`, `$values` через `ContentValueReader`); контролери формують повні списки кандидатів каскаду з `design.md` (рішення 6) і рендерять через `ThemeManager::render()`; перевірити, що контролери не звертаються до шаблона напряму (`grep -rn "view(" app/Http/Controllers/Public` порожній).
- [x] 4.2 Зареєструвати публічні маршрути в кінці `routes/web.php`: `/`, `/{first}`, `/{first}/{second}` з обмеженням формату сегмента (`ContentNode::SLUG_PATTERN` без якорів) і `Route::fallback` → `errors/404` зі статусом 404; прибрати маршрут `welcome`; зарезервовані шляхи в БД (обхід валідації) не віддаються як сторінка; перевірити `docker compose exec app php artisan route:list --path=/` і `tests/Feature/Content/ReservedPathsGuardTest.php`: `docker compose exec app php artisan test --compact --filter=ReservedPathsGuardTest`.
- [x] 4.3 Додати `tests/Feature/Public/PublicRoutesTest.php`: головна 200 з `home` у `layout`; видима сторінка 200; вимкнена, запланована, завершена, не-page → 404; список типу з видимими записами; деталі 200; запис іншого типу й невидимий → 404; службові шляхи (`/login`, `/admin/dashboard`) не перехоплюються (зокрема з рядком-сторінкою `login`, вставленою в обхід моделі); невідомий шлях 404 у `layout`; блок `/block-footer` → 404; відповідь без кореневого елемента Inertia; `<title>` із `title` запису й назвою сайту: `docker compose exec app php artisan test --compact --filter=PublicRoutesTest`.
- [x] 4.4 Оновити/замінити тести, що залежали від `welcome` або `/` (знайти `grep -rn "'/'\|welcome" tests e2e`), і запустити зачеплені.
- [x] 4.5 Додати `docs/public-site.md` (маршрути, пріоритет службових шляхів, видимість, 404, оболонка, дані шаблонів, блоки не маршрутизуються), оновити `docs/README.md` і `docs/content-model.md` (посилання на публічні маршрути поряд зі slug); перевірити відносні посилання.
- [x] 4.6 Зміни зачіпають провайдер, конфігурацію й `routes/web.php`, тож виконати повний `docker compose exec app php artisan test --compact`; запустити `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 5. Vite і ресурси теми

- [x] 5.1 Додати в `vite.config.js` входи `themes/*/assets/{css,js}` (glob) і виведення ресурсів активної теми з `html` (лише коли файли є в маніфесті; тема без `assets` не ламає рендер); перевірити `npm run build` на хості (збірка проходить, маніфест містить `themes/default/assets/css/app.css`) і сценарій теми без `assets` у `ThemeManagerTest`.
- [x] 5.2 Описати збірку ресурсів тем у `docs/site-themes.md` і `docs/admin-shell.md` (Vite із `themes/*/assets`); перевірити, що команди з документа відповідають `package.json`.

## 6. `theme:check`

- [x] 6.1 Створити `App\Console\Commands\ThemeCheckCommand` (`theme:check`): `theme.json`, `themes/default`, обов'язкові шаблони (`html`, `layout`, `home`, `errors/404`, `pages/page`, `content/list`, `content/details`), коректність `hooks.php`; список проблем окремими рядками, код 0/ненульовий; PHPDoc з `@example`; перевірити `tests/Feature/Theme/ThemeCheckCommandTest.php` (повна тема, відсутній `layout`, некоректний `hooks.php`): `docker compose exec app php artisan test --compact --filter=ThemeCheckCommandTest` і `docker compose exec app php artisan theme:check`.
- [x] 6.2 Описати команду в `docs/site-themes.md` (що перевіряє, коди завершення, приклад виводу).

## 7. Браузерні перевірки

- [x] 7.1 Додати `e2e/public-site.spec.js`: головна, видима сторінка, список і деталі запису `content`, 404 для невідомого шляху, `/login` не перехоплюється; дані створюються й прибираються за наявним шаблоном `cleanup`; запустити `npm run test:e2e -- e2e/public-site.spec.js` (контейнери запущено).
- [x] 7.2 Оновити `docs/e2e-testing.md`: рядок «публічний сайт → `e2e/public-site.spec.js`» у таблиці «Область → спек»; у «Спільний код → спеки» додати рядок про шаблони теми/маршрути; перевірити, що таблиці відповідають наявним спекам.

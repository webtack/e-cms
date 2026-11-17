# Proposal

## Why

Публічного сайту ще немає: `/` віддає `welcome.blade.php`, інших публічних маршрутів нема, а дані (типи, сторінки, блоки, меню) уже готові й захищені від колізій першого сегмента URL (зміна `reserve-type-keys-and-public-paths`). Потрібен каркас, на який лягатимуть шаблони: система тем у стилі Drupal 7 (`themes/<name>/views`, каскад кандидатів «від конкретного до загального», хуки `preprocess`/`suggest`), публічні маршрути й оболонка сторінки. Без каркаса неможливо ні писати шаблони полів (наступна зміна), ні перевіряти, що тема повна.

Рішення по темах узгоджені з власником у режимі explore; ця зміна — друга з трьох: ключі й шляхи (зроблено) → **каркас теми** → шаблони `default` і `present()` у `FieldType`.

## What Changes

- Тема — каталог `themes/<name>/` з `theme.json`, `hooks.php`, `assets/`, `views/` (Blade, без Inertia). Активна тема береться з `APP_THEME` (`.env`, `config/theme.php`); тема `default` обов'язкова й завжди лежить у кінці списку шляхів, тож інша тема містить лише перевизначене.
- `ThemeManager` реєструє namespace Blade `theme` зі шляхами `[active/views, default/views]` і резолвить шаблон за **списком кандидатів**: спершу кандидат, потім тема (як у D7), через `view()->first($candidates)`. Якщо немає жодного кандидата: виняток `ThemeException` зі списком спробуваних імен. Під `app.debug` у HTML додаються коментарі зі списком кандидатів і вибраним файлом.
- Хуки: `themes/<t>/hooks.php` повертає функцію з реєстром `preprocess('field'|'field.youtube'|…)` (від загального до конкретного; `themes/default/hooks.php` вантажиться завжди, потім активної теми) і `suggest(...)` для зміни списку кандидатів. Клас `ThemeHooks`.
- Публічні маршрути: `/` → `home`; `/{slug}` → запис виду `page`; `/{type.slug}` → список типу `content`; `/{type.slug}/{node.slug}` → деталі запису. Маршрути реєструються останніми, не перекривають службових шляхів (`content.reserved_paths`), читають лише видимі записи (`onlyVisible: true`), невідомий шлях дає `errors/404` зі статусом 404. Блоки не маршрутизуються.
- Оболонка: `html` (документ), `layout` (шапка/меню/футер; `home` і решта сторінок рендеряться всередині), `home`, `errors/404`. Для маршрутів сторінки, списку й деталей у `default` лежать мінімальні шаблони-заглушки (`pages/page`, `content/list`, `content/details`), які наступна зміна замінить повними.
- Vite: входи з `themes/*/assets` (css/js) збираються поряд з адмінкою; шаблони підключають активну тему директивою `@vite` за її каталогом.
- Команда `php artisan theme:check`: перевіряє активну тему (є `theme.json`, `default`, обов'язкові шаблони, `hooks.php` повертає функцію) і виводить помилки; дедалі корисніша, коли шаблонів побільшає.
- `.env.example` отримує `APP_THEME=default`.
- **BREAKING** (лише для коду й тестів): `/` більше не віддає `welcome.blade.php` (він видаляється), тести/e2e, що залежали від `welcome`, переходять на `home`.

Поза обсягом (свідомо): мови сайту (префікс у шляху), регіони блоків, перемикання теми з адмінки, field-шаблони й `present()` у `FieldType` (зміна 3), шаблони `pages/blocks/content/menus` повного вигляду (зміна 3).

## Capabilities

### New Capabilities

- `site-themes`: структура теми, `ThemeManager`, каскад кандидатів і виняток `ThemeException`, хуки `preprocess`/`suggest`, ресурси теми через Vite, команда `theme:check`, конфігурація `APP_THEME`.
- `public-site`: публічні маршрути (`/`, `/{slug}`, `/{type.slug}`, `/{type.slug}/{node.slug}`), порядок і пріоритет щодо службових шляхів, видимість записів, 404, оболонка `html`/`layout`/`home`, змінні, що отримують шаблони.

### Modified Capabilities

Немає: поведінка наявних можливостей (контент, адмінка) не змінюється; `content.reserved_paths` лише читається.

## Impact

- Код: новий `app/Theme/` (`ThemeManager`, `ThemeHooks`, `ThemeException`, `CandidateResolver`, команда `ThemeCheckCommand`), `app/Http/Controllers/Public/*` (home, page, content list/details, fallback 404), `app/Providers/AppServiceProvider.php` (реєстрація менеджера й Blade-директив), `routes/web.php` (публічні маршрути в кінці; видалити маршрут `welcome`), `config/theme.php`, `.env.example`, `vite.config.js`, каталог `themes/default/{theme.json,hooks.php,assets,views}`, видалення `resources/views/welcome.blade.php`.
- Тести: `tests/Feature/Theme` (каскад кандидатів, `ThemeException`, хуки, `theme:check`, fixture-тема в tmp-каталозі), `tests/Feature/Public` (маршрути, видимість, 404, пріоритет службових шляхів); `tests/Feature/Content/ReservedPathsGuardTest.php` лишається чинним.
- e2e: новий спек `e2e/public-site.spec.js` (головна, сторінка, список/деталі, 404); рядки в `docs/e2e-testing.md`.
- Залежності: нових npm/composer-пакетів немає. Міграцій немає (лише читання наявних даних); повний прогін PHP-тестів потрібен через зміну `config`, провайдера й `routes/web.php`.
- Docs: нові `docs/site-themes.md` (структура, каскад, хуки, `theme:check`, `APP_THEME`) і `docs/public-site.md` (маршрути, видимість, оболонка); оновити `docs/README.md` (індекс), `docs/content-model.md` (посилання на публічні маршрути поряд зі slug), `docs/e2e-testing.md` (таблиця «область → спек»), `docs/admin-shell.md` (збірка Vite з `themes/*/assets`).

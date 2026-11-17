# Design

## Context

Мотивація й обсяг: див. `proposal.md`; поведінка: `specs/site-themes/spec.md`, `specs/public-site/spec.md`. Поточний стан:

- Публічно існує лише `Route::get('/', fn () => view('welcome'))`; `resources/views/` містить `app.blade.php` (Inertia-оболонка адмінки) і `welcome.blade.php`. Каталогу `themes/` немає, `APP_THEME` у `.env.example` немає.
- Глобальна група `web` має `EnsureUserIsActive`, `SetLocale`, `HandleInertiaRequests`; останній ділиться лише лінивими даними, тож на Blade-маршрутах він нічого не коштує.
- Дані для сторінки: `ContentNode::findBySlug($slug, onlyVisible: true)`, `ContentNodeQuery::paginate($type, NodeQueryParams(onlyVisible: true))`, `ContentValueReader::load()`; налаштування сайту (`SiteSettings`: назва, логотипи, контакти), меню (`MenuTree::tree($key)`).
- Колізій першого сегмента URL між службовими шляхами, slug сторінок і slug типів `content` немає за побудовою (`PublicPaths`, `content.reserved_paths`). Пріоритет під час маршрутизації залишено цій зміні.
- Мова вмісту на публіці: `default` (мови сайту поза обсягом).
- Vite: входи `resources/css/app.css` і `resources/js/app.tsx`; збірка на хості (`npm run build`).

## Goals / Non-Goals

**Goals:**
- Один механізм вибору шаблона для всього сайту (сторінка, блок, поле, content): список кандидатів у порядку «конкретне → загальне», спершу кандидат, потім тема.
- Тема `default` як гарантована основа; невдача вибору дає зрозумілий виняток, а не порожню сторінку.
- Хуки змінюють змінні шаблона й список кандидатів без залежності від обраного view (на відміну від View Composers).
- Публічні маршрути, що не конфліктують зі службовими й не потребують нових міграцій.

**Non-Goals:**
- Field-шаблони, `present()`, повні `pages/blocks/content/menus`: зміна 3 (тут лише заглушки, щоб маршрути працювали).
- Мови сайту, регіони блоків, перемикання теми в адмінці, кеш рендера.
- Меню в `layout`: рендер меню належить зміні 3 (`menus/`); тут `layout` має лише назву сайту/логотип/контакти.

## Decisions

### 1. Каталог теми й конфіг

`themes/<name>/{theme.json, hooks.php, assets/, views/}`. `config/theme.php`: `active` (з `APP_THEME`, `default`), `path` (`base_path('themes')`), `fallback` (`default`, не змінюється). `theme.json` містить лише `name` і `version`; невідомі ключі ігноруються, обов'язковий лише наявний валідний JSON з `name`.

*Альтернатива:* тема як пакет Composer. Відхилено: потрібна проста копія каталогу, без додаткового середовища.

### 2. `ThemeManager` і namespace Blade

`ThemeManager` (singleton) знає `active()`, `fallback()`, `viewPaths()` = `[active/views, default/views]` (лише один елемент, коли активна тема `default`). Під час `boot` провайдер викликає `View::addNamespace('theme', $manager->viewPaths())`. Тому `theme::layout` і `@include('theme::fields.field')` автоматично віддають перший знайдений файл за порядком шляхів, а `@extends`/`@include` між шаблонами теми працюють без власного коду.

Перевірка активної теми (існує каталог, є `theme.json`) виконується при першому зверненні до `ThemeManager` публічного запиту, а не в `boot`, тому адмінка й консольні команди не падають через зіпсовану `APP_THEME`.

*Альтернатива:* `config('view.paths')` зі зміною порядку. Відхилено: змішує адмінські й публічні шаблони, не дає окремого namespace.

### 3. Список кандидатів і резолв: порядок «кандидат → тема»

`ThemeManager::render(array $candidates, array $data)`:
1. кандидати проходять `suggest`-хуки (рішення 4);
2. для кожного кандидата й **кожного** шляху теми (`active`, потім `default`) перевіряється `view()->exists("theme::$candidate")`, тобто Blade-finder уже віддає пріоритет активній темі;
3. перше існуюче виконується через `view()->first(...)`; порожній збіг дає `ThemeException` з усім списком.

Фактично це `view()->first(array_map(fn ($c) => "theme::$c", $candidates), $data)`: namespace уже зводить «спершу тема, потім default» всередині кожного кандидата, а `first()` забезпечує порядок «кандидат важливіший за тему», як у D7. `ThemeException` будується з `InvalidArgumentException` від `first()` і повертає список імен у порядку перебору.

Під `app.debug` до відрендереного HTML додається коментар `<!-- theme: candidates [...] ; used: ... -->`. Він додається в `render()`, а не в шаблони, тому тема не може його забути. Імена кандидатів із крапками перетворюються в шляхи (`pages/page` ↔ `pages.page`) в одному місці.

*Альтернатива:* власний `ViewFinder`. Відхилено: Blade уже дає namespace і `first()`; зайвий код для тих самих гарантій.

### 4. Хуки: `ThemeHooks`

`themes/default/hooks.php` і `themes/<active>/hooks.php` повертають `function (ThemeHooks $hooks): void { ... }`. Реєстр містить `preprocess(string $name, callable $fn)` і `suggest(string $name, callable $fn)`. Виклик `preprocess('field.youtube', $vars)`: хуки `field`, потім `field.youtube` (за префіксами крапкового імені), у порядку реєстрації (спершу `default`, потім активна тема); кожен отримує `array &$vars`. `suggest` отримує `array &$candidates` і працює так само. Файли хуків вантажаться один раз при першому виклику й кешуються в singleton.

Хуки не є View Composers навмисно: composer прив'язаний до імені view, а кандидат стає відомим лише після резолву; хуки ж працюють до нього й можуть змінити сам вибір.

*Альтернатива:* події Laravel. Відхилено: немає зручної упорядкованості «загальне → конкретне» й порядку «default → активна».

### 5. Публічні маршрути

В кінці `routes/web.php`, після адмінки й службових маршрутів, реєструється **одна** пара fallback-маршрутів:

- `GET /` → `HomeController`;
- `GET /{first}` → `PublicNodeController@show`;
- `GET /{first}/{second}` → `PublicNodeController@details`;
- решта (три й більше сегментів, інші HTTP-методи на публічних шляхах) → `Route::fallback` із 404 теми.

Оскільки службові маршрути оголошені раніше, вони виграють сегментами за порядком реєстрації (Laravel віддає перший збіг). Пріоритет, що вирішується тут: **службовий шлях → сторінка/тип → 404**. Між сторінкою й типом конфліктів немає (гарантує `PublicPaths`); для детермінованості контролер спершу шукає сторінку, потім тип `content`.

`{first}` обмежується регулярним виразом `ContentNode::SLUG_PATTERN` без якорів (`[a-z0-9]+(-[a-z0-9]+)*`), тому шляхи на кшталт `/favicon.ico` не потрапляють у контролер, а швидко отримують 404 сайту через fallback. Зарезервовані шляхи додатково відсікаються в контролері на випадок рядка в БД, що обійшов валідацію (сценарій `login`).

Видимість: `ContentNode::findBySlug($slug, onlyVisible: true)` плюс перевірка `kind = page` (запит `ContentType::kind` вузла); список типу: `ContentNodeQuery::paginate($type, new NodeQueryParams(onlyVisible: true, ...))`; деталі: вузол за slug у межах `content_type = $type->key` і `visible()`.

*Альтернатива:* `Route::fallback` як єдиний вхід, з розбором шляху вручну. Відхилено: втрачаємо іменовані маршрути й `Route::getRoutes()` для тесту-стража (`ReservedPathsGuardTest` ловить лише маршрути з першим сегментом без параметра; наш `{first}` не потрапляє в нього, що правильно).

### 6. Оболонка й дані шаблонів

Шаблони: `html` (документ, `<title>`, `@vite` теми), `layout` (`@extends('theme::html')`, шапка/футер, `@yield('content')`), `home`, `errors/404`, заглушки `pages/page`, `content/list`, `content/details`. Контролер викликає `ThemeManager::render()`, передаючи спільні дані через один метод-збірку (`PublicData`): `$site` (`SiteSettings`), `$theme` (ім'я), `$title` (документа), `$node`/`$type`/`$paginator` де застосовно, значення вузла з `ContentValueReader::load()` у `$values`. Заголовок: `{title запису} — {назва сайту}`; без запису — назва сайту.

`welcome.blade.php` видаляється. Кандидати сторінки формуються вже за повним каскадом (`{t}/page`, `pages/page-{t}--{id}`, `pages/page-{t}`, `pages/page`; для деталей content `{t}/details--{id}`, `{t}/details`, `content/details`; для списку `{t}/list`, `content/list`), тож зміна 3 лише додає шаблони, не змінюючи контролерів. Ідентифікатор в імені файла: сторінка й деталі content — числовий `id` запису.

### 7. Vite

`vite.config.js` додає до `input` усі файли `themes/*/assets/{css,js}/*.{css,js}` (glob у конфігу). У шаблонах `html` активна тема підключається `@vite(['themes/'.$theme.'/assets/css/app.css', ...])` лише коли такий файл є в маніфесті (`ThemeManager::assets()` перевіряє наявність файлів перед виводом). Режим `dev` працює без змін.

### 8. `theme:check`

Команда `App\Console\Commands\ThemeCheckCommand` перевіряє: `theme.json`, `themes/default`, наявність кожного обов'язкового шаблона через `view()->exists`, коректність `hooks.php` (завантажується в ізольований `ThemeHooks`). Виводить помилки списком, повертає `Command::FAILURE` за наявності.

## Risks / Trade-offs

- [Fallback-маршрут `/{first}` перехоплює шляхи, які пізніше стануть службовими] → тест-страж `ReservedPathsGuardTest` лишається; новий маршрут реєструється **до** публічного блока, тому виграє.
- [`view()->first()` перебирає кандидатів на файловій системі] → Blade finder кешує знайдені шляхи в процесі; кількість кандидатів ≤ 8 на поле, виміряно буде в зміні 3.
- [Хуки з побічними ефектами ламають рендер] → винятки хуків не перехоплюються, але `ThemeException` вказує файл; debug-коментар показує кандидатів.
- [Зіпсована `APP_THEME` кладе весь сайт] → адмінка не залежить від теми (перевірка лише на публічному запиті); `theme:check` показує причину.
- [Заглушки `pages/page`, `content/list`, `content/details` лишаться мінімальними й виглядатимуть сиро] → закрита зміною 3.
- [Публічний `GET /` зникає з `welcome`] → оновити тести й e2e, що на нього покладалися.

## Migration Plan

1. Додати `config/theme.php`, `APP_THEME=default` у `.env.example` (і локальний `.env`).
2. Створити `themes/default` із мінімальним набором шаблонів, видалити `welcome.blade.php`.
3. Зареєструвати сервіси й публічні маршрути; зібрати `npm run build`.
4. Відкат: повернути маршрут `welcome` і видалити публічний блок маршрутів; даних і міграцій немає.

## Open Questions

- Тривалість життя `ThemeManager` під Octane (якщо колись з'явиться): хуки кешуються в singleton, тож для довгоживучого процесу їх треба скидати між запитами; зараз Octane не використовується.

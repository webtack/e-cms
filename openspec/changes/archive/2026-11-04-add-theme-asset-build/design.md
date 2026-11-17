# Design

## Context

Мотивація: див. [proposal.md](proposal.md). Поточний стан, від якого відштовхується підхід:

- `vite.config.js` (один конфіг) через `themeEntries()` додає до входів адмінки всі файли `themes/*/assets/{css,js}/*`. Вихід спільний: `public/build` + `manifest.json`; `npm run build` = `tsc --noEmit && vite build`.
- `ThemeManager::assets()` читає `public/build/manifest.json` (ключі з префіксом `themes/<active>/assets/`, лише `isEntry`) або, якщо є `public/hot`, перелічує файли теми на диску. `html.blade.php` викликає `@vite($assets)`.
- Laravel `Vite` (`__invoke($entrypoints, $buildDirectory)`) приймає каталог збірки другим аргументом, а hot-файл бере зі свого стану (`useHotFile`, за замовчуванням `public/hot`) і перевіряє його до маніфеста: у hot-режимі `$buildDirectory` ігнорується.
- Tailwind 4 не розрахований на препроцесори (Sass, Less, Stylus); документація Tailwind: «Think of Tailwind CSS itself as your preprocessor». `themes/default/assets/css/app.css` — Tailwind-вхід (`@import 'tailwindcss' source(none)` + `@source`).
- `sass` у `node_modules` відсутній; Vite 8 (`laravel-vite-plugin` 3.2) компілює `.scss` сам, коли пакет `sass` встановлено.

Описані компоненти: [docs/site-themes.md](../../../docs/site-themes.md) (розділ «Ресурси теми (Vite)»), [docs/e2e-testing.md](../../../docs/e2e-testing.md) (передумови).

## Goals / Non-Goals

**Goals:**
- Працювати над однією темою (збірка, dev-сервер) без `tsc`, без React-адмінки й без впливу на `public/build`.
- SCSS, компільований JS, бібліотеки з `node_modules` або з файлів теми, розділення на `vendor.*` і `app.*`.
- Мінімальна зміна публічного коду: тема й далі підключається одним викликом у `html.blade.php`.

**Non-Goals:**
- SCSS у темі `default` (лишається Tailwind у чистому CSS).
- Окремий `package.json` на тему / npm workspaces (залежності лежать у кореневому `package.json`).
- Збірка тем із PHP/artisan, автоматична збірка під час деплою, перевірка ресурсів у `theme:check`.
- Мови сайту, регіони блоків, зміна теми в адмінці (поза цією зміною).

## Decisions

### 1. Окремий конфіг `vite.theme.config.js` і запуск через `scripts/theme.mjs`

Тема збирається окремим конфігом, який читає `THEME=<name>`. Скрипти:

```
"theme:dev":   "node scripts/theme.mjs dev"      # ім'я теми обов'язкове
"theme:build": "node scripts/theme.mjs build"    # ім'я необов'язкове
```

`scripts/theme.mjs` розбирає аргумент, перевіряє, що `themes/<name>/assets` існує, і запускає `vite -c vite.theme.config.js` (`build` — послідовно для кожної теми з `assets/`, коли імені немає) із `THEME` у середовищі. Імена без `assets/` пропускаються (збірка всіх), а явно названа тема без `assets/` дає помилку.

Чому не `vite build` з аргументом: Vite не має власних параметрів, а змінна середовища в конфігу — стандартний спосіб. Чому не один конфіг із `--mode`: `emptyOutDir` і набір плагінів адмінки (React, `tsc`) тоді лишаються спільними, а розділення — суть зміни.

Альтернатива: масив конфігів (`defineConfig([...])`) у `vite.config.js` — Vite не збирає кілька конфігів за один виклик, тож не вирішує проблеми незалежного запуску.

### 2. Вихід `public/themes/<name>/` з власним маніфестом і hot-файлом

`laravel({ input, buildDirectory: 'themes/<name>', hotFile: 'public/themes/<name>.hot' })`. Тоді:

```
public/build/               адмінка (не змінюється)
public/hot                  dev-сервер адмінки
public/themes/<name>/       збірка теми + manifest.json
public/themes/<name>.hot    dev-сервер теми
```

`/public/themes` додається до `.gitignore` (як `/public/build`). Dev-сервер теми: порт із `THEME_DEV_PORT` (за замовчуванням 5174), `strictPort: true`, щоб не підмінити порт адмінки тихо.

### 3. Входи за конвенцією `assets/{app,vendor}.{css,scss,js}`

`vite.theme.config.js` додає до `input` лише існуючі `themes/<name>/assets/{vendor,app}.{css,scss,js}`. Усе інше (`_*.scss`, `lib/`, `js/*.js`) — partials. Ключі маніфеста залишаються шляхами від кореня проєкту (`themes/default/assets/app.css`, `themes/x/assets/vendor.scss`).

Замість каталогів `assets/css` і `assets/js` входи лежать у корені `assets/`: SCSS і JS одного бандла (`app.scss` + `app.js`) стоять поруч, а підкаталоги вільні для partials.

Альтернатива: лишити glob по `assets/{css,js}/*`. Відкинуто: будь-який partial став би входом, а vendor/app не відрізнити від решти.

### 4. Vendor явний, а не автоматичний

`vendor.scss`/`vendor.css` і `vendor.js` самі перелічують бібліотеки (`@use 'bootstrap/scss/bootstrap'`, `import 'swiper'`), `app.*` містить код теми. Джерело бібліотеки — пакет із `node_modules` або файл у темі (`assets/lib/...`, імпорт за відносним шляхом): обидва — звичайні ES-імпорти Vite. Плагінам, яким потрібні глобальні змінні, `vendor.js` присвоює їх сам (`window.$ = jQuery`); `app.js` не імпортує те, що вже в `vendor`.

Чому не автоматично (усе з `node_modules` → `vendor` через групи чанків): для JS це працює, для CSS — ні (Sass інлайнить `@use`/`@import` у файл, що імпортує), тож правило було б різним для JS і CSS, а порядок каскаду (`vendor` перед `app`) не під контролем автора. Явний `vendor.*` дає той самий механізм для CSS і JS.

Якщо `app.js` усе ж імпортує модуль, який імпортує й `vendor.js`, Rolldown виносить його у спільний чанк (дубля немає, `@vite` додає `modulepreload`). Це допустимо, але не конвенція.

Файли зберігають хеші в іменах (кеш-бастинг через маніфест); `vendor`/`app` — ключі маніфеста (`vendor.scss` → `vendor-<hash>.css`).

### 5. Підключення: окремий екземпляр `Vite`, порядок vendor → app

`ThemeManager`:

- `assets()` повертає ключі входів активної теми в порядку `vendor`, потім `app` (усередині — як у маніфесті). У prod читає `public/themes/<name>/manifest.json` (немає файла — порожній список, як зараз без маніфеста); у dev (існує `public/themes/<name>.hot`) перелічує `assets/{vendor,app}.*` на диску.
- Новий метод (наприклад `assetTags()`) повертає HTML тегів: `(new Vite)->useHotFile(public_path('themes/<name>.hot'))->useBuildDirectory('themes/<name>')->__invoke($assets)`. Окремий екземпляр, а не глобальний singleton, тож hot-файл теми не підміняє `public/hot` адмінки й тести не залежать від стану.

`html.blade.php` викликає `{!! $assetTags !!}` (або `@themeAssets`) замість `@vite($assets)`; `PublicData` передає тег поряд зі списком `$assets`.

Альтернатива: `@vite($assets, 'themes/x')` без окремого екземпляра. Відкинуто: у hot-режимі Laravel бере глобальний `public/hot`, тож dev-сервер теми ніколи не буде використано.

### 6. Адмінка не знає про теми

`vite.config.js` втрачає `themeEntries()`, `fs` і `path` для тем. `npm run build` лишається `tsc --noEmit && vite build` і пише лише в `public/build`. Правила ізоляції CSS адмінки ([default-theme-styling](../../specs/default-theme-styling/spec.md)) не змінюються.

### 7. Tailwind у `default`, SCSS в інших темах

`vite.theme.config.js` підключає `tailwindcss()` для всіх тем (плагін не чіпає файли без Tailwind-директив), `sass` — залежність проєкту. `default/assets/app.css` лишається чистим CSS; його `@source` коригуються на нову глибину (`../views`, `../../*/views`, `../../../vendor/...`). Тема на SCSS не використовує Tailwind-директив у `.scss`.

### 8. Міграція існуючої теми

`themes/default/assets/css/app.css` → `themes/default/assets/app.css` (`git mv`), `@source` коригуються. Тести `ThemeManagerTest`/`DefaultThemeStylingTest` читають `public/themes/default/manifest.json` і ключ `themes/default/assets/app.css`; тест порядку `vendor` → `app` будує маніфест фікстурної теми в `public/themes/<унікальне-ім'я>/` і прибирає його в `tearDown`.

## Risks / Trade-offs

- [Забули зібрати тему після оновлення: `public/themes/default` немає, сторінка без стилів] → `assets()` дає порожній список без помилки (вимога «Тема без ресурсів»); у `docs/site-themes.md` і `docs/e2e-testing.md` додати команду в передумови; e2e `public-site` це виявить.
- [Rolldown дублює або виносить у спільний чанк модуль, що імпортують і `vendor.js`, і `app.js`] → конвенція «бібліотеки лише у `vendor`»; перевірка в задачах: збірка `default` + фікстурна тема з `vendor.js`, у вихідних файлах немає дубля.
- [Hot-файл теми лишився після аварійної зупинки dev-сервера: сторінки шукають мертвий порт] → той самий ризик, що й у `public/hot` адмінки; плагін Laravel видаляє файл при зупинці; у документації — «видали `public/themes/<name>.hot`».
- [Два dev-сервери (адмінка 5173, тема 5174) і CORS] → плагін Laravel виставляє CORS для `APP_URL`, як і для адмінки; порт 5174 з `strictPort`, щоб конфлікт був явною помилкою.
- [`sass` — нова dev-залежність] → лише `devDependencies`, не потрапляє в прод-збірку адмінки.
- [**BREAKING** для сторонніх тем у `assets/css` і `assets/js`] → у проєкті одна тема з ресурсами (`default`); міграція описана в задачах і в `docs/site-themes.md`.

## Migration Plan

1. `npm install --save-dev sass`.
2. Перенести `default/assets/css/app.css` → `default/assets/app.css`, виправити `@source`.
3. `npm run build` (адмінка) і `npm run theme:build` (теми); перевірити `public/themes/default/manifest.json`.
4. Видалити старі файли тем із `public/build` (зникнуть самі після повторної `npm run build`).
5. Відкат: повернути `themeEntries()` у `vite.config.js` і файл у `assets/css/`; `public/themes` видалити.

## Open Questions

- Чи додавати `theme:build` до деплой-скрипта — деплой у цьому репозиторії не описаний, вирішується під час розгортання, на поведінку не впливає.

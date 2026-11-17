# Proposal

## Why

Ресурси теми зараз є лише додатковими входами збірки адмінки: `vite.config.js` збирає разом адмінку (React) і `themes/*/assets/{css,js}/*`, а результат лягає в спільні `public/build` і `manifest.json`. Тому над темою не можна працювати окремо: кожна правка тягне `tsc` і збірку адмінки, збірка адмінки стирає файли тем, dev-сервер теми конфліктує з dev-сервером адмінки через один `public/hot`. Крім того, немає SCSS (пакета `sass` у проєкті немає), немає JS теми і немає розділення на власний код і код бібліотек: кожен файл у `assets/css` чи `assets/js` стає окремим входом, тож partial неможливо відрізнити від бандла.

## What Changes

- Тема збирається окремо від адмінки: нові команди `npm run theme:dev -- <name>` (dev-сервер з HMR) і `npm run theme:build [-- <name>]` (збирає одну тему або, без імені, усі) з окремим конфігом `vite.theme.config.js`, без `tsc` і без адмінки.
- Результат збірки теми лягає в `public/themes/<name>/` зі своїм `manifest.json`; dev-сервер теми має власний hot-файл і власний порт. Збірка адмінки (`npm run build`) і збірка теми не стирають файли одна одної.
- Входи теми задаються конвенцією, а не glob по всіх файлах: `assets/app.{css,scss,js}` (власний код теми) і `assets/vendor.{css,scss,js}` (бібліотеки). Решта файлів в `assets/` — partials, вони імпортуються, але окремими входами не стають.
- Підтримується SCSS (додається залежність `sass`). Бібліотеки підключаються імпортом із `node_modules` або за шляхом усередині теми (`assets/lib/...`). Глобальні змінні, потрібні плагінам, виставляє сам `vendor.js`.
- Шаблон `html` підключає `vendor` перед `app` з manifest теми (`@vite($assets, 'themes/<name>')`); `ThemeManager::assets()` читає manifest теми, а не `public/build/manifest.json`; у dev-режимі використовується hot-файл теми.
- `vite.config.js` (адмінка) перестає знати про теми: `themeEntries()` прибирається.
- **BREAKING**: розташування входів теми змінюється. `themes/default/assets/css/app.css` переїжджає в `themes/default/assets/app.css`, а ключ manifest стає `themes/default/assets/app.css`. Файли з `assets/css/*` і `assets/js/*`, що не названі `app`/`vendor`, більше не є входами. Після оновлення потрібна `npm run theme:build`.
- Тема `default` лишається на Tailwind у звичайному `app.css`, без SCSS: Tailwind 4 не призначений для роботи з препроцесорами (Sass, Less, Stylus), тому Tailwind-вхід лишається чистим CSS.

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `site-themes`: вимога «Ресурси теми» змінюється (окрема збірка, `outDir` і manifest теми, входи `app`/`vendor`, підключення з manifest теми); додаються вимоги до конвенції входів, до SCSS і бібліотек та до незалежності збірок теми й адмінки.
- `default-theme-styling`: стилі теми `default` підключаються з `assets/app.css`, а не `assets/css/app.css`; Tailwind-вхід лишається звичайним CSS.

## Impact

- Код: `vite.config.js` (прибирається `themeEntries()`), новий `vite.theme.config.js`, `package.json` (скрипти `theme:dev`, `theme:build`, залежність `sass`), `app/Theme/ThemeManager.php` (`assets()`, hot-файл і каталог збірки теми), `themes/default/views/html.blade.php` (`@vite` з каталогом збірки теми), `themes/default/assets/css/app.css` → `themes/default/assets/app.css` (шляхи `@source` коригуються), `.gitignore` (`/public/themes`).
- Тести: `tests/Feature/Theme/ThemeManagerTest.php` і `DefaultThemeStylingTest.php` (шлях manifest і ключ), перевірка входів і порядку `vendor` → `app`.
- Збірка: окремі `public/themes/<name>/`; `public/build` лишається лише адмінкою.
- Документація: `docs/site-themes.md` (розділ «Ресурси теми (Vite)»: структура `assets/`, команди, vendor/app, SCSS, бібліотеки, Tailwind і Sass), `docs/e2e-testing.md` (передумова: окрім `npm run build` потрібна `npm run theme:build`), `docs/README.md` (опис рядка про теми, якщо змінюється).
- E2E: `e2e/public-site.spec.js` і `e2e/smoke.spec.js` запускаються, бо публічна сторінка змінює підключення стилів.

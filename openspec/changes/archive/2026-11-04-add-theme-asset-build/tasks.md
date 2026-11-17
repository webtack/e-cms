# Tasks

## 1. Залежності й окрема збірка теми

- [x] 1.1 Додати `sass` у `devDependencies` (`npm install --save-dev sass` на хості) і перевірити, що `node_modules/sass` існує, а `package.json`/`package-lock.json` оновлені.
- [x] 1.2 Створити `vite.theme.config.js`: читає `THEME`, додає до `input` лише існуючі `themes/<name>/assets/{vendor,app}.{css,scss,js}`, плагіни `laravel` (`buildDirectory: 'themes/<name>'`, `hotFile: 'public/themes/<name>.hot'`) і `tailwindcss`, порт з `THEME_DEV_PORT` (5174) із `strictPort`. Англійські коментарі/JSDoc з `@example`. Перевірка: `THEME=default npx vite build -c vite.theme.config.js` створює `public/themes/default/manifest.json` і не чіпає `public/build`.
- [x] 1.3 Створити `scripts/theme.mjs` і скрипти `theme:dev` (ім'я обов'язкове) та `theme:build` (ім'я необов'язкове; без імені збирає всі теми з `assets/` послідовно, явно названа тема без `assets/` — помилка з ненульовим кодом) у `package.json`. Перевірка: `npm run theme:build -- default`, `npm run theme:build`, `npm run theme:build -- missing` (ненульовий код і зрозуміле повідомлення).
- [x] 1.4 Прибрати `themeEntries()` і непотрібні імпорти з `vite.config.js`, додати `/public/themes` у `.gitignore`. Перевірка: `npm run build` на хості завершується успішно, `public/build/manifest.json` не містить ключів `themes/`, а попередньо зібрана `public/themes/default/` лишилась на місці.

## 2. Входи за конвенцією й міграція теми `default`

- [x] 2.1 Перенести `themes/default/assets/css/app.css` у `themes/default/assets/app.css` (`git mv`), виправити шляхи `@source` на нову глибину (`../views`, `../../*/views`, `../../../vendor/...`), лишити файл чистим CSS (Tailwind). Перевірка: `npm run theme:build -- default`, у `public/themes/default/assets/app-*.css` є утиліти з шаблонів теми (наприклад `.max-w-prose`), а в адмінському CSS (`public/build`) їх немає.
- [x] 2.2 Перевірити конвенцію на тимчасовій темі `scratch` (створити й видалити після перевірки, у репозиторій не комітити): `app.scss` з `_vars.scss` (partial), `vendor.scss` з імпортом пакета з `node_modules`, `vendor.js` з імпортом файла з `assets/lib/` і `window.X = ...`, `app.js`. Перевірка: у `public/themes/scratch/manifest.json` лише входи `app` і `vendor` (partial без окремого файла), змінні SCSS розкриті, код бібліотеки лише у `vendor`, у `app` немає дубля; помилка в `app.scss` дає ненульовий код і ім'я файла.

## 3. Підключення в публічному шаблоні

- [x] 3.1 Переписати `ThemeManager::assets()`: маніфест `public/themes/<active>/manifest.json`, hot-режим за `public/themes/<active>.hot`, порядок `vendor` → `app`, порожній список без маніфеста й без `assets/`. Додати метод тегів на окремому екземплярі `Vite` (`useHotFile`, `useBuildDirectory('themes/<active>')`). JSDoc/PHPDoc англійською з `@example`.
- [x] 3.2 Передати теги з `PublicData` і підключити їх у `themes/default/views/html.blade.php` замість `@vite($assets)`, оновити коментар змінних шаблона. Перевірка: `docker compose exec app php artisan test --compact --filter=Theme`.
- [x] 3.3 Оновити тести: `ThemeManagerTest` (шлях `public/themes/default/manifest.json`, ключ `themes/default/assets/app.css`), `DefaultThemeStylingTest`, плюс нові тести порядку `vendor` → `app` і теми без маніфеста на фікстурній темі з унікальним ім'ям у `public/themes/` (прибирається в `tearDown`). Перевірка: `docker compose exec app php artisan test --compact --filter=Theme` проходить, тест порядку падає, якщо порядок змінити.
- [x] 3.4 Прогнати Pint для змінених PHP-файлів (`docker compose exec app vendor/bin/pint --dirty --format agent`) і e2e `npm run test:e2e -- e2e/public-site.spec.js` та `e2e/smoke.spec.js` (після `npm run build` і `npm run theme:build`). Повний PHP-набір і повний e2e не запускати: міграцій, глобальних middleware й автентифікації зміна не чіпає. Результат: Pint пройшов, e2e 12 з 13 зелені; `public-site.spec.js` «the main menu is in the header» падає через дані dev-БД (там уже є меню `main`, тож спек не створює своє «E2E Menu News») — не пов'язано з цією зміною.

## 4. Dev-режим теми

- [x] 4.1 Перевірити dev-режим вручну: `npm run theme:dev -- default` разом з `npm run dev`; на `http://localhost:8080` публічна сторінка бере ресурси з `localhost:5174`, адмінка з `localhost:5173`, зміна `app.css` оновлюється без перезавантаження; після зупинки dev-сервера `public/themes/default.hot` зникає й сторінка працює зі збірки. Результат записати в `docs/site-themes.md` (задача 5.1).

## 5. Документація

- [x] 5.1 Оновити `docs/site-themes.md` (українською): структура `assets/` (входи `app`/`vendor`, partials), команди `theme:dev`/`theme:build`, `public/themes/<name>/` і hot-файл, vendor/app і глобальні змінні, SCSS, бібліотеки з `node_modules` і з файлів теми, чому `default` лишається на Tailwind без SCSS, порт `THEME_DEV_PORT`, прибрати опис glob по `assets/{css,js}`. Перевірка: усі шляхи й команди в тексті виконуються як написано (виконати з 1.3).
- [x] 5.2 Оновити `docs/e2e-testing.md`: у передумовах поряд з `npm run build` додати `npm run theme:build`; у таблиці «Область → спек» рядок про шаблони теми згадує збірку теми. Перевірка: перелік передумов збігається з тим, що потрібно для `e2e/public-site.spec.js`; посилання в `docs/README.md` дійсні, рядок про `site-themes.md` оновлено, якщо змінився опис.

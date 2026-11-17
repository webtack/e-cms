# Tasks

## 1. Ізоляція збірки адмінки

- [x] 1.1 У `resources/css/app.css` замінити `@import 'tailwindcss';` на `@import 'tailwindcss' source(none);` і додати `@source '../../resources';`. Зберегти вендорний `@source` для шаблонів пагінації. Не додавати `@source` на `storage/framework/views` (кеш Blade містить шаблони теми).
- [x] 1.2 Перевірити, що `themes/default/assets/css/app.css` і його `@source` не змінені.

## 2. Перевірка збірки

- [x] 2.1 На хості виконати `npm run build`.
- [x] 2.2 Grep по `public/build/assets/app-*.css` (CSS адмінки, його ім'я з `public/build/manifest.json`): класів `.max-w-prose` і `.gap-x-6` немає; у CSS теми (`themes/default/assets/css/app.css`) вони є. `.aspect-video` не перевіряємо: адмінка використовує його через `@apply` у правилі iframe.
- [x] 2.3 Порівняти кількість правил адмінського CSS до і після зміни. Різниця не повинна містити класів, які використовують джерела адмінки (`resources/`).

## 3. Документація

- [x] 3.1 У `docs/site-themes.md` (українською) додати розділ про ізоляцію стилів адмінки від теми з посиланням на `openspec/specs/default-theme-styling`. Оновити `docs/README.md`, якщо змінюється структура документів.

## 4. Перевірка поведінки

- [x] 4.1 PHP-тести не змінюються, тому PHP-тести не запускаються.
- [x] 4.2 Запустити лише спек оболонки адмінки: `npm run test:e2e -- e2e/admin-shell.spec.js`. Зміна CSS впливає на вигляд адмінки, тому спек потрібен; повний `npm run test:e2e` не потрібен.

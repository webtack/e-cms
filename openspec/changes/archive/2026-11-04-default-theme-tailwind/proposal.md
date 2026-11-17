# Proposal

## Why

Тема `default` після скелета (`add-theme-skeleton`) і шаблонів полів віддає голий HTML з одним плоским `app.css` на кілька рядків. Сайт без стилів не має прийнятного вигляду навіть для демо та для перевірки системи тем. Адмінка вже збирає Tailwind 4 через `@tailwindcss/vite`, тож стилізувати публічну тему тим самим інструментом дешевше, ніж писати окремий CSS-фреймворк або тягнути нову UI-бібліотеку.

## What Changes

- `themes/default/assets/css/app.css` переписується на Tailwind 4: `@import 'tailwindcss'` і `@source` на шаблони `themes/*/views`, щоб утиліти з усіх тем потрапляли в збірку. Стилі елементів пишуться утилітами Tailwind у шаблонах; `app.css` містить імпорт, `@source`, токени `@theme` і звичайні селектори `.richtext` для вмісту з редактора. Класи-хуки шаблонів лишаються без змін.
- Шаблони `default` отримують Tailwind-утиліти в розмітці: `html`/`layout` (шапка з логотипом, назвою і меню `main`, основна зона з обмеженою шириною, футер з контактами), `home`, `pages/page`, `content/list` і картка `content/preview`, `content/details`, `blocks/block`, `menus/menu` і `menus/list` (горизонтальне меню на десктопі, зручне на мобільному), `errors/404`, а також шаблони полів `fields/*` (підписи, значення, зображення, посилання, YouTube, richtext).
- Для полів задається узгоджений вигляд: відступи й типографіка для `richtext`, адаптивні зображення, блок YouTube із співвідношенням сторін 16:9.
- Стилі працюють на мобільному (375px) без горизонтальної прокрутки сторінки; на десктопі контент центрується з обмеженням ширини.
- Класи-хуки, які вже є в шаблонах і використовуються як контракт для перевизначення іншими темами (`site-header`, `site-main`, `site-footer`, `menu`, `menu--{key}`, `menu__item`, `field`, `field-{ftype}`, `field--{key}`, `field__label`, `field__item`, `page`, `record-list`), лишаються на місці. Утиліти додаються поруч із ними, а не замість них.
- Нових npm-залежностей не додаємо: без `@tailwindcss/typography`, без UI-бібліотек, без React на публічній частині. Шрифт і кольори задаються токенами Tailwind `@theme` у файлі теми.
- Темний режим, перемикач теми і шрифти з CDN поза межею: це окремі рішення, у цій зміні не робимо.

**Не BREAKING**: змінні шаблонів, маршрути, `ThemeManager`, хуки й каскад кандидатів не змінюються. Зміна стосується лише вигляду `default` теми.

## Capabilities

### New Capabilities
- `default-theme-styling`: визуальні вимоги до теми `default`: Tailwind-збірка її CSS через Vite, відповідність шаблонів узгодженому вигляду (шапка, меню, сторінки, списки, картки, поля), адаптивність до 375px, збереження класів-хуків для перевизначення в інших темах.

### Modified Capabilities
- Немає. Поведінка рендеру, каскаду шаблонів і маршрутизації не змінюється; `site-themes` описує лише те, що `default` має `assets/css/app.css`, і цей факт лишається правдою.

## Impact

- Код: `themes/default/assets/css/app.css`, `themes/default/views/**/*.blade.php` (layout, html, home, pages, content, blocks, menus, errors, fields). `vite.config.js` і `resources/css/app.css` (адмінка) не змінюються. Tailwind 4 і `@tailwindcss/vite` уже є в `package.json`.
- Збірка: `npm run build` на хості (або `npm run dev`), щоб `public/build/manifest.json` містив `themes/default/assets/css/app.css`.
- Тести PHP (змінна область): `tests/Feature/Theme` і `tests/Feature/Public/PublicRoutesTest.php`, якщо вони перевіряють розмітку; змін у PHP-логіці немає.
- E2E (змінна область): `e2e/public-site.spec.js` (публічні сторінки, меню, 404, службові шляхи) і `e2e/smoke.spec.js`; обидва — за таблицею «Шаблони теми» в `docs/e2e-testing.md`.
- Документація, які оновлюються (українською): `docs/theme-templates.md` (розділ «Шаблони `default`» і описи класів, якщо змінюється структура розмітки), `docs/site-themes.md` (розділ «Ресурси теми (Vite)»: `default/assets/css/app.css` тепер Tailwind-збірка, а `@source` охоплює шаблони тем). Нових файлів у `docs/` немає, тож `docs/README.md` не змінюється.
- Залежності: без змін.

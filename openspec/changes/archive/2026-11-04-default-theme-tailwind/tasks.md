# Tasks

## 1. Збірка стилів теми

- [x] 1.1 Переписати `themes/default/assets/css/app.css`: `@import 'tailwindcss';`, `@source` на `../../views` і на `views` інших тем (`../../../*/views`), блок `@theme` з системним стеком шрифтів і кольорами, а також звичайні селектори `.richtext` (заголовки, абзаци, списки, посилання, цитати, таблиці) без `@apply`. Перевірити збірку `npm run build` на хості без помилок і що `public/build/manifest.json` містить `themes/default/assets/css/app.css`
- [x] 1.2 Перевірити, що `@source` потрапляє в збірку: клас, який використано лише в `themes/default/views/menus/list.blade.php` (наприклад `underline`), є в зібраному CSS (grep по `public/build/assets/*.css`); і що правило `.richtext h2` є в тому самому файлі

## 2. Каркас сторінки: html, layout, home, 404

- [x] 2.1 Стилізувати утилітами `html.blade.php` (клас на `<body>`: шрифт, колір, фон; `@vite` без змін), `layout.blade.php` (шапка з логотипом, назвою, меню `main` з `flex-wrap`; основна зона з `max-w` і `px-4`/`lg:px-6`; футер з відступом зверху), `home.blade.php` і `errors/404.blade.php`, зберігши класи `site-header`, `site-main`, `site-footer` як хуки; перевірити `npm run build` і відкриття `http://localhost:8080/` у браузері на 375px і 1440px, що горизонтальної прокрутки немає
- [x] 2.2 Додати тест `tests/Feature/Theme/DefaultThemeStylingTest.php`: головна при `APP_THEME=default` містить `site-header`, `site-main`, `site-footer` і посилання на `themes/default/assets/css/app.css` (або на зібраний файл з manifest), 404 має статус 404 і містить `site-main`; перевірити `docker compose exec app php artisan test --filter=DefaultThemeStylingTest`
- [x] 2.3 Розширити `e2e/public-site.spec.js` тестом на 375px: `document.documentElement.scrollWidth <= window.innerWidth` на головній і на сторінці зі списком, а шапка має ненульові відступи (`getComputedStyle`); перевірити `npm run test:e2e -- e2e/public-site.spec.js`
- [x] 2.4 Оновити `docs/site-themes.md` у розділі «Ресурси теми (Vite)»: `default/assets/css/app.css` є Tailwind-збіркою, `@source` охоплює `views` тем; перевірити, що посилання в розділі не зламані

## 3. Меню, список, картка, сторінка, блок

- [x] 3.1 Стилізувати `menus/menu.blade.php` і `menus/list.blade.php`: горизонтальний список з `flex-wrap` на широкому екрані, вкладені пункти з відступом (`ml-*`) і видимою різницею для батьківського; зберегти `menu`, `menu--{key}`, `menu__item`, `menu__item`-класи та `css_class` пункту; перевірити `docker compose exec app php artisan test --filter=MenuRendererTest`
- [x] 3.2 Стилізувати `content/list.blade.php` (заголовок, `record-list` у стовпчик з відступами, пагінатор відокремлений), `content/preview.blade.php` (`card`, `card__title`) і `content/details.blade.php`, `pages/page.blade.php` (`page` з обмеженням ширини), `blocks/block.blade.php` (`block`, `block--{slug}`); перевірити `docker compose exec app php artisan test --filter=PublicRoutesTest`
- [x] 3.3 Оновити `docs/theme-templates.md`: розділ «Шаблони `default`» і «Меню», якщо змінилися описані класи; перевірити, що всі згадані класи є в шаблонах (grep)
- [x] 3.4 Розширити `e2e/public-site.spec.js` перевіркою, що на сторінці з меню `main` пункти видимі, а шапка має `flex`-розкладку (`getComputedStyle(...).display === 'flex'`); перевірити `npm run test:e2e -- e2e/public-site.spec.js`

## 4. Поля

- [x] 4.1 Стилізувати шаблони `fields/field.blade.php` і `fields/field-*.blade.php` (15 типів): `field` з відступом, `field__label` дрібнішим і приглушеним, `field__item` з відступом; у `field-richtext.blade.php` додати клас `richtext` до `field__item` (стилі вмісту в `app.css`, див. 1.1); `field-youtube` з `aspect-video w-full` обгорткою; `field-image` і `field-file` з `max-w-full h-auto` і підписами `field__caption`/`field__description`; зберегти всі класи `field`, `field-{ftype}`, `field--{key}`; перевірити `docker compose exec app php artisan test --filter=DefaultFieldTemplatesTest`
- [x] 4.2 Розширити `tests/Feature/Theme/DefaultFieldTemplatesTest.php` перевіркою, що для `richtext` на `field__item` є клас `richtext`, а для `youtube` є `aspect-video`; перевірити `docker compose exec app php artisan test --filter=DefaultFieldTemplatesTest`
- [x] 4.3 Оновити `docs/theme-templates.md` у розділі «Шаблони `default`» (класи полів, `richtext`); перевірити, що приклади в документі відповідають розмітці
- [x] 4.4 Розширити `e2e/public-site.spec.js` перевіркою зображення в полі: ширина `img` не перевищує ширину контенту на 375px; перевірити `npm run test:e2e -- e2e/public-site.spec.js`

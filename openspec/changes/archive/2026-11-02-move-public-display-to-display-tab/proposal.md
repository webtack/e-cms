# Proposal

## Why

Форма налаштувань типу (`/edit`) стала задовгою: крім основних полів вона містить секції «Список записів» і «Публічне відображення». Вкладка «Розділ» — окрема сторінка зі своєю формою й кнопкою збереження — існує лише для виду `content`, а «Публічне відображення» стосується всіх трьох видів. Логічніше зібрати все, що визначає, як тип виглядає на сайті, в одній вкладці й розвантажити форму налаштувань.

## What Changes

- Вкладка «Розділ» перейменовується на **«Відображення»** (`Display`): адреса `/{section}/{key}/display` замість `/content-types/{key}/section`, заголовок сторінки й пункт меню.
- Вкладка «Відображення» з'являється для **всіх видів** (`content`, `page`, `block`) у меню між «Налаштуваннями» та «Полями» (для `content` — перед «SEO»).
- Секція «Публічне відображення» (`settings.public`) **переноситься** з форми налаштувань типу на вкладку «Відображення» без зміни її поведінки, обмежень за видом і формату збережених даних.
- Вміст вкладки: для `content` — блок значень розділу (`section_title`, `section_description`, `section_cover`) і блок «Публічне відображення»; для `page` і `block` — лише «Публічне відображення» (власних значень розділу в них немає).
- Вкладка має **одну спільну кнопку «Зберегти»**, що зберігає і значення розділу, і `settings.public` одним запитом (`PUT /{section}/{key}/display`) атомарно: помилка в будь-якій частині не зберігає нічого.
- Форма налаштувань типу лишає основні поля й секцію «Список записів»; збереження форми більше не приймає `settings.public`.
- Вкладка «SEO» лишається як є (лише `content`, власна форма й `PUT /content-types/{key}/values`).
- **BREAKING** (лише адмінка): адреса `/admin/content-types/{key}/section` зникає (404), іменований маршрут `admin.content-types.section` замінено на `admin.{section}.display`.

## Capabilities

### New Capabilities

### Modified Capabilities
- `admin-content-types`: вкладка «Розділ» → «Відображення» для всіх видів зі спільним збереженням; секція «Публічне відображення» виходить із форми налаштувань; форма й збереження змін типу без `settings.public`; меню розділу.
- `admin-page-types`: пункт «Відображення» в меню розділу типу сторінки.
- `admin-block-types`: пункт «Відображення» в меню розділу типу блоку.

## Impact

- Код: `routes/web.php` (маршрути `display` у циклі видів, прибрати `/section`), `ContentTypeController` (замість `sectionTab()` — `displayTab()`; прибрати `publicOptions` з `edit()` і `mergePublicSettings` з `update()`), новий контролер й form request для `PUT …/display`, `StoreContentTypeRequest::sharedRules()` (правила `settings.public` переїжджають у запит вкладки), `ContentTypeValuesController` лишається для SEO; React: `Pages/Admin/ContentTypes/Section.tsx` → `Display.tsx`, нова форма вкладки (значення розділу + `PublicDisplaySettingsSection`), `ContentTypeNav.tsx`, `ContentTypeForm.tsx`, `Edit.tsx`, `TypeValuesPanels.tsx`; `lang/uk.json`, `lang/en.json` (ключ «Display»).
- Тести: `ContentTypeValuesTest`, `ContentTypeEditTest` (сценарії публічних налаштувань переходять у новий тест вкладки), `PageTypeTest`, `BlockTypeTest`; e2e `admin-content-types.spec.js`, `admin-page-types.spec.js`, `admin-block-types.spec.js`.
- Docs (оновити): `docs/admin-content-types.md` (секція «Публічне відображення у формі», опис меню й вкладок, маршрути, тести), `docs/admin-page-types.md`, `docs/admin-block-types.md` (меню розділу), `docs/e2e-testing.md` (таблиці «Область → спек» і опис спеків), за потреби `docs/content-model.md` і `docs/content-querying.md` (посилання на форму), перевірити `docs/README.md`.
- Міграцій БД немає, формат `settings.public` і значень `section_*` не змінюється.

# Proposal

## Why

Після попередньої зміни (`stay-on-edit-preview-home-meta`) вибір мета-полів лежить на вкладці «Відображення», де йому не місце: це SEO-налаштування. У типів `page` немає вкладки «SEO» зі значеннями самого типу. Сайт також не бере мета-даних, записаних у вкладці «SEO» типу: ланцюжок мета-даних цього джерела не має.

## What Changes

- Блок «Мета-дані» (вибір полів для заголовка, опису й зображення, `settings.public.meta`) **переїжджає** з вкладки «Відображення» на вкладку «SEO» типу. Він стоїть окремою секцією з бордером під значеннями `meta_*` типу, не має власної кнопки: вкладка «SEO» — одна форма з однією липкою панеллю «Зберегти», що одним атомарним запитом зберігає значення `meta_*` типу й `settings.public.meta`, не чіпаючи решти `settings`. З вкладки «Відображення» блок зникає; її збереження не стирає збережений `meta`.
- Типи `page` отримують вкладку «SEO» (`/admin/page-types/{key}/seo`) так само, як `content`: значення `meta_title`, `meta_description`, `meta_image` **самого типу** та блок «Мета-дані». Для цього тип `page` стає власником значень цих трьох системних полів (поля в типах сторінок уже є; міграція не потрібна). Тип `block` вкладки не має.
- Ланцюжок мета-даних публічної сторінки отримує нове джерело. Порядок для кожного значення окремо: `meta_*` запису → поле, вибране в блоці «Мета-дані» → (лише для заголовка) `title` запису → `meta_*` самого типу (вкладка «SEO» типу) → налаштування сайту. Сторінка без запису (список записів типу) бере `meta_*` типу, потім налаштування сайту; головна й 404 — лише налаштування сайту.
- Заголовок документа `<title>` більше не дописує « — {назва сайту}»: це значення з ланцюжка як є (для запису без мета-заголовка — `title` запису). Якщо суфікс потрібен, його додають хуки або шаблони теми.
- **BREAKING (поведінка):** `<title>` сторінок, що раніше мали вигляд «{title} — {назва сайту}», тепер дорівнює «{title}».

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `content-model`: «Системні поля метаданих і розділу» і «Власники значень поля» — поля `meta_*` типу `page` належать і записам, і самому типу.
- `admin-content-types`: «Вкладки «Відображення» і «SEO» типу» (вкладка «SEO» є в `content` і `page`), «Секція «Публічне відображення»…» (без підблоку «Мета-дані»); нова вимога про блок «Мета-дані» на вкладці «SEO».
- `admin-page-types`: «Меню розділу типу сторінки» — пункт «SEO».
- `public-site`: «Мета-дані сторінки…» — нове джерело (`meta_*` типу) і вибір `title`.

## Impact

- Backend: `App\Content\SystemFields` (власники `meta_*` для `page`); `ContentTypeController` (`seoTab()`, `typeValuesProps()` більше не обмежені видом `content`; пропси блоку «Мета-дані» для вкладки «SEO», з `displayTab()` вони прибираються); `ContentTypeValuesController` (і маршрути `…/values` для `page-types`; `update` атомарно зберігає значення й `settings.public.meta`, лише `content` і `page`); `routes/web.php`; `PublicDisplaySettings` (`formOptions() без мета-частини, окремий метод опцій для SEO), `ContentType::assertValidPublicMeta()`, `UpdateContentTypeDisplayRequest` і `ContentTypeDisplayController::mergePublicSettings()` (більше не приймають `meta` і не стирають його); `App\Theme\PageMeta` (ланцюжок із `meta_*` типу через `ContentValueReader::loadForType()`), `App\Theme\PublicData` (заголовок без суфікса назви сайту).
- Frontend: `PublicDisplaySettingsSection.tsx`/`TypeDisplayForm.tsx` (без підблоку), `Pages/Admin/ContentTypes/Seo.tsx` і новий компонент блоку «Мета-дані» з бордером, `ContentTypeNav.tsx` (пункт «SEO» для `page`), переклади.
- Тести: Feature (`ContentTypeDisplayTest`, `ContentTypeValuesTest`, `PageTypeTest`, `SystemFieldsTest`, `TypeValuesTest`, Theme/Public), e2e (`admin-content-types`, `admin-page-types`, `public-site`).
- Docs: `docs/admin-content-types.md`, `docs/admin-page-types.md`, `docs/content-model.md`, `docs/content-values.md`, `docs/public-site.md`, `docs/theme-templates.md`, `docs/e2e-testing.md`.

## Припущення

- Вкладка «SEO» типу `page` має ті самі три поля значень типу, що й у `content` (рішення власника).
- Блок «Мета-дані» не має власної кнопки: значення `meta_*` типу й `settings.public.meta` зберігаються одним атомарним запитом (як на вкладці «Відображення»), щоб липка панель дій була одна.
- Для заголовка `title` запису стоїть після власного `meta_title` й вибраного поля, але **перед** `meta_title` типу: `meta_title` типу діє лише там, де `title` запису немає (сторінка списку типу). Окремого пункту «Заголовок запису» у виборі немає, бо `title` виводиться за замовчуванням.
- Назва сайту в `<title>` лише там, де немає ні запису, ні мета-заголовка (головна, 404); суфікса до значень запису й типу немає.
- Існуючі значення `settings.public.meta` лишаються чинними (формат той самий).

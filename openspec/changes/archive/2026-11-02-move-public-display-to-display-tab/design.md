# Design

## Context

Мотивацію й межі див. у `proposal.md`. Поточний стан (прочитано в коді):

- Вкладка «Розділ» — `GET /content-types/{key}/section` (`ContentTypeController::sectionTab()`, сторінка `Section.tsx`), зареєстрована окремо від циклу видів у `routes/web.php` і лише для `content`. Форма вкладки — `TypeValuesPanels` (панель `section`), зберігає `PUT /content-types/{key}/values` через `ContentTypeValuesController` → `ContentValueWriter`, помилки приходять як `values.{key}`.
- «Публічне відображення» — `PublicDisplaySettingsSection` у `ContentTypeForm`, дані `publicOptions` приходять з `edit()`, зберігається разом із формою через `ContentTypeController::update()` (`mergeSettings()` → `mergePublicSettings()`), правила `settings.public.*` лежать у `StoreContentTypeRequest::sharedRules()`, ключі перевіряє `ContentType::assertValidPublicSettings()` при `save`.
- Меню — `ContentTypeNav` (пункти `settings | section | seo | fields | nodes`, `section` лише для `content`).

Документація, що описує ці компоненти: `docs/admin-content-types.md` (меню, маршрути, «Публічне відображення у формі»), `docs/admin-page-types.md`, `docs/admin-block-types.md`, `docs/content-model.md`, `docs/content-querying.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- Вкладка «Відображення» для `content`, `page`, `block` з єдиним збереженням значень розділу й `settings.public`.
- Форма налаштувань без `settings.public`.

**Non-Goals:**
- Зміна формату `settings.public`, значень за замовчуванням, обмежень за видом і валідації ключів.
- Зміна вкладки «SEO» та панелі `section` як системної панелі полів (`SystemFields::panel()` лишає ідентифікатор `section` — це внутрішній ключ, не підпис).
- Публічні маршрути й шаблони.

## Decisions

**1. Маршрути `display` — у циклі видів.** `GET /{section}/{key}/display` (`admin.{section}.display`) і `PUT /{section}/{key}/display` (`admin.{section}.display.update`) додаються до `$typeRoutes`, тож вид і `kind` беруться зі звичного механізму (`ResolvesTypeSection`, `Route::bind` у межах виду). Окремий маршрут `/content-types/{key}/section` видаляється без редіректу (адмінка закрита, закладок на неї немає; 404 зафіксовано в специфікації). *Альтернатива:* лишити `/section` для `content` і додати `/display` лише для `page`/`block` — відхилено, бо розбиває одну вкладку на дві адреси.

**2. Єдине збереження — окремий `PUT …/display`, а не розширення `PUT …/values`.** Новий `ContentTypeDisplayController::update` приймає `values` (лише `content`; для `page`/`block` ключ ігнорується, бо в них немає полів розділу) і `settings.public`. Запит `UpdateContentTypeDisplayRequest` перевіряє форму `values` (як `UpdateContentTypeValuesRequest`) і правила `settings.public.*`, які переїжджають із `StoreContentTypeRequest::sharedRules()`. `ContentTypeValuesController` лишається для «SEO». *Альтернатива:* додати `settings.public` у `PUT …/values` — відхилено: той ендпоїнт обслуговує SEO, а змішування ламає його контракт «зберігає лише поля своєї вкладки» і маршрут `values` існує тільки для `content`.

**3. Атомарність.** Контролер виконує запис значень розділу (`ContentValueWriter::write`, що кидає `ValidationException` з ключами без префікса, які контролер перейменовує в `values.{key}`) і `ContentType::update(['settings' => …])` у `DB::transaction`. Помилка в будь-якому кроці відкочує обидва, тож «нічого не зберігається» виконується й тоді, коли `assertValidPublicSettings()` кидає виняток уже після запису значень.

**4. Перенесення логіки злиття.** `mergePublicSettings()`, `replaceSetting()` (спільний з `list`) і `PublicDisplaySettings::formOptions()` лишаються як є за змістом; `mergePublicSettings` переїжджає в код нового контролера (або в `PublicDisplaySettings`, якщо це зменшує дублювання — вирішується в реалізації), `ContentTypeController::update()` більше не торкається `settings.public`, `edit()` не віддає `publicOptions`. `settings` у `UpdateContentTypeRequest` звужується до `array:list`, тож `settings.public` у запиті редагування дає помилку валідації (сценарій у специфікації).

**5. Фронтенд.** `Section.tsx` → `Display.tsx`; нова форма вкладки `TypeDisplayForm` має один `useForm` (`values` + `settings.public`), рендерить `NodeFieldsPanel name="section"` (для `content`, заголовок «Section»), `PublicDisplaySettingsSection` і один `FormActionBar`. Сторінка отримує `publicOptions` і (для `content`) `typeFields`/`typeValues`/`typeFiles` з наявного `typeValuesProps($type, 'section')`. `TypeValuesPanels` після цього обслуговує лише «SEO»: параметр `panel` звужується до `'seo'`, а спільні частини (стан файлів, `NodeFieldFrame`) за потреби виносяться, щоб не дублювати код. `ContentTypeNav`: `active` отримує `'display'` замість `'section'`, пункт «Відображення» показується для всіх видів. Блок значень розділу всередині вкладки лишає заголовок «Розділ»/«Section» та існуючий `data-testid="section-panel"`; для нового блоку підпис вкладки — ключ перекладу `Display` («Відображення»). Спільний `useForm` отримує помилки як `values.{key}` і `settings.public.*`; помилки `settings.public` показуються так само, як у `ContentTypeForm` зараз.

**6. Перенесення UI-стану.** `ContentTypeForm` прибирає `publicOptions`, `public` зі стану й `transform`, помічники `isPublicError`/`publicErrors`; `Edit.tsx` не приймає `publicOptions`. У режимі `create` змін немає.

## Risks / Trade-offs

- [Зламані тести й e2e, що відкривають `/section` або шукають публічну секцію у формі] → оновлюються в тих самих групах завдань, що й код; перелік у `tasks.md`.
- [Користувач очікує публічну секцію у формі налаштувань] → зафіксовано в специфікації, форма налаштувань коротшає навмисно.
- [Часткове збереження при збої посеред запиту] → транзакція (рішення 3).
- [`TypeValuesPanels` після звуження лишається з одним споживачем] → прийнятно; спільні шматки виносяться лише якщо їх треба в `TypeDisplayForm`.

## Migration Plan

Міграцій БД немає, `settings.public` і значення `section_*` зберігаються як є. Розгортання — звичайне; відкат — повернення коду. Закладки на `/admin/content-types/{key}/section` перестають працювати (404).

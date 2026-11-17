# Design

## Context

Див. `proposal.md`. Поточний стан (прочитано в коді):

- `settings.public.meta` (`title`, `description`, `image`) редагується на вкладці «Відображення»: `PublicDisplaySettings::formOptions()` віддає `metaFields` і `current.meta`, `TypeDisplayForm`/`PublicDisplaySettingsSection` показують підблок, `ContentTypeDisplayController` зберігає його разом зі значеннями розділу, `UpdateContentTypeDisplayRequest` приймає `meta`.
- Вкладка «SEO» є лише в `content`: маршрути `content-types/{type}/seo` і `…/values` стоять поза циклом по видах із `defaults('kind', 'content')`; `ContentTypeController::typeValuesProps()` віддає поля панелі лише для `kind === 'content'`.
- Власників значень задає `SystemFields::DEFINITIONS`: `meta_*` мають `owners`: `content => [node, type]`, `page => [node]`. `ValueOwner::forType($type)->fields()` і `ContentValueWriter` відсіюють поля за `owns()`. Поля `meta_*` у типах `page` уже існують (створені разом із типом).
- `App\Theme\PageMeta::make($values, $type)` бере `meta_*` запису → вибране поле типу → налаштування сайту. `PublicData` передає `$extra['type']` і для списку типу (без `values`), і для запису.
- `ContentValueReader::loadForType($type, $keys, $lang)` читає значення самого типу.

## Goals / Non-Goals

**Goals:**
- Блок «Мета-дані» на вкладці «SEO» (`content` і `page`) окремою секцією з бордером, без власної кнопки: зберігається разом зі значеннями `meta_*` типу єдиною кнопкою вкладки.
- Вкладка «SEO» і значення `meta_*` типу для `page`.
- Ланцюжок: запис → вибрані поля → (для заголовка) `title` запису → `meta_*` типу → налаштування сайту; `<title>` без суфікса назви сайту.

**Non-Goals:**
- Нові поля метаданих, `og:title`/`og:description`, переклад значень типу (вкладка працює з базовою мовою, як зараз).
- SEO для типів `block`.
- Зміна формату `settings.public.meta`.
- Суфікс « — {назва сайту}» у `<title>`: його, якщо потрібен, додають хуки чи шаблони теми.

## Decisions

1. **`page` стає власником `meta_*` типу через `SystemFields`.** У `DEFINITIONS` для `meta_title`, `meta_description`, `meta_image` стає `'page' => [node, type]`. Міграція не потрібна: поля вже є в типах `page`, а власник визначається визначенням, не стовпцем. Тип `block` не змінюється. Альтернатива (окрема таблиця чи обхід `owns()`) відкинута: правило власника — єдине джерело істини.
2. **Вкладка «SEO» для `page`.** Маршрути `seo` і `values.update` переносяться в цикл по видах `content`/`page` (імена `content-types.seo`, `page-types.seo`, `content-types.values.update`, `page-types.values.update`); для `block` їх немає (404). `typeValuesProps()` і `ContentTypeValuesController` визначають поля через `ValueOwner::forType()` і панель (`seo`/`section`), а не через `kind === 'content'`; для `page` панель `section` порожня, бо `section_*` типу не належать. `ContentTypeNav` показує «SEO» для `content` і `page`.
3. **Блок «Мета-дані» живе на вкладці «SEO» і зберігається разом зі значеннями `meta_*` одним запитом.** Вкладка — одна форма з однією липкою панеллю «Скасувати / Зберегти»; блок рендериться у `fieldset` із бордером (`rounded-lg border p-4`) нижче полів `meta_*`, без власної кнопки. Як і вкладка «Відображення», `PUT /{section}/{key}/values` (`ContentTypeValuesController::update`, імена `content-types.values.update` / `page-types.values.update`; для `block` немає) у одній транзакції записує значення `meta_*` типу й, коли в запиті є `settings.public.meta`, зливає лише цей ключ у `settings.public` (перевірка через `ContentType::assertValidPublicSettings()`; інші ключі `public` і `settings` не чіпає; порожній `meta` прибирається, порожній `public` не зберігається). Помилка в будь-якій частині не зберігає нічого; помилки значень показуються біля полів, помилки `meta` — біля блоку. Відповідь — `back()` з `success`. Окремий маршрут `…/seo/meta`, `updateMeta()` і `UpdateContentTypeMetaRequest` прибираються. Альтернатива — окрема кнопка й окремий запит — відкинута: друга кнопка ламає липку панель дій і розбиває одне збереження вкладки на два.
4. **З вкладки «Відображення» мета прибирається.** `PublicDisplaySettings::formOptions()` перестає віддавати `metaFields` і `current.meta` (їх віддає новий `metaOptions()` для вкладки «SEO»); `PublicDisplaySettingsSection` і `TypeDisplayForm` — без підблоку; `toPublicSettings()` не додає `meta`. `UpdateContentTypeDisplayRequest` перестає приймати `settings.public.meta`, а `ContentTypeDisplayController::mergePublicSettings()` **зберігає наявний `meta`** при збереженні решти `public` (інакше «Відображення» стирало б мета-налаштування). Запит із `meta` на вкладці «Відображення» відхиляється помилкою валідації.
5. **Ланцюжок у `PageMeta`.** Для опису й зображення: `meta_*` запису → вибране поле → значення типу (`ContentValueReader::loadForType($type, ['meta_title','meta_description','meta_image'])`, базова мова) → налаштування сайту. Для заголовка: `meta_title` запису → вибране поле (поле типу «рядок») → `title` запису (`$values['title']`) → `meta_title` типу → назва сайту. Значення типу очищається тими ж методами, що й поле: заголовок — `text()`, опис — `text()` + скорочення `shorten()` до `meta_description_max`, зображення — `imageUrl()`. На сторінці списку (без `values`) кроки запису, поля й `title` запису порожні. Завантаження вибраних полів і значень типу виконується лише для типів, що мають `meta`/`meta_*`, щоб не додавати зайвих запитів. Службового пункту `title` у виборі «Заголовок» немає: `title` запису й так береться за замовчуванням.
6. **`<title>` без суфікса назви сайту.** `PublicData` більше не складає «{title запису} — {назва сайту}»: заголовок документа — результат ланцюжка як є; назва сайту лише коли жодного значення немає (головна, 404), а без неї — назва застосунку. Додавання суфікса — справа хуків чи шаблонів теми.
7. **Контракт `$meta` не змінюється** (три ключі). `docs/theme-templates.md` лише уточнює джерела.

## Risks / Trade-offs

- [Зміна `<title>`: зникає суфікс « — {назва сайту}»] → явне рішення власника; тема може додати його хуком чи в шаблоні; оновити docs, тести й e2e.
- [Збереження «Відображення» стирало б `meta`] → `mergePublicSettings()` зберігає `meta`; окремий тест.
- [Значення `meta_*` типу `page` раніше не могли існувати] → нових значень немає, міграція не потрібна; бекфіл-міграція лишається знімком і не змінюється.
- [Два джерела помилок в одному запиті] → транзакція; помилки значень і `meta` повертаються в одній відповіді й показуються біля своїх полів (перевірити e2e).
- [Доступ] → нові маршрути за `can:types`, як решта вкладок типу.

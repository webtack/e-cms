# Design

## Context

- `app_settings` (`App\Support\AppSettings`) уже вміє зберігати JSON-значення за ключем `модуль.назва`, але кешує в пам'яті екземпляра й не має транзакційних гарантій (`set` робить `upsert` без власної транзакції). Опис — `docs/app-settings.md`.
- Системне право `settings` існує (`AccessResolver::SYSTEM`, `can:settings`), пункт Settings у `navigation.ts` — макет (`mock: true`, `/admin/settings`).
- Зв'язки файлів ведуться через `FileRelations::sync(ownerType, ownerId, role, lang, uuids)`; тип власника реєструється в `config/files.php` (`owners`) класом `MediaOwner`. `owner_id` — `unsigned bigint`, без FK. Зображення пункту меню — зразок: значення `{uuid}.{ext}`, перевірка правилом `FileValue('image', …)`, зв'язок у тій самій транзакції (`MenuItemWriter`), на формі `FileUpload profile="image"` ([media-library.md](../../../docs/media-library.md), [file-storage.md](../../../docs/file-storage.md)).
- Перетягування: `@dnd-kit/core` і `@dnd-kit/sortable` уже вживаються (`NodeFieldList.tsx`, `ContentFields/Index.tsx`).
- Мови: базова мова — `default`; мовний UI в цій зміні не робимо. Див. proposal.md.

## Goals / Non-Goals

**Goals:**
- Одна сторінка, що зберігає весь набір `site.*` атомарно.
- Формат даних, у якому додавання мов не вимагає міграції значень.
- Один читач для шаблонів з фолбеком на `default`.
- Облік логотипів у `media_relations` без нової таблиці.

**Non-Goals:**
- Мовний UI (перемикач, переклад полів), виведення `meta robots`/`X-Robots-Tag`/`robots.txt`.
- Міжпроцесний кеш налаштувань, історія змін, версіонування.
- Зміна `AppSettings` (його API лишається як є).

## Decisions

### 1. Зберігання в `app_settings`, 9 ключів `site.*`
Окремої таблиці й міграції немає. Альтернатива — «псевдотип» на рушії полів (`content_values_*`) — відхилена в explore: вона тягне `ContentType` і `SystemFields` для сутності, що не є типом, і не дає потреби в динамічних полях. Рядки зберігаємо з ключем мови навіть коли мова одна.

```
site.name        {"default": "Мій сайт"}
site.short_name  {"default": "Сайт"}
site.logo_large  {"default": "0190….png"}
site.logo_small  {"default": "0190….png"}
site.emails      [{"value": "a@b.c", "label": {"default": "Загальні"}}]
site.phones      [{"value": "+380 44 000", "label": {"default": "Відділ продажів"}}]
site.socials     [{"network": "instagram", "url": "https://…", "label": {"default": "…"}}]
site.schedule    {"default": [["Пн–Пт", "9:00", "18:00", "обід 13–14"], ["Сб", "10:00", "14:00", ""]]}
site.indexable   false
```

Логотип зберігається як `{uuid}.{ext}` (а не голий `uuid`), бо так зберігаються файлові значення в усьому проєкті і `FileStorage::urlForValue` будує URL саме з цього формату.

Порожнє значення: порожня назва/лого/графік — ключ `default` прибирається, а якщо перекладів не лишилось — налаштування `forget`. Порожній `label` — відсутній ключ. Порожні списки зберігаються відсутнім налаштуванням.

### 2. Клас `App\Settings\SiteSettings`: читач і писач в одному
Singleton поверх `AppSettings`. Читання: `name($lang = null)`, `shortName()`, `logoLarge()`, `logoSmall()`, `emails()`, `phones()`, `socials()`, `schedule()`, `indexable()` — кожен метод повертає значення запитаної мови з фолбеком на `default` (мова за замовчуванням — поточна локаль застосунку). Запис — `save(array $data)`: приймає вже провалідовану структуру форми (одна мова `default`), перетворює її на формат п.1, записує всі ключі в `DB::transaction` разом із синхронізацією логотипів (п.3). Альтернатива — розкласти читання й запис на два класи — зайва: формат знає один клас.

Якщо транзакція відкотилась, кеш `AppSettings` містить нові значення, яких у БД немає. `save()` у `catch` викликає `AppSettings::flush()` і повторно кидає виняток, щоб подальше читання в цьому процесі не брехало.

Збереження не торкається чужих префіксів (пишуться лише дев'ять ключів `site.*`), а «затирання» значень, яких немає у формі, виключено тим, що форма відправляє весь набір, а мовні ключі, відмінні від `default`, `save()` при записі зберігає: нове значення `default` зливається зі збереженим об'єктом, а не замінює його.

### 3. Логотипи: власник `site_setting`, `owner_id = 0`
Налаштування сайту — одиночна сутність без рядка в БД, тож `owner_id` — константа `0` (колонка `unsigned`, FK немає; 0 не збігається з жодним справжнім `id`, що починаються з 1). Тип власника `site_setting`, роль — ключ логотипа (`logo_large`/`logo_small`), мова — `default`. `SiteSettings::save()` у тій самій транзакції викликає `FileRelations::sync('site_setting', 0, $role, 'default', $uuids)`. `App\Files\Owners\SiteSettingOwner::describe()` повертає для `0` підпис «Налаштування сайту» і `url` `/admin/settings`; клас реєструється в `config/files.php`. Перевірка файлу — правило `FileValue('image', …)`, як у меню. Альтернатива «owner_id = хеш ключа» відхилена як штучна; альтернатива «окремий рядок у БД під налаштування» — зайва міграція.

Видалення «власника» неможливе (його немає), тож хука прибирання зв'язків не потрібно. Помилка валідації файлу ловиться form request-ом до запису.

### 4. Довідник соцмереж у `config/social_networks.php`
Асоціативний масив `ключ => ['name' => 'Facebook']` (іконку фронтенд бере за ключем, тут її не зберігаємо). Ключі: `facebook`, `instagram`, `x`, `linkedin`, `youtube`, `tiktok`, `telegram`, `viber`, `whatsapp`, `threads`, `pinterest`, `github`, `behance`, `dribbble`, `discord`, `snapchat`, `reddit`, `medium`, `twitch`, `vimeo`, `skype`, `vk`, `ok`. Перелік фіксований; новий додається правкою конфігурації. Форма отримує його пропом `networkOptions` (`[{value, label}]`), валідація — `Rule::in(array_keys(config('social_networks')))`. Альтернатива «довільна назва + url» відхилена користувачем.

### 5. Валідація
Один `UpdateSiteSettingsRequest`. Межі беремо з розумних, не з вимог користувача (тут це припущення, змінюється без зміни специфікації): `name` ≤ 255, `short_name` ≤ 100; логотип — `string`, `max:255`, `FileValue('image')`; `emails`/`phones`/`socials` ≤ 20 записів; `email` — правило `email` ≤ 255; телефон — `regex:/^\+?[0-9\s()\-]{3,32}$/` (цифри, пробіли, дужки, дефіс, необов'язковий `+`); `url` — `url:http,https` ≤ 2048 (правило поля `url` контенту, `UrlType::isValid`, тут не підходить: воно допускає відносні посилання, `mailto:` і `tel:`, а соцмережі мають бути лише абсолютними `http(s)`); `label` ≤ 255; `schedule` ≤ 20 рядків по рівно 4 рядки ≤ 255, `nullable`. `network` — `required` + `in` довідника. Рядок із порожнім `value`/`url` — помилка (ключ `emails.0.value` тощо); рядок графіка з усіма порожніми колонками відкидається **до** валідації (`prepareForValidation`), тож помилки не дає.

### 6. Маршрути й контролер
`GET /admin/settings` (`admin.settings.edit`) і `PUT /admin/settings` (`admin.settings.update`), обидва за `['auth','verified']` + `can:settings` (гість — `/login`, без права — 403). Один `Admin\SiteSettingsController` (`edit`, `update`), після `update` — редірект назад із flash «Settings saved.». Контролер віддає пропси: `values` (мова `default`, структура форми), `logoFiles` (опис завантажених файлів, як `imageInfo` меню), `networkOptions`, `limits` (максимуми для клієнтських підказок). У `navigation.ts` з пункту Settings прибираємо `mock: true`; заголовок хлібних крихт береться з самого пункту, тож `extraTitles` не змінюється.

### 7. UI: одна форма, спільний компонент списку
`Pages/Admin/Settings/Site.tsx` на Inertia `useForm`, липка `FormActionBar` (як інші форми). Блоки — секції: «Загальне» (назва, скорочена назва), «Логотипи» (два `FileUpload profile="image"`), «Контакти» (email-и, телефони), «Соцмережі», «Графік роботи», «Індексація» (`Switch`). Щоб не дублювати чотири списки, один загальний `SortableList` у `Components/Admin/Settings/SortableList.tsx` на `@dnd-kit` (ручка + вгору/вниз клавіатурою, як у `NodeFieldList`), що приймає масив рядків із стабільним `id` клієнтського рівня, рендер-проп для вмісту рядка, `onChange`, кнопку «Додати». Елементам форми потрібен тимчасовий клієнтський `id` для стабільних ключів при перетягуванні; на сервер він не відправляється. Кожен список має `data-testid`. Бібліотека перетягування — наявна, друга не додається.

### 8. Мовна готовність
Форма оперує плоскими значеннями мови `default` (`values.name`, `values.emails[i].label` …); `SiteSettings` загортає їх у `{ default: … }` при записі й розгортає при читанні форми. Додавання мовного UI згодом означає змінити лише форму та `save()`/`edit()`-мапінг, а не формат даних.

## Risks / Trade-offs

- **Одна сутність з `owner_id = 0`** → єдиний виняток із «owner_id — реальний id». Мітигація: константа і її документація в `docs/media-library.md` та `docs/admin-site-settings.md`; `SiteSettingOwner` — єдине місце, що її знає.
- **Кеш `AppSettings` після відкоту транзакції** → `save()` скидає кеш у `catch` (див. п.2); покривається тестом відкоту.
- **Розбіжність між збереженим `default` і майбутніми іншими мовами при редагуванні лише `default`** → `save()` зливає значення `default` зі збереженими іншими мовами, не затираючи їх.
- **Фіксований довідник соцмереж застаріває** → правка конфігурації. Збережена мережа, якої вже немає у довіднику, читається, а форма додає її до `networkOptions` як поточне значення, щоб редагування не губило запис; при збереженні така мережа відхиляється валідацією, доки її не змінять.
- **Межі валідації — припущення** → змінюються в одному form request без зміни специфікації.
- **Клієнтські `id` рядків списку** → не зберігаються; після збереження сторінка перезавантажується, `id` генеруються заново.

Опис компонентів, які створює ця зміна, зберігається у `docs/admin-site-settings.md` (нова), `docs/app-settings.md` (префікс `site.`), `docs/media-library.md` (власник `site_setting`), `docs/access-control.md` (право `settings`), `docs/admin-shell.md` (пункт Settings), `docs/e2e-testing.md` (новий спек).

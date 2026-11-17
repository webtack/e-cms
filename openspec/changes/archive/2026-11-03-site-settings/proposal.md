# Proposal

## Why

У CMS немає місця для загальних відомостей про сайт: назви, логотипів, контактів, соцмереж, графіка роботи й глобального прапорця індексації. Фронтенд їх потребуватиме в шапці, підвалі та `meta`, а право ролі `settings` уже існує, але не застосовується ні до чого («її ще немає» у `docs/access-control.md`), і пункт Settings у sidebar лишається макетом.

## What Changes

- Нова сторінка `GET/PUT /admin/settings` («Налаштування сайту») під правом `settings`; пункт Settings у sidebar перестає бути макетом.
- Нові налаштування в `app_settings` з префіксом `site.`:
  - `site.name`, `site.short_name`, `site.schedule` — перекладні (`{ "default": ... }`);
  - `site.logo_large`, `site.logo_small` — перекладні файли профілю `image` (`{ "default": "{uuid}.{ext}" }`), необов'язкові;
  - `site.emails`, `site.phones` — списки `{ value, label? }`, `label` перекладний (`{ "default": ... }`);
  - `site.socials` — список `{ network, url, label? }`, `network` з фіксованого довідника в `config/`, дублі дозволені, `label` перекладний;
  - `site.indexable` — `bool`, за замовчуванням `false`.
- Графік роботи — список рядків, у кожному 4 однорядкові тексти (колонки), без обмеження змісту; межа кількості рядків — у валідації.
- Списки (`emails`, `phones`, `socials`, `schedule`) мають додавання, видалення й порядок перетягуванням.
- Формат даних готовий до мов (усі перекладні значення зберігаються під ключем `default`), а UI показує й редагує лише `default`; мовний UI — окрема майбутня зміна.
- Читач `SiteSettings` поверх `AppSettings` для шаблонів із фолбеком на `default`.
- Логотипи відстежуються в `media_relations` новим власником `site_setting`, тож використаний файл не можна видалити з медіатеки; медіатека показує власника з посиланням на сторінку налаштувань.
- Поза межами: виведення `meta robots`, `X-Robots-Tag`, `robots.txt` (фронтенду немає), мовний UI, ефект `site.indexable` в адмінці.

## Capabilities

### New Capabilities

- `admin-site-settings`: сторінка налаштувань сайту в адмінці — поля, валідація, списки з перетягуванням, збереження, доступ за правом `settings`.
- `site-settings`: сховище й читач налаштувань сайту (`site.*`) — формат значень, значення за замовчуванням, фолбек на `default`, довідник соцмереж.

### Modified Capabilities

- `admin-shell`: пункт Settings у sidebar стає робочим (більше не макет) і веде на `/admin/settings`.
- `media-library`: з'являється власник зв'язків `site_setting` (логотипи), який медіатека описує підписом і посиланням.

Спека `access-control` не змінюється: системне право «налаштування сайту» вже в ній визначене, а його застосування до сторінки описує `admin-site-settings`. Оновлюється лише `docs/access-control.md`.

## Impact

- **Код:** `routes/web.php`; контролер `Admin\SiteSettingsController`; form request; `App\Settings\SiteSettings` (читач і писач) та довідник `config/social_networks.php`; `App\Files\Owners\SiteSettingOwner` і рядок у `config/files.php`; `resources/js/Pages/Admin/Settings/Site.tsx` із компонентами списків; `navigation.ts` (прибрати `mock`, заголовок сторінки); переклади (`lang`).
- **БД:** міграцій немає — використовується наявна `app_settings` і `media_relations`.
- **Залежності:** нових немає; перетягування — наявний `@dnd-kit`.
- **Docs:** створити `docs/admin-site-settings.md`; оновити `docs/app-settings.md` (префікс `site.`), `docs/access-control.md` (право `settings`), `docs/admin-shell.md` (пункт Settings), `docs/media-library.md` (власник `site_setting`), `docs/README.md` (індекс), `docs/e2e-testing.md` (новий спек у «Область → спек»).
- **Тести:** Feature-тести `tests/Feature/Settings` (читач) і `tests/Feature/Admin` (сторінка), новий спек `e2e/admin-site-settings.spec.js`.

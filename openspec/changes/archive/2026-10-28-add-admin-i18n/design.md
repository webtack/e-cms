# Design

## Context

Поточний стан (перевірено в коді): `config/app.php` має `locale = env('APP_LOCALE','en')` і `fallback_locale = en`; каталогу `lang/` немає, але `LoginRequest` уже використовує `__('auth.failed')` та `__('auth.throttle')`. `resources/views/app.blade.php` виставляє `<html lang>` з `app()->getLocale()`. Усі рядки React (`Login`, `VerifyEmail`, `Dashboard`, `Profile`, `UserMenu`, `ThemeToggle`, `AdminHeader`, `AppSidebar`) і заголовки в `navigation.ts` — англійські літерали. `HandleInertiaRequests` ділить `auth.user` (явний allow-list полів) і `flash`. `User` не має поля локалі. Мотивація — див. `proposal.md`, поведінка — `specs/admin-i18n` і `specs/admin-shell`.

Компоненти описані в нових/оновлених `docs/admin-i18n.md` та `docs/admin-shell.md`; логін — у `docs/admin-auth.md`.

## Goals / Non-Goals

**Goals:**
- Один набір перекладів (`lang/*.json`) для React; серверні повідомлення — стандартні `lang/{locale}/*.php`.
- Додати третю мову = додати запис у конфіг, `lang/xx.json` (копія `en.json`) і за потреби `lang/xx/*.php`, без правок коду.
- Нові екрани одразу пишуться через `t()`; відсутній переклад ніколи не ламає сторінку.

**Non-Goals:**
- Переклад контенту CMS, локалізовані URL, дати/числа (`Intl`), plural, RTL, SSR.
- Вибір мови в профілі як окрема форма (профіль — заглушка; перемикач у шапці та на логіні).
- Автогенерація/витяг ключів зі сторінок (інструмент типу i18next-parser).

## Decisions

- **Конфіг мов.** `config/app.php`: `available_locales` = `['en' => 'English', 'uk' => 'Українська']` (власні назви для перемикача). `locale` лишається `env('APP_LOCALE','en')`; `fallback_locale = en`. Альтернатива — окремий `config/locales.php`: зайвий файл, бо це розширення app-конфігу.
- **Ключі — англійські фрази** (`"Log in": "Увійти"`), як у Laravel JSON-перекладах; `en.json` містить ті самі ключі з тими самими значеннями. Альтернатива — dotted-ключі (`auth.login.title`): стійкіші до правки тексту, але важче читати код і ширший розрив між ключем і видимим текстом. `en.json` потрібен явно, щоб тест паритету ключів мав еталон і щоб копіювання `en.json` було шаблоном нової мови.
- **Визначення мови — middleware `SetLocale`** у групі `web`, **перед** `HandleInertiaRequests` (бо той читає `app()->getLocale()`). Порядок: `Auth::user()?->locale` → cookie `locale` → `$request->getPreferredLanguage(array_keys(available_locales))` → `config('app.locale')`; кожне значення перевіряється на належність до `available_locales`. `app()->setLocale()` застосовується на запит. `getPreferredLanguage` має зіставляти `uk-UA` з `uk`; це перевіряється тестом під час реалізації.
- **Збереження.** Колонка `users.locale` — `string(5)`, nullable (null = «не обирав», тоді працює визначення за cookie/Accept-Language). Нова міграція, існуючі не чіпаємо. Для гостя — cookie `locale` (`Cookie::forever`, через стандартне шифрування cookie). Для авторизованого одночасно ставиться й cookie, щоб після виходу логін лишився тією ж мовою. Альтернатива «лише сесія» відкинута: сесія знищується при logout і мова втрачалась би саме на сторінці входу.
- **`POST /locale` (`LocaleController`, name `locale.update`)** поза групою `auth` (потрібен гостям). Валідація `Rule::in(array_keys(available_locales))`; відповідь `back()` (302 безпечний для Inertia POST). `throttle:30,1` від зловживань. Це змінює стан → POST, а не GET-посилання.
- **Доставка в React — спільний проп `locale`** у `HandleInertiaRequests::share`: `{ current, available: [{code, name}], messages }`, де `messages` = вміст `lang/{current}.json` (файл повертається як є, також для `en`, щоб `t()` не мала особливих гілок). Розмір JSON невеликий; за зростання (сотні ключів) перейти на розбиття за сторінками — окрема зміна.
- **Клієнт.** `lib/i18n.ts` — чиста функція `translate(messages, key, replace?)`: `messages[key] ?? key`, потім заміна `:name` (довші імена першими, як у Laravel, щоб `:minutes` не зачепило `:min`). `hooks/useTranslation.ts` читає `usePage().props.locale` і повертає `{ t, locale, locales }`. Альтернатива `react-i18next` відхилена власником: друге джерело перекладів поряд із `lang/`.
- **Навігація.** `navigation.ts` зберігає **ключі** (англійські фрази) у `title`; переклад виконують компоненти (`AppSidebar`, `Breadcrumbs`) через `t(item.title)`. `getBreadcrumbs` і далі повертає ключі — функція лишається чистою й не залежить від React. Заголовок «Admin» теж ключ.
- **Перемикач.** `Components/Admin/LocaleSwitcher.tsx` — DropdownMenu (є `ui/dropdown-menu`) з іконкою `Languages`; обрана мова позначена галочкою; дія `router.post('/locale', { locale }, { preserveScroll: true, preserveState: true })`. Persistent layout не перемонтується, тож стан sidebar зберігається. На логіні той самий компонент у куті сторінки (поза `AdminLayout`).
- **`<Head title>`** і `aria-label` також через `t()`. Серверні повідомлення: `lang/uk/auth.php` (повний переклад), `lang/uk/validation.php` (правила, які реально використовуються, + `attributes`); відсутні ключі дають fallback на `en`. Англійські `lang/en/auth.php`/`validation.php` публікуються `php artisan lang:publish` (у Laravel 13 їх у скелеті немає).
- **Значення за замовчуванням.** `APP_LOCALE` лишається `en`: e2e і поточні тести покладаються на англійські підписи, а Playwright за замовчуванням шле `Accept-Language: en-US`. Власник змінює дефолт через env.
- **Тести.** PHPUnit: визначення мови (user → cookie → Accept-Language → default, недопустиме значення), `POST /locale` (гість/авторизований/невалідне), спільний проп `locale`, паритет ключів `en.json`/`uk.json`, `auth.failed` українською. Playwright `e2e/admin-i18n.spec.js`: перемикання на логіні й в адмінці, збереження після logout; тест у `afterEach` повертає `en` для seeded-користувача, щоб не ламати `admin-shell.spec.js`.

## Risks / Trade-offs

- [Правка англійського тексту ламає ключ і переклад] → паритет-тест ловить розсинхрон `en`/`uk`; ключі міняються разом з обома файлами.
- [Усі переклади йдуть у кожній відповіді] → поки ключів мало — прийнятно; пункт на майбутнє — розбиття за доменами.
- [Забули обгорнути рядок у `t()`] → не ловиться автоматично; зафіксувати правило в `docs/admin-i18n.md` та у перевірці під час apply (grep по літералах у JSX).
- [E2E залежать від стану `users.locale` seeded-користувача] → відновлення мови в `afterEach`, пояснення в `docs/e2e-testing.md`.
- [Cookie `locale` шифрується і не читається клієнтським JS] → це очікувано: мову віддає сервер у пропсі, JS cookie не читає.
- [Cache-Control/CDN] → адмінка за авторизацією, публічного кешування немає; для публічного сайту це окрема тема.

## Migration Plan

1. `php artisan migrate` додає `users.locale` (nullable) — існуючі користувачі не змінюються, працює визначення за cookie/Accept-Language.
2. Відкат: `migrate:rollback` видаляє колонку; middleware без колонки не використовуємо, тож відкат змін коду та міграції — разом.

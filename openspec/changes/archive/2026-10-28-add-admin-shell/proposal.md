# Proposal

## Why

Застосунок — це чистий скелет Laravel 13: немає ні фронтенд-фреймворку (лише `resources/js/app.js` із заглушкою), ні автентифікації, ні адмінки. Модульній CMS потрібна єдина адмін-оболонка (layout, навігація, тема, сповіщення), на якій надалі будуватимуться типи контенту, конструктор сторінок і медіатека. Робити її зараз, до функціоналу, дешевше, ніж перебудовувати пізніше.

## What Changes

- Підключити **Inertia.js (React) + TypeScript (`.tsx`)** до існуючого Vite/Tailwind 4 стеку; корінь Inertia — `resources/views/app.blade.php`, вхід — `resources/js/app.tsx`.
- Ініціалізувати **shadcn/ui** (тема zinc, скруглені кути), UI-примітиви в `resources/js/Components/ui/`, адмін-компоненти в `resources/js/Components/Admin/`.
- Додати **власну мінімальну автентифікацію** (без Breeze): сторінки Login, дія Logout, увімкнення `MustVerifyEmail` на `User`, початковий користувач через seeder.
- Додати **`AdminLayout`** як persistent layout (`Page.layout`): згортний sidebar (shadcn `Sidebar`: expanded / icons-only, мобільний Sheet), навігація з макетними групами (Dashboard, Content Types, Pages, Media Library, Settings) та підсвіткою активного пункту, header із breadcrumbs, перемикачем світлої/темної теми та меню користувача (імʼя/аватар, посилання на профіль, logout через Inertia).
- Додати глобальні **toast-сповіщення** (shadcn Sonner), повʼязані з flash-повідомленнями `success` / `error`.
- Додати групу маршрутів `/admin` (`auth` + `verified`), `Admin\DashboardController` та сторінку `Admin/Dashboard.tsx`.
- Додати `HandleInertiaRequests`, що ділиться користувачем і flash-повідомленнями.
- Додати Profile як **заглушку-сторінку** (щоб посилання з меню користувача вело на робочий маршрут); редагування профілю поза обсягом.
- Додати e2e-тест (Playwright): вхід → `/admin/dashboard`, гість перенаправляється на логін.
- **Не входить:** конструктор типів контенту, datatables (TanStack Table), інтеграція Spatie Media Library, реєстрація, скидання пароля, редагування профілю.

## Capabilities

### New Capabilities
- `admin-auth`: вхід/вихід адміністратора, захист `/admin/*` middleware `auth` + `verified`, редірект гостей.
- `admin-shell`: persistent `AdminLayout` — sidebar, header (breadcrumbs, тема, меню користувача), toast-сповіщення, спільні Inertia-пропси, стартова сторінка `/admin/dashboard`.

### Modified Capabilities
<!-- Немає: єдина наявна специфікація `e2e-testing` не змінює вимог. -->

## Impact

- **Backend (PHP, через `docker compose exec app`):** `composer require inertiajs/inertia-laravel`; `bootstrap/app.php` (middleware `HandleInertiaRequests`, редірект гостей), `routes/web.php`, нові `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Auth/*`, `app/Http/Controllers/Admin/DashboardController.php`, `app/Models/User.php` (`MustVerifyEmail`), `database/seeders/DatabaseSeeder.php`, PHPUnit feature-тести.
- **Frontend (npm на хості):** `@inertiajs/react`, `react`, `react-dom`, `typescript`, `@vitejs/plugin-react`, залежності shadcn/ui (Radix, `lucide-react`, `sonner`, `class-variance-authority`, `clsx`, `tailwind-merge`), `vite.config.js`, `tsconfig.json`, `components.json`, `resources/css/app.css` (токени теми), `resources/js/**`. Встановлення npm потребує `--legacy-peer-deps` (див. `docs/e2e-testing.md`).
- **Тести:** новий `e2e/admin-shell.spec.js`; для e2e потрібен seeded користувач і міграції в БД Docker.
- **Документація (`docs/`, українською):** створити `docs/admin-shell.md` (стек, структура каталогів, layout, sidebar, тема, toasts), `docs/admin-auth.md` (вхід, verified, seeder); оновити `docs/README.md` (індекс) і `docs/e2e-testing.md` (новий сценарій і залежність від seeder).
- **Відомі відхилення від брифу:** проєкт на Laravel 13 (не 11); обрано TypeScript, тому сторінка — `Admin/Dashboard.tsx`, а не `.jsx`.

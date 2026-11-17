# Design

## Context

Поточний стан (перевірено в коді): Laravel 13 (`laravel/framework ^13.17`), PHP 8.3+, Tailwind 4 через `@tailwindcss/vite`, Vite 8 з `laravel-vite-plugin`; `resources/js/app.js` порожній, єдиний view — `welcome.blade.php`, `routes/web.php` має лише `/`. Автентифікації немає: в `app/Http/Controllers` лише базовий `Controller`, є `User` (без `MustVerifyEmail`) і стандартні міграції `users`/`sessions`. БД у Docker — MySQL (`ecms_db`), сесії в БД. PHP виконується лише в контейнері `app`, npm — лише на хості. Мотивація та межі — див. `proposal.md`, поведінка — `specs/admin-auth` і `specs/admin-shell`.

## Goals / Non-Goals

**Goals:**
- Робочий вхід та `/admin/dashboard` в єдиній persistent-оболонці, придатній як фундамент для наступних розділів.
- Одне джерело правди для навігації (sidebar і breadcrumbs).
- Збірка `npm run build` без помилок TypeScript і бандлера.

**Non-Goals:**
- Реєстрація, скидання пароля, надсилання листів верифікації, редагування профілю, ролі/права.
- Серверна навігація з БД (меню статичне), i18n інтерфейсу.
- SSR для Inertia.

## Decisions

- **Inertia без Breeze.** Ставимо `inertiajs/inertia-laravel` і `@inertiajs/react` напряму; auth пишемо вручну (`Auth\LoginController`, `LogoutController`). Рішення власника. Альтернатива — Breeze React: тягне свої layout/сторінки, які довелося б переписувати під shadcn і `/admin`. Версії пакетів перевірити на сумісність із Laravel 13/Vite 8 під час встановлення.
- **TypeScript (`.tsx`).** Рішення власника; shadcn CLI генерує TSX, `tsc --noEmit` ловить помилки до збірки. Скрипт `build` = `tsc --noEmit && vite build`. Сторінка — `Admin/Dashboard.tsx` (відхилення від `.jsx` у брифі).
- **Структура фронтенду.** Вхід `resources/js/app.tsx` (`createInertiaApp`, `resolve` через `import.meta.glob('./Pages/**/*.tsx')`, дефолтний layout для `Admin/*` сторінок через `Page.layout`). Каталоги: `Pages/`, `Layouts/AdminLayout.tsx`, `Components/ui/` (shadcn), `Components/Admin/` (sidebar, header, breadcrumbs, user menu, theme toggle), `lib/utils.ts`, `hooks/`. Alias `@` → `resources/js` (у `vite.config.js` і `tsconfig.json`).
- **Регістр `Components`.** Брифом задано `Components/` з великої літери, тоді як shadcn за замовчуванням `components/`. У `components.json` виставляємо aliases на `@/Components/ui` тощо. Ризик: macOS нечутлива до регістру, а Linux-контейнер чутливий — використовувати лише один регістр скрізь.
- **shadcn/ui + Tailwind 4.** `npx shadcn@latest init` (база zinc, CSS-змінні, `--radius`); токени теми — в `resources/css/app.css` (`@theme inline`, `@custom-variant dark`). Компоненти: `sidebar`, `sheet`, `breadcrumb`, `dropdown-menu`, `avatar`, `button`, `tooltip`, `separator`, `sonner`, `skeleton`. `next-themes` не додаємо.
- **Тема.** Власний невеликий `ThemeProvider` + хук: клас `dark` на `<html>`, вибір у `localStorage`, дефолт — `prefers-color-scheme`. Inline-скрипт у `app.blade.php` виставляє клас до першого рендера, щоб уникнути «спалаху» світлої теми. Альтернатива `next-themes` — зайва залежність, орієнтована на Next.
- **Sidebar.** Офіційний примітив `Sidebar` (`collapsible="icon"`, на мобільних — Sheet). Стан розгортання тримає `SidebarProvider` у persistent-layout, тож він переживає Inertia-навігацію без перезавантаження; cookie `sidebar_state` shadcn також зберігає вибір між візитами.
- **Навігація та breadcrumbs — один конфіг.** `Components/Admin/navigation.ts` описує пункти (назва, `href`, іконка) для макетних груп. Активний пункт — за `usePage().url` (префікс шляху). Breadcrumbs будуються з поточного URL і назв із цього конфігу, без передачі з контролерів. Альтернатива (prop `breadcrumbs` з кожного контролера) дублює логіку в усіх контролерах. Макетні пункти ведуть на `#`/неіснуючі маршрути, не створюючи заглушок на бекенді.
- **Persistent layout.** Сторінки задають `Page.layout = (page) => <AdminLayout>{page}</AdminLayout>`; layout не перемонтується між візитами (потрібно для вимоги про стан sidebar). Альтернатива — обгортка в кожній сторінці — перемонтовує sidebar.
- **Спільні пропси (`HandleInertiaRequests`).** `auth.user` = `{id, name, email}` (явний вибір полів, а не `->toArray()`, щоб не витекли приховані поля), `flash.success` / `flash.error` = `session()->get(...)`. Кореневий view — `app.blade.php` (`@vite`, `@inertiaHead`, `@inertia`). Middleware реєструється в `bootstrap/app.php` через `$middleware->web(append: ...)`.
- **Flash → toast.** Компонент `Components/Admin/FlashToaster` у layout читає `usePage().props.flash` і в `useEffect` викликає `toast.success/error`. Flash живе в сесії один запит, тому повторів немає; ефект залежить від значення пропса. `<Toaster />` (Sonner) монтується в layout, тема toast береться з `ThemeProvider`.
- **Auth вручну.** `GET/POST /login` (`guest`), `POST /logout` (`auth`), валідація через `FormRequest`, `Auth::attempt` із `session()->regenerate()`, throttling — стандартний `RateLimiter` на email+IP. Форма логіну — Inertia `useForm`. `User` реалізує `MustVerifyEmail`. Маршрут `verification.notice` потрібен, бо middleware `verified` редіректить на нього; робимо мінімальну сторінку «email не підтверджено» з кнопкою виходу (без надсилання листів — поза межами). `redirectGuestsTo('/login')` налаштовується в `bootstrap/app.php`.
- **Профіль — заглушка.** `GET /admin/profile` → `Admin/Profile.tsx` з коротким текстом; потрібен лише щоб посилання з меню працювало.
- **Seeder.** `DatabaseSeeder` створює користувача з підтвердженим email; email/пароль із `config`/env (`ADMIN_EMAIL`, `ADMIN_PASSWORD`) з dev-значеннями за замовчуванням лише поза `production`. Значення додаються в `.env.example`.
- **Тести.** PHPUnit feature-тести (`tests/Feature/Auth`, `tests/Feature/Admin`) для входу/виходу/гостя/непідтвердженого/спільних пропсів (`assertInertia`); Playwright `e2e/admin-shell.spec.js` для входу, сайдбара, теми, виходу. Ці e2e залежать від seeded-користувача в БД Docker.
- **Документація.** `docs/admin-shell.md` (стек, структура каталогів, layout, sidebar, тема, toasts, як додати пункт меню), `docs/admin-auth.md` (вхід, verified, seeder, змінні env), оновлення `docs/README.md` та `docs/e2e-testing.md`.

## Risks / Trade-offs

- [Сумісність пакетів з Vite 8 / Laravel 13 (`@vitejs/plugin-react`, shadcn, Inertia)] → перевірити peer-залежності при встановленні; npm вимагає `--legacy-peer-deps` (див. `docs/e2e-testing.md`); при неможливості зафіксувати сумісні версії — зупинитися й повідомити власника.
- [shadcn CLI очікує типову структуру та регістр каталогів] → налаштувати `components.json` aliases до першого `add`; після генерації перевірити імпорти.
- [Регістр `Components` на macOS vs Linux] → єдиний регістр; перевірка збіркою в контейнері/CI не потрібна, але імпорти звіряти grep-ом.
- [Спалах теми при завантаженні] → inline-скрипт у `app.blade.php` до завантаження бандла.
- [Ручна автентифікація — ризик безпеки] → використовувати лише штатні механізми Laravel (`Auth::attempt`, `regenerate`, `RateLimiter`, `Hash`), покрити тестами; розширення (reset, 2FA) — окремими змінами.
- [Макетні пункти меню ведуть у нікуди] → явно позначені як макет у документації; реальні маршрути додаються наступними змінами.
- [E2E залежать від стану БД] → seeder + явна передумова в `docs/e2e-testing.md`.

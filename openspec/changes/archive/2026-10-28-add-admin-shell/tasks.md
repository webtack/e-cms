# Tasks

## 1. Фронтенд-стек (Inertia + React + TypeScript)

- [x] 1.1 На хості встановити `react`, `react-dom`, `@inertiajs/react`, `@vitejs/plugin-react`, `typescript`, `@types/react`, `@types/react-dom` (`npm install --legacy-peer-deps`); перевірка: залежності в `package.json`, `package-lock.json` оновлено, `npm ls react` без помилок
- [x] 1.2 Додати `tsconfig.json` (alias `@/*` → `resources/js/*`, `jsx: react-jsx`, `strict`) і оновити `vite.config.js` (плагін React, alias `@`, вхід `resources/js/app.tsx`); перевірка: `npx tsc --noEmit` проходить
- [x] 1.3 Замінити `resources/js/app.js` на `resources/js/app.tsx` з `createInertiaApp` (`resolve` через `import.meta.glob('./Pages/**/*.tsx')`), JSDoc англійською; скрипт `build` = `tsc --noEmit && vite build`; перевірка: `npm run build` завершується без помилок
- [x] 1.4 Створити `docs/admin-shell.md` (розділ «Стек і збірка»: залежності, `npm run build`/`dev`, alias `@`, структура `resources/js`) і додати його в `docs/README.md`; перевірка: посилання в індексі працюють, команди з документа виконуються як написано

## 2. Бекенд: Inertia та автентифікація

- [x] 2.1 `docker compose exec app composer require inertiajs/inertia-laravel` і створити `resources/views/app.blade.php` (`@vite`, `@inertiaHead`, `@inertia`, inline-скрипт теми); перевірка: пакет у `composer.json`, view рендериться (feature-тест відповіді Inertia)
- [x] 2.2 Створити `App\Http\Middleware\HandleInertiaRequests` (кореневий view `app`, спільні `auth.user` лише з `id/name/email`, `flash.success/error`), зареєструвати в `bootstrap/app.php`, налаштувати редірект гостей на `/login`; перевірка: feature-тест `assertInertia` підтверджує спільні пропси і відсутність `password`/`remember_token`
- [x] 2.3 Реалізувати `Auth\LoginController` (show/store з `FormRequest`, `session()->regenerate()`, rate limiting), `Auth\LogoutController` та маршрути `/login`, `/logout` (+ сторінка `Auth/Login.tsx` з Inertia `useForm`), JSDoc англійською; перевірка: feature-тести — успішний вхід, неправильний пароль, редірект авторизованого з `/login`, вихід
- [x] 2.4 Додати `MustVerifyEmail` до `User`, мінімальний маршрут `verification.notice` зі сторінкою «email не підтверджено» та кнопкою виходу; перевірка: feature-тест — непідтверджений користувач не бачить `/admin/dashboard` і не отримує 500
- [x] 2.5 Оновити `DatabaseSeeder` (початковий користувач із підтвердженим email, `ADMIN_EMAIL`/`ADMIN_PASSWORD` з env, dev-значення лише поза production) і `.env.example`; перевірка: `docker compose exec app php artisan migrate:fresh --seed` проходить, користувач існує (feature-тест на seeder)
- [x] 2.6 Створити `docs/admin-auth.md` (вхід/вихід, verified, seeder і змінні env, rate limiting), додати в `docs/README.md`; перевірка: `docker compose exec app php artisan test` і `docker compose exec app vendor/bin/pint --dirty --format agent` без помилок, команди з документа працюють

## 3. shadcn/ui

- [x] 3.1 Виконати `npx shadcn@latest init` на хості та налаштувати `components.json` (TypeScript, база zinc, CSS-змінні, aliases на `@/Components`, `@/Components/ui`, `@/lib/utils`); перевірка: `components.json` і `resources/js/lib/utils.ts` існують, токени теми та `@custom-variant dark` у `resources/css/app.css`
- [x] 3.2 Додати компоненти `sidebar`, `sheet`, `breadcrumb`, `dropdown-menu`, `avatar`, `button`, `tooltip`, `separator`, `skeleton`, `input`, `label`, `sonner` у `resources/js/Components/ui/`; перевірка: файли існують, імпорти не містять `@/components` (регістр), `npm run build` проходить
- [x] 3.3 Додати в `docs/admin-shell.md` розділ «shadcn/ui» (як додавати компоненти, aliases, регістр `Components`); перевірка: описана команда `npx shadcn@latest add <name>` працює з поточним `components.json`

## 4. Адмін-оболонка (AdminLayout)

- [x] 4.1 Реалізувати `ThemeProvider` + хук `useTheme` (клас `dark` на `<html>`, `localStorage`, дефолт — системна тема) і `ThemeToggle` у `Components/Admin/`, JSDoc із `@example`; перевірка: збірка проходить, ручна перевірка перемикання й збереження після перезавантаження
- [x] 4.2 Створити `Components/Admin/navigation.ts` (макетні пункти: Dashboard, Content Types, Pages, Media Library, Settings) та `AppSidebar` на примітиві shadcn `Sidebar` (`collapsible="icon"`, мобільний Sheet, активний пункт за `usePage().url`); перевірка: збірка проходить, у браузері працюють згортання до іконок і мобільний drawer
- [x] 4.3 Створити `AdminHeader`: `SidebarTrigger` (гамбургер на мобільних), `Breadcrumbs` з URL і `navigation.ts`, `ThemeToggle`, `UserMenu` (імʼя/аватар-ініціали, посилання на профіль, logout через Inertia `router.post('/logout')`); перевірка: збірка проходить, меню відображає дані `auth.user`
- [x] 4.4 Створити `Layouts/AdminLayout.tsx` (`ThemeProvider`, `SidebarProvider`, `AppSidebar`, `AdminHeader`, `<Toaster />`, `FlashToaster` для `flash.success/error`), типи спільних пропсів у `resources/js/types/`; перевірка: `npx tsc --noEmit` і `npm run build` без помилок
- [x] 4.5 Додати в `docs/admin-shell.md` розділи про layout, sidebar, тему, toasts і «Як додати пункт меню»; перевірка: опис відповідає коду, посилання між розділами працюють

## 5. Дашборд і маршрути адмінки

- [x] 5.1 Додати групу маршрутів `/admin` (`auth`, `verified`, імена `admin.*`), `Admin\DashboardController` (`GET /admin/dashboard` → `Inertia::render('Admin/Dashboard')`) і редірект `/admin` → `/admin/dashboard`; перевірка: feature-тести — гість → `/login`, підтверджений користувач → 200 з компонентом `Admin/Dashboard`
- [x] 5.2 Створити `Pages/Admin/Dashboard.tsx` із `Dashboard.layout = ...` (persistent `AdminLayout`) та заглушкою вмісту; перевірка: `npm run build` проходить, сторінка відображається в оболонці
- [x] 5.3 Додати `GET /admin/profile` → `Admin/Profile.tsx` (заглушка) у тій самій групі; перевірка: feature-тест 200 для авторизованого, посилання з меню користувача відкриває сторінку
- [x] 5.4 Додати feature-тест, що flash `success`/`error` після редіректу потрапляє в спільні пропси один раз; перевірка: тест проходить, Pint без зауважень
- [x] 5.5 Оновити `docs/admin-shell.md` (маршрути `/admin`, Dashboard, Profile-заглушка, спільні пропси); перевірка: перелічені маршрути збігаються з `docker compose exec app php artisan route:list --path=admin`

## 6. E2E та інтеграційна перевірка

- [x] 6.1 Додати `e2e/admin-shell.spec.js` (гість на `/admin/dashboard` → `/login`; вхід seeded-користувачем → дашборд із sidebar; згортання sidebar; перемикання теми зі збереженням; вихід), з коментарями англійською; перевірка: `npm run test:e2e` проходить при запущених контейнерах і засіяній БД
- [x] 6.2 Оновити `docs/e2e-testing.md` (новий сценарій, передумова `migrate --seed`, облікові дані з env); перевірка: описані команди виконуються як написано
- [x] 6.3 Повний прогін: `npm run build`, `docker compose exec app php artisan test`, `docker compose exec app vendor/bin/pint --dirty --format agent`, `npm run test:e2e`, `openspec validate add-admin-shell`; перевірка: усе без помилок, `docs/README.md` індекс актуальний

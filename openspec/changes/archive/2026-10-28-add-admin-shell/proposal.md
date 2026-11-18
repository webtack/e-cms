# Proposal

## Why

The application is a bare Laravel 13 skeleton: there is no frontend framework (only `resources/js/app.js` with a stub), no authentication, and no admin panel. A modular CMS needs a single admin shell (layout, navigation, theme, notifications) on which content types, the page builder and the media library will be built. Doing it now, before the functionality, is cheaper than rebuilding it later.

## What Changes

- Connect **Inertia.js (React) + TypeScript (`.tsx`)** to the existing Vite/Tailwind 4 stack; the Inertia root is `resources/views/app.blade.php`, the entry is `resources/js/app.tsx`.
- Initialize **shadcn/ui** (zinc theme, rounded corners), UI primitives in `resources/js/Components/ui/`, admin components in `resources/js/Components/Admin/`.
- Add **custom minimal authentication** (without Breeze): Login pages, a Logout action, enabling `MustVerifyEmail` on `User`, an initial user via a seeder.
- Add **`AdminLayout`** as a persistent layout (`Page.layout`): a collapsible sidebar (shadcn `Sidebar`: expanded / icons-only, a mobile Sheet), navigation with layout groups (Dashboard, Content Types, Pages, Media Library, Settings) and highlighting of the active item, a header with breadcrumbs, a light/dark theme switcher and a user menu (name/avatar, a link to the profile, logout via Inertia).
- Add global **toast notifications** (shadcn Sonner), linked to the `success` / `error` flash messages.
- Add the `/admin` route group (`auth` + `verified`), `Admin\DashboardController` and the `Admin/Dashboard.tsx` page.
- Add `HandleInertiaRequests`, which shares the user and the flash messages.
- Add Profile as a **stub page** (so that the link from the user menu leads to a working route); profile editing is out of scope.
- Add an e2e test (Playwright): login → `/admin/dashboard`, a guest is redirected to login.
- **Not included:** the content type builder, datatables (TanStack Table), Spatie Media Library integration, registration, password reset, profile editing.

## Capabilities

### New Capabilities
- `admin-auth`: administrator login/logout, protection of `/admin/*` with the `auth` + `verified` middleware, guest redirects.
- `admin-shell`: the persistent `AdminLayout` — sidebar, header (breadcrumbs, theme, user menu), toast notifications, shared Inertia props, the start page `/admin/dashboard`.

### Modified Capabilities
<!-- None: the only existing specification, `e2e-testing`, does not change requirements. -->

## Impact

- **Backend (PHP, via `docker compose exec app`):** `composer require inertiajs/inertia-laravel`; `bootstrap/app.php` (the `HandleInertiaRequests` middleware, guest redirect), `routes/web.php`, new `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Auth/*`, `app/Http/Controllers/Admin/DashboardController.php`, `app/Models/User.php` (`MustVerifyEmail`), `database/seeders/DatabaseSeeder.php`, PHPUnit feature tests.
- **Frontend (npm on the host):** `@inertiajs/react`, `react`, `react-dom`, `typescript`, `@vitejs/plugin-react`, shadcn/ui dependencies (Radix, `lucide-react`, `sonner`, `class-variance-authority`, `clsx`, `tailwind-merge`), `vite.config.js`, `tsconfig.json`, `components.json`, `resources/css/app.css` (theme tokens), `resources/js/**`. Installing npm requires `--legacy-peer-deps` (see `docs/e2e-testing.md`).
- **Tests:** a new `e2e/admin-shell.spec.js`; the e2e tests require a seeded user and migrations in the Docker DB.
- **Documentation (`docs/`, in English):** create `docs/admin-shell.md` (stack, directory structure, layout, sidebar, theme, toasts), `docs/admin-auth.md` (login, verified, seeder); update `docs/README.md` (index) and `docs/e2e-testing.md` (the new scenario and the dependency on the seeder).
- **Known deviations from the brief:** the project is on Laravel 13 (not 11); TypeScript was chosen, so the page is `Admin/Dashboard.tsx`, not `.jsx`.

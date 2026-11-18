# Tasks

## 1. Frontend stack (Inertia + React + TypeScript)

- [x] 1.1 On the host install `react`, `react-dom`, `@inertiajs/react`, `@vitejs/plugin-react`, `typescript`, `@types/react`, `@types/react-dom` (`npm install --legacy-peer-deps`); check: the dependencies are updated in `package.json` and `package-lock.json`, `npm ls react` shows no errors
- [x] 1.2 Add `tsconfig.json` (alias `@/*` → `resources/js/*`, `jsx: react-jsx`, `strict`) and update `vite.config.js` (the React plugin, alias `@`, entry `resources/js/app.tsx`); check: `npx tsc --noEmit` passes
- [x] 1.3 Replace `resources/js/app.js` with `resources/js/app.tsx` using `createInertiaApp` (`resolve` via `import.meta.glob('./Pages/**/*.tsx')`), JSDoc in English; the `build` script = `tsc --noEmit && vite build`; check: `npm run build` completes without errors
- [x] 1.4 Create `docs/admin-shell.md` (the section "Stack and build": dependencies, `npm run build`/`dev`, alias `@`, the structure of `resources/js`) and add it to `docs/README.md`; check: the links in the index work, the commands from the document work as written

## 2. Backend: Inertia and authentication

- [x] 2.1 `docker compose exec app composer require inertiajs/inertia-laravel` and create `resources/views/app.blade.php` (`@vite`, `@inertiaHead`, `@inertia`, the inline theme script); check: the package is in `composer.json`, the view renders (a feature test of the Inertia response)
- [x] 2.2 Create `App\Http\Middleware\HandleInertiaRequests` (root view `app`, shared `auth.user` with only `id/name/email`, `flash.success/error`), register it in `bootstrap/app.php`, configure the guest redirect to `/login`; check: a feature test with `assertInertia` confirms the shared props and the absence of `password`/`remember_token`
- [x] 2.3 Implement `Auth\LoginController` (show/store with a `FormRequest`, `session()->regenerate()`, rate limiting), `Auth\LogoutController` and the routes `/login`, `/logout` (+ the page `Auth/Login.tsx` with an Inertia `useForm`), JSDoc in English; check: feature tests — successful login, wrong password, redirect of an authenticated user away from `/login`, logout
- [x] 2.4 Add `MustVerifyEmail` to `User`, a minimal route `verification.notice` with an "email not verified" page and a logout button; check: a feature test — an unverified user does not see `/admin/dashboard` and does not get a 500
- [x] 2.5 Update `DatabaseSeeder` (an initial user with a confirmed email, `ADMIN_EMAIL`/`ADMIN_PASSWORD` from env, dev values only outside production) and `.env.example`; check: `docker compose exec app php artisan migrate:fresh --seed` passes, the user exists (a feature test of the seeder)
- [x] 2.6 Create `docs/admin-auth.md` (login/logout, verified, the seeder and env variables, rate limiting), add it to `docs/README.md`; check: `docker compose exec app php artisan test` and `docker compose exec app vendor/bin/pint --dirty --format agent` without errors, the commands from the document work

## 3. shadcn/ui

- [x] 3.1 Run `npx shadcn@latest init` on the host and configure `components.json` (TypeScript, base zinc, CSS variables, aliases to `@/Components`, `@/Components/ui`, `@/lib/utils`); check: `components.json` and `resources/js/lib/utils.ts` exist, the theme tokens and `@custom-variant dark` are in `resources/css/app.css`
- [x] 3.2 Add the components `sidebar`, `sheet`, `breadcrumb`, `dropdown-menu`, `avatar`, `button`, `tooltip`, `separator`, `skeleton`, `input`, `label`, `sonner` to `resources/js/Components/ui/`; check: the files exist, the imports do not contain `@/components` (casing), `npm run build` passes
- [x] 3.3 Add a section "shadcn/ui" to `docs/admin-shell.md` (how to add components, aliases, the `Components` casing); check: the described command `npx shadcn@latest add <name>` works with the current `components.json`

## 4. Admin shell (AdminLayout)

- [x] 4.1 Implement `ThemeProvider` + the hook `useTheme` (the class `dark` on `<html>`, `localStorage`, the default — the system theme) and `ThemeToggle` in `Components/Admin/`, JSDoc with `@example`; check: the build passes, a manual check of switching and persistence after a reload
- [x] 4.2 Create `Components/Admin/navigation.ts` (layout items: Dashboard, Content Types, Pages, Media Library, Settings) and `AppSidebar` on the shadcn `Sidebar` primitive (`collapsible="icon"`, a mobile Sheet, the active item via `usePage().url`); check: the build passes, collapsing to icons and the mobile drawer work in the browser
- [x] 4.3 Create `AdminHeader`: `SidebarTrigger` (hamburger on mobile), `Breadcrumbs` from the URL and `navigation.ts`, `ThemeToggle`, `UserMenu` (name/avatar initials, a link to the profile, logout via Inertia `router.post('/logout')`); check: the build passes, the menu shows the `auth.user` data
- [x] 4.4 Create `Layouts/AdminLayout.tsx` (`ThemeProvider`, `SidebarProvider`, `AppSidebar`, `AdminHeader`, `<Toaster />`, `FlashToaster` for `flash.success/error`), the types of the shared props in `resources/js/types/`; check: `npx tsc --noEmit` and `npm run build` without errors
- [x] 4.5 Add sections on the layout, sidebar, theme, toasts and "How to add a menu item" to `docs/admin-shell.md`; check: the description matches the code, the links between sections work

## 5. Dashboard and admin routes

- [x] 5.1 Add the `/admin` route group (`auth`, `verified`, names `admin.*`), `Admin\DashboardController` (`GET /admin/dashboard` → `Inertia::render('Admin/Dashboard')`) and the redirect `/admin` → `/admin/dashboard`; check: feature tests — a guest → `/login`, a confirmed user → 200 with the component `Admin/Dashboard`
- [x] 5.2 Create `Pages/Admin/Dashboard.tsx` with `Dashboard.layout = ...` (the persistent `AdminLayout`) and a stub content; check: `npm run build` passes, the page is displayed in the shell
- [x] 5.3 Add `GET /admin/profile` → `Admin/Profile.tsx` (a stub) in the same group; check: a feature test returns 200 for an authenticated user, the link from the user menu opens the page
- [x] 5.4 Add a feature test that a `success`/`error` flash after a redirect reaches the shared props once; check: the test passes, Pint reports no issues
- [x] 5.5 Update `docs/admin-shell.md` (the `/admin` routes, the Dashboard, the Profile stub, the shared props); check: the listed routes match `docker compose exec app php artisan route:list --path=admin`

## 6. E2E and integration check

- [x] 6.1 Add `e2e/admin-shell.spec.js` (a guest on `/admin/dashboard` → `/login`; login with the seeded user → the dashboard with the sidebar; collapsing the sidebar; switching the theme with persistence; logout), with comments in English; check: `npm run test:e2e` passes with the containers running and the DB seeded
- [x] 6.2 Update `docs/e2e-testing.md` (the new scenario, the precondition `migrate --seed`, credentials from env); check: the described commands work as written
- [x] 6.3 Full run: `npm run build`, `docker compose exec app php artisan test`, `docker compose exec app vendor/bin/pint --dirty --format agent`, `npm run test:e2e`, `openspec validate add-admin-shell`; check: everything passes without errors, the `docs/README.md` index is up to date

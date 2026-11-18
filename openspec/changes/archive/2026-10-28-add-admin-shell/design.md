# Design

## Context

Current state (verified in code): Laravel 13 (`laravel/framework ^13.17`), PHP 8.3+, Tailwind 4 via `@tailwindcss/vite`, Vite 8 with `laravel-vite-plugin`; `resources/js/app.js` is empty, the only view is `welcome.blade.php`, and `routes/web.php` has only `/`. There is no authentication: `app/Http/Controllers` has only the base `Controller`, there is `User` (without `MustVerifyEmail`) and the standard `users`/`sessions` migrations. The DB in Docker is MySQL (`ecms_db`), sessions are in the DB. PHP runs only in the `app` container, npm only on the host. The motivation and boundaries are in `proposal.md`, the behaviour is in `specs/admin-auth` and `specs/admin-shell`.

## Goals / Non-Goals

**Goals:**
- A working login and `/admin/dashboard` in a single persistent shell, suitable as the foundation for the next sections.
- One source of truth for the navigation (sidebar and breadcrumbs).
- The build `npm run build` without TypeScript and bundler errors.

**Non-Goals:**
- Registration, password reset, sending verification emails, profile editing, roles/permissions.
- Server-side navigation from the DB (the menu is static), i18n of the interface.
- SSR for Inertia.

## Decisions

- **Inertia without Breeze.** We install `inertiajs/inertia-laravel` and `@inertiajs/react` directly; auth is written by hand (`Auth\LoginController`, `LogoutController`). The owner's decision. Alternative — Breeze React: it brings its own layout/pages, which would have to be rewritten for shadcn and `/admin`. Check the package versions for compatibility with Laravel 13/Vite 8 during installation.
- **TypeScript (`.tsx`).** The owner's decision; the shadcn CLI generates TSX, `tsc --noEmit` catches errors before the build. The `build` script = `tsc --noEmit && vite build`. The page is `Admin/Dashboard.tsx` (a deviation from `.jsx` in the brief).
- **Frontend structure.** Entry `resources/js/app.tsx` (`createInertiaApp`, `resolve` via `import.meta.glob('./Pages/**/*.tsx')`, a default layout for `Admin/*` pages via `Page.layout`). Directories: `Pages/`, `Layouts/AdminLayout.tsx`, `Components/ui/` (shadcn), `Components/Admin/` (sidebar, header, breadcrumbs, user menu, theme toggle), `lib/utils.ts`, `hooks/`. Alias `@` → `resources/js` (in `vite.config.js` and `tsconfig.json`).
- **`Components` casing.** The brief sets `Components/` with a capital letter, while shadcn defaults to `components/`. In `components.json` we set the aliases to `@/Components/ui` etc. Risk: macOS is case-insensitive, while the Linux container is case-sensitive — use only one casing everywhere.
- **shadcn/ui + Tailwind 4.** `npx shadcn@latest init` (base zinc, CSS variables, `--radius`); theme tokens are in `resources/css/app.css` (`@theme inline`, `@custom-variant dark`). Components: `sidebar`, `sheet`, `breadcrumb`, `dropdown-menu`, `avatar`, `button`, `tooltip`, `separator`, `sonner`, `skeleton`. We do not add `next-themes`.
- **Theme.** A small custom `ThemeProvider` + hook: the class `dark` on `<html>`, the choice in `localStorage`, the default — `prefers-color-scheme`. An inline script in `app.blade.php` sets the class before the first render to avoid a "flash" of the light theme. Alternative `next-themes` — an extra dependency aimed at Next.
- **Sidebar.** The official `Sidebar` primitive (`collapsible="icon"`, a Sheet on mobile). The expanded state is held by `SidebarProvider` in the persistent layout, so it survives Inertia navigation without a reload; the shadcn `sidebar_state` cookie also keeps the choice between visits.
- **Navigation and breadcrumbs — one config.** `Components/Admin/navigation.ts` describes the items (title, `href`, icon) for the layout groups. The active item is determined by `usePage().url` (path prefix). Breadcrumbs are built from the current URL and the titles from this config, without being passed from controllers. Alternative (a `breadcrumbs` prop from each controller) duplicates the logic in all controllers. The layout items point to `#`/non-existent routes, without creating stubs on the backend.
- **Persistent layout.** Pages set `Page.layout = (page) => <AdminLayout>{page}</AdminLayout>`; the layout is not remounted between visits (required by the sidebar-state requirement). The alternative — a wrapper in each page — remounts the sidebar.
- **Shared props (`HandleInertiaRequests`).** `auth.user` = `{id, name, email}` (an explicit choice of fields, not `->toArray()`, so that hidden fields do not leak), `flash.success` / `flash.error` = `session()->get(...)`. The root view is `app.blade.php` (`@vite`, `@inertiaHead`, `@inertia`). The middleware is registered in `bootstrap/app.php` via `$middleware->web(append: ...)`.
- **Flash → toast.** The `Components/Admin/FlashToaster` component in the layout reads `usePage().props.flash` and calls `toast.success/error` in a `useEffect`. Flash lives in the session for one request, so there are no repeats; the effect depends on the prop value. `<Toaster />` (Sonner) is mounted in the layout, the toast theme is taken from `ThemeProvider`.
- **Manual auth.** `GET/POST /login` (`guest`), `POST /logout` (`auth`), validation through a `FormRequest`, `Auth::attempt` with `session()->regenerate()`, throttling — the standard `RateLimiter` on email+IP. The login form is an Inertia `useForm`. `User` implements `MustVerifyEmail`. The route `verification.notice` is needed because the `verified` middleware redirects to it; we make a minimal "email not verified" page with a logout button (without sending emails — out of scope). `redirectGuestsTo('/login')` is configured in `bootstrap/app.php`.
- **Profile — a stub.** `GET /admin/profile` → `Admin/Profile.tsx` with a short text; it is needed only so that the link from the menu works.
- **Seeder.** `DatabaseSeeder` creates a user with a confirmed email; the email/password come from `config`/env (`ADMIN_EMAIL`, `ADMIN_PASSWORD`) with dev defaults only outside `production`. The values are added to `.env.example`.
- **Tests.** PHPUnit feature tests (`tests/Feature/Auth`, `tests/Feature/Admin`) for login/logout/guest/unverified/shared props (`assertInertia`); Playwright `e2e/admin-shell.spec.js` for login, sidebar, theme, logout. These e2e tests depend on the seeded user in the Docker DB.
- **Documentation.** `docs/admin-shell.md` (stack, directory structure, layout, sidebar, theme, toasts, how to add a menu item), `docs/admin-auth.md` (login, verified, seeder, env variables), updates to `docs/README.md` and `docs/e2e-testing.md`.

## Risks / Trade-offs

- [Compatibility of the packages with Vite 8 / Laravel 13 (`@vitejs/plugin-react`, shadcn, Inertia)] → check the peer dependencies when installing; npm requires `--legacy-peer-deps` (see `docs/e2e-testing.md`); if compatible versions cannot be pinned — stop and inform the owner.
- [The shadcn CLI expects the typical structure and directory casing] → configure the `components.json` aliases before the first `add`; check the imports after generation.
- [`Components` casing on macOS vs Linux] → one casing; verification by build in the container/CI is not needed, but the imports are checked with grep.
- [Theme flash on load] → an inline script in `app.blade.php` before the bundle loads.
- [Manual authentication — a security risk] → use only the standard Laravel mechanisms (`Auth::attempt`, `regenerate`, `RateLimiter`, `Hash`), cover them with tests; extensions (reset, 2FA) — in separate changes.
- [Stub menu items lead nowhere] → explicitly marked as a layout in the documentation; the real routes are added by the next changes.
- [E2E depend on the DB state] → a seeder + an explicit precondition in `docs/e2e-testing.md`.

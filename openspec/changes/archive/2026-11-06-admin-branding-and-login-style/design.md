## Context

`AppSidebar` renders the header as the plain text "E-CMS", hidden when the sidebar is collapsed to icons. `Pages/Auth/Login.tsx` is a bare centred form with a `LocaleSwitcher` in the corner. The site name is stored in `app_settings` as `site.name` and read via `App\Settings\SiteSettings::name()`; it is not yet shared with admin pages (`HandleInertiaRequests` shares `auth`, `navigation`, `locale`, `richtext`, `flash`). The images `public/images/logo-mini.png` (119×32 RGBA) and `public/images/auth-bg.png` (2048×1152) exist and are served as static files.

## Goals / Non-Goals

**Goals:** logo and site name in the sidebar header; styled login page with background, card, and logo.

**Non-Goals:** using the logos from site settings (`logo_large`/`logo_small`) in the admin; editing the images; changing login behaviour; styling `VerifyEmail`.

## Decisions

- **Shared prop `site.name`.** Add `'site' => ['name' => fn () => app(SiteSettings::class)->name() ?? config('app.name')]` to `HandleInertiaRequests::share()`, lazy like `navigation`. Rejected: a per-controller prop (every admin page would need it) and a fetch from the client (extra request). The fallback matches `PublicData`. The prop does not depend on the user, so it is also available on the guest login page, which shows the name. Add `site: { name: string }` to the shared props type.
- **Sidebar header.** Replace the span with a block: `<img src="/images/logo-mini.png">` and below it a `truncate` text with the name; the whole block keeps `group-data-[collapsible=icon]:hidden`. The `img` has an empty `alt` (decorative; the name is next to it) and explicit `width`/`height` to avoid layout shift. A `data-testid` is added for the name for e2e.
- **Dark theme.** Two `img` elements are rendered: `logo-mini.png` with `dark:hidden` and `logo-mini-white.png` with `hidden dark:block` (the `dark` class is set on `<html>` by `ThemeProvider` and the inline script in `app.blade.php`). The same pair is used on the login page. A small shared component `BrandLogo` (`Components/Admin/BrandLogo.tsx`) holds the pair so the sidebar and the login page do not duplicate it.
- **Login page.** The root container gets the background through inline style `backgroundImage: url('/images/auth-bg.png')` with `bg-cover bg-center`; the form moves into `<div className="w-full max-w-sm space-y-4 rounded-xl border bg-card p-6 shadow-sm">`, with the logo and the site name (from `usePage().props.site.name`) above the `h1`. Inline `url()` is used because the file is in `public/`, outside the Vite pipeline. The `LocaleSwitcher` stays in the top-right corner. The `h1` keeps its size (spec `admin-visual-style`, "Auth pages unchanged").

## Risks / Trade-offs

- The background image is 2048×1152 and loads on every first visit to `/login` → acceptable for a single static file; the browser caches it.
- The legibility of the language switcher over the image → check on both themes during implementation; wrap in a card-coloured chip if needed.

## Docs

`docs/admin-shell.md` (sidebar header, shared props), `docs/admin-auth.md` (login page), `docs/admin-visual-style.md` (auth pages line).

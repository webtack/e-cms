## Why

The admin sidebar shows the plain text "E-CMS" and the login page is an unstyled bare form. A logo, the site's own name, and a styled login screen give the admin panel a recognisable identity.

## What Changes

- The sidebar header shows the logo `public/images/logo-mini.png` instead of the text "E-CMS"; in the dark theme it shows `public/images/logo-mini-white.png`.
- Under the logo the sidebar shows the site name from `/admin/settings` (`site.name`, in the current interface language, with the fallback of `SiteSettings::name()` and then `config('app.name')`).
- The site name is passed to the admin pages as a shared Inertia prop.
- The login page gets the background image `public/images/auth-bg.png`, the form is wrapped in a `bg-card` block, and the logo (the white variant in the dark theme) and the site name are shown above the form.
- Assumptions (no ambiguity that changes scope): the collapsed (icon-only) sidebar hides the whole header block, as the text did before; the logos are fixed static files, not the logos from site settings.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-shell`: the sidebar header shows the logo and the site name.
- `admin-auth`: the login page has a background image, a card around the form, and the logo.

## Impact

- Code: `resources/js/Components/Admin/AppSidebar.tsx`, `resources/js/Pages/Auth/Login.tsx`, `app/Http/Middleware/HandleInertiaRequests.php` (shared `site.name`), `resources/js/types/index.d.ts`.
- Tests: a Feature test for the shared prop, including for a guest (`tests/Feature/Admin`); e2e `admin-shell` (sidebar header, login page) and `admin-site-settings` if the name change is checked there.
- Docs: `docs/admin-shell.md` (sidebar header, shared prop), `docs/admin-auth.md` (login page look), `docs/admin-visual-style.md` (auth pages note).

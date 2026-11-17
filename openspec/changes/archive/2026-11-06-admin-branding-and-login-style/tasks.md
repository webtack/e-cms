## 1. Shared site name

- [x] 1.1 Add the lazy `site.name` prop (`SiteSettings::name()` with fallback to `config('app.name')`) to `HandleInertiaRequests::share()` and describe it in the method's docblock; add the `site` shared prop type in `resources/js/types/index.d.ts` with JSDoc.
- [x] 1.2 Add a Feature test for the shared prop (stored name, fallback to the app name, and availability for a guest) in `tests/Feature/Admin`. Verify: `docker compose exec app php artisan test --compact --filter=<Name>`, then `docker compose exec app vendor/bin/pint --dirty --format agent`.
- [x] 1.3 Document the shared prop in `docs/admin-shell.md`.

## 2. Sidebar header

- [x] 2.0 Add `Components/Admin/BrandLogo.tsx`: renders `/images/logo-mini.png` (`dark:hidden`) and `/images/logo-mini-white.png` (`hidden dark:block`) with explicit size and empty `alt`; JSDoc with `@example`.
- [x] 2.1 In `resources/js/Components/Admin/AppSidebar.tsx` replace the "E-CMS" text with `BrandLogo` and, under it, the site name from `props.site.name`; keep the block hidden when the sidebar is collapsed; add `data-testid` for the name; update the component JSDoc.
- [x] 2.2 Update the sidebar section of `docs/admin-shell.md`.
- [x] 2.3 Extend `e2e/admin-shell.spec.js`: the header shows the logo and the site name, and nothing says "E-CMS". Verify: `npm run build`, then `npm run test:e2e -- e2e/admin-shell.spec.js -g "<name>"`.

## 3. Login page

- [x] 3.1 In `resources/js/Pages/Auth/Login.tsx` add the background `/images/auth-bg.png`, wrap the form in a `bg-card` block with border, padding, and shadow, and add `BrandLogo` and the site name above the heading; keep the `LocaleSwitcher` legible; update the component JSDoc.
- [x] 3.2 Update `docs/admin-auth.md` (login page look) and the "Auth pages" line in `docs/admin-visual-style.md`.
- [x] 3.3 Extend the login-page test in `e2e/admin-shell.spec.js` (logo, site name, and card visible) and run the login-related tests of `admin-shell` and `admin-i18n` (`-g` on the login-page tests), since they use the form.

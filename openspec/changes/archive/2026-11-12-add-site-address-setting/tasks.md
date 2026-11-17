## 1. Backend

- [x] 1.1 Add `address()` to `app/Settings/SiteSettings.php`; include `address` in `formValues()` (and its docblock type) and in the keys handled by `save()`; update the class docblock with JSDoc/PHPDoc in English.
- [x] 1.2 Add `address` to `LIMITS` (500) and a `nullable|string|max` rule in `UpdateSiteSettingsRequest`; pass `address` in the `limits` prop in `SiteSettingsController`.
- [x] 1.3 Add Feature tests (save, clear, read with fallback, validation limit) in `tests/Feature/Settings/SiteSettings*Test.php` and `tests/Feature/Admin/SiteSettings*Test.php`; run `docker compose exec app php artisan test --compact --filter=SiteSettings`; run `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 2. Frontend

- [x] 2.1 In `resources/js/Pages/Admin/Settings/Site.tsx` add `address` to the form types/props/limits and render an "Address" `textarea` (`id="address"`, error display) in the "Contacts" section after phones; add translations to `lang/uk.json` and `lang/en.json`; keep JSDoc in English.
- [x] 2.2 Extend `e2e/admin-site-settings.spec.js` with an address fill/save/reload check; run `npm run test:e2e -- e2e/admin-site-settings.spec.js -g "<fragment>"` for the new test; run `npm run build`.

## 3. Documentation

- [x] 3.1 Update `docs/admin-site-settings.md` (key count and table, props, validation table, interface "Contacts" block) and the `site.` key mention in `docs/app-settings.md` if it lists keys.

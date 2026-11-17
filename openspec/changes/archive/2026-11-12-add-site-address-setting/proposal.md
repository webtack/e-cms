## Why

The "Contacts" block of the `/admin/settings` page holds emails and phones but has no postal address. Templates need a translatable address (for example for the footer or the contacts page).

## What Changes

- Add the setting `site.address`: an optional multiline translatable text stored as `{"default": "…"}`, like `site.description`.
- Add a "Address" `textarea` to the "Contacts" block of `/admin/settings` (the form edits only the `default` language).
- Add `SiteSettings::address()` and include `address` in `formValues()` and `save()`.
- Validate `address` as a nullable string up to 500 characters and expose the limit to the page.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `site-settings`: the set of keys and the list of translatable settings include `site.address`.
- `admin-site-settings`: the "Contacts" block includes the address `textarea`.

## Impact

- Code: `app/Settings/SiteSettings.php`, `app/Http/Requests/Admin/UpdateSiteSettingsRequest.php`, `app/Http/Controllers/Admin/SiteSettingsController.php`, `resources/js/Pages/Admin/Settings/Site.tsx`, `lang/uk.json`, `lang/en.json`.
- Tests: `tests/Feature/Settings/SiteSettings*Test.php`, `tests/Feature/Admin/SiteSettings*Test.php`, `e2e/admin-site-settings.spec.js`.
- Docs: `docs/admin-site-settings.md` (data format, props, validation, interface).
- No migrations: the value lives in `app_settings`.

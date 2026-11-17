# Proposal

## Why

The storage for translations already exists (`app_languages`, the `lang` column of the value tables, term and menu item translations), but there is no way to use it from the admin panel: content languages and the multilingual switch can only be changed through code or the database, so no translation UI can appear. The site also has no named base language: `default` is a placeholder without a code, while `<html lang>`, `hreflang` and `/en/` URL prefixes need the real code of the base language.

## What Changes

- New admin page `/admin/settings/languages` (right `settings`): the multilingual switch, the base language code, and a list of additional content languages (add, rename, activate/deactivate, weight, delete).
- New setting `content.base_lang`: the code of the language the `default` values are written in. Enabling multilingual mode requires it to be set; without a saved value it reads as the application locale.
- An additional language cannot have the same code as the base language.
- **Deleting a language is rejected while any translation of it exists**, not only content values: term translations and menu item translations count too, and later changes register their own translations (labels, site settings, file descriptions) through one registry of "language usages".
- A link to the new sub-page from the "Site" settings page; the sidebar keeps "Site" highlighted on it.

## Capabilities

### New Capabilities
- `admin-content-languages`: the admin page that manages content languages, the multilingual switch and the base language code.

### Modified Capabilities
- `content-languages`: adds the base language code; the deletion guard counts every kind of stored translation, not only content values.

## Impact

- Code: new controller, form requests and routes under `can:settings`; `ContentLanguages` gets the base language reader/writer and the usage registry; `AppLanguage` deletion guard uses the registry; new React page `Pages/Admin/Settings/Languages.tsx`; link on `Pages/Admin/Settings/Site.tsx` and the title mapping in `navigation.ts`; `lang/en.json`, `lang/uk.json`.
- Database: no migrations (settings go to `app_settings`).
- Docs: new `docs/admin-content-languages.md`; update `docs/content-languages.md`, `docs/admin-site-settings.md` (link), `docs/README.md`, `docs/e2e-testing.md` (new spec row).
- Tests: PHP Feature tests for the page and the domain rules; new e2e spec `admin-content-languages`.
- Later changes in this series (`translation-ui-foundation`, `translate-nodes-and-types`, `translate-site-and-media`, `public-language-routing`) build on this one.

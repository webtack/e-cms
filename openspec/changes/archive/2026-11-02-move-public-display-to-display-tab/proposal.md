# Proposal

## Why

The settings form of a type (`/edit`) has become too long: besides the main fields it contains the sections «Список записів» ("List of records") and «Публічне відображення» ("Public display"). The tab «Розділ» ("Section") — a separate page with its own form and save button — exists only for the kind `content`, while «Публічне відображення» concerns all three kinds. It is more logical to gather everything that defines how the type looks on the site into one tab, and to unload the settings form.

## What Changes

- The tab «Розділ» is renamed to **«Відображення»** (`Display`): the address `/{section}/{key}/display` instead of `/content-types/{key}/section`, the page heading and the menu item.
- The tab «Відображення» appears for **all kinds** (`content`, `page`, `block`) in the menu between «Налаштування» ("Settings") and «Поля» ("Fields") (for `content` — before «SEO»).
- The section «Публічне відображення» (`settings.public`) is **moved** from the settings form of the type to the tab «Відображення» without changing its behaviour, the restrictions by kind and the format of the saved data.
- The content of the tab: for `content` — the block of values of the section (`section_title`, `section_description`, `section_cover`) and the block «Публічне відображення»; for `page` and `block` — only «Публічне відображення» (they have no own values of the section).
- The tab has **one shared button «Зберегти»** ("Save") that saves both the values of the section and `settings.public` in one request (`PUT /{section}/{key}/display`) atomically: an error in any part saves nothing.
- The settings form of the type keeps the main fields and the section «Список записів»; saving the form no longer accepts `settings.public`.
- The tab «SEO» remains as it is (only `content`, its own form and `PUT /content-types/{key}/values`).
- **BREAKING** (only the admin): the address `/admin/content-types/{key}/section` disappears (404), the named route `admin.content-types.section` is replaced by `admin.{section}.display`.

## Capabilities

### New Capabilities

### Modified Capabilities
- `admin-content-types`: the tab «Розділ» → «Відображення» for all kinds with a shared saving; the section «Публічне відображення» leaves the settings form; the form and the saving of changes of the type without `settings.public`; the menu of the section.
- `admin-page-types`: the item «Відображення» in the menu of the section of a page type.
- `admin-block-types`: the item «Відображення» in the menu of the section of a block type.

## Impact

- Code: `routes/web.php` (the routes `display` in the loop of kinds, remove `/section`), `ContentTypeController` (instead of `sectionTab()` — `displayTab()`; remove `publicOptions` from `edit()` and `mergePublicSettings` from `update()`), a new controller and a form request for `PUT …/display`, `StoreContentTypeRequest::sharedRules()` (the rules `settings.public` move to the request of the tab), `ContentTypeValuesController` remains for SEO; React: `Pages/Admin/ContentTypes/Section.tsx` → `Display.tsx`, the new form of the tab (values of the section + `PublicDisplaySettingsSection`), `ContentTypeNav.tsx`, `ContentTypeForm.tsx`, `Edit.tsx`, `TypeValuesPanels.tsx`; `lang/uk.json`, `lang/en.json` (the key «Display»).
- Tests: `ContentTypeValuesTest`, `ContentTypeEditTest` (the scenarios of public settings move to the new test of the tab), `PageTypeTest`, `BlockTypeTest`; e2e `admin-content-types.spec.js`, `admin-page-types.spec.js`, `admin-block-types.spec.js`.
- Docs (to update): `docs/admin-content-types.md` (the section «Публічне відображення у формі» ("Public display in the form"), the description of the menu and the tabs, the routes, the tests), `docs/admin-page-types.md`, `docs/admin-block-types.md` (the menu of the section), `docs/e2e-testing.md` (the tables «Область → спек» ("Area → spec") and the description of the specs), if needed `docs/content-model.md` and `docs/content-querying.md` (references to the form), check `docs/README.md`.
- There are no DB migrations; the format of `settings.public` and of the values `section_*` does not change.

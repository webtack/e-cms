# Tasks

## 1. Domain: base language and language usages

- [x] 1.1 Add the base language code to `ContentLanguages` (`baseLang()`, `setBaseLang()`, normalization, fallback to `config('app.locale')`, rejection of a code equal to an additional language, rejection of enabling multilingual without a saved code). Verify with `docker compose exec app php artisan test --compact --filter=ContentLanguages` covering the scenarios of the `content-languages` delta; add JSDoc-style English docblocks with `@example` for the new public methods.
- [x] 1.2 Reject adding or renaming an additional language to the saved base code in `AppLanguage` validation. Verify with `--filter=AppLanguage`.
- [x] 1.3 Introduce the language usage registry (interface and container tag `content.language-usages`) and move the existing `content_values_*` check into a checker; add checkers for term translations and menu item translations; make `AppLanguage::deleting` ask the registry. Verify with `--filter=AppLanguage` and `--filter=ContentLanguages` including a test that a term translation and a menu item translation block deletion.
- [x] 1.4 Update `docs/content-languages.md` (base language code, usage registry, deletion rule, how a feature registers its checker) and `docs/app-settings.md` (new key `content.base_lang`). Verify that the examples in the docs match the code.

- [x] 1.5 Read the unsaved base language from the new config key `content.base_lang_default` (`env('APP_LOCALE', 'en')`) instead of `config('app.locale')`, and update the examples in `docs/content-languages.md`. Verify with a Feature test that a request with the interface locale `uk` and the configured default `en` (and the reverse) reports the configured default; adjust `ContentLanguageAdminTest` accordingly.

## 2. Admin backend

- [x] 2.1 Add `ContentLanguageController` with routes under `can:settings` (`index`, `updateSettings`, `store`, `update`, `destroy`), form requests, and the language route binding by `code`; `index` returns the switch, the effective and saved base code, and the languages ordered by weight. Verify with new Feature tests (`--filter=ContentLanguageAdmin`) covering access (403 / login redirect), enabling without a base code, base code collisions, add/edit/delete and deletion with a term translation.
- [x] 2.2 Write `docs/admin-content-languages.md` (routes, page response, validation, messages) and link it from `docs/README.md`, `docs/content-languages.md` and `docs/admin-site-settings.md`. Verify that all links resolve.

## 3. Admin UI

- [x] 3.1 Build `Pages/Admin/Settings/Languages.tsx` (switch and base code form, languages table with add/edit/delete dialog, explanations, errors next to fields), add the link on `Pages/Admin/Settings/Site.tsx` and the title mapping in `navigation.ts`; add `lang/en.json` and `lang/uk.json` strings. Verify with `npm run build` and the e2e spec of task 3.2; every new component and exported helper has English JSDoc with `@example` where usage is not obvious.
- [x] 3.2 Add `e2e/admin-content-languages.spec.js` (enable switch needs base code, add/activate/reorder/delete a language, delete blocked by a translation; it snapshots existing `app_languages` rows and the settings `content.multilingual` and `content.base_lang` before it starts and restores them after it, and deletes only the data it created, so saved data is never lost) and its row in the "area -> spec" table of `docs/e2e-testing.md`. Verify with `npm run test:e2e -- e2e/admin-content-languages.spec.js`; also run `e2e/admin-site-settings.spec.js` because the Site page gets a link.

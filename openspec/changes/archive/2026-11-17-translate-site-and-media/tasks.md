# Tasks

## 1. Domain: site settings and file descriptions

- [x] 1.1 Add `SiteSettings::saveTranslation()` / `clearTranslation()` / a translation form shape (rows by identity, prefilled values), and register a usage checker for site settings. Verify with `docker compose exec app php artisan test --compact --filter=SiteSettings` covering every scenario of the `site-settings` delta; English docblocks with `@example`.
- [x] 1.2 Add the migration `media_translations`, the `FileTranslation` model, the `FileDescriptions` service (read one language, save one language with the equal-to-base rule, cascade) and its usage checker. Verify with the full `php artisan test --compact` (schema change) and `--filter=FileDescription` for the `media-library` delta scenarios.
- [x] 1.3 Update `docs/file-storage.md`, `docs/media-library.md` and the settings docs (storage shape and the one-language operation). Verify the examples against the code.

## 2. Site settings page

- [x] 2.1 `SiteSettingsController`: `?lang=` through the shared concern, translation-mode response, `PUT/DELETE /admin/settings/translations/{lang}`, requests with the translation rules. Verify with `--filter=SiteSettingsTranslation` (all scenarios of the `admin-site-settings` delta, access 403).
- [x] 2.2 `Site.tsx` in the translation mode as the same form with the same layout (the translatable settings editable and prefilled, schedule table prefilled; the identity rows, logos, indexing and every other non-translatable control stay in place, shown inert and dimmed with their base values; "Save" disabled until a translatable value changes, "Clear translation" in the action bar through `ClearTranslationButton`); the form shows the switcher above it when languages are active. Verify with `npm run build` and `npm run test:e2e -- e2e/admin-site-settings.spec.js` after adding translation tests.
- [x] 2.3 Update `docs/admin-site-settings.md` and `docs/admin-translation-mode.md`. Verify the routes against `php artisan route:list`.

## 3. File names and descriptions in forms

- [x] 3.1 Extend the node and type-value translation save actions to accept `file_meta.<uuid>.description` for uuids that belong to the owner's values, and return file descriptions (prefilled) in the translation-mode response. Verify with `--filter=ContentNodeTranslation` and `--filter=ContentTypeValuesTranslation` (shared file, non-translatable field, foreign uuid rejected, name unchanged).
- [x] 3.2 Node form and type tabs: description inputs for files in the translation mode (read-only file for non-translatable fields). Verify with `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "translation"` and `e2e/admin-content-types.spec.js -g "translation"`.
- [x] 3.3 Media library details panel: translated descriptions with language names. Verify with `npm run test:e2e -- e2e/admin-media.spec.js -g "details"`.
- [x] 3.4 Update `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/media-library.md` and `docs/e2e-testing.md`. Verify that the documented behavior matches the specs.

## 4. Translated file names

- [x] 4.1 Add the migration `name` (nullable) and nullable `description` to `media_translations`; extend `FileTranslation`, `FileDescriptions` (names read, saved with the equal-to-base rule, a row holds at least one value), `TranslationSaver::saveFileDescriptions()`, the translation requests (`file_meta.<uuid>.name`) and the prefill in the node, type and media responses. Verify with `--filter=FileDescription`, `--filter=ContentNodeFileDescriptionTranslation`, `--filter=MediaLibraryTest`.
- [x] 4.2 Node form, type tabs and media details: editable name in the file dialog and in the block of a non-translatable field; translations with names in the details panel. Verify with the e2e translation tests of nodes, types and `admin-media -g "details"`.
- [x] 4.3 Update the docs (`file-storage`, `media-library`, `admin-content-nodes`, `admin-content-types`, `e2e-testing`).

# Tasks

## 1. Domain: optional fields and saving one language

- [x] 1.1 Add a migration making `taxonomy_term_translations.name` and `menu_item_translations.title` nullable (rollback guarded against null rows). Verify with the full `docker compose exec app php artisan test --compact` (schema change) and `migrate:rollback`/`migrate` on the dev database.
- [x] 1.2 Add `saveTranslation(Term $term, string $lang, array $data)` and `clearTranslation()` to `TermWriter`: validate the language as writable, store only non-empty values that differ from `default`, delete the row when nothing remains, never touch other languages; adjust `create/update` so a blank non-base name no longer deletes a translation that still has a description. Verify with `--filter=Taxonomy` / `--filter=Term` Feature tests covering every scenario of the `taxonomy` delta; English docblocks with `@example`.
- [x] 1.3 Add the same operations to `MenuItemWriter` and make `MenuTree`/readers treat a null title as missing. Verify with `--filter=Menu` tests for the `menus` delta, including a partial translation read by `MenuTree`.
- [x] 1.4 Update `docs/taxonomy.md` and `docs/menus.md` (optional fields, one-language saving, equal-to-base rule). Verify that the examples match the code.

## 2. Backend of the translation mode

- [x] 2.1 Add the controller concern that resolves and validates `?lang=` (404 rules of the spec) and builds the shared `translation` prop with the per-entity `translated` marker. Verify with a Feature test of the concern through the term controller (active, inactive, unknown language, multilingual off).
- [x] 2.2 Term controller: `edit` returns prefilled translation-mode values; add `PUT .../terms/{term}/translations/{lang}` and `DELETE` of the same address under `can:taxonomy`; the create and base update stop handling translations. Verify with `--filter=TermAdmin` (base save keeps translations, save one language, equal-to-base, clear, access).
- [x] 2.3 Menu item controller: the same for items under `can:menus`. Verify with `--filter=MenuItemAdmin`.
- [x] 2.4 Write `docs/admin-translation-mode.md` (contract, props, routes, the single "translated" definition, how a new entity adopts it) and link it from `docs/README.md`. Verify that all links resolve.

## 3. Frontend foundation

- [x] 3.1 Build `LanguageSwitcher`, `TranslationFormShell` and `useDirtyGuard` (switcher with markers and unsaved-changes confirmation, save disabled until dirty, clear with confirmation); English JSDoc with `@example`; strings in `lang/en.json` and `lang/uk.json`. Verify with `npm run build`.
- [x] 3.2 Rework `TermForm` into base mode (no translation blocks) plus the translation mode through the shell, and wire the edit page. Verify with `npm run test:e2e -- e2e/admin-taxonomy.spec.js` after updating the spec (remove the old translation-block tests, add: switcher visibility, prefill, save, equal-to-base, clear, unsaved confirmation).
- [x] 3.3 Rework `MenuItemForm` the same way. Verify with `npm run test:e2e -- e2e/admin-menus.spec.js` after updating the spec.
- [x] 3.4 Update `docs/admin-taxonomy.md`, `docs/admin-menus.md` and the "shared code -> specs" table in `docs/e2e-testing.md` (the new shared components and their consumers `TermForm`, `MenuItemForm`). Verify that the documented behavior matches the e2e specs.

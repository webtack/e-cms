# Tasks

## 1. Configuration and the mode «all» in the selection

- [x] 1.1 Add `content.max_all` (1000) to `config/content.php` and the mode `per_page = 0` in `ContentNodeQuery::paginate()` (`limit(max_all)`, the result is one `LengthAwarePaginator`; `max_per_page` only for `> 0`), update the JSDoc/PHPDoc of `NodeQueryParams`. Check: the new tests in `tests/Feature/Content` (all records, the ceiling, the usual number is unchanged) — `docker compose exec app php artisan test --compact --filter=ContentNodeQuery`; the task has comments with `@example`.
- [x] 1.2 Update `docs/content-querying.md` (the sections on pagination/parameters: `per_page = 0`, `max_all`). Check: the links in the document are valid.

## 2. Model and reading of the public settings

- [x] 2.1 Add `App\Content\PublicDisplaySettings` (`clean()`, `formOptions()`, `resolve()`): only added fields are admissible, `title` always, `page_fields` absent = all, the default sorting `published_at desc`, `pagination` is effectively «no» with `per_page = 0`; English PHPDoc with `@example`. Check: unit/feature tests of the default values, the expansion of «all», the rejection of stale keys — `docker compose exec app php artisan test --compact --filter=PublicDisplaySettings`.
- [x] 2.2 Add `ContentType::assertValidPublicSettings()` (the fields of the type, only added ones, sortable ones, the direction, `per_page` in `[0, max_per_page]`, the restrictions by the kind `content`) and call it where `assertValidListSettings()` is called. Check: tests of `ContentType`/the settings of the type by the scenarios of the spec content-model — `docker compose exec app php artisan test --compact --filter=ContentType`.
- [x] 2.3 Update `docs/content-model.md` (the section «Налаштування типу (`settings`)»: the key `public`, the default values, the restrictions by kind) and `docs/content-querying.md` (reading the public settings). Check: the cross-references work.

## 3. Saving through the form of the type

- [x] 3.1 Generalize `ContentTypeController::mergeListSettings()` to the replacement of one key (`list`, `public`), add `publicOptions` in `edit()`, extend `StoreContentTypeRequest` (`array:list,public` and the rules `settings.public.*`; `page_fields` is absent when «Усі» is chosen). Check: Feature tests of saving (the scenarios of admin-content-types, `list` is not erased, `page`/`block` reject the list keys) — `docker compose exec app php artisan test --compact --filter=ContentType`; `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 4. The section «Публічне відображення» in the admin

- [x] 4.1 Add the component `PublicDisplaySettingsSection` (the switch «Усі додані поля» + checkboxes for the page, checkboxes for the card, the sorting, the number, the switch of the paginator; for `page`/`block` only the fields of the page; the paginator is disabled at `0`) and connect it in `ContentTypeForm` (the mode `edit`) and `Edit.tsx`; English JSDoc with `@example`; keys in `lang/uk.json` and `lang/en.json`. Check: `npm run build` without errors, the section is visible in the browser.
- [x] 4.2 Add a scenario to `e2e/admin-content-types.spec.js` (the default values, the explicit choice of the page fields, the return to «Усі», `0` switches off the paginator, saving and returning the state, `page` without the fields of the list) and run only it: `npm run test:e2e -- e2e/admin-content-types.spec.js`.
- [x] 4.3 Update `docs/admin-content-types.md` and the description of the spec in `docs/e2e-testing.md` (the tables «область → спек» do not change, there is no new spec). Check: the description matches the scenario.

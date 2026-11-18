# Tasks

## 1. Backend: field types and integrity

- [x] 1.1 Create `App\Content\FieldTypes\TypeRefType` and `NodeRefType` (by the pattern of `TermType`: `table()` = `content_values_string`, `rules`, `prepare`, `cast`, `operators` = `eq`, `in`, `settingsErrors`, `lockReferences`) with PHPDoc in English and `@example`; register `type_ref` and `node_ref` in `config/content.php`. Check: `docker compose exec app php artisan test --compact --filter=RefFieldType` (see 1.2).
- [x] 1.2 Add Feature tests `tests/Feature/Content/TypeRefFieldTypeTest.php` and `NodeRefFieldTypeTest.php` (the scenarios of the spec `content-values`: valid/invalid values, settings, immutability, multiplicity, translation, filters). Check: `--filter=RefFieldType` is green; `tests/Feature/Content/FieldTypeExtensibilityTest.php` is green.
- [x] 1.3 Add the protection against deletion: a check of the usage in `deleting` of the models `ContentNode` (`node_ref`) and `ContentType` (`type_ref` and `node_ref` on the records of the type, except the own values of the type) through a separate class (by the pattern of `TermUsage`). Check: tests of deleting a record/type with and without references — `--filter=RefUsage`.
- [x] 1.4 Sorting by the label: in `ContentNodeQuery::applySort` for `type_ref`/`node_ref` join `content_types.name` / `title` (the language `default`) of the target by the first value; `NodeListSettings::sortable()` keeps these types sortable. Check: a Feature test of the order (ascending/descending, records without a value last) — `--filter=ContentNodeQuery` and `--filter=ContentNodeIndex`.
- [x] 1.5 Documentation: `docs/content-values.md` (the table of types, the sections «Посилання на тип» and «Посилання на запис»), `docs/admin-content-fields.md` (the types and `settings`), update the cross-references. Check: `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks, the links are valid.

## 2. Search endpoints and data of the form

- [x] 2.1 Add a search controller and the routes in the admin group (`GET /lookup/content-types`, `GET /lookup/nodes`): validation of the parameters, the limit 20, a stable order, the search by `name` / `title` of the language `default`. Check: the Feature test `tests/Feature/Admin/ReferenceLookupTest.php` (the results, an empty request, 422, a guest) — `--filter=ReferenceLookup`.
- [x] 2.2 In `ContentNodeController` give the labels of the selected values of the form (`labels`) and the labels and `href` of the targets in the cells of the table (`{label, href}`) in one request per type of the field (by the pattern of `termNames()`). Check: a Feature test of the table and the form — `--filter=ContentNode`.
- [x] 2.3 Documentation: `docs/admin-content-nodes.md` (the endpoints, the cell of the table, sorting). Check: the links are valid, Pint without remarks.

## 3. Admin: settings of the field and widgets

- [x] 3.1 In `ContentFieldForm.tsx` add the settings of `type_ref` (the choice of the kind) and `node_ref` (the choice of the type with a search), blocking at `has_values`, errors next to the choice; translations in `lang/uk.json` and `lang/en.json`. Check: `npm run build`; `docker compose exec app php artisan test --compact tests/Feature/Locale`; JSDoc in English with `@example`.
- [x] 3.2 Create `RefAutocomplete.tsx` (debounce, cancelling of outdated requests, clearing, the label of the selected) and connect it in `NodeFieldInput` for both types; update `types.ts`, `ContentNodeForm.initialValue`, `Pages/Admin/ContentNodes/Index.tsx` (the cell — Inertia `<Link>` without `target`). Check: `npm run build` without errors; JSDoc in English with `@example`.
- [x] 3.3 Add the e2e spec `e2e/content-reference-fields.spec.js` (the settings of both fields, the search and the choice, saving and reopening with the label, clearing, the label-link in the table, the transition by it in the same window, sorting of the column); update the tables in `docs/e2e-testing.md`. Check: `npm run test:e2e -- e2e/content-reference-fields.spec.js` is green; also the specs of the fields and of the form of records per the table in `docs/e2e-testing.md`.
- [x] 3.4 Update `docs/admin-content-fields.md` and `docs/admin-content-nodes.md` (the widget, the settings). Check: the description matches the implementation, the links are valid.

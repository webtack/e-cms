# Tasks

## 1. Бекенд: типи полів і цілісність

- [x] 1.1 Створити `App\Content\FieldTypes\TypeRefType` і `NodeRefType` (за зразком `TermType`: `table()` = `content_values_string`, `rules`, `prepare`, `cast`, `operators` = `eq`, `in`, `settingsErrors`, `lockReferences`) з PHPDoc англійською та `@example`; зареєструвати `type_ref` і `node_ref` у `config/content.php`. Перевірка: `docker compose exec app php artisan test --compact --filter=RefFieldType` (див. 1.2).
- [x] 1.2 Додати Feature-тести `tests/Feature/Content/TypeRefFieldTypeTest.php` і `NodeRefFieldTypeTest.php` (сценарії спеки `content-values`: валідні/невалідні значення, налаштування, незмінність, множинність, переклад, фільтри). Перевірка: `--filter=RefFieldType` зелений; `tests/Feature/Content/FieldTypeExtensibilityTest.php` зелений.
- [x] 1.3 Додати захист від видалення: перевірка використання у `deleting` моделей `ContentNode` (`node_ref`) і `ContentType` (`type_ref` та `node_ref` на записи типу, крім власних значень типу) через окремий клас (за зразком `TermUsage`). Перевірка: тести видалення запису/типу з посиланнями й без — `--filter=RefUsage`.
- [x] 1.4 Сортування за підписом: у `ContentNodeQuery::applySort` для `type_ref`/`node_ref` приєднати `content_types.name` / `title` (мова `default`) цілі за першим значенням; `NodeListSettings::sortable()` лишає ці типи сортованими. Перевірка: Feature-тест порядку (зростання/спадання, записи без значення останні) — `--filter=ContentNodeQuery` та `--filter=ContentNodeIndex`.
- [x] 1.5 Документація: `docs/content-values.md` (таблиця типів, розділи «Посилання на тип» і «Посилання на запис»), `docs/admin-content-fields.md` (типи й `settings`), оновити перехресні посилання. Перевірка: `docker compose exec app vendor/bin/pint --dirty --format agent` без зауважень, посилання валідні.

## 2. Ендпоінти пошуку та дані форми

- [x] 2.1 Додати контролер пошуку й маршрути в адмін-групі (`GET /lookup/content-types`, `GET /lookup/nodes`): валідація параметрів, ліміт 20, стабільний порядок, пошук за `name` / `title` мови `default`. Перевірка: Feature-тест `tests/Feature/Admin/ReferenceLookupTest.php` (результати, порожній запит, 422, гість) — `--filter=ReferenceLookup`.
- [x] 2.2 У `ContentNodeController` віддавати підписи вибраних значень форми (`labels`) і підписи й `href` цілей у комірках таблиці (`{label, href}`) одним запитом на тип поля (за зразком `termNames()`). Перевірка: Feature-тест таблиці й форми — `--filter=ContentNode`.
- [x] 2.3 Документація: `docs/admin-content-nodes.md` (ендпоінти, комірка таблиці, сортування). Перевірка: посилання валідні, Pint без зауважень.

## 3. Адмінка: налаштування поля і віджети

- [x] 3.1 У `ContentFieldForm.tsx` додати налаштування `type_ref` (вибір виду) і `node_ref` (вибір типу з пошуком), блокування при `has_values`, помилки біля вибору; переклади в `lang/uk.json` і `lang/en.json`. Перевірка: `npm run build`; `docker compose exec app php artisan test --compact tests/Feature/Locale`; JSDoc англійською з `@example`.
- [x] 3.2 Створити `RefAutocomplete.tsx` (debounce, скасування застарілих запитів, очищення, підпис вибраного) і підключити в `NodeFieldInput` для обох типів; оновити `types.ts`, `ContentNodeForm.initialValue`, `Pages/Admin/ContentNodes/Index.tsx` (комірка — Inertia `<Link>` без `target`). Перевірка: `npm run build` без помилок; JSDoc англійською з `@example`.
- [x] 3.3 Додати e2e-спек `e2e/content-reference-fields.spec.js` (налаштування обох полів, пошук і вибір, збереження й повторне відкриття з підписом, очищення, підпис-посилання в таблиці, перехід за ним у тому ж вікні, сортування колонки); оновити таблиці в `docs/e2e-testing.md`. Перевірка: `npm run test:e2e -- e2e/content-reference-fields.spec.js` зелений; також спеки полів і форми записів за таблицею `docs/e2e-testing.md`.
- [x] 3.4 Оновити `docs/admin-content-fields.md` і `docs/admin-content-nodes.md` (віджет, налаштування). Перевірка: опис збігається з реалізацією, посилання валідні.

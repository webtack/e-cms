# Tasks

## 1. Translation and e2e

- [x] 1.1 In `lang/uk.json` replace all forms of «вузол» with «запис» (the key `"Nodes"` → «Записи», «Створити запис», «Видалити запис», «Редагувати запис», «Запис створено/оновлено/видалено.», «Записи типу «:name»», hints and messages about forbidding the deletion of a type with records); do not change the English keys. Check: `grep -n "вузл\|Вузл" lang/uk.json` is empty, and `docker compose exec app php -r 'json_decode(file_get_contents("lang/uk.json"), false, 512, JSON_THROW_ON_ERROR); echo "ok";'` prints `ok`.
- [x] 1.2 Update the expectations in `e2e/admin-content-nodes.spec.js` (`/Вузли типу/`, `Видалити вузол`, `Створити вузол`) and `e2e/admin-content-types.spec.js` (`'Вузли'` in the list of menu items) to the new labels. Check: `grep -n "вузл\|Вузл" e2e/*.js` is empty.
- [x] 1.3 Run only `npm run test:e2e -- e2e/admin-content-nodes.spec.js` and `npm run test:e2e -- e2e/admin-content-types.spec.js` (the change is only translation rows; do not run the other specs). Check: both specs are green; if they fail because of `locale = uk`, reset the locale to `en` and repeat.

## 2. Documentation in docs/

- [x] 2.1 In `docs/admin-content-nodes.md` and `docs/admin-content-types.md` replace «вузол» with «запис» contextually (keeping `nodes_count`, `/nodes`, `ContentNode…`), resolving the collision with the already existing «запис» in the sense of a DB row. Check: `grep -n "вузл\|Вузл\|вузо\|Вузе" docs/admin-content-nodes.md docs/admin-content-types.md` is empty, the links to sections (`#зв'язок-з-вузлами` etc.) are updated together with the headings and work.
- [x] 2.2 The same for `docs/content-model.md`, `docs/content-values.md`, `docs/content-querying.md`, `docs/admin-block-types.md`, `docs/admin-page-types.md`, `docs/admin-data-table.md`. Check: `grep` for `вузл|Вузл|вузо|Вузе` in these files is empty, internal anchors that contained «вузл» are fixed in all referencing files (`grep -rn "#.*вузл" docs`).
- [x] 2.3 Update `docs/README.md` (the item describing nodes) and `docs/e2e-testing.md` (the rows of the tables «Вузли типу контенту» etc., the heading of the scenario section). File and spec names do not change. Check: `grep -rn "вузл\|Вузл\|вузо\|Вузе" docs` is empty, all links in `docs/README.md` lead to existing files.

## 3. Main specifications

- [x] 3.1 Editorially replace the term «вузол» with «запис» in prose, requirement headings and scenarios of the main specifications `admin-block-types`, `admin-page-types`, `content-model`, `content-querying`, `content-values` and in `admin-content-nodes`, `admin-content-types` — except the requirements that have deltas in this change («Сторінка списку вузлів», «Меню дій рядка», «Горизонтальне меню розділу типу»): do not touch them, synchronization will update them. Resolve the collision with «запис» in the sense of a DB row. Check: `grep -rn "вузл\|Вузл\|вузо\|Вузе" openspec/specs` returns only the three mentioned requirements.
- [x] 3.2 Check the change. Check: `openspec validate rename-nodes-to-entries-uk --strict` without errors (the scenario names «Вузли лише цього типу», «Вузли», «Меню на сторінках вузлів» in the deltas intentionally remain old: the validator matches them by name).
- [x] 3.3 After the synchronization of the deltas with the main specifications (during `/opsx:archive`) rename manually in `openspec/specs/admin-content-nodes/spec.md` the scenario «Вузли лише цього типу» → «Записи лише цього типу», and in `openspec/specs/admin-content-types/spec.md` — «Вузли» → «Записи» and «Меню на сторінках вузлів» → «Меню на сторінках записів», and include this in the same archiving commit. Check: `grep -rn "вузл\|Вузл\|вузо\|Вузе" openspec/specs docs lang e2e` is empty.

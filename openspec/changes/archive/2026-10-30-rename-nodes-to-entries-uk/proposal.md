# Proposal

## Why

In the Ukrainian interface `ContentNode` is called «вузол» («Вузли», «Створити вузол», «Вузол створено.»). The project owner considers this term unsuitable for editors: «вузол» is a technical word from graph theory, not a CMS concept. «Запис» was chosen (an analogue of entries in Strapi, Craft, Contentful): countable, neutral, and it does not coincide with the already taken «елемент» (repeated fields) or with «Контент» (`Content`, «Типи контенту»).

## What Changes

- Ukrainian translation: all forms of the word «вузол» (`вузол`, `вузла`, `вузли`, `вузлів`, `вузлам`, `вузлом` …) are replaced with the corresponding forms of the word «запис» in `lang/uk.json`: the key `"Nodes"` → «Записи», `"Create node"` → «Створити запис», `"Node created."` → «Запис створено.», `"Nodes of “:name”"` → «Записи типу «:name»» and the other rows with «вузол».
- The e2e expectations of the specs (`e2e/admin-content-nodes.spec.js`, `e2e/admin-content-types.spec.js`) are updated to the new labels.
- The Ukrainian documentation in `docs/` and the wording in the main specifications use «запис» instead of «вузол».
- The labels of the items «Вузли» in the row action menu of a type and in the horizontal menu of the type section become «Записи» (observable behaviour, so these requirements change through deltas).
- **Not changed**: the English translation keys, `node`/`ContentNode` in code and DB, the routes `/admin/content-types/{key}/nodes`, file and spec names (`admin-content-nodes`), the English locale, the behaviour of the list, forms and deletion. Only the text changes.
- Archived changes in `openspec/changes/archive/` are history and are not edited.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: the requirement «Сторінка списку вузлів» is renamed to «Сторінка списку записів»; the current item of the section menu is «Записи».
- `admin-content-types`: the requirements «Меню дій рядка» and «Горизонтальне меню розділу типу» — the menu item is called «Записи» (it was «Вузли»).

The other specifications (`admin-block-types`, `admin-page-types`, `content-model`, `content-querying`, `content-values` and other requirements listed above) mention «вузол» only as a term in prose, without interface labels. Their requirements do not change; the term in them is updated by an editorial edit during apply (see design.md).

## Impact

- Code: `lang/uk.json` (≈12 rows). The English keys and `resources/js` are not touched.
- Tests: `e2e/admin-content-nodes.spec.js`, `e2e/admin-content-types.spec.js`.
- Documentation to update: `docs/README.md`, `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/admin-block-types.md`, `docs/admin-page-types.md`, `docs/admin-data-table.md`, `docs/content-model.md`, `docs/content-querying.md`, `docs/content-values.md`, `docs/e2e-testing.md`. No new files; links and file names do not change.
- Specifications: deltas in `admin-content-nodes`, `admin-content-types`; an editorial change of the term in the main specifications `admin-block-types`, `admin-page-types`, `admin-content-nodes`, `admin-content-types`, `content-model`, `content-querying`, `content-values`.
- DB, migrations, API, dependencies: no changes.

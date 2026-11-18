# Design

## Context

- The Ukrainian interface text lives in `lang/uk.json`; `resources/js` and PHP contain English keys (`"Nodes"`, `"Create node"` …), so the code does not contain the Ukrainian «вузол» (checked by searching `resources`, `app`, `tests`, `database`, `routes`).
- The word «вузол» (all cases) is also in: `e2e/admin-content-nodes.spec.js` (lines 386, 392, 396), `e2e/admin-content-types.spec.js` (360), 10 files in `docs/` and 7 main specifications (`admin-block-types`, `admin-content-nodes`, `admin-content-types`, `admin-page-types`, `content-model`, `content-querying`, `content-values`).
- Interface labels in the specifications, in quotes «…», appear only in three requirements: «Сторінка списку вузлів» (`admin-content-nodes`), «Меню дій рядка» and «Горизонтальне меню розділу типу» (`admin-content-types`). Only they change the observable behaviour and go through deltas.
- «Вузол» and «запис» are masculine, the endings coincide, except for the genitive singular («вузла» → «запису»).
- The word «запис» is already in `docs/` and the specifications: ≈85 occurrences, mostly verb forms («записується», «записати»), and also the noun in the sense of "a DB row" («Тип контенту визначається записом», «Поле визначається записом»).

## Goals / Non-Goals

**Goals:**
- In the Ukrainian text (UI, e2e expectations, docs, specifications) everywhere «запис» instead of «вузол».
- No changes in the code, routes, translation keys, DB and the English locale.
- The e2e specs of the areas touched by the translation remain green.

**Non-Goals:**
- Renaming `ContentNode`, `node`, `/nodes`, file names, the specs `admin-content-nodes`, `docs/admin-content-nodes.md`.
- Editing the archived changes in `openspec/changes/archive/`.
- Changing the English labels («Nodes» remains in English).

## Decisions

1. **The word «Записи»** (chosen by the owner among the variants «Матеріали», «Документи», «Сутності» etc.): countable, neutral, does not coincide with «елемент» (repeated fields) and «Контент».
2. **Deltas only for requirements with interface labels.** Other mentions in the specifications are a term in prose; the behaviour does not change. Deltas: `admin-content-nodes` (RENAMED + MODIFIED «Сторінка списку записів») and `admin-content-types` (MODIFIED of two requirements). The requirement heading is renamed through `RENAMED`, not by a direct edit, so that the delta finds the requirement during synchronization.
3. **An editorial edit of the rest of the main specifications during apply**, directly in `openspec/specs/*/spec.md`: a contextual replacement of the term in prose, in requirement headings (except «Сторінка списку вузлів», which the delta renames) and in scenarios. Requirements that have a delta are not edited manually; synchronization will update them during archiving. The alternative (MODIFIED for each of the ≈40 requirements) is rejected: hundreds of lines of repeated text without a change of behaviour.
4. **The replacement is contextual, not automatic.** Rule: the forms of the word «вузол» are replaced with the corresponding forms of «запис», with a check of the case and of the agreement of adjectives/numerals; technical identifiers (`ContentNode`, `content_node_id`, `/nodes`, `nodes_count`, `node` in code) are not changed.
5. **A collision with the meaning "DB row".** Where the replacement creates an ambiguity (e.g. «запис запису»), the old noun «запис» in the sense of a DB row is reworded («рядок у БД», «дані»), while verb forms are not touched. It is decided for each occurrence while editing the corresponding file.
6. **Verification.** A search for `вузл|Вузл|вузо|Вузе` outside `openspec/changes/archive/` must return an empty result; e2e only `admin-content-nodes` and `admin-content-types` (the area "Area → spec" in `docs/e2e-testing.md`; the change does not touch a shared component, only `lang/uk.json`, which all pages read, but only the node rows change).

## Risks / Trade-offs

- **Ambiguity of «запис»** (see item 5): a risk of missing the noun «запис» in the DB sense. Mitigation: review each occurrence of `[Зз]апис` in the changed files.
- **A missed word form** (e.g. «вузлоподібний», «вузлові»): a final search `вузл|вузо|вузе` should reveal it.
- **Scenario names in deltas.** The validator requires the scenario names to match the old ones, so three names («Вузли лише цього типу», «Вузли», «Меню на сторінках вузлів») stay old in the deltas and are renamed manually in the main specifications after synchronization (task 3.3).
- **Mismatch of the delta and the main specification**: if a requirement with a delta is edited manually, synchronization may not find it by the old name. Mitigation: do not edit requirements with deltas manually (item 3).
- **The e2e dependence on the user's language**: if the e2e fail because of `locale = uk`, resetting the locale to `en` without asking is allowed (see the project memory).
- Documentation described by the change: `docs/README.md`, `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/admin-block-types.md`, `docs/admin-page-types.md`, `docs/admin-data-table.md`, `docs/content-model.md`, `docs/content-querying.md`, `docs/content-values.md`, `docs/e2e-testing.md`.

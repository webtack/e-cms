# Proposal

## Why

Node values, the type's own values (section and SEO) and the labels of fields are the bulk of what the public site shows, and their storage already carries a language (`lang` in the value tables). Editors still cannot enter a single translation: the node form writes only the base language. This change adds the translation mode (defined in `translation-ui-foundation`) to nodes, to type values and to field labels, and lets editors see which nodes lack a translation.

## What Changes

- **Nodes:** the edit page of a node of any kind (content, page, block) gets the translation mode (`?lang=`) in the same layout as the base form: translatable fields editable (`title`, `meta_*`, regular fields with `is_translatable`), prefilled from the base, saved by one button, values equal to the base not stored, "Clear translation".
- **Node list:** a "Translations" column (languages that have a translation) and a filter "Without translation in <language>", both shown only when multilingual mode is on and a language is active.
- **Type values:** the "Display" tab (`section_*`) and the "SEO" tab (`meta_*`) of a type get the same mode; the type's shared settings stay in place, disabled, in a translation.
- **Field labels:** the label (`name`) of any field can be translated in the translation mode of the field edit page. Translations live in the field's `settings` under `labels`, so no new table is needed. The base field form never changes them, and cloning a type copies them.
- A single, tested "save one language" operation for node and type values; required fields are not enforced in a translation (an empty field means "use the base").
- Registers field labels with the language usage registry, so a language that has a translated label cannot be deleted.

## Capabilities

### New Capabilities
- None.

### Modified Capabilities
- `admin-content-nodes`: translation mode of the node edit page, the Translations column and the missing-translation filter.
- `admin-content-types`: translation mode of the Display and SEO tabs.
- `admin-content-fields`: translation of a field's label; `settings.labels` exempt from per-type settings restrictions.
- `content-values`: saving one language of a node or a type; the "translated in a language" definition.
- `content-model`: storage and cloning of translated field labels.

## Impact

- Code: node, type values and field controllers (edit with `?lang=`, translation save/clear routes), a translation saving service for node and type values, the translated-languages query for the list, changes to `StoreContentFieldRequest` / `ContentFieldController` so `labels` is accepted for every type and preserved by the base form, `ContentTypeCloner`, a usage checker for labels; React: `ContentNodeForm` and the node list, type tabs forms, `ContentFieldForm` (all through the shared shell).
- Database: no migrations.
- Docs: update `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/content-values.md`, `docs/content-model.md`, `docs/admin-translation-mode.md`, `docs/e2e-testing.md`.
- Tests: Feature tests for the saving service, controllers, the list column and filter, labels and cloning; e2e `admin-content-nodes` (translation tests only, with `-g`), `admin-content-types`, `admin-content-fields`.
- Depends on `content-languages-admin` and `translation-ui-foundation`.

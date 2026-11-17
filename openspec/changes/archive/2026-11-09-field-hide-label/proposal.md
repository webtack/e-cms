# Proposal

## Why

The older CMS has a per-field setting "Hidden label" (`hidden_label`): the label of the field is not printed on the material page. 43 of 62 legacy field definitions use it, because most fields (body, preview, date, video) read better without a caption. The new CMS always prints the field name as a label, so the imported pages would show captions the editors deliberately switched off, and an editor has no way to turn a caption off for a new field. The legacy import (`import-legacy-site`) needs this setting to exist before it can carry the value over.

The second legacy setting "Hidden field" (`hidden`) needs no new field flag: what the public page shows is already chosen on the "Public display" tab of the type (`page_fields`). That mapping is handled in `import-legacy-site`.

## What Changes

- A new boolean setting of a content field, `hide_label` (default `false`): when it is on, the public templates do not print the label of the field. It is a general property of any field type, stored in its own column of `content_fields`, not in `settings`.
- The create and edit forms of a field get a checkbox "Hide label" with a hint that it affects the public page only.
- The default theme: `fields/field.blade.php` and the 15 `fields/field-{ftype}.blade.php` print `field__label` only when `hide_label` is off. A theme's own templates receive `$field->hide_label` and decide for themselves.
- The setting does not change the value, its validation or storage, and the admin node form keeps showing the label of every field (the editor still needs it). It is allowed for system fields too.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-fields`: new requirement "Hiding the label of a field" (the checkbox, saving, the default).
- `content-model`: "A field is defined by a row in the DB" mentions the `hide_label` flag.
- `theme-rendering`: "Rendering a field" prints the label unless `hide_label` is on.

## Impact

- Code: a migration adding `content_fields.hide_label`; `ContentField` (`#[Fillable]`, cast, docblock); `StoreContentFieldRequest::sharedRules()` and the update request; `ContentFieldController` (`row()` and the edit props); `ContentFieldForm.tsx` (checkbox, form data, types); the 16 field templates of `themes/default/views/fields/`; `ContentFieldFactory`.
- Tests: field create/edit feature tests (`ContentFieldCreateTest`, `ContentFieldEditTest`), a public rendering test (`FieldRenderer`/theme field templates), e2e `e2e/admin-content-fields.spec.js`. The change adds a migration, so the full PHP suite runs once.
- Docs: `docs/admin-content-fields.md` (the checkbox), `docs/content-model.md` (the column), `docs/theme-templates.md` (the variable `$field->hide_label` and the default templates), `docs/e2e-testing.md` only if a spec row changes.
- Dependents: `import-legacy-site` maps the legacy `hidden_label` to this setting; it should be applied after this change.

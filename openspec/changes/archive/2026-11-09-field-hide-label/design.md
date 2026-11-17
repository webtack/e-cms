# Design

## Context

A field of a content type is a `content_fields` row with general properties in columns (`is_multiple`, `max_items`, `is_required`, `is_translatable`, `description`) and type-specific options in the JSON `settings` (file profiles, the vocabulary of a `term`, the kind of a `type_ref`). `settings` is validated per type through `ValidatesSettings` and the request rules `settingsRules()`, which are prohibited for most types today. The default theme prints the label in `fields/field.blade.php` and in each of the 15 `fields/field-{ftype}.blade.php` as `<div class="field__label">{{ $field->name }}</div>`; `FieldRenderer::render()` already passes the whole `ContentField` to the template as `$field`. See proposal.md for the motivation.

The legacy `hidden` flag (hide a field on the page) is handled by the "Public display" tab of the type (`page_fields`) and by `import-legacy-site`, not here.

## Goals / Non-Goals

**Goals:**
- One boolean per field, editable in the field form, honored by the default theme.
- A value the legacy importer can set when it creates a field.

**Non-Goals:**
- Hiding the label in the admin node form, in the field table, or in list cards of the admin.
- A per-field "hidden field" flag: visibility of a field stays a property of the type's "Public display".
- A central switch in `FieldRenderer` that drops the label from any theme's HTML. Themes own their markup.

## Decisions

**Column `hide_label`, not `settings.hide_label`.** The flag applies to every field type, like `is_multiple` or `description`, and `settings` is for the options of one type. A column also avoids loosening the per-type `prohibited` rules of `settings`. Alternative: a key in `settings`; rejected because every type's settings validation would have to learn it, and `ContentFieldController::attributes()` rebuilds `settings` per type and would drop it.

**Boolean `NOT NULL DEFAULT false`, name `hide_label`.** The positive-sounding default (label shown) needs no data migration: existing rows become `false`. The name follows the owner's wording and the legacy `hidden_label`; the form label is "Hide label".

**The theme decides.** `$field->hide_label` is already available to templates through `$field`. The 16 default templates wrap the label in `@unless ($field->hide_label)`. The contract for other themes is documented in `docs/theme-templates.md`. Alternative: pass a ready `$showLabel` variable; rejected as a second name for the same fact, and `$field` is already the documented place for the settings.

**Allowed for system fields.** Like `description` it is not part of `ContentField::SYSTEM_LOCKED`; hiding the caption of `title` is harmless because the page prints the title as the heading.

**No type-level override.** The flag lives on the field only. A theme template `{t}/field--{key}` can already override a single field's markup.

## Risks / Trade-offs

- [A custom theme prints the label without checking the flag] -> The flag is documented in `docs/theme-templates.md`, and the default templates are the reference; nothing breaks, the label simply stays visible.
- [The flag looks like it hides the field] -> The hint under the checkbox says it hides only the label on the public page, and the admin node form keeps the label.
- [`FieldRenderer` caches nothing about the label, so a stale `ContentField` could keep the old value] -> The model is read per request (the fields are loaded once per request by `DisplayFields`), so a saved change is visible immediately.

## Migration Plan

One additive migration (`content_fields.hide_label`, default `false`, after `is_translatable`); `down()` drops the column. No data backfill. Deploy the migration with the code; rollback is the `down()`. `import-legacy-site` sets the value after this change is applied.

## Documentation

`docs/admin-content-fields.md` (the form), `docs/content-model.md` (the column in the field description), `docs/theme-templates.md` (the variable and the default templates).

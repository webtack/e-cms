# Proposal

## Why

The value type of a field can currently be changed while the field has no stored values, and after the first record it is locked. Such conditional immutability depends on the state of the data: the same request succeeds today and is rejected tomorrow, and the decision "is the type changed or not" has to be checked with a query to the value table. The project owner decided to make the type of a field immutable after creation always, like the `key` of a field and the `key`/`kind` of a content type. Together with this, a field can be deleted only without stored values: at the moment only the admin checks this prohibition, while the model cascades the deletion of values, so code, tinker or an import can silently destroy data.

## What Changes

- The value type of a field **does not change after creation**, regardless of whether values exist. A change of the field type is rejected with an error next to the field `type`.
- The edit form of a field always shows the value type as locked (together with a hint), not only for system fields and fields with values.
- Submitting the form with the same type passes without an error; with a different one, it is rejected by the server regardless of the state of the form.
- Deleting a field that has stored values is rejected **in the model** `ContentField` for any path (admin, tinker, import, code): the field and the values remain, and a `ValidationException` is thrown. A field without values is deleted as before. The DB cascade when a **content type** is deleted (its fields, records and values) does not change.
- The helper check `hasStoredValues` in `ContentField` is no longer related to the type: only the deletion check uses it.
- **BREAKING** (behaviour): a field without values can no longer be switched to another type; for another type the field is created again (deleting an empty field is not restricted).

Not included: a change of the admin deletion of a field (the dialog and the controller check remain), type conversion, a hint about the immutability of the type in the creation form, data migration (there is no data affected by it: values are stored as they are).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-model`: the requirement "Immutability of the value type of a field" — the type is always immutable, not only for a field with values; the requirement "Cascade deletion of configuration" — a field with values is not deleted, the cascade on deleting a field concerns only an empty field, the cascade of the type remains.
- `admin-content-fields`: the edit form of a field (the type is always locked) and saving the changes of a field (a change of type is always rejected; the same type in the request is not an error).

## Impact

- Code (PHP): `app/Models/ContentField.php` (`assertValid`, the `deleting` event, `hasStoredValues`), comments in `UpdateContentFieldRequest` and `ContentFieldController`.
- Code (frontend): `Components/Admin/ContentFields/ContentFieldForm.tsx` (`lockType`), `lang/en.json` and `lang/uk.json` (the hint about the immutability of the type).
- Tests: `tests/Feature/Content/ContentFieldTest.php`, `tests/Feature/Content/CascadeTest.php`, `tests/Feature/Content/FileFieldTypesTest.php`, `tests/Feature/Admin/ContentFieldEditTest.php`; e2e `admin-content-fields`.
- Documentation to update: `docs/content-model.md` (fields, immutability of the type, cascades of deletion), `docs/content-values.md` (the mention of the type of a field with values in the section on `image`/`file`), `docs/admin-content-fields.md` (locking in the `edit` mode, protection through the model, tests and e2e), `docs/e2e-testing.md` (the description of the scenario of the fields); `docs/README.md` unchanged.
- The change is independent of `add-richtext-field-type`; after it, the rule "text ↔ richtext" is no longer a special case.

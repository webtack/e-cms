# Design

## Context

The rule lives in `ContentField::assertValid` (the `saving` event): if `type` has changed and the value table of the original type has rows of this field (`hasStoredValues`), a `ValidationException` with the key `type` is thrown. The other immutable attributes (`key`, `content_type`) are checked in the same method through `isDirty`. The edit form (`ContentFieldForm.tsx`) blocks the type select with `lockType = field.is_system || field.has_values`. `UpdateContentFieldRequest` reuses `StoreContentFieldRequest::sharedRules()`, so `type` always arrives in the request. The flag `has_values` remains needed for the deletion dialog. The motivation and the boundaries are in `proposal.md`, the requirements are in `specs/`.

## Goals / Non-Goals

**Goals:**
- One unconditional layer of the rule in the model, which applies to any write path (form, tinker, import).
- The form does not offer an action that the server would reject anyway.

**Non-Goals:**
- A hint about the immutability of the type in the creation form.
- A change of the deletion dialog in the admin and of `has_values` (they already match the requirement).
- Transferring values between types (conversion).

## Decisions

**1. The rule of the type in the model, without a DB query.** The condition `isDirty('type')` on an existing field throws `ValidationException(['type' => ...])` without accessing the value table. `hasStoredValues` for the type is no longer needed, but stays for the deletion check (see decision 6). This is simpler and faster, and the result does not depend on the state of the data. Alternative — keep the condition by the presence of values: this is the current behaviour, which the owner has abandoned.

**2. A change of type is rejected with an error, not ignored.** For `key` and `content_type` the request contains no values (they are not in `validated()`), while `type` is always in the form, so "ignoring" would mean silently discarding what was entered. The server returns an error next to the field `type` (as for a field with values now); the form that sends the current type passes. Alternative — remove `type` from the update rules and ignore it: less code, but an explicit change of type by a direct request would pass unnoticed.

**3. One message.** The error text: `The type of a field cannot be changed.` (en) / «Тип поля змінити не можна.» (uk). This replaces the two existing translation rows (`…of a field that has values…` and `…of a system field…`) with one, because the reason is now one. The old keys are removed from `lang/*.json`.

**4. The form: `lockType = !!field`.** In the `edit` mode the type select is always `disabled` with a hint. The checkboxes "multiple" and "required" for a system field are blocked as before. `SYSTEM_LOCKED` in the model stays unchanged (`type` in it becomes a duplicated safeguard for a system field, no harm).

**5. The data is not migrated.** The rules do not touch the stored values, so no migration is needed. A field created with a wrong type must be deleted and created again, and for a field with values the values must first be cleared.

**6. The model forbids deleting a field with values.** In `booted()`, before the `deleting` event (where the check of the system field already is), a check is added: if the value table of the field's type has rows with the `content_type` and `field_key` of this field, `ValidationException(['key' => 'A field that has values cannot be deleted.'])` is thrown. The table is taken from `FieldTypeRegistry` by the `type` of the field. The controller `ContentFieldController::destroy` keeps its own check (`valuedKeys`, `back()->with('error', ...)`), so that the user gets a normal message rather than a validation error; the model is the last barrier for code, tinker and imports. The DB cascade when a **type** is deleted does not go through the model event and does not change: the type is deleted together with all its content by a separate requirement ("Каскадне видалення конфігурації"). Alternative — keep the check only in the controller: then `ContentField::delete()` in code would silently destroy values, which contradicts the spirit of the immutability that this change introduces.

**7. Tests of the cascades.** `CascadeTest` now checks: a field without values is deleted; a field with values is rejected, the values are intact; a type with fields that have values is deleted by cascade.

## Risks / Trade-offs

- [A mistake with the type, values already exist] → first delete the values (a field with values is not deleted either in the admin or in the model), then delete and create the field again; conversion of types is a separate change, if needed.
- [Code that relied on the cascade deletion of values at `ContentField::delete()`] → in the project only `CascadeTest`; the other paths (the admin) already forbade the deletion. A mass deletion of values before deleting the field is done by the call itself.
- [A mass `ContentField::query()->delete()` bypasses the model events] → as for the other checks of the model; only the deletion through a model instance is protected (documented).
- [Tests and e2e that rely on changing the type of an empty field] → updated in this change: `ContentFieldTest`, `ContentFieldEditTest`, `FileFieldTypesTest` (the scenario "without values") and the e2e `admin-content-fields` (a check of the locked select on a field without values).
- [A shared form component] → it touches `ContentFieldForm`, so per `docs/e2e-testing.md` `admin-content-fields` and the dependent specs of types and pages are run (`admin-content-types`, `admin-page-types`, `admin-block-types`, `admin-content-nodes`).
- [A type of data created before the change, in an "intermediate" state] → the behaviour for existing fields is the same: the type cannot be changed.

## Migration Plan

There are no migrations or dependencies. Rollback — return to the previous code. There is no published data that the change makes invalid.

## Documentation

`docs/content-model.md` (the row about the attributes that are immutable after creation: add the `type` of a field), `docs/content-values.md` (the section `image`/`file`: remove the warning about the shared table as a reason), `docs/admin-content-fields.md` (the locking in the `edit` mode, "Protection through the model", the description of the tests and e2e), `docs/e2e-testing.md` (the scenario of the fields).

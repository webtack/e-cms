## 1. Data model

- [x] 1.1 Add the migration `add_hide_label_to_content_fields_table` (`boolean hide_label` default `false`, after `is_translatable`, `down()` drops it); add `hide_label` to `ContentField` (`#[Fillable]`, boolean cast, `@property` docblock) and to `ContentFieldFactory`. Verify: `docker compose exec app php artisan migrate`, then `docker compose exec app php artisan test --compact --filter=ContentFieldTest`, and a new case in `tests/Feature/Content/ContentFieldTest.php`: that a field defaults to `hide_label = false` and keeps `true` after a save; the full `docker compose exec app php artisan test --compact` runs once here because of the migration. JSDoc/PHPDoc in English for the changed public code is part of the task.
- [x] 1.2 Update `docs/content-model.md`: the `content_fields` row lists the `hide_label` column and says it hides only the public label. Verify: the link and table render, no other docs mention a stale column list (`grep -rn "is_translatable" docs`).

## 2. Admin form

- [x] 2.1 Accept `hide_label` in `StoreContentFieldRequest::sharedRules()` (`['boolean']`, shared with the update request) and return it from `ContentFieldController::row()` so the edit form and the table props carry it. Verify: `docker compose exec app php artisan test --compact --filter=ContentField` with new cases in `ContentFieldCreateTest` and `ContentFieldEditTest` (default off, saved on, turned off again, allowed for the system field `title`, a non-boolean value is rejected).
- [x] 2.2 Add the checkbox "Hide label" with the hint "Only affects the public page: the field name is not printed above its value." to `ContentFieldForm.tsx` (form data, the `flag()` helper or an equivalent control, TypeScript types) and add the strings to the `lang` files used by the admin i18n. Verify: `npm run build` on the host, then `npm run test:e2e -- e2e/admin-content-fields.spec.js -g "label"` with a new test (create with the box on, edit shows it on, turn it off); also `e2e/admin-content-nodes.spec.js` is skipped because the node form does not change.
- [x] 2.3 Update `docs/admin-content-fields.md`: the checkbox, the hint, system fields, the node form keeps the label. Verify: the section matches `openspec/changes/field-hide-label/specs/admin-content-fields/spec.md`.

## 3. Public templates

- [x] 3.1 Wrap the label of `themes/default/views/fields/field.blade.php` and the 15 `field-{ftype}.blade.php` in `@unless ($field->hide_label)`; keep the hook classes and the markup of the label unchanged. Verify: `docker compose exec app php artisan test --compact --filter=DefaultFieldTemplatesTest` with a new case per template family that a field with `hide_label` renders its value without `field__label`, and `--filter=FieldRendererTest` unchanged; `docker compose exec app php artisan theme:check` still passes.
- [x] 3.2 Update `docs/theme-templates.md`: `$field->hide_label` in the field template variables and the rule for the default templates. Verify: the variable list matches the header comment of `fields/field.blade.php` (update that comment too); `docs/README.md` links stay valid.

## 4. Wrap-up

- [x] 4.1 Run Pint for the changed PHP (`docker compose exec app vendor/bin/pint --dirty --format agent`) and re-run the focused tests of 2.1 and 3.1. Verify: Pint reports no changes and the tests pass.

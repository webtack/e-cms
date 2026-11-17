## Context

Types of the three kinds share the routes in `routes/web.php` (one loop per section, the kind comes from the route default) and the controller `ContentTypeController`. A type creates its system fields in `ContentType::created` (inside the save transaction); user fields are `ContentField` rows with `content_type` = type key, validated by the model on `saving` (key format, reserved keys, field type settings). The type form rules live in `StoreContentTypeRequest::sharedRules()`. See `docs/admin-content-types.md`, `docs/admin-content-fields.md` and `docs/content-model.md`.

## Goals / Non-Goals

**Goals:**
- Copy a type with its field definitions through the existing models, so all validation and invariants still apply.
- Reuse the create-form rules for `name`, `key`, `slug`.

**Non-Goals:**
- Copying nodes, values, SEO values, description, role levels, favorites.
- Changing the kind during cloning.
- Cloning several types at once.

## Decisions

- **Route and controller.** `POST /{section}/{contentType}/clone` (`content-types.clone`, etc.) added to `$typeRoutes`, so it gets `can:types` and the kind-bound `{contentType}` for free (404 for another kind). Action `ContentTypeController::clone`. Alternative, a GET page with a form, was rejected: three inputs fit a dialog next to the row menu.
- **Request.** `CloneContentTypeRequest` takes `name`, `key`, `slug` and `parent_type` rules from `StoreContentTypeRequest` (`key` rule and `sharedRules(kind: …)` reduced to `name`, `slug`, `parent_type`; the rest of the shared rules, i.e. `description` and `settings`, are not accepted). The slug hierarchy rule checks the chosen `parent_type`. Alternative, a copy of the rules, was rejected as drift-prone.
- **Service `App\Content\ContentTypeCloner`.** In one `DB::transaction`: `ContentType::create` with the source `kind`, the entered `name`/`key`/`slug`/`parent_type` and no `settings` (the model creates system fields), then for each source field ordered by weight: if the field is system, update the matching new system field's `name`, `description`, `weight`, `hide_label`, `settings`; otherwise `ContentField::create` with the copied attributes. Finally the source type `settings` are assigned to the new type and saved. This is done last because the model validates `settings.list` and `settings.public` against the type's existing fields, so setting them on create would fail. Going through the model keeps validation (settings, reserved keys); `is_system` is not fillable and is never copied for user fields. Alternative, raw `insert` of rows, was rejected: it bypasses model validation.
- **Type settings are copied as is.** `settings.list` and `settings.public` reference fields (and service keys) by key, and the cloned fields keep their keys, so no rewriting is needed. Type files (SEO values and their file relations) are not copied.
- **Field settings are copied as is.** `settings` of a field refer to its own configuration (limits, allowed types, reference targets by key), not to the type, so no rewriting is needed. A field of a file kind does not hold files in `settings`, only rules, so no file relations are created.
- **Parent options for the dialog.** The list page has no `parentOptions` today, so `index` passes them (types of the section's kind, via `parentOptions()`), only for users who can manage; the row already carries `parent_type`.
- **Frontend.** A `CloneTypeDialog` component next to `ContentTypeRowActions.tsx` (rendered next to the menu, like the delete dialog) with `useForm`; the slug input only when `section.kind === 'content'`. On success Inertia follows the redirect to the fields page.
- **Redirect.** `redirect()->route("{$section}.fields.index", $new)` with `success`. The section name comes from the route name prefix as in the other actions of the controller.

## Risks / Trade-offs

- [A source field is invalid by today's rules (old data)] → the clone fails and rolls back with an error toast; the source is untouched.
- [Large types make the request slow] → fields are tens at most; one transaction is acceptable.
- [Parent with a slug and an empty slug in the dialog] → same behaviour as the create form, no special case.
- [Settings of the source are stale (reference a deleted field)] → saving them fails model validation and the clone rolls back with an error; the source is untouched.

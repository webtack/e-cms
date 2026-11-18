# Design

## Context

Reading of `settings.list` is already implemented: `ContentNodeController::listSettings()` clears stale references, `tableColumns()` describes the columns for `TableRequest`, `ContentNodeQuery::applySort()` can sort by `published_at` and by a field, and `ContentType::assertValidListSettings()` checks the keys on saving. What is missing is writing from the admin (`UpdateContentTypeRequest` does not accept `settings`, `ContentTypeController::update()` does `$contentType->update($request->validated())`) and the service columns in `visible`: `Pages/Admin/ContentNodes/Index.tsx` hard-codes `slug`, `status`, `published_at` at the end. See `proposal.md` for the motivation.

Described in: [docs/admin-content-nodes.md](../../../docs/admin-content-nodes.md), [docs/admin-content-types.md](../../../docs/admin-content-types.md), [docs/content-querying.md](../../../docs/content-querying.md), [docs/content-model.md](../../../docs/content-model.md).

## Goals / Non-Goals

**Goals:**
- Allow setting the position of `title` in the list and rearranging the columns by drag-and-drop.
- Show multiple values as a column, images as a thumbnail-link, files as a name-link.
- Edit `settings.list` (the columns with their order, the search, the default sorting) on the «Налаштування» page of the type for all three kinds.
- Make the service columns ordinary elements of `visible`.
- Make all service attributes of a record available as columns and sort fields (except slug), the initial sorting choice in the form — «Дата створення».

**Non-Goals:**
- Several sortings, list filters, a setting of the number of records per page.
- Saving the personal choice of columns on the server (it stays in `localStorage`).
- Settings of the lists of types, fields, menus or taxonomy.
- The column `user_id` (the author) — it is not among the service columns of this change.

## Decisions

**1. Service columns live in `visible`.** The service keys are `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`. So that they do not conflict with the fields of the type, `ContentField::RESERVED_KEYS` is extended with the keys `is_active`, `created_at`, `updated_at`, `unpublished_at` (the rule from `content-model.md` requires it: a new service attribute that becomes a column adds its key to `RESERVED_KEYS`). A new field with such a key cannot be created (`StoreContentFieldRequest`, `ContentField::assertValid()`); existing fields with such keys remain valid, but are not offered as a column and do not duplicate the service one (the value of the row for this key is the service one). Alternative — a separate key `list.standard`: simpler to validate, but the order of the service columns does not mix with the order of the fields. One array is chosen, because the order is the main requirement.

**2. The semantics of `visible`.** `title` is an ordinary key of `visible` for the position: the column is always present (it is not hidden), stands at the position of its key, and without a key — first. Therefore the existing settings without `title` remain valid without a migration; the form keeps `title` in `visible` if it is not first or `visible` is not empty (an empty choice that is left only with `title` first is not saved). A missing or empty `visible` — the old look: `title`, `slug`, `status`, `published_at`. A non-empty `visible` completely defines the composition: a service column is present only if its key is in the array. The values in the row: a multiple field — a list (empty → `null`); `image` — `{url, name}` (multiple — a list of such); `file` — `{url, name, ext}`; `url` is built by `FileStorage::url()` from the record `File`, `name` — `original_name`; a file without a record in `media` — the string as is (without a link). The types `image` and `file` are added to the columns (`sortable: false`, as now). The controller gives the client a single ordered `columns` with the service columns (`kind: 'service'` or by key) and the values of the row for these keys: the dates `Y-m-d H:i` (UTC; an empty `unpublished_at` — `null`), `is_active` — a boolean (the client shows «Так»/«Ні»), `status` — as now. `Index.tsx` no longer appends the service columns itself; the rendering (the status badge, the boolean, the date) stays on the client by the key.

**3. Migration of the existing data.** The new rule would change the look of the types that already have a non-empty `visible` (they would lose the service columns). Therefore a one-time data migration appends `slug`, `status`, `published_at` to the end of a non-empty `settings.list.visible`. Types without `visible` are not touched. Alternative — do not migrate and accept the change of the look; rejected, because it would be a silent loss of columns.

**4. Sorting.** `sort` is stored as `{field, direction}`; `field` — a sortable field of the type or a service key `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at` (`slug` is not sorted). «Дата створення» ascending is the current order (by `id`/`created_at`), so the form does not store it (there is no `sort` key), which agrees with «without settings — the order of creation». In `ContentNodeQuery::applySort()` the service keys branch as follows: `created_at`, `updated_at`, `unpublished_at` — `ORDER BY content_nodes.<col> {dir}` (for `unpublished_at` the empty ones go to the end in both directions: `IS NULL` as the first key), then `content_nodes.id` in the same direction, **without** a secondary order by `title` (by the requirement — not `title`); `published_at`, `status`, `is_active` — the main key, then `title` ascending and `id`, as now for `published_at`. `is_active`: `false` before `true` ascending. `status` — derived, so it is sorted by an SQL expression `CASE` with the same logic as `ContentNode::status()` (disabled; scheduled if `published_at` is in the future; expired if `unpublished_at` ≤ now; otherwise published) in the order «Опубліковано» (0), «Заплановано» (1), «Завершено» (2), «Вимкнено» (3). The logic of the state stays in one place: the `CASE` expression is used by the sorting and, where possible, `status()`; a test compares them on the same input data. The secondary `title` for an explicit sorting from the address by a field remains unchanged. The columns of the table are `sortable` accordingly: `status`, `is_active` and the dates — yes, `slug` — no.

**5. The form.** A new section (the service columns: «Slug», «Стан», «Активний», «Дата створення», «Дата оновлення», «Дата публікації», «Дата зняття з публікації») in `ContentTypeForm` (a separate component in `Components/Admin/ContentTypes/`), which receives from `ContentTypeController::edit()` the list of available columns (`{key, name, kind: field|service, sortable, searchable}`) and the current settings. The columns are a list with checkboxes and drag handles (dragging through `@dnd-kit/sortable`, as in the multiple fields; the order is changed also from the keyboard: space — lift, arrows — move; there are no «up/down» buttons; the checkbox of `title` is marked and disabled). The search — checkboxes by the text fields. The sorting — a `Select` of the field + a `Select` of the direction. The data goes in the same `PUT` of the form as `settings.list`. The creation of a type (`Create.tsx`) has no section: there are no fields yet.

**6. Writing `settings`.** `ContentTypeController::update()` merges only the key `list` into the existing `settings` (the other keys remain); empty `visible`/`search` and a missing `sort` are not stored (the key `list` is removed if it is empty). Validation: the rules of the form (`array`, the allowed forms of keys) + `ContentType::assertValidListSettings()` as the final check against the fields of the type; it learns all service keys in `visible` and the service keys except `slug` in `sort.field`. The errors are returned under the key `settings`.

**7. Cleaning on reading.** `ContentNodeController::listSettings()` also accepts all service keys in `visible` and the service keys except `slug` in `sort.field`, and clears the rest as before. The view in the form is built from the same rules, so that the form and the list do not diverge.

**8. Rendering of the values on the client.** The client shows a multiple value as a vertical list. A thumbnail is an `<img>` of the file itself, reduced by CSS (there are no separate thumbnails in the storage), inside `<a target="_blank" rel="noopener noreferrer">`. A file is an `<a target="_blank" rel="noopener noreferrer">` with the name; `download` is added if `ext` is not `pdf` (the files lie on the same origin in `/storage`, so the attribute works).

## Risks / Trade-offs

- [A non-empty `visible` without service keys now hides slug/status/date] → the data migration (decision 3) and an explicit scenario in the spec.
- [Two sources of visibility: the settings of the type and `localStorage`] → the personal hiding works only on top of the composition of the type; `docs/admin-data-table.md` already describes this («збережено лише список прихованих»), a mention of the default from the type is added.
- [A column added by the admin later appears visible for a user with a saved `localStorage`] → a known limit of `DataTable`, it does not change.
- [The thumbnail is the full file, reduced by CSS: heavy images in a long list] → `loading="lazy"` and a small fixed size; separate thumbnails are beyond the limits of this change.
- [The `CASE` expression for `status` duplicates the rule of `ContentNode::status()`] → one source of truth and a test on the match of the order with `status()` for all four states.

## Migration Plan

1. The data migration appends `slug`, `status`, `published_at` (only these three, as it was before this change) to the non-empty `settings.list.visible` (idempotent: the keys that are already there are not duplicated); the new service keys become available through the form. `down()` restores nothing (the rollback is safe: the extra keys in `visible` remain valid).
2. Deployment without a switch-off: the old settings without `visible` work as before.

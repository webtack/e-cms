# Proposal

## Why

The list of records already reads `content_types.settings.list` (`visible`, `search`, `sort`), but the admin has no place where it can be set: the form of a type edits only the name, the slug, the parent type and the description, and `UpdateContentTypeRequest` does not accept `settings`. The columns, the search and the order are configured only through tinker. The service columns (`slug`, the state, the publication date) are appended at the end by the client, and they cannot be hidden or rearranged.

## What Changes

- A section «Список записів» on the «Налаштування» page of the type (`/admin/{section}/{key}/edit`) for all three kinds (`content`, `page`, `block`): the choice of columns and their order (by drag-and-drop), the search fields, the default sorting (field + direction).
- All service attributes of a record become ordinary keys in `settings.list.visible` and take part in the order of the columns: `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`. `title` is also a key in `visible`: its position can be changed, but the column is always present and cannot be hidden; without the key `title` in `visible` it stands first.
- The default sorting in the form by the initial choice — «Дата створення» (`created_at`), not `title`. The service columns `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at` are sortable (`slug` — not); they are also valid as the default sorting field together with the sortable fields of the type.
- `UpdateContentTypeRequest` (and the creation of a type) accept `settings.list` with a check of the keys and the direction; saving updates only the key `list` and does not erase the other keys of `settings`.
- `ContentNodeController` and the client table build the columns in the order of `visible`, including the service ones; empty settings keep today's look (`title`, then slug, the state, the publication date).
- The values of multiple fields in the list are shown as a column (a list of values, not a string with commas). The field `image` is shown as a thumbnail-link to the file (in a new tab), the field `file` — as the original file name, a link in a new tab: `pdf` opens in the browser, the other types offer a download (`download`).
- `ContentNodeQuery` understands the service sort fields: `created_at`, `updated_at`, `unpublished_at` (empty ones at the end; then `id`, without `title`), and also `status` (the derived state) and `is_active`.
- The keys `is_active`, `created_at`, `updated_at`, `unpublished_at` become reserved keys of fields (`ContentField::RESERVED_KEYS`): a new field with such a key cannot be created; existing ones remain valid and are not offered as columns.
- The personal hiding of columns in `localStorage` (the button «Колонки») remains and works on top of the values of the type by default.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: the columns of the table of records are built from `settings.list.visible` together with the service columns (in particular `is_active`, `created_at`, `updated_at`, `unpublished_at`) and their order; the service columns are sortable (except slug), including by default.
- `admin-content-types`: the form of editing a type gets a section of the settings of the list of records, saving accepts and checks `settings.list`.
- `content-querying`: sorting by service attributes (`created_at`, `updated_at`, `unpublished_at`, `status`, `is_active`) and the valid values of `settings.list`.
- `content-model`: the list of reserved keys of fields is extended with `is_active`, `created_at`, `updated_at`, `unpublished_at`.
- `admin-content-fields`: the form of creating a field rejects these keys as reserved.

## Impact

- Code: `app/Models/ContentType.php` (`assertValidListSettings`), `app/Models/ContentField.php` (`RESERVED_KEYS`), `app/Http/Requests/Admin/StoreContentFieldRequest.php`, `app/Http/Requests/Admin/StoreContentTypeRequest.php` and `UpdateContentTypeRequest.php`, `app/Http/Controllers/Admin/ContentTypeController.php` (passing the fields of the type to the form, merging `settings`), `ContentNodeController.php` (`listSettings`, `tableColumns`, `row`, the format of the values of file and multiple fields), `app/Content/ContentNodeQuery.php` (`applySort`), `resources/js/Components/Admin/ContentTypes/ContentTypeForm.tsx` + the new component of the section, `resources/js/Pages/Admin/ContentTypes/Edit.tsx`, `resources/js/Pages/Admin/ContentNodes/Index.tsx`, translations `en`/`uk`.
- Tests: Feature (`ContentTypeEditTest`, `ContentNodeIndexTest`, if needed `PageTypeTest`/`BlockTypeTest`), tests of `ContentNodeQuery`, e2e `admin-content-types` and `admin-content-nodes`.
- Docs (to update): `docs/admin-content-types.md`, `docs/admin-content-nodes.md`, `docs/content-querying.md`, `docs/content-model.md`, `docs/admin-content-fields.md`, `docs/e2e-testing.md` (if the tables «область → спек» change).
- There are no migrations and no new dependencies: `settings` is already a JSON column of the type.

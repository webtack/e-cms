## Why

The form of a record is one narrow column, where the service data (slug) is mixed with the content, and a record has neither a status, nor a publication date, nor an author. A place is needed for service information that can be extended gradually, and for managing the visibility of a record on the site: a deferred publication and a simple disabling.

## What Changes

- The form of creating/editing a record is split into two columns of about 70/30 on `lg`: on the left the fields of the type, on the right a sticky column «Деталі».
- On screens `< lg` the service column becomes a slide-out panel (Sheet), opened by an icon button (without text and without the status badge; the accessible name «Деталі») in the sticky bottom action bar next to «Скасувати» and «Зберегти»; the button marks the errors of the service fields.
- The forms of the content type and of the field of a type are wrapped in a container `rounded-md border`; between the content of the form and the sticky action bar there is a small indent, so that the form does not stick to the bar.
- All forms of the admin (record, field, content type) get a shared sticky bottom action bar with «Скасувати» and «Зберегти» on all screen sizes (the component `FormActionBar`); the buttons no longer stand in the flow of the form.
- The slug moves into the service column (no longer in a separate frame of the main column), and the following are added: the switch «Показувати на сайті» (`is_active`), the editable publication date (`published_at`, by default — now), «Оновлено» (`updated_at`, read-only, only when editing) and «Автор» (read-only). `created_at` is not shown in the form.
- `content_nodes` gets the columns `published_at` (NOT NULL), `is_active` (NOT NULL, by default `true`) and `user_id` (NOT NULL, FK to `users`). Existing records get `published_at = created_at` and `user_id` of the administrator from `ADMIN_EMAIL`.
- The author is set by the server from the current user at creation and is not accepted from the request and not changed.
- Visibility for the front-end: a record is visible if `is_active = true` and `published_at <= now()`. The selection `ContentNodeQuery` gets the option «only visible»; the admin sees all records.
- The keys `uuid`, `slug`, `status`, `published_at` are reserved: a new field with such a key cannot be created (existing fields are not touched).
- The table of records gets a status column (the badge «Опубліковано» / «Заплановано» / «Вимкнено») and a publication date column with sorting.
- **BREAKING (internal)**: `ContentNodeCreator::create()` requires an author (a parameter or the current user), `ContentNodeFactory` gets new default values; the existing tests that create records without a user are updated.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: the layout of the form (two columns, the mobile panel), the service fields, saving of the service attributes, the updated requirement about the containers (the slug is no longer in a separate frame), the columns of the table of records (the status, the publication date).
- `content-model`: the service attributes of a record (`published_at`, `is_active`, `user_id`) and the rules of their values; the reserved keys of fields (`uuid`, `slug`, `status`, `published_at`) that cannot be used when creating a field.
- `admin-content-fields`: the form of creating a field rejects a reserved key.
- `content-querying`: the visibility of records for the public selection.

## Impact

- Code: the migration of `content_nodes`; `App\Models\ContentNode` (+ the relation `author`), `ContentNodeFactory`, `ContentNodeCreator`, `ContentNodeQuery`/`NodeQueryParams`, `ContentNodeController` (+ `forForm`, `row`, `tableColumns`), `StoreContentNodeRequest`/`UpdateContentNodeRequest`, `StoreContentFieldRequest`, `ContentField` (reserved keys); frontend: `ContentNodeForm.tsx`, the new components of the service panel, the pages `Create`/`Edit`/`Index`, `types.ts`, the hook of the media query `lg`.
- Tests: PHPUnit (`tests/Feature/Admin/ContentNode*`, `tests/Feature/Content/*`), Playwright `e2e/admin-content-nodes.spec.js`.
- Documentation: update `docs/admin-content-nodes.md`, `docs/admin-content-fields.md`, `docs/content-model.md`, `docs/content-querying.md`, `docs/e2e-testing.md`; the index `docs/README.md` remains valid (there are no new files).
- Translations: `lang/uk.json`, `lang/en.json` (the same keys, `TranslationParityTest`).

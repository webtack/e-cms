# Content fields in the admin panel

The `GET /admin/content-types/{key}/fields` page (`admin.content-types.fields.index`) shows the fields of a content type of kind `content`; for kind `page` the same pages are at `/admin/page-types/{key}/fields…` (names `admin.page-types.fields.*`, see [admin-page-types.md](admin-page-types.md)), for kind `block` — at `/admin/block-types/{key}/fields…` (names `admin.block-types.fields.*`, see [admin-block-types.md](admin-block-types.md)); they are served by the same controller and React pages, and the kind is taken from the route. Access — `auth` + `verified` and the role right `types`; a guest is redirected to `/login`. See [access control by roles](access-control.md). The field model is described in [content-model.md](content-model.md), the value tables in [content-values.md](content-values.md); the type page from which one gets here is [admin-content-types.md](admin-content-types.md).

## Routes

All routes are nested under the type: `/admin/content-types/{contentType}/fields…`, names `admin.content-types.fields.*`.

| Method and path | Name | Action |
| --- | --- | --- |
| `GET …/fields` | `index` | list of fields |
| `GET …/fields/create` | `create` | create form |
| `POST …/fields` | `store` | create |
| `PUT …/fields/order` | `reorder` | save the order |
| `GET …/fields/{field}/edit` | `edit` | edit form |
| `PUT …/fields/{field}` | `update` | update |
| `DELETE …/fields/{field}` | `destroy` | delete |
| `PUT …/fields/{field}/translations/{lang}` | `fields.translations.save` | save the label of one language ([translation mode](#translation-of-the-label)) |
| `DELETE …/fields/{field}/translations/{lang}` | `fields.translations.clear` | remove the label of one language |

`PUT …/fields/order` is declared **before** the routes with `{field}`, so that `order` is not bound as a field key.

`{contentType}` is bound by `key` and only in the kind's section ([admin-content-types.md](admin-content-types.md#editing)): the key of a missing type, as well as types of another kind, give 404 on all routes of this page. `{field}` is bound by an explicit `Route::bind('field', …)` in `routes/service.php`: it looks up the field by the **pair** (the type key from the already resolved parameter, `key`), so the key of a missing field or of a field of another type also gives 404. A field is addressed by its key, not by a numeric `id`, like all other entities.

The logic is in `Webtack\Ecms\Http\Controllers\Admin\ContentFieldController`; the requests are `StoreContentFieldRequest`, `UpdateContentFieldRequest`, `ReorderContentFieldsRequest` (`src/Http/Requests/Admin`).

## `index` response

The Inertia page `Admin/ContentFields/Index` receives:

- `type` — `{key, name}` of the type;
- `fields` — **all fields of the type, except the panel fields** (`meta_*`, `section_*`; [below](#hidden-panel-fields)), as an array ordered by `weight`, and for equal weight by `id` (the order of the relation `ContentType::fields()`). A row: `key`, `name`, `type`, `is_multiple`, `max_items`, `is_required`, `is_translatable`, `is_system`, `weight`, `has_values`.

`has_values` says whether the field has saved values. It is computed by `valuedKeys()` — **one query per used value table** (not per field): the fields of the type are grouped by the value table of their type from the [registry](content-values.md), and for each table the different `field_key`s of this content type are taken. The flag is needed by the deletion dialog and by the edit form.

## List page

`Pages/Admin/ContentFields/Index.tsx` shows the [data table](admin-data-table.md) in **client mode** (`mode="client"`, `tableId="content-fields"`): all fields are passed to the page, and search, sorting, pagination, and page size are performed by the browser without requests to the server. The `filterable` prop is not passed, so there are **no filters and no filter button**. Without explicit sorting, the rows go in the order of the `fields` array (by weight). The page has no title of its own: the heading is the type name that the [type section menu](admin-content-types.md#type-section-menu) (`ContentTypeNav`, with "Fields" active) shows above the tabs, with a link back to the list of types; the same menu is on the create and edit forms of a field, which also have no title of their own; above the table there is a "Create" button (`/admin/content-types/{key}/fields/create`).

### Columns

| Key | Type | Notes |
| --- | --- | --- |
| `name` | text | always visible; for a system field the label "System" is shown under the name |
| `key` | text | always visible |
| `type` | text | the value type key from the registry as is (`string`, `richtext`, `integer`, …; `richtext` has no settings, see [content-values.md](content-values.md#rich-text); `youtube` also has no settings, see [content-values.md](content-values.md#youtube-videos); `url` also has no settings, see [content-values.md](content-values.md#links); `term` — with the vocabulary selection, see [below](#term-field-and-vocabulary); `type_ref` and `node_ref` — with the target selection, see [below](#type_ref-and-node_ref-fields)): the keys are technical; there are no separate localized type names |
| `is_multiple`, `is_required`, `is_translatable` | boolean | "Yes"/"No" in the interface language (the standard display of booleans) |
| `weight` | number | |

Search looks through the text columns: the name, the key, and the value type.

### Row menu

`Components/Admin/ContentFields/ContentFieldRowActions.tsx` — a button group (`ButtonGroup`): the main button "Edit" (a link to the form; active for any field, including a system one) and next to it the "⋮" button with a menu: "Edit" (the same form) and "Delete" (destructive, separated by a divider). For a system field "Delete" is disabled. For a field with `has_values` the item is active, but the dialog (`AlertDialog`) shows the reason ("Delete this field's values first.") and has no confirm button; for the rest the dialog names the field and asks for confirmation. The dialog is rendered next to the menu, not inside it, so that it does not close together with it. The server checks the prohibitions itself ([deletion](#deletion)).

The item "Fields" of the row menu of a content type leads here: [admin-content-types.md](admin-content-types.md#row-actions-and-the-create-button).

## Creation and editing

`create` provides `type`, `typeOptions` (the keys of the registered value types), `fileTypes`, `vocabularies`, `kinds` (`config('content.kinds')`), `contentTypes` (all types: `{key, name, kind}`, by name — for searching a type in the `node_ref` setting) and `defaultWeight` — the largest weight of the type's fields plus one (0 if there are no fields). `edit` provides `type`, `field` (the field's attributes, `description`, `settings`, and `has_values`), `typeOptions`, and `fileTypes`. `fileTypes` — for each type that carries a file (`image`, `file`), its entire upload profile: `{mimes: [...], labels: {mime: label}, max_size: KB, max_width: px|null, max_height: px|null}` (via `UploadRules::forProfile`, without executable files and SVG); `max_width`/`max_height` — the profile's limits in pixels (`null` if the profile does not limit the side, in particular for `file`); `labels` — the short extension of each type (`FileTypes::label`, see [file-storage.md](file-storage.md#short-type-labels)), which the form shows next to the checkbox instead of the long MIME; only for such types does the form show the file settings.

### Validation rules

| Field | Rules | Where |
| --- | --- | --- |
| `key` | required, `^[a-z][a-z0-9_]*$`, up to 64 characters, unique **within the type**, not [reserved](#reserved-keys) | creation only |
| `name` | required, up to 255 characters | both |
| `description` | optional, text up to 1000 characters | both |
| `type` | required, one of the registered value types | both |
| `is_multiple`, `is_required`, `is_translatable`, `hide_label` | booleans | both |
| `max_items` | optional, an integer from 0 to 65535; `0` (default) — no limit; for a single-value field it is reset to `0` | both |
| `weight` | required, an integer from 0 to 65535 | both |
| `settings` | for `image` and `file`: an array with the keys `mimes`, `max_size`, and for `image` also `max_width`, `max_height` (see below); for `term`: an array with only the key `vocabulary` — required, the key of an existing vocabulary (`settings.vocabulary`, [see below](#term-field-and-vocabulary)); for `type_ref`: an array with only the key `kind` — required, one of `content`, `page`, `block`; for `node_ref`: an array with only the key `content_type` — required, the key of an existing type ([see below](#type_ref-and-node_ref-fields)); for other types, including `richtext`, `youtube`, and `url`, it is forbidden (`prohibited`) | both |

The shared part is given by `StoreContentFieldRequest::sharedRules()`, so the forms do not diverge. The rules deliberately duplicate the model ones: the form receives field errors, and the model remains the last barrier. Spaces at the edges are trimmed by the framework.

`is_system`, `content_type`, and `key` (in the update) are not part of the rules, so they do not get into `validated()`: a created field is **never system**, and the key and the owning type do not change.

### `term` field and vocabulary

The type `term` ([content-values.md](content-values.md#term-term)) is among the registered types that the create and edit forms offer (there is no hiding of types in the configuration), but **only for content types** (`content`): in the page and block sections `term` is not offered (`typeOptions` without it), and the server rejects `type = term` with an error next to `type` (`StoreContentFieldRequest::sharedRules($kind)`, shared with editing). For it, the form shows a required vocabulary selection (`Select` `#vocabulary`, `data-testid="vocabulary-setting"`), and the controller passes `vocabularies: {key, name}[]` (by name) as props:

- Saving without a vocabulary or with a non-existent one — an error next to the selection (the error key `settings.vocabulary`); the field is not created. The rules are `StoreContentFieldRequest::settingsRules('term')`; the same branch is used by `UpdateContentFieldRequest`; the `settings` for `term` is stored as `{"vocabulary": "<key>"}`.
- If there are no vocabularies, instead of the selection the form explains that a vocabulary must be created first, with a link to the [Taxonomy](admin-taxonomy.md) section.
- The selection is **blocked** in the edit form when the field has values (`has_values`); the server also rejects a change of the vocabulary of such a field (`TermType::settingsErrors`, an error under the key `settings.vocabulary`). While there are no values, the vocabulary can be changed.
- For other types the vocabulary selection is not shown, and the server rejects `settings.vocabulary` (`prohibited`; for file types — a key outside the allowed `array:` `mimes,max_size`, and for a type with images also `max_width,max_height`).

### `type_ref` and `node_ref` fields

The types [`type_ref` and `node_ref`](content-values.md#type-reference-type_ref) are offered in the forms of all three sections (unlike `term`). For each, the form shows a required setting of the target:

- **`type_ref`** — a selection of the kind of types (`Select` `#kind`, the options "Content types" / "Page types" / "Block types"). Stored as `settings = {"kind": "<kind>"}`.
- **`node_ref`** — a selection of the node type with search by name and key (`RefAutocomplete` `#content_type`, local search over `contentTypes`, without requests). Stored as `settings = {"content_type": "<key>"}`.

Rules: `StoreContentFieldRequest::settingsRules('type_ref' | 'node_ref')` (shared by create and edit); the controller saves only the needed key (`ContentFieldController::attributes()`). Saving without a selection or with an unknown target — an error next to the selection (`settings.kind`, `settings.content_type`; the selection gets `aria-invalid`); the field is not created. The same checks are done by `TypeRefType`/`NodeRefType::settingsErrors`.

The selection is **blocked** when the field has values (`has_values`): for `type_ref` the `Select` is disabled; for `node_ref` only the name and key of the type are shown, both with an explanation; the server also rejects a change (an error under the same key). While there are no values, the target can be changed. For other types these settings are not shown, and the server rejects them.

### Reserved keys

The keys `uuid`, `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`, `author` (`ContentField::RESERVED_KEYS`, see [content-model.md](content-model.md#reserved-field-keys)) coincide with the keys of the service columns of the node table, and `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover` coincide with the keys of the system fields that only the system creates ([content-model.md](content-model.md#system-fields)). Therefore a new field with such a key cannot be created: the form shows "This key is reserved" next to the key, and the model rejects such a record from other paths. The field `title` is not reserved (its key is rejected by key uniqueness). **Existing fields** with such keys remain valid and are edited as before, but they are not offered as columns of the node table. To reserve another key, add it to the constant — the list here and in the model is the same.

### Maximum number of items

`content_fields.max_items` (`unsignedSmallInteger`, `default 0`; migration `2026_10_30_130000_add_max_items_to_content_fields_table.php`) — the upper limit on the number of items of a **multiple** field of any value type; `0` — no limit. It is a separate column, not `settings`: the limit is a general property of multiplicity alongside `is_multiple`, not a setting of the value type. The rule `max_items` (`nullable|integer|between:0,65535`) is in `StoreContentFieldRequest::sharedRules()`, so the edit form inherits it too. `ContentFieldController::attributes()` converts the value to an integer and resets it to `0` (also for an empty one) if the field is not multiple; `max_items` is not in `SYSTEM_LOCKED` (system fields are not multiple). `index` and `edit` provide `max_items`.

The limit is enforced by the server when values are written (`ContentValueWriter::validate()`, [see content-values.md](content-values.md)) and by the node form ([see admin-content-nodes.md](admin-content-nodes.md#multiple-fields)). If the limit is lowered below the number of already saved items, the existing data does not change, but the node cannot be saved until the number of items is within the limit.

### Field description

`content_fields.description` (`text`, `nullable`; migration `2026_10_30_120100_add_description_to_content_fields_table.php`) — an instruction for the node editor. The rule `description` (`nullable|string|max:1000`) is in `StoreContentFieldRequest::sharedRules()`, so the edit form inherits it too. An empty description (also one of spaces only: `TrimStrings` + `ConvertEmptyStringsToNull`) is stored as `NULL`. The description is plain text and is **not in `SYSTEM_LOCKED`**, so it can be changed even for the system field `title`; it does not affect the field's values, their validation, or storage. `ContentFieldController::edit` provides `field.description`, and `create`/`update` save it via `validated()`. The node form shows the description under the field label — [see admin-content-nodes.md](admin-content-nodes.md#node-form).

### Settings for types, size, and image dimensions (`image`, `file`)

For the `image` and `file` fields the form narrows the type's upload profile ([content-values.md](content-values.md#images-and-files)). The rules for `settings` are built by `StoreContentFieldRequest::settingsRules($type)` (both requests use them):

| Key | Rules |
| --- | --- |
| `settings.mimes` | optional array; each element is a type from the field type's profile (executable files and SVG do not pass even in the "extended" profile) |
| `settings.max_size` | optional integer from 1 to the profile's `max_size`, KB |
| `settings.max_width`, `settings.max_height` | only for types whose profile has `image/*` (`image`, not `file`): an optional integer from 1 to the profile's limit (`max_width`/`max_height`), px; without a profile limit — any positive value. For `file` the keys are forbidden (`settings` is rejected). The smaller of the field's and the profile's limits applies on upload: a larger image is downscaled ([file-storage.md](file-storage.md#image-processing-imageresizer)) |

Errors come with the keys `settings.mimes.N`, `settings.max_size`, `settings.max_width`, and `settings.max_height` and are shown next to the list of types and the corresponding fields. The controller reduces the settings to a filled-in form (numbers are cast to `int`): an empty list and empty size and limits give `settings = null` (the whole profile applies). Changing the limits does not touch the existing files: a new limit applies only to subsequent uploads. A field that has values can be narrowed: the saved values do not change, and the new rules apply on the next write of a value.

### Form

The shared component `Components/Admin/ContentFields/ContentFieldForm.tsx` (`useForm`, modes `create`/`edit`), the pages `Pages/Admin/ContentFields/{Create,Edit}.tsx`. Fields: key, name, "Description" (`Textarea`, optional, `maxLength` 1000, an error next to the field), value type (`Select` with `typeOptions`); for types with `fileTypes` — the block "File settings" (checkboxes of the allowed file types of the profile with extension labels `pdf`, `docx`, `png`; the form values and the saved `settings.mimes` remain full MIMEs; and "Maximum size (KB)" with a hint about the profile limit; and when the profile of the type has images (`image`) — also "Maximum width (px)" and "Maximum height (px)" (`#max_width`, `#max_height`, a hint about the profile limit or "no limit", an error next to the field with `aria-invalid`; for `file` they are absent, and `transform` does not send them); without a selection the whole profile applies; `transform` does not send `settings` for other types), four checkboxes (`Checkbox`: "Multiple", "Required", "Translatable", "Hide label" — the last with the hint "Only affects the public page: the field name is not printed above its value."; it is available for every type, including system fields, and the node form in the admin panel keeps the label of the field), for a multiple field — "Maximum number of items" (`type="number"`, 0–65535, the hint "0 — no limit"; turning off "Multiple" hides the field and resets the value to `0`, [see above](#maximum-number-of-items)), and the weight (`type="number"`; next to it a hint that the order is more convenient to change by dragging). Server errors are shown next to the fields, entered values are kept, "Save" is disabled during submission, and "Cancel" leads to the list without changes.

In the `edit` mode the key is `readOnly` with a hint about immutability. Blocked (`disabled`):

- **any field** — the type (the hint "The field type cannot be changed.");
- **a system field** (`is_system`: `title`, the metadata and section fields) — additionally "multiple" and "required".

Blocking is a convenience of the interface; the server rejects such changes by itself ([protection by the model](#model-protection)). Breadcrumbs for `…/fields`, `…/fields/create`, and `…/fields/{key}/edit` are built by `getBreadcrumbs` (`navigation.ts`): "Admin → Content Types → Fields / Create field / Edit field".

### Translation of the label

The label (`name`) of a field is its only translatable attribute. `GET …/fields/{field}/edit?lang=en` is the [translation mode](admin-translation-mode.md): the same form is shown, where only the label is editable, prefilled with the stored translation or else the base name (`translation.values.name`, up to 255 characters; `translation.exists` is true when a label is stored for `lang`). The key, description, type, flags, weight and settings keep their place, shown inert and dimmed with their base values, and are not changed. An unknown or inactive language answers 404; panel fields stay hidden (404) as on the base page. The right is the type configuration right (`can:types`), as for the whole section; labels are available for every value type, including system fields such as `title`.

- `PUT …/fields/{field}/translations/{lang}` (`ContentFieldController::saveTranslation()`, `SaveContentFieldTranslationRequest`) — body `name` (nullable, max 255). A label equal to the base name is not stored and an empty one removes the stored label (`ContentField::setLabel()`); other languages and every other attribute are untouched. It redirects to `…/edit?lang={lang}` with "Translation saved."
- `DELETE …/fields/{field}/translations/{lang}` — removes the label of that language and redirects to the same form with "Translation cleared."

Translations live in `settings.labels` ([content-model.md](content-model.md#translated-field-labels)). The base form never changes them: `StoreContentFieldRequest::settingsRules()` lets `labels` through for every type, but `ContentFieldController::attributes()` drops what the form sends and keeps the stored labels (also when the controller rebuilds `settings` for `term`, `type_ref`, `node_ref` and file types), and a labels-only save does not run the settings checks of the value type, so it works for a `term` field that has values too. A language with a translated label cannot be deleted ([content-languages.md](content-languages.md)).

### Model protection

`update` calls `$field->update($validated)`. The prohibitions of `ContentField` ([content-model.md](content-model.md)) — changing the type of any field (a request with the current type is not an error) and changes of the type, multiplicity, and requiredness of a system field (`title`, `meta_*`, `section_*`) — throw `ValidationException`, which Laravel turns into a redirect back with an error next to the corresponding field. There is no separate logic in the controller, and the prohibition is checked by the server regardless of the state of the form.

After success, `store` returns to the list of the type's fields, and `update` leaves the user on the field form (`back()`); both with a `success` message.

## Deletion

`DELETE …/fields/{field}`. A field is **not deleted** (the user is returned back with an `error` message) if it is system (`title`, `meta_*`, `section_*`) or has saved values. The same prohibition for a field with values is also enforced by the `ContentField` model (`ValidationException`), so the controller only gives the user a regular message instead of a validation error. Otherwise the field is deleted, and the user goes to the list with `success`.

## Hidden panel fields

The system fields of panels — `meta_title`, `meta_description`, `meta_image` and `section_title`, `section_description`, `section_cover` (those for which `SystemFields::panel($key) !== null`) — are configured by the system, so the "Fields" tab does not show them for any kind of type. The field `title` does not belong to the panels and remains. `ContentFieldController::index` excludes such fields, and `edit`, `update`, and `destroy` respond with 404 for them (the private `abortForPanelField`). The values of these fields are edited on the "Display" tab (the section values) and the "SEO" tab of the type ([admin-content-types.md](admin-content-types.md#display-tab-all-kinds)) and in the "SEO" section of the node ([admin-content-nodes.md](admin-content-nodes.md#fields-of-the-node-and-the-seo-panel)); the fields themselves, their values, and the possibility of choosing `meta_*` as a column of the node table do not change ([content-model.md](content-model.md#system-fields)). The weights of hidden fields are not touched by `reorder`: the order in the node form is determined only by the shown fields.

## Order

`PUT …/fields/order` accepts `order` — an array of field keys from the first to the last. `ReorderContentFieldsRequest` requires that these be **exactly all shown fields of the type** (all except the panel fields): without omissions, without repeats, without foreign keys. An incomplete or foreign set gives an error under `order`, a repeat — under the element (`order.1`); the weights do not change in either case.

After a successful check, `reorder` in a transaction sets `weight = index` (0…n-1) by direct row updates, without the model's `saving` checks: only the weight changes, and the full key check has already been done. The system field `title` can also be moved; panel fields in the request are rejected as foreign, and their weights do not change. The response is a redirect back with `success`.

### Drag and drop on the list page

The table is enabled with the `reorder` prop ([admin-data-table.md](admin-data-table.md#reordering-by-drag-and-drop-reorder)): a handle in the first cell of each row, with the mouse and the keyboard. The handles are active only when the whole list is visible in the original order: without search, column sorting, and on condition that all fields fit on one page; otherwise they are inactive with a hint.

The page keeps a **local copy** of `fields`: after a drop the row immediately moves to the new place, and the page sends `router.put('{basePath}/{key}/fields/order', { order }, { preserveScroll: true, only: ['fields', 'flash'] })`. After success the server returns the fresh `fields` (with weights 0…n-1) and `flash` (with it `success` shows a toast; without `flash` in `only` the message would not arrive); the local copy is synchronized by the change of the prop.

**Rollback on error.** If the server rejected the order (for example, the set of keys is stale because another user added or deleted a field), `onError` restores the previous order and shows the first error text as a toast (`errors.order` or the element's error). Reloading the page gives a fresh list. The `weight` field in the form remains; next to it there is a hint that the order is more convenient to change by dragging.

## Tests

`tests/Feature/Admin/ContentFieldIndexTest.php`, `ContentFieldCreateTest.php`, `ContentFieldEditTest.php`, `ContentFieldDestroyTest.php` (together with the order): a guest and an unverified user, props, order by weight, `has_values`, 404 for `page`/`block` and a foreign key (the `page` and `block` sections — in `PageTypeTest` and `BlockTypeTest`), validation, the field description (saving, clearing, the 1000-character limit, changing the description of `title` while the other prohibitions remain), prohibitions for `title` and fields with values, the immutability of the type of a field without values, the order, hidden panel fields (the list, 404 on edit/update/destroy, rejection of their keys in the order).

Settings of reference fields and their integrity: `tests/Feature/Content/TypeRefFieldTypeTest.php`, `NodeRefFieldTypeTest.php` (values, settings `kind` / `content_type`, immutability, multiplicity, translation, filters), `RefUsageTest.php` (the prohibition on deleting a target).

```bash
vendor/bin/phpunit --filter=ContentField
vendor/bin/phpunit --filter=RefFieldType
```

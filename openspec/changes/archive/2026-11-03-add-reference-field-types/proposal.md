# Proposal

## Why

Editors need references between the entities of the CMS: to choose a type of content (for example, for the block «Список записів» — "List of records") and to choose a specific record of a certain type. Right now for this only `string` remains, which knows nothing about the existence of the target and does not update the label.

## What Changes

- A new field type `type_ref` («Посилання на тип контенту» — "Reference to a content type"): the value is the `key` of a type, in `content_values_string`. In the settings of the field the kind of types is chosen (`settings.kind`: `content` / `page` / `block`). In the form of a record — a `Select` with autocomplete and a search on the server by the `name` of the type.
- A new field type `node_ref` («Посилання на запис» — "Reference to a record"): the value is the `uuid` of a record (lower case), in `content_values_string`. In the settings of the field the type of the record is chosen (`settings.content_type`). In the form of a record — an autocomplete with a search on the server by the `title` of the record (the language `default`).
- Validation: the value must point to an existing type of the required kind / an existing record of the required type; locking of the references at the time of writing (as in `term`).
- The settings of the field are required, and after the values appear they are immutable (as `vocabulary` in `term`).
- New admin endpoints of the search (JSON, only for authorized admins): types and records by the request `q`, a limit on the response.
- In the table of records the cell shows the `name` of the type / the `title` of the record (and not the `key`/`uuid`) as a link to edit this entity, which opens in the same window; the column is sorted by this label.
- Protection of the integrity: a type/record that is referenced by the values of fields cannot be deleted (as a term that is used).
- There are no new tables (`content_values_string`); no migration is needed.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-values`: the types `type_ref` and `node_ref` (the value, validation, filters, protection against deletion).
- `admin-content-fields`: the settings of the fields `type_ref` (the kind) and `node_ref` (the type of the record).
- `admin-content-nodes`: the widgets of autocomplete in the form of a record, the endpoints of the search, the display in the table.

## Impact

- Code: new `App\Content\FieldTypes\TypeRefType` and `NodeRefType`, rows in `config/content.php`; the endpoints of the search (a controller + routes in `routes/web.php`); `PresentsFormFields`, `ContentNodeController` (labels in the cells, the initial labels of the selected values); the models `ContentNode` / `ContentType` (prohibition of deleting the used ones); `resources/js/Components/Admin/ContentFields/ContentFieldForm.tsx` (the settings), `resources/js/Components/Admin/ContentNodes/` (a new `RefAutocomplete`, `NodeFieldInput`, `types.ts`), `Pages/Admin/ContentNodes/Index.tsx`; translations `lang/uk.json`, `lang/en.json`.
- Tests: Feature tests of both types, of the endpoints of the search and of the protection against deletion; an e2e spec of the form of a record.
- Documentation: `docs/content-values.md`, `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/e2e-testing.md`.

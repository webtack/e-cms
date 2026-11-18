# Proposal

## Why

Editors need a field for a link with an optional caption (a «Read more» button, a link to a document, an internal page of the site). Now for this a plain `string` remains, which knows nothing about the address and does not check it, or two separate fields.

## What Changes

- A new field type `url`: the link (a scalar value in `content_values_string`, the column `value`) and an optional title (`title` in the JSON `options` of the same row) — through the existing mechanism of composite values.
- Absolute links (`http`/`https`) and relative ones (a path `/…`, `./…`, `../…`, a fragment `#…`, a query `?…`) are accepted. Other schemes (`javascript:`, `data:`, `mailto:` etc.) are rejected.
- In the form of writing the field looks like two single-line text fields side by side: «Link» and «Title» (optional). Without modal windows and requests to external services.
- In the table of records the cell shows the title, and without it — the link.
- The type has no settings of the field (`settings` is forbidden, as for `youtube`). There are no new tables and no migrations.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-values`: the new field type `url` (the value, validation, filters, reading/writing of the composite value).
- `admin-content-nodes`: the input element of the field `url` in the form of writing and the display of the value in the table of records.

## Impact

- Code: the new `App\Content\FieldTypes\UrlType`, a row in `config/content.php` (`field_types`), `ContentNodeController` (the text of the table cell), `resources/js/Components/Admin/ContentNodes/` (`NodeFieldInput`, `ContentNodeForm`, `NodeFieldList`, `types.ts`), translations `lang/uk.json` and `lang/en.json`.
- Tests: a Feature test of the type (by the pattern of `YoutubeFieldTypeTest`), an e2e spec of the form of writing.
- Documentation: `docs/content-values.md` (the table of types and the section «Посилання» — "Link"), `docs/admin-content-fields.md` (the list of types, `settings` forbidden), `docs/admin-content-nodes.md` (the widget and the table cell), `docs/e2e-testing.md` (the table «Область → спек» — "Area → spec").

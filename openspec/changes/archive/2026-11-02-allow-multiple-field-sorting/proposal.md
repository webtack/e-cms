# Proposal

## Why

For sections such as "Афіша" (a schedule of events) a record has several dates (a multiple field `datetime`: each showing is a separate value), and the list has to be ordered by date. Today both the selection (`ContentNodeQuery`) and the table of records in the admin forbid sorting by a multiple field, so the schedule cannot be sorted without duplicating the date in a separate non-multiple field.

## What Changes

- A single rule of the sort key for the admin and the site: the **first value** of the field (`weight = 0`) in the specified language, identically for a multiple and a non-multiple field. The selection (`ContentNodeQuery`) sorts by any field, and the admin does not offer sorting by file fields (`file`, `image`) and `youtube`.
- Records without a value remain at the end in both directions. The secondary order for an explicit sorting (by a field and by `published_at`) is by `title` in ascending order (records without `title` in the specified language go after those that have it), then by `id`. Without an explicit sorting the order does not change. There is no fallback of `title` to the language `default`, as in the rest of the selection.
- The restriction by multiplicity disappears: the error "Sorting by a multiple field is not supported" in the selection and the check `! is_multiple` in `ContentNodeController::sortable()` and `listSettings`. The restriction by type remains only in the admin: `sortable()` still excludes `AbstractFileFieldType` and `youtube`.
- The columns of all fields in the table of records, except the file ones (`file`, `image`) and `youtube`, are sortable, including the multiple ones; `settings.list.sort` accepts any field whose column is sortable.
- The sorting by `published_at` does not change; the columns of the slug and the state in the admin remain unsorted.
- The filters do not depend on whether the field is multiple or not: a record matches the filter if at least one value of the field matches it (OR over the values). This rule becomes an explicit requirement, including the case of several filters on one field (each filter is executed independently, possibly by different values); between the filters AND remains.
- A new operator `in` (the value is a non-empty array): a record matches if any of its values equals any of the passed ones (OR inside the filter). Available for the types `string`, `text`, `richtext`, `integer`, `decimal`, `date`, `datetime`.

**Accepted consequence.** "The first value" is what the editor put first in the form of the record, not the nearest date. The schedule is sorted by date only when the dates are entered in order. The filters do not change the sort key. File fields and `youtube` store the file name `{uuid}.{ext}` or the URL of the video, so there is no meaningful order by them: the admin does not offer such sorting (the column is `sortable: false`, `sort` in the address is ignored). The selection `ContentNodeQuery` does not forbid this (as before the change) and sorts by the stored string.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-querying`: the requirement "Sorting by the type of a field" — sorting by the first value of any field, including a multiple one, instead of a ban; the requirement "Filtering by the value of a field" — the operator `in` and the explicit rule of OR over the values.
- `admin-content-nodes`: the requirement "Search and sorting of records" — the columns of fields are sorted by the first value, including the multiple ones; file and `youtube` fields remain unsorted.

## Impact

- Code: `app/Content/ContentNodeQuery.php` (`applySort` together with the secondary order by `title`, `filterValue`, `valueCondition`), the field types with the operator `in` (`StringType`, `TextType`, `RichTextType`, `IntegerType`, `DecimalType`, `DateType`, `DateTimeType`), `app/Http/Controllers/Admin/ContentNodeController.php` (`sortable()` without the check of multiplicity, `listSettings`, docblocks).
- Tests: `tests/Feature/Content/ContentNodeQueryTest.php`, `tests/Feature/Admin/ContentNodeIndexTest.php`.
- Docs: `docs/content-querying.md` (the sections "Sorting" and "Operators by types"), `docs/admin-content-nodes.md` (sorting).
- E2E: `e2e/admin-content-nodes.spec.js` (the check `?sort=tags` is no longer about ignoring it).
- Not changed: the interface `FieldType`, the search, the combination of filters through AND, new field types, the order without an explicit sorting, the absence of a fallback to `default`. The name of the change remains `allow-multiple-field-sorting`, although it now also covers the operator `in`.

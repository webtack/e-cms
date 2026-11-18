# Design

## Context

Sorting (`ContentNodeQuery::applySort`) joins through a `LEFT JOIN` the row `weight = 0` of one non-multiple field; for a multiple field it is rejected with a validation error. The table of records in the admin (`ContentNodeController::sortable()`, `listSettings`) has its own criterion: not multiple, not a file field (`instanceof AbstractFileFieldType`) and not `youtube` (by the key string). All types, including the file and `youtube` ones, store values in a table with a `value` column (`content_values_string` and the others), so a `LEFT JOIN` on it is technically suitable for each of them; the restriction of file types is needed only by the admin, because there is no meaningful order by a file name or URL. The motivation is in [proposal.md](proposal.md); the description of the selection is in [docs/content-querying.md](../../../docs/content-querying.md).

## Goals / Non-Goals

**Goals:**
- One rule of the sort key for the admin and the site: the first value (`weight = 0`), for both a multiple and a non-multiple field.
- Remove the restriction by multiplicity in the controller and in the selection; the sortability by type remains in the admin.
- Record that the filter works in the same way for a multiple and a non-multiple field (OR over the values), and add the operator `in` (OR over the passed values).

**Non-Goals:**
- Collapsing the values of a multiple field (`MIN`/`MAX`) and taking the filters into account in the sort key.
- Changing the combination of filters (remains AND), changing the search, other new operators; new field types; sorting by several fields; a method `sortable()` in `FieldType`.
- Sorting by `published_at` does not change; the slug and the state in the table of the admin remain unsorted (they are not fields).

## Decisions

**1. Sorting by `weight = 0` for any field.**
The existing `LEFT JOIN` of the row `weight = 0` (with `content_type`, `field_key`, `lang`) already gives at most one row per record, so there are no duplicates in the selection or in the counter. The only change in the selection is to remove the block `if ($field->is_multiple) { throw … }`; no type checks are added (there were none there), so the selection sorts also by file fields and `youtube`. A secondary key by `title` is added to the order (decision 6): `ORDER BY (sort_value.value IS NULL), sort_value.value {direction}, (title_value.value IS NULL), title_value.value ASC, content_nodes.id`. The alternatives — `MIN`/`MAX` with collapsing and filters, and `FieldType::sortable()` to exclude file types — were rejected as excessive for the need.

**2. The admin does not offer sorting by file fields and `youtube`.**
`ContentNodeController::sortable()` remains, but loses the condition `! $field->is_multiple`: `return ! $this->isFileField($field) && $field->type !== 'youtube';`. The columns of multiple fields become `sortable: true`; the columns `file`, `image` and `youtube` remain `sortable: false`, and `sort` for them in the address is ignored (`TableRequest`). `listSettings` keeps `settings.list.sort` if the field exists and `sortable()` is true for it (instead of the check `! is_multiple`), so a reference to a file field is dropped, as is the column in the table.

**3. Service columns.**
`slug` and `status` are not fields, so in `tableColumns` they remain `sortable: false`; `published_at` — `true`. `TableRequest` continues to ignore `sort` for columns with `sortable: false`.

**4. The filter does not distinguish multiple and non-multiple fields.**
The current `EXISTS` over the table of values already gives "at least one value matches"; the logic does not change, and the rule is written into the spec. Several filters on one field remain independent `EXISTS` (each may be satisfied by a different value of the record); this is described by a separate scenario. The filters and the sorting are intentionally not reconciled: the filter looks at any value, the sorting — at the first one.

**5. The operator `in`.**
The value is a non-empty array; each element is checked by the rules of the type of the field (as for `eq`) and prepared through `prepare`. A branch `in` appears in `filterValue` (by the pattern of `between`), and in `valueCondition` — `whereIn("{table}.value", $values)`. The operator is added to `operators()` of the types `string`, `text`, `richtext`, `integer`, `decimal`, `date`, `datetime`; `boolean` (two values), `youtube` and the file types do not receive it. The alternative — OR between filters (`match: any`) — was rejected: it changes the semantics of all filters, and the need for "either/or" on one field is covered by `in`.

**6. A secondary order by `title`.**
For an explicit sorting (by a field and by `published_at`), after the main key the records are ordered by `title`, then by `id`. `title` is a system field of the type `string`, required in the language `default`, non-multiple, `weight = 0` ([content-model.md](../../../docs/content-model.md)), so the `LEFT JOIN` of the row `weight = 0` gives at most one row per record. The direction of the secondary key is always `ASC` regardless of the direction of the main one. The language is the one specified in the request; **there is no fallback to `default`** (as in the rest of the selection, [content-values.md](../../../docs/content-values.md)): a record without `title` in this language goes after the records with `title`, then by `id`. Without an explicit sorting the order does not change (from `settings.list.sort` or by creation). A separate case of sorting by `title` itself is not singled out: the secondary `LEFT JOIN` remains, it is safe. The join alias is `title_value`. The alternative — `COALESCE` with `title` of the language `default` — was rejected: it introduces a fallback that the system does not have; the question of translations is discussed separately.

The documentation that the design describes: `docs/content-querying.md` (the sections "Sorting" and "Operators by types"), `docs/admin-content-nodes.md` (sorting of columns).

## Risks / Trade-offs

- [An additional `LEFT JOIN` on the table of values for `title` at every explicit sorting] → the column `value` is indexed, the cost is small; the join can be removed only in a separate case (sorting by `title`), which is not introduced for the sake of simplicity.
- [Records with equal keys change their order: before by `id`, now by `title`] → intentional; it concerns only explicit sorting, the default order does not change.
- [The first value is not necessarily the nearest date] → an accepted consequence; the editor must enter the values in order. Automatic sorting of values on saving is a separate change, if it is needed.
- [The selection `ContentNodeQuery` sorts by a file field and `youtube` by the stored string (`{uuid}.{ext}`, URL), which gives no meaningful order] → accepted: the admin does not offer such sorting, and a separate ban in the selection would require a criterion of the sortability of the type, which was not there before either.
- [The change affects the admin, not only the site] → intentional, the rule is shared; the e2e check `?sort=tags` is updated together with the variable spec.
- [An array `in` without an upper bound on the number of values] → `whereIn` over the indexed column `value` is cheap for usual sizes; a separate limit is not introduced until there is a need (a change of configuration is a separate change).
- [Several filters on one multiple field may be satisfied by different values] → accepted and fixed by a scenario; for "both conditions by one value" there is no operator, which is not part of this change.
- [`settings.list.sort` on a multiple field stops being dropped] → intentional; a reference to a file field, to `youtube` or to a missing field is dropped. For file fields and `youtube` this is new behaviour: previously `listSettings` dropped only multiple fields.

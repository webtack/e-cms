# Design

## Context

Scalar values that reference other entities already exist: `TermType` (the uuid of a term in `content_values_string`, `ValidatesSettings`, `LocksReferences`, `TermUsage`, protection against deletion in `Term::booted`). The new types repeat this pattern. The `title` of a record is a system field (`SystemFields`), the value of the language `default` in `content_values_string` (`field_key = 'title'`). The `key` of a type is the primary key of `content_types.key`. Description of the components: `docs/content-values.md`, `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`.

## Goals / Non-Goals

**Goals:**
- Two classes of types by the pattern of `TermType`; no migrations.
- A search on the server with a limited response; the form does not load all types/records.
- Integrity: the targets are not deleted while there are references to them.

**Non-Goals:**
- Filters by the nested fields of the target, back references, the public expansion (expand) of the target.
- Changing the `kind`/`content_type` of a field that already has values.
- Multilingual search and labels (only `default`).

## Decisions

- **Key types**: `type_ref`, `node_ref`. The storage — `content_values_string`; `type_ref` writes the `key` of the type, `node_ref` — the `uuid` (lower case, `prepare()`), as `term`.
- **Settings** (`ValidatesSettings`): `type_ref` — `settings.kind` ∈ `config('content.kinds')`, required; `node_ref` — `settings.content_type` — the key of an existing type, required. After the values appear they cannot be changed (as `vocabulary`). For both the other keys of `settings` are forbidden; `ContentFieldForm` sends only the needed key.
- **Validation of the value**: `type_ref` — a string, an existing type of the required `kind`; `node_ref` — a `uuid`, an existing record in `content_nodes` with `content_type` = the setting. The filters (an empty field, as in `term`) check only the existence. Operators: `eq`, `in`.
- **Locking**: both types implement `LocksReferences` (a shared lock on `content_types` / `content_nodes` in the transaction of writing).
- **Protection against deletion**: a `Term`-like check in `static::deleting` of the models `ContentNode` and `ContentType`: a query to `content_values_string` ⨝ `content_fields` (`f.type = 'node_ref'` and `v.value = uuid`; for a type — `f.type = 'type_ref'` and `v.value = key`) → `ValidationException` («використовується» — "is used"). For `ContentType::deleting` the values of the fields of the type itself are also deleted by the cascade and are not counted as usage (we count only the values of other types/owners). Alternative — leave «dangling» values and show them empty: rejected, because it silently breaks the content of the site.
- **Search**: JSON endpoints in the admin group: `GET /lookup/content-types?kind=&q=` (search by the `name` of the type, LIKE, up to 20 results, `{key, name}`) and `GET /lookup/nodes?content_type=&q=` (search by the `title` of the language `default`, up to 20, `{uuid, title}`); an empty `q` — the first N in a stable order (`name` / `title`). The parameters are required and checked (an unknown `kind`/type → 422). The endpoints do not depend on a particular field, the rights are the same as in the admin. Alternative — an endpoint per field (`fields/{field}/options`): one more layer of binding, not needed.
- **Initial label**: when the form is opened, the label of the selected values is not searched separately: `ContentNodeController` together with the data of the record gives `labels` (`{value: label}`) in one request per type (as `termNames()`), the client shows them before the first search. If the target does not exist yet (it must not happen), the label is the value itself.
- **Client**: one component `RefAutocomplete` (Popover + Command/Input with a page of results, debounce 250 ms, cancelling of outdated requests), used by both types; `NodeFieldInput` chooses the endpoint by the type of the field. The multiple fields work through `NodeFieldList` without changes. The value of the form is a string (`''` — no value).
- **Table**: `ContentNodeController` collects the `name` of the types (with `kind`) and the `title` of the records (with `content_type`) of the page with one request per type of the field (as `termNames`) and gives the cell an object `{label, href}`; `href` — the address of editing of the target in the section of its kind (`{section}.edit` of the type, `{section}.nodes.edit` of the record). `NodeCell` draws an ordinary Inertia `<Link>` without `target`, i.e. in the same window. The column is `text`.
- **Sorting**: `ContentNodeQuery::applySort` for `type_ref` / `node_ref` joins to the first value (`weight = 0`) the table of labels (`content_types.name` / the value `title` of the language `default` of the record) and sorts by the label, not by `key`/`uuid`; records without a value — last, the secondary order — as for the other fields. `NodeListSettings::sortable()` no longer excludes these types, they are available in `sortOptions` and as the default sorting. Alternative — sort by the raw value: rejected, because the order does not match the visible label.

## Risks / Trade-offs

- [The protection against deletion adds a request in `deleting`] → one indexed request; it blocks only when there are references.
- [Deleting a type by cascade destroys its records, which others may point to] → the check of `ContentType::deleting` also takes into account the `node_ref` that point to the records of this type (`settings->content_type`), and `type_ref` to the type itself.
- [Search LIKE `%q%` on `title` is slow on large volumes] → a limit of 20; a prefix index is not needed at this scale; move it to a future change.
- [A change of the `title` in the root makes the label outdated] → the label is always taken from the DB, it is not stored.

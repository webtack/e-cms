# Design

## Context

The admin is Inertia + React + TypeScript. The sections of types are built by one pattern: the controller returns `Inertia::render`, the list is a `DataTable` (server mode through `TableRequest`/`TablePage`, or client mode with `reorder`), the forms use `useForm` with `FormActionBar`, the menu is in `navigation.ts` (one source for the sidebar and the breadcrumbs), the labels are English keys, the translations are in `lang/uk.json`. A field of a type is created in the admin by the form `ContentFieldForm`; the value of a record is entered by `NodeFieldInput`, the single place of the display of the value type as a widget; the list of records formats the values through `ContentNodeController::cell()/text()`. A record edits only the base language `default`.

This change depends on [add-taxonomy-core](../add-taxonomy-core/design.md): the models `Vocabulary`/`Term`, the services `TermTree`, `TermWriter`, `TermUsage`, the type `term`. The requirements are in [specs/](specs/); the motivation is in [proposal.md](proposal.md). The components described: the new `docs/admin-taxonomy.md`, and also `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/admin-shell.md`, `docs/taxonomy.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- The section «Таксономія» by the same patterns as the types/fields (without new libraries).
- The invariants are not duplicated in the UI: the controllers and the requests turn the `ValidationException` of the core into the errors of the form and the messages.

**Non-Goals:**
- Autocomplete/creation of a term in the form of a record; dragging between parents; public pages; access rights.

## Decisions

### 1. Routes and binding

```
/admin/taxonomy                              GET   vocabularies index      admin.taxonomy.index
/admin/taxonomy/create                       GET   form                    admin.taxonomy.create
/admin/taxonomy                              POST                          admin.taxonomy.store
/admin/taxonomy/{vocabulary}/edit            GET                           admin.taxonomy.edit
/admin/taxonomy/{vocabulary}                 PUT / DELETE                  admin.taxonomy.update / destroy
/admin/taxonomy/{vocabulary}/terms           GET   tree table              admin.taxonomy.terms.index
/admin/taxonomy/{vocabulary}/terms/create    GET                           admin.taxonomy.terms.create
/admin/taxonomy/{vocabulary}/terms           POST                          admin.taxonomy.terms.store
/admin/taxonomy/{vocabulary}/terms/order     PUT                           admin.taxonomy.terms.reorder
/admin/taxonomy/{vocabulary}/terms/{term}/edit | PUT | DELETE              ...edit / update / destroy
```

`Route::bind('vocabulary')` by `key` (the single identifier of the vocabulary: the vocabulary has no `uuid`, so `key` stands in the URL, in `settings.vocabulary` and in the `Select` of the form of the field); `Route::bind('term')` by `uuid` only within the vocabulary from the route (by the example of `field`, which is looked up by the pair). `terms/order` is declared before `{term}`, as `fields/order`. The controllers `VocabularyController` and `TermController`, the requests `Store/Update` for each and `ReorderTermsRequest`.

### 2. Errors of the core → UX

The models and `TermWriter` throw `ValidationException` with the keys of the fields; the requests duplicate the format rules, so that the form has errors by fields (as `StoreContentFieldRequest`), and the core remains the last barrier. The deletion (`destroy`) catches `ValidationException` and returns `back()` with a flash message about the error (the existing `FlashToaster`); for a used term the message is composed from `TermUsage` (the field, the type, the count).

### 3. The tree in the table and the order

The page of terms gives **all** terms of the vocabulary as rows in the order of the walk of the tree with `depth`, `children_count`, `usage_count` (from the `TermUsage` of the core: one grouped query by `content_values_string` for the fields of the type `term`, for the deletion dialog), `uuid` and the base name; `DataTable` `mode="client"` with `reorder`, the name with an indent by `depth` (the cell `cell`). The tree is assembled by `TermTree::tree`; the controller flattens the rows.
**Dragging changes only the order.** The client sends `order[]` — the `uuid` of all terms in the new order of rows; the server rewrites `weight = position` (a direct `UPDATE`, the invariants of the tree do not depend on the weight) and requires exactly the whole set of `uuid` of the vocabulary. Since the siblings are sorted by `weight`, their relative order coincides with the order of the rows; a row dropped into a foreign branch only changes the order among its siblings after the refresh. The parent is changed by the form.
**Alternatives:** nested dragging with re-parenting (requires its own tree widget; `@dnd-kit` in the project is only a flat sortable list) — postponed; buttons «вгору/вниз» — inconvenient with many terms.

### 4. The list of possible parents

The server computes and passes the form `parentOptions: {uuid, name, depth}[]` in the order of the tree: all terms of the vocabulary, except (on editing) the term itself and its descendants, and only those for which `depth(parent) + 1 + height of the subtree ≤ max_depth` (for a new term the height of the subtree is 0). The client does not compute the depth itself.

### 5. The form of a term and the translations

`TermForm`: `name`, `description`, `slug`, `parent_uuid`, `weight`, `image` (a choice or an upload of a file of the profile `image` by the same mechanism as `NodeFieldInput` for the field `image`; the server checks it by the rule of the core and stores it together with the link in `media_relations` through `TermWriter`) and, when `ContentLanguages::isMultilingual()`, a block `translations[lang][name|description]` for each active language (`ContentLanguages::active()`; the server passes `languages`). Saving calls `TermWriter` (the core) in a transaction; an empty translation is deleted. The default weight: the largest weight among the siblings + 1. The interface language of the administrator and the content languages are independent; the content languages are taken only from `ContentLanguages`.

### 6. The field `term` in the form of a field of a type

`ContentFieldController` stops hiding `term` (`admin_hidden_field_types` is removed from `config/content.php` together with the filter), but `term` remains in `typeOptions` only for the section `content`; for `page` and `block` it is removed, and `StoreContentFieldRequest`/`UpdateContentFieldRequest` reject `type = term` when the owner type is not `content` (an error next to `type`). The forms receive `vocabularies: {key, name}[]`. `ContentFieldForm` shows a `Select` of the vocabulary for the type `term`, the choice is blocked when `has_values`. `StoreContentFieldRequest::settingsRules` gets a branch for `term`: `settings` — an array with only `vocabulary`, `settings.vocabulary` is required and `exists:taxonomy_vocabularies,key`; for the other non-file types `prohibited` remains. `UpdateContentFieldRequest` uses the same rules, and the prohibition of changing the vocabulary of a field with values is ensured by the core (`settings.vocabulary`).

### 7. The field `term` in the form and the list of a record

`PresentsFormFields::formFieldData` for `term` adds `settings.terms: {uuid, name, depth}[]` (the tree of the vocabulary, the base names) — one query per field. `NodeFieldInput` gets a branch `term`: for a non-multiple field a `Select` with an empty option, for a multiple one — a list of checkboxes with indents (the multiplicity and `max_items` are already handled by `NodeFieldList`). The state of the form is a string with the `uuid`; the server checks it by the rule of the type `term` (the UUID format, the term exists in the vocabulary of the field). In the list `ContentNodeController::text` for `term` takes the name from the map `termNames` (one query per page for all `uuid`, as `fileNames`), `sortable()` returns `false` for `term`.
**Limitation:** the list of terms is passed entirely, without autocomplete; for vocabularies with thousands of terms a separate search widget will be needed (outside this change).

### 8. Navigation

`navigation.ts` gets `{ title: 'Taxonomy', href: '/admin/taxonomy', icon: Tags }` between Block Types and Pages; `extraTitles` and `patternTitles` — «Create vocabulary», «Edit vocabulary», «Terms», «Create term», «Edit term». The Ukrainian translations are in `lang/uk.json`.

## Risks / Trade-offs

- [Dragging a «foreign» branch behaves non-obviously] → only the order changes; document it in `docs/admin-taxonomy.md`, a hint in the UI; the parent is changed by the form.
- [Large vocabularies are heavy for the form of a record and the page of terms] → pagination of the client table, the choice in the form of a record without autocomplete; the limit is documented, the autocomplete is a separate change.
- [Client-side sorting of the table breaks the tree] → `reorder` is already disabled when sorting/searching; the tree is shown without sorting by default.
- [Several shared components are changed (`ContentFieldForm`, `NodeFieldInput`, `navigation.ts`)] → we run the dependent e2e specs from the table «Спільний код → спеки»; a full run is not needed.
- [Races: a term is deleted in another tab while the form of a record is open] → the server rejects a non-existent term with an error next to the field.

## Migration Plan

There are no migrations. Deployment after `add-taxonomy-core`; the rollback — remove the routes/pages and return `admin_hidden_field_types => ['term']` (for page/block `term` is filtered by the section separately). The data of the taxonomy is not affected.

## Open Questions

- The labels and translations of the new phrases (the exact texts) are specified during the implementation, they do not affect the specification.

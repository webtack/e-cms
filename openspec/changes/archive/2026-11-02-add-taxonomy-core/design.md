# Design

## Context

The content model is EAV: the structure (types, fields) is data, the values lie in typed tables `content_values_{type}` with a shared contract (the owner `entity` = a record or a type, `lang`, `weight`, `value`, `options`). The type of a field is a class in the registry `config/content.php` (`field_types`); the core of records, reading and selection does not change for the new types. There is no field that references other entities. The justification is in [proposal.md](proposal.md); the requirements are in [specs/](specs/).

Agreed in the discussion: the taxonomy is a separate domain, not a `kind` of a content type and not a new `entity` in the value tables; the vocabularies are global and are connected through a field; a tree with one parent; the descendants are searched by a walk in PHP; the deletion of a used term is forbidden.

The components described: `docs/content-values.md` (the registry of types, the schema of values), `docs/content-querying.md` (the operators), `docs/content-model.md`, the new `docs/taxonomy.md`.

## Goals / Non-Goals

**Goals:**
- A minimal new code in the core of the content: one type of field and two small extension points (the check of the `settings` of a field; the expansion of the values of a filter).
- All invariants of the taxonomy act regardless of the way of writing (code, import, the future admin): in the models and at the level of the DB, not in the controllers.

**Non-Goals:**
- The admin interface, the choice of a term in the form of a record, public routes of categories (`add-taxonomy-admin` and beyond).
- The import-export of terms between environments (the mechanism itself; the `uuid` is laid for it).
- Custom (user) fields on terms: the set of attributes of a term is fixed.
- A materialized path or a closure table for the tree.

## Decisions

### 1. Separate tables, not content nodes

```
taxonomy_vocabularies(id, key UNIQUE, name, description, settings JSON, timestamps)
taxonomy_terms(id, uuid UNIQUE,
               vocabulary_key FK → taxonomy_vocabularies.key RESTRICT,
               parent_uuid FK self → taxonomy_terms.uuid RESTRICT null,
               slug, weight, image null, timestamps;
               UNIQUE(vocabulary_key, slug), INDEX(parent_uuid, weight))
taxonomy_term_translations(id, term_uuid FK → taxonomy_terms.uuid CASCADE, lang, name, description null;
                           UNIQUE(term_uuid, lang))
```

**Identity.** A term is identified by `uuid` (UUID v7, `CHAR(36)`, as `content_nodes.uuid`): it is set explicitly (import) or generated, and it is immutable. A vocabulary has no `uuid`: its identifier is the immutable unique `key` (`VARCHAR(64)`, the format `^[a-z][a-z0-9_]*$`), as in `content_types`, where other tables reference `key`, not `id`. The relations: term → vocabulary by `vocabulary_key`, term → parent by `parent_uuid`, translation → term by `term_uuid`; the field `term` references the vocabulary by `key` (`settings.vocabulary`), the value of the field references the term by `uuid`. The `id` remains the primary key (needed for `media_relations.owner_id`, which is `unsigned bigint`) and does not go outside. Precedents: the FK by `key` — `content_types.parent_type`, the FK by `uuid` — `media_relations.file_uuid`. The indexes fit within the InnoDB limit: `slug` (190 characters, ≈ 760 bytes) + `vocabulary_key` (64 characters, ≈ 256 bytes) ≪ 3072.

A term is a light entity without `published_at`, author or SEO. The set of its attributes is fixed: `slug`, `weight`, `image`, translations (`name`, `description`). Its `slug` has its own space within the vocabulary (the nodes have a `slug` globally unique; the URL of the public pages of categories will contain the vocabulary, so the pair (vocabulary, slug) uniquely finds the term), the `slug` is unique without regard to the case (nocase on SQLite, the MySQL collation by default — as `content_nodes.slug`) and is not translated. `lang` has a binary collation, as in the tables of values. The slug is flat within the vocabulary, not within the parent: same-named terms under different parents get the suffix `-2`.

**The image** — one per term, the column `image` (the value `{uuid}.{extension}`, the profile `image`, the rule `FileValue`), not in the translations. The accounting of usage — through `FileRelations::sync('taxonomy_term', $term->id, 'image', 'default', …)` in one transaction with the writing of the term (`TermWriter`); the owner is registered in `config/files.php` by the class `App\Files\Owners\TermOwner` (the label — the name of the term in `default`; the link appears in `add-taxonomy-admin`, until then — without a link). The hook `deleting` of a term calls `FileRelations::forgetOwner('taxonomy_term', $id)`.

**Alternatives:** a term as a `ContentNode` with a new `kind` — gives the fields/translations for free, but drags the service attributes, the shared (global) namespace of slugs, `parent_id`/`weight` in all nodes and a new kind of type in the admin; a new value `entity = 'term'` in all value tables — a migration of the whole EAV schema. Both rejected: the custom fields on terms are not needed (the owner's decision), the fixed columns and translations are cheaper and give the tree in one query.

### 2. Invariants — in the models (`saving`/`deleting`), atomic writes — in the service

Following `ContentType`/`ContentField`: the models `Vocabulary`, `Term`, `TermTranslation` in `app/Models`, the validation in the events `saving` and `deleting` with `ValidationException`. It checks: the format and immutability of `key`; a parent from the same vocabulary; the absence of cycles (walking up the ancestors in PHP); the depth (the depth of the parent + 1 + the height of the subtree ≤ `max_depth`); the immutability of the vocabulary of a term; `max_depth` is an integer ≥ 1 and not below the current height of the tree; the deletion of a term (children, usages), the deletion of a vocabulary (terms, references of fields). The service `App\Taxonomy\TermWriter` creates/updates a term together with the translations in one transaction (the languages through `ContentLanguages::assertWritable`, the name `default` is required) and generates the `slug` from the name `default` (the transliteration as in `SlugGenerator`, the suffix `-2`, `-3` within the vocabulary), if the `slug` is not set. `SlugGenerator::fromTitle` looks only in `content_nodes`, so it does not fit directly; the generation for terms is written next to it in `App\Taxonomy`, without changing `SlugGenerator`.

The cascades of the DB do not run the events of the models, therefore: the FK `parent_uuid` and `vocabulary_key` are `RESTRICT` (the DB does not let a parent or a vocabulary with terms be deleted), the translations cascade together with the term.

### 3. The type of field `term`: the value in `content_values_string`

`App\Content\FieldTypes\TermType` (`AbstractFieldType`): `key() = 'term'`, `table() = 'content_values_string'` (as `image`/`file`/`youtube`), `cast`/`prepare` → `string` (the canonical lower case of `uuid`), `operators() = ['eq', 'in']`. There is no new value table and no migration: the field belongs to the type of content, the values — to the records (`entity = node`); there are no values of the type in the ordinary fields (`SystemFields::owns`), so the shared contract of the owner adds nothing here. Reading returns a `string` — the `uuid` of the term. The names of the terms in the language and the tree for the display — through the service of the tree (decision 5), not through `ContentValueReader`.

The validation rules of the value (`rules($field)`): a string of the UUID format + a check that the term exists and belongs to the vocabulary `settings.vocabulary` of the field (`taxonomy_terms.uuid` with `vocabulary_key = settings.vocabulary`). The filters check the value with an empty `ContentField` (see `ContentNodeQuery::filterValue`), so for an empty field the rule reduces to the UUID format + `exists:taxonomy_terms,uuid` (as `AbstractFileFieldType` distinguishes the field and the filter).

**Integrity without an FK.** There is no foreign key to `taxonomy_terms` in `content_values_string` (the table is shared with the string types). Therefore: (a) `TermUsage` counts the usages by a query to `content_values_string` with a join to `content_fields` (`type = 'term'`, `settings->vocabulary` of the vocabulary of the term) by (`content_type`, `field_key`); (b) the deletion of a term is performed in a transaction with `lockForUpdate` on the row of the term, and `TermType` implements a new optional capability `App\Content\FieldTypes\LocksReferences` (`lockReferences(array $prepared): void`), which `ContentValueWriter::replace` calls inside the transaction of the writing of a record (next to `TracksFiles`): `lockForShare` on the referenced terms. So the deletion and the writing of the value are serialized (on SQLite the locking is a no-op). The precedent — the deletion of a file under locking in the media library.

**Alternatives:** a separate `content_values_term` with an FK on `taxonomy_terms` — a hard barrier in the DB, but a migration in the final form of the contract (CHECK, a functional index, the branches of MySQL/SQLite) for the sake of one FK; the slug of a term as the value — breaks when the slug changes. Both rejected.

### 4. The check of the `settings` of a field type

A new optional interface capability `App\Content\FieldTypes\ValidatesSettings` (next to `TracksFiles`, `ComposedValue`): `settingsErrors(array $settings, ?array $original, bool $hasValues): array<string, string>`. `ContentField::assertValid` calls it for the type that implements it, and turns the errors into a `ValidationException` under the key `settings.<key>`. `TermType` requires an existing `vocabulary` and forbids its change if the field has values (`ContentField::hasStoredValues`). The other types do not change.

### 5. The service of the tree and the descendants (a BFS in PHP)

`App\Taxonomy\TermTree`: `descendantUuids(array $uuids): list<string>` — a breadth-first walk, one query `WHERE parent_uuid IN (...)` per level, with a set of the visited (protection against duplicates and, in case of damaged data, against looping); `height(Term)` for the check of the depth; `tree(Vocabulary, lang)` — the tree of terms for the display (siblings by `weight`, `id`; the name in the language without fallback — `null` if there is no translation). The levels are few (the depth is limited by `max_depth`), so the walk is cheaper than an extra `path` column, which would have to be recomputed when a branch is moved. If the trees grow to tens of thousands of terms, the transition to a materialized path will not change the specification.

### 6. The filter by a term covers the descendants

A new optional capability `App\Content\FieldTypes\ExpandsFilterValues`: `expandFilterValues(array $prepared): array`. In `ContentNodeQuery::applyFilters`, after `filterValue`, for a type with this capability the value is expanded, and the operator `eq` is converted into `in` over the expanded set — the rest (`EXISTS` over the table of values, the multiple fields, the AND between the filters) does not change. The descendants «always by default» — there is no separate flag (agreed); a query «only directly» will add a separate operator, if it is needed.

### 7. The type `term` does not appear in the admin until a separate change

`ContentFieldController` gives the form `typeOptions = registry->keys()`. Without an interface of the choice of a vocabulary and the input of values in the form of a record, the type in the dropdown would create an unfinished field. Therefore `config/content.php` gets `admin_hidden_field_types => ['term']`, and `ContentFieldController` removes these keys from `typeOptions`. `add-taxonomy-admin` removes the entry from the configuration. The direct creation of a field `term` by code/request remains possible and is checked by the model.

## Risks / Trade-offs

- [`whereIn` with thousands of ids for a large branch] → the trees are limited by `max_depth`; the walk by levels; if a branch becomes too large, switch to a materialized path.
- [Race: a term is used between the check in the model and the deletion] → `lockForUpdate` of the term at deletion against `lockForShare` at the writing of the value (`LocksReferences`); there is no hard FK in the DB on the values. The deletion of the rows of a term with raw SQL leaves a «dangling» `uuid` in the values — documented in `docs/taxonomy.md`.
- [`whereIn` by `uuid` is wider than by `id`] → the trees are limited by `max_depth`; the difference is insignificant for the expected sizes of vocabularies.
- [A decrease of `max_depth` does not check parallel inserts] → the risk is acceptable for administrative operations; the check at every saving of a term complements it.
- [A changed slug of a term breaks external links to it] → the identifier in the values is the `uuid`, not the `slug`; the public routes of categories are a separate future change.
- [Direct deletion of rows by SQL bypasses the models] → document in `docs/taxonomy.md`: delete the terms and the vocabularies through the model; the DB keeps the `RESTRICT` barriers.
- [A new field created by code before the admin will have no input in the form of a record] → the field is not offered in the admin (decision 7); `add-taxonomy-admin` closes the gap.

## Migration Plan

1. The migration of the taxonomy tables (`vocabularies` → `terms` → `translations`).
2. Registration of the type in `config/content.php` and of the owner `taxonomy_term` in `config/files.php`.
Rollback: `down()` drops the taxonomy tables in the reverse order; the existing data is not affected (the values `term` lie in `content_values_string`: before the rollback of the migration the fields `term` must be deleted). The full PHP test set runs once for this change (migrations/DB schema, configuration).

## Open Questions

- Whether to call the admin section «Таксономія» and whether vocabularies need a translated name (now the name of a vocabulary is not translated, as the name of a type) — decided in `add-taxonomy-admin`, does not change the schema.

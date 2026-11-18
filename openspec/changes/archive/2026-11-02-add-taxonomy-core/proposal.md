# Proposal

## Why

Content needs to be categorized: headings, tags, reference books shared by several content types. Right now the model has only scalar fields, files and `youtube`; there is no field that references other entities, so the only way of categorization is free text, which cannot be translated, arranged into a tree, or filtered by hierarchy.

## What Changes

- A separate domain of the taxonomy, not mixed with the content types: vocabularies (`taxonomy_vocabularies`) and terms (`taxonomy_terms`) with a tree (one parent), the depth of the tree in the `settings` of the vocabulary and the weight for the order of the sibling terms. The terms have an immutable `uuid` (UUID v7, as in the records of content), the vocabulary — an immutable unique `key`; **the relations between the tables of the taxonomy go by these identifiers** (`vocabulary_key`, `parent_uuid`, `term_uuid`), and the value of the field `term` goes by the `uuid` of the term; the numeric `id` remains only the primary key (needed, in particular, for `media_relations.owner_id`).
- The image of a term: one per term (the column `image`, the value `{uuid}.{extension}` of a file of the profile `image`), with accounting of usage in the media library (`media_relations`, the owner `taxonomy_term`).
- The translations of terms (the name, the description) in a separate table `taxonomy_term_translations` modeled on the languages of the content: `default` plus the enabled languages, without fallback. The set of attributes of a term is fixed (system): there are no custom fields on terms.
- Non-trivial rules of integrity: the unique immutable `key` of a vocabulary, the unique `slug` of a term within the vocabulary, the prohibition of cycles and of exceeding `max_depth`, the prohibition of deleting a term with children or with usages, the prohibition of deleting a vocabulary with terms or with the fields that reference it.
- A new type of field `term`: the value is the `uuid` of a term, stored in the existing `content_values_string` (no separate table of values and no migration, as for `image`/`file`); the vocabulary is set in `settings.vocabulary` of the field (the key of the vocabulary). The field belongs to the type of content, the values — to the records; the field can be multiple.
- The selection of records by the field `term`: the operators `eq` and `in` always cover the chosen term **and all its descendants** (a walk of the tree in PHP, then `whereIn` by `uuid`).
- The type `term` is not offered yet in the form of creating a field in the admin (its interface and the input of values in the form of a record — a separate change `add-taxonomy-admin`).

Not included in this change: the admin section «Таксономія», the choice of terms in the form of a record, the public pages/routes of categories, the import-export of terms between environments.

## Capabilities

### New Capabilities
- `taxonomy`: vocabularies and terms, the tree with one parent and the restriction of depth, the translations of terms, the rules of uniqueness and deletion, the service of the tree (the descendants of a term).

### Modified Capabilities
- `content-values`: the new type of field `term` (the value in `content_values_string`, the validation rules by the vocabulary, the reading of the `uuid` of the term), the check of `settings.vocabulary` on saving the field.
- `content-querying`: the operators `eq`/`in` for the field `term` with the expansion of the descendants.

## Impact

- Code: a new migration (3 tables of the taxonomy; there is no migration of the table of values), the models `Vocabulary`, `Term` (+ translations), the class of the type `App\Content\FieldTypes\TermType`, the services of the tree and of the writing, the media owner `App\Files\Owners\TermOwner` (a line in `config/files.php`), small extension points in `ContentNodeQuery` (the expansion of the values of the filter), `ContentField` (the check of the `settings` of the type) and `ContentValueWriter` (locking of the references in the transaction of the writing), a line in `config/content.php`; hiding of `term` from `typeOptions` in `ContentFieldController` until the interface appears.
- Documentation (`docs/`): a new `docs/taxonomy.md`; updates of `docs/content-values.md` (the registry of types and the schema), `docs/content-querying.md` (the operators by types), `docs/content-model.md` (a reference to the taxonomy), `docs/README.md` (the index).
- Specifications: the new `taxonomy`, the deltas of `content-values` and `content-querying`.
- Tests: PHP Feature/Unit for the taxonomy, the type `term` and the selection; e2e are not needed (the change without an interface).

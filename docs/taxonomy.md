# Taxonomy

Taxonomy is a domain for categorization, separate from content types: **vocabularies** (sections, tags, reference lists) with **terms** that form a tree with one parent. A node field references a term by the type `term` ([content-values.md](content-values.md#term-term)); in the admin panel it can be added only to content types (not to page and block types). The taxonomy admin section is described in [admin-taxonomy.md](admin-taxonomy.md).

## Tables

| Table / model | Purpose |
| --- | --- |
| `taxonomy_vocabularies` / `Vocabulary` | Vocabulary: `key` (unique, immutable identifier), `name`, `description`, `settings`. |
| `taxonomy_terms` / `Term` | Term: `uuid`, `vocabulary_key`, `parent_uuid`, `slug`, `weight`, `image`. |
| `taxonomy_term_translations` / `TermTranslation` | The name and description of a term in a language: `term_uuid`, `lang`, `name` (nullable), `description` (nullable). |

The set of term attributes is **fixed**: there are no custom fields on terms. Vocabularies are global: they do not belong to a content type, and the same vocabulary can be used by fields of different types.

## Identification and relations

- Only a term has a `uuid`: UUID v7, set explicitly (import) or generated, **immutable**. A vocabulary has no `uuid`: its identifier is the unique immutable `key`.
- Terms, translations, and values of a `term` field are linked by `uuid` (`parent_uuid`, `term_uuid`), and a term with its vocabulary — by `vocabulary_key` (a foreign key to `taxonomy_vocabularies.key`); the numeric `id` is only a primary key (needed by `media_relations.owner_id`) and does not go outside. `Vocabulary::terms()` and `Term::vocabulary()` — by `key`; `Term::parent()/children()/translations()` — by `uuid`.
- The `key` of a vocabulary: `^[a-z][a-z0-9_]*$`, up to 64 characters, unique and immutable. Fields reference a vocabulary by `key` (`settings.vocabulary`).
- The `slug` of a term: `^[a-z0-9]+(-[a-z0-9]+)*$`, up to 190 characters, not translated, unique **within the vocabulary** (not the parent), case-insensitive; it repeats in different vocabularies.
- All foreign keys are `RESTRICT` (except translations: `CASCADE` with the term). The DB will not allow deleting a parent or a vocabulary with terms.

## Tree and depth

A term has at most one parent from the same vocabulary; cycles and self-references are rejected (`parent_uuid`). A vocabulary does not change after a term is created. Sibling terms are ordered by `weight`, then by `id`.

The vocabulary's `settings.max_depth` is an integer ≥ 1 (a root has depth 1); without it the depth is not limited. The check is the depth of the parent + 1 + the height of the term's subtree (moving a branch takes the whole branch into account); the `max_depth` cannot be reduced below the actual height of the tree. The error of a new term is under the key `parent_uuid`; changes of the limits — `settings.max_depth`.

The invariants apply in the models (`saving`/`deleting`, `ValidationException`) regardless of how the record is written.

## Translations

`default` plus the content languages by the rules of [content-languages.md](content-languages.md) (`ContentLanguages::assertWritable`). The name in `default` is required. In any other language the name and the description are each optional: a translation may hold only one of them (the other reads as `null`) and a row that holds neither does not exist. **There is no fallback to `default` by default**: a missing translation is `null`. The public site reads with `TermTree::tree($vocabulary, $lang, fallback: true)` and `PresentContext::term()`: a blank name or description is replaced by the `default` one, each independently of the other.

One language is saved on its own by `TermWriter::saveTranslation($term, $lang, ['name' => ..., 'description' => ...])`: only that language changes, a value equal to the `default` value of the same field is not stored, an empty value removes its field, and the row is deleted when nothing remains. `clearTranslation($term, $lang)` deletes the row. Both reject `default` and unavailable languages with a `ValidationException` under `lang`. A term is considered *translated* into a language when such a row exists; the admin form is described in [admin-translation-mode.md](admin-translation-mode.md).

## Images

One optional image per term: the column `image`, the value `{uuid}.{extension}` of a file of the `image` profile, the rule `FileValue`. It is not translated. The usage is tracked in `media_relations` (owner `taxonomy_term`, role `image`, language `default`): [media-library.md](media-library.md#owner-registry). Replacing, clearing, and deleting a term remove the relation; the file remains in the media library.

## Services

All in `Webtack\Ecms\Taxonomy`.

- **`TermWriter`** — `create(Vocabulary, array)` / `update(Term, array)`: the term, translations, and image in one transaction. Keys: `parent_uuid`, `slug`, `weight`, `image` (empty clears it), `translations` (`lang => ['name', 'description']`; a non-base translation is deleted when both its name and description are empty). Without `slug` it is generated from the `default` name (transliteration, suffix `-2`, `-3` within the vocabulary).
- **`TermTree`** — `descendantUuids(array $uuids)` (terms and all descendants without duplicates, a breadth-first traversal: one query per level, protection against looping), `height(Term)`, `vocabularyHeight(Vocabulary)`, `ancestorUuids(Term)`, `tree(Vocabulary, lang, fallback = false)` (a nested tree by `weight`, `id`; the name and description in the language, the `default` ones for a blank text only with `fallback`).
- **`TermUsage`** — `forTerm(Term)`: where the term is used (the value's owner, the content type, the field key, the count) from `content_values_string`, joined to the fields of type `term` with the term's vocabulary; `forVocabulary(Vocabulary)` — the same for all terms of the vocabulary in one query (the term's `uuid` → places), for the admin table.

```php
$term = app(TermWriter::class)->create($vocabulary, [
    'parent_uuid' => $clothes->uuid,
    'translations' => ['default' => ['name' => 'Jackets'], 'en' => ['name' => 'Jackets']],
]); // slug "jackets"
app(TermTree::class)->descendantUuids([$clothes->uuid]);
app(TermUsage::class)->forTerm($term); // [['owner' => 'node', 'content_type' => 'article', 'field_key' => 'tags', 'count' => 2]]
```

## Deletion

- A **term** is not deleted if it has children or is used by field values (`ValidationException` under the key `uuid`). The deletion is done in a transaction with `lockForUpdate` on the term's row; writing a value locks the referenced terms with `lockForShare` ([`LocksReferences`](content-values.md#term-term)), so deletion and writing are serialized (SQLite has no locking). The values are not deleted by the cascade. Deleting removes the translations and the image relation.
- A **vocabulary** is not deleted if it has terms or if the `settings.vocabulary` of at least one `term` field references it (the key `key`).

Terms and vocabularies should be deleted **through the model**. Raw SQL bypasses the usage checks: a "dangling" `uuid` will remain in `content_values_string` (there is no foreign key to terms there); the DB keeps only the `RESTRICT` barriers of the tree.

## Term filter

Querying nodes by a `term` field covers the selected term and all its descendants: [content-querying.md](content-querying.md#operators-by-type).

```php
new NodeQueryParams(filters: [['field' => 'category', 'operator' => 'eq', 'value' => $clothes->uuid]]);
// nodes with category = "Clothes", "Jackets", "Winter", ...
```

The owner `taxonomy_term` is described by `Webtack\Ecms\Files\Owners\TermOwner` (the label is the `default` name, the link — to the term form in the [admin section](admin-taxonomy.md)).

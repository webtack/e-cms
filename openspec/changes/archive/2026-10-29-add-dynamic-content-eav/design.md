# Design

## Context

Laravel 13 / PHP 8.3, MySQL in Docker (`ecms_db`); the tests run on SQLite `:memory:` (see `phpunit.xml`). Currently `app/Models` has only `User`. The motivation and scope are in `proposal.md`. The code is written in English with docblocks, the documentation in `docs/` — in Ukrainian.

## Goals / Non-Goals

**Goals:**
- The structure of content is data; new content types/fields do not require migrations.
- A new value type = one class + one table migration + a row in the registry configuration; the core does not change.
- Translations are ordinary value rows with a different `lang`, suitable for filtering and sorting in the DB.
- Selection by dynamic fields is performed in the DB (filter, search, sorting, pagination), not in PHP.

**Non-Goals:**
- Admin UI, HTTP controllers/API, a block builder, migration of the old system.
- A node hierarchy (a page tree), multi-segment public paths, redirects on slug change, relations between nodes, files/images, versioning, access rights.
- A `default` fallback for a missing translation, translation of field/type names, a UI for managing languages, the frontend pages themselves (only PHP access to the language settings is provided), changing the language of the admin interface (`admin-i18n` is not affected).
- Full-text search (only `LIKE`).

## Decisions

### 1. Table schema

- `content_types`: `id` (PK), `key` `VARCHAR(64)` (unique, immutable, format `^[a-z][a-z0-9_]*$`; relations to the type refer to it), `name` `VARCHAR(255)`, `description` `TEXT` null (an arbitrary description of the type for the admin; does not affect behaviour), `parent_type` `VARCHAR(64)` null (FK → `content_types.key`, self-FK, `restrictOnDelete`), `kind` `VARCHAR(32)` default `'content'`, `settings` JSON null, timestamps.
- `content_fields`: `id`, `content_type` `VARCHAR(64)` (FK → `content_types.key`, cascade), `key` `VARCHAR(64)` (immutable, format `^[a-z][a-z0-9_]*$`, as for the type key), `name` `VARCHAR(255)`, `type` `VARCHAR(32)` (a key from the registry), `is_multiple`, `is_required`, `is_translatable`, `is_system` (all boolean, default `false`), `weight` unsigned small int default 0, `settings` JSON null, timestamps; unique (`content_type`, `key`).
- `content_nodes`: `id`, `uuid` `CHAR(36)` (unique), `content_type` `VARCHAR(64)` (FK → `content_types.key`, cascade), `slug` `VARCHAR(190)` NOT NULL (unique, with a case-insensitive collation), timestamps; index (`content_type`, `id`); unique (`id`, `content_type`) — the target of the composite FK of the value tables (see below).
- The relations to a type (`parent_type`, `content_fields.content_type`, `content_nodes.content_type`) refer to `content_types.key`, not to `id`: `key` is unique and immutable, so the FK is stable between environments and needs no `id` map during import; `ON UPDATE` is not needed. The FK columns have the same collation and length as `content_types.key`. The value tables refer to a field by the pair (`content_type`, `field_key`) → `content_fields(content_type, key)` and to a node by `id` and type (see below).
- All three tables and `app_languages` are created by one migration per table with all columns at once (`kind`, `parent_type`, `slug`, etc.): the tables are new, so separate "add-on" migrations are not needed. "Node" does not mean a tree of nodes: the hierarchy exists only among types (`content_types.parent_type`), and there is no parent relation of nodes (see Non-Goals).
- `app_languages` (a common system table of languages; the `ContentLanguages` service applies them to content): `id`, `code` `VARCHAR(16)` (unique, binary collation `utf8mb4_bin`; only the normalized form, see §3), `name` `VARCHAR(255)`, `is_active` default `false`, `weight` unsigned small int default 0, timestamps.
- `app_settings`: `key` `VARCHAR(191)` (PK), `value` JSON NOT NULL (JSON `null` is a valid value), timestamps. A common storage for the core and any modules; keys `module.name`. The switch of multilingualism — `content.multilingual` (absent = `false`).
- Value tables `content_values_{string,text,integer,decimal,boolean,date,datetime}`: `id`, `content_node_id` (→ `content_nodes.id`), `content_node_uuid` `CHAR(36)` (the `uuid` of the node, for import/export; filled and not changed by the model), `content_type` `VARCHAR(64)` (there is no separate FK: the existence of the type is guaranteed by the composite FK on the field), `field_key` `VARCHAR(64)`, `lang` `VARCHAR(16)` NOT NULL default `'default'` (binary collation `utf8mb4_bin`, a normalized code), `weight` unsigned small int default 0, `value` (the corresponding type), `options` JSON null. There is no `field_id` column: the field is defined by the pair (`content_type`, `field_key`). Foreign keys: composite (`content_type`, `field_key`) → `content_fields(content_type, key)` (cascade: deleting a field deletes its values); composite (`content_node_id`, `content_type`) → `content_nodes(id, content_type)` (cascade) — it guarantees at the DB level that the type in the value matches the type of the node, and therefore the field belongs to the type of the node. Unique index (`content_node_id`, `content_type`, `field_key`, `lang`, `weight`).

The types of the `value` column: string — `VARCHAR(512)`, text — `LONGTEXT`, integer — `BIGINT`, decimal — `DECIMAL(20,6)`, boolean — `TINYINT(1)` (0/1), date — `DATE`, datetime — `DATETIME`; all NOT NULL (no value = no row).

**Why separate tables, not one with `value_*` columns or JSON:** each type has its own index and correct comparison/sorting at the DB level; a JSON column does not give typed indexes. The alternative "one table with a string value" was rejected because of incorrect numeric/date sorting.

**Translations through `lang` in the value row, not in JSON and not in separate tables:** the alternative "JSON `{lang: value}`" was rejected: it breaks the typed index, filtering and sorting by language. The alternative "a separate translations table" is redundant, because the values are already EAV. `lang = 'default'` — a value without a language / the base language; other codes — translated. `options` remains for additional settings of the value, not for translations.

**The value key is the pair (`content_type`, `field_key`), not a numeric `field_id`:** the field `key` and the type are immutable, so the relation is the same in all environments and needs no `id` map during import, and selection does not look up `field_id` with a separate query. The cost: up to 128 bytes and wider indexes per value row (instead of one number), plus `content_node_uuid` (36 bytes) per row. The type in the value duplicates `content_nodes.content_type`, so consistency is ensured by the composite FK on `content_nodes(id, content_type)`.

### 2. The system field `title`

`ContentType::created` (in the same transaction as the creation of the type) inserts the field `title`: type `string`, `is_required = true`, `is_multiple = false`, `is_translatable = true`, `is_system = true`, `weight = 0`. The model `ContentField` rejects `deleting` for `is_system` (except the cascade from deleting the type, which is performed at the DB level by the FK and does not fire model events) and rejects `updating` of the changes of `key`, `type`, `is_multiple`, `is_required`, `is_system` for system fields. The method `ContentType::create` via Eloquent is the only path of creation, so `title` is guaranteed to appear; a test checks this also through the factory.

**Alternative:** require creating `title` manually — rejected by the product requirement. **Alternative:** a `title` column in `content_nodes` — rejected: `title` must be translatable and filtered/sorted in the same way as the other fields.

### 2a. The kind of a content type (`kind`)

Pages and blocks are content types with a different `kind`, not separate tables: they share fields, values, translations and selection. The valid values are taken from `config/content.php` (`'kinds' => ['content', 'page', 'block']`), so adding a kind later = a row in the configuration. `ContentType` validates `kind` against the configuration and forbids its change after creation (`updating`), regardless of the presence of nodes. The scope `ContentType::ofKind($kind)` for listing types; `ContentNodeQuery` remains bound to one type and does not know about the kind; the search of a node by slug is a separate method `ContentNode::findBySlug`. The behaviour specific to kinds (a page tree, `slug`, block placement, block nesting, a node-reference field, publication) is deliberately moved to future changes: they must extend the model with helper tables 1:1 to `content_nodes` or with new field types, without changing the core.

**Alternatives:** separate `pages`/`blocks` tables — rejected (duplication of EAV); without a discriminator — rejected (different invariants and separate lists in the admin); a `section` kind — rejected (a section is a page with child pages, which is solved by a tree later).

### 2b. The hierarchy of types

`parent_type` (the `key` of the parent type) is an organizational tree of types (groups of types in the navigation of the admin, etc.); fields and settings are not inherited (simpler and without unexpected consequences when the parent changes; inheritance is a separate change if needed). `ContentType` on save checks: the parent exists, `kind` matches, there are no cycles (walking up the chain of `parent_type`); the FK `restrictOnDelete` does not allow deleting a type with child types. Deleting a type without children cascades as before. Scopes `ContentType::childrenOf`/`roots`.

### 2c. The slug of a node

The slug is a column `content_nodes.slug`, not an EAV field: it is needed for uniqueness at the DB level, for O(1) search, and it is not translated. Uniqueness is shared for all kinds by one index (the collation `utf8mb4_0900_ai_ci` / `NOCASE` in SQLite; the values are stored in lowercase anyway).

Creating a node goes through `ContentNodeCreator::create(ContentType, array $values, ?string $slug = null, string $lang = 'default')` — the only path, because `slug` is NOT NULL, and `title` is in the values: in a transaction the values are validated (`ContentValueWriter` in the `$creating` mode), the slug is determined, the node is inserted, and the values are written. The model `ContentNode` additionally checks on `saving` the format (`^[a-z0-9]+(-[a-z0-9]+)*$`, ≤190), the prefix `block-` (mandatory for `block`; not restricted for other kinds), and does not allow changing the slug implicitly (a change only through assignment).

`SlugGenerator`: for `content`/`page` — `Str::slug($title, '-', 'uk')` (transliteration); an empty result → `node`; no prefix is added, a slug with a leading `block-` is allowed; in case of a match it looks for a free suffix `-2`, `-3`, … (one query `LIKE base%`, not a loop of queries). For `block` — `block-` + 8 characters `a–z` (`Str::random` with a restricted alphabet), a retry on a match (several attempts, then an error). An explicit slug is never changed by the generator.

**Decision (confirmed by the owner) about `block-[8 [a-z-]]`:** the automatic part is 8 lowercase Latin letters without hyphens (a hyphen in the random part would give leading/trailing/double hyphens, which contradicts the slug format); hyphens are allowed in manual slugs. **Races:** the unique index is the final arbiter; on a violation the generator retries once, an explicit slug returns a validation error. A public path as a hierarchy (`parent/child`) and routing are future changes; for now a slug is one segment.

**Alternatives:** a slug as an EAV field — rejected (no global uniqueness and no cheap search); separate uniqueness spaces per kind — rejected by the product requirement (one identifier for all three kinds).

### 2d. Stable `uuid` of nodes and the identity of types and fields

`content_nodes` has a column `uuid` (`CHAR(36)`, NOT NULL, unique). This is an external stable identifier: `id` differs between environments, the slug of a node may change, while the `uuid` does not. Types and fields have no `uuid`. The `key` of a type is unique in the whole table, immutable (`updating` rejects a change), and matches the format `^[a-z][a-z0-9_]*$`, so it is itself a stable identifier, and the relations to the type refer to it (see §1). The `key` of a field is also immutable, and unique within the type, so the identity of a field between environments is the pair (`content_type`, `key`) — the same pair by which values refer to the field. The `uuid` is intended for matching nodes during import/export (a repeated import updates the node with the same `uuid` rather than duplicating it).

It is generated automatically on creation (UUID v7 via `HasUuids`/`Str::uuid7`; the ordered format fragments the index less). An explicitly given `uuid` (import) is kept as is, if it is valid and free. After creation the `uuid` does not change (`updating` rejects a change). `ContentNode::findByUuid` returns a node or `null`; `ContentType::findByKey` — a type by key or `null`. The primary keys and the relations of values remain on `id` (queries, JOINs, indexes), because a 36-character key would bloat the indexes of large value tables.

**Configuration relations.** A relation to a type (`parent_type`, `content_fields.content_type`, `content_nodes.content_type`) keeps the type `key` as the FK, so no separate `*_uuid`/`*_key` columns are needed next to it: in the export file the relation is already expressed by the key. The `content_type` of a field and of a node does not change after creation; `parent_type` may be changed. Values refer to a field by the pair (`content_type`, `field_key`) — also without `id` — and to a node through `content_node_id` together with `content_node_uuid`: the `uuid` of the node is copied into each value row, so that the export of values needs no JOIN, and it remains unchanged (because the `uuid` of the node is immutable).

**Import.** Order: first the types (the parents first by `parent_type`), then fields and nodes, then values. A type is looked up by `key`, a field — by the pair (`content_type`, `key`), a node — by `uuid`: a found one is updated, otherwise it is inserted, and the target DB assigns it its own `id`. For the relation to a type and for the field in a value the `key` from the file is taken as is, without an `id` map. The map `uuid → id` is needed only for nodes: a value in the file refers to a node through `content_node_uuid`, the importer finds the local `content_node_id` and writes both. The `id` from the source environment is not transferred.

**Index and column type.** On `uuid` in `content_nodes` there is one unique index, no composite ones. It serves `findByUuid` and the matching of nodes during import, and it is also the final arbiter of uniqueness at the DB level (like the `slug` index); the check in the model only gives an understandable error. The column type is `CHAR(36)` (`$table->uuid()`): readable, works the same way on SQLite in the tests and on MySQL. UUID v7 is ordered by time, so inserts mostly go to the end of the index.

**Alternatives:** the pair (`*_id`, `*_uuid`/`*_key`) in the configuration relations — rejected (duplicates the data); a composite FK that only protects the pair `id`+`uuid` — rejected (it does not help with transfer), but a composite FK on (`content_nodes.id`, `content_type`) for values is accepted — it protects another invariant: the type of the value matches the type of the node; a `uuid` in `content_types` — rejected (the type `key` is already unique and immutable); a `uuid` as the primary key — rejected (the size of the indexes of the value tables); `BINARY(16)` — rejected (more compact, but it complicates queries, debugging and tests, and the `uuid` does not go into the value tables); a `uuid` in `content_fields` — rejected (the identity of a field is given by the pair (`content_type`, `key`), `key` is immutable); export of nodes by `slug` — rejected (`slug` is changeable). The import/export itself and relations between nodes are separate future changes; here only the identity is laid down.

### 3. Content languages and the multilingual switch

`AppLanguage` (table `app_languages`, a common system one) — ordinary records; a new language is inactive (`is_active = false`) until explicitly activated; `lang` in the value tables remains a string without an FK (because `default` is not in `app_languages`). The service `ContentLanguages` (singleton, depends on `AppSettings`) provides `isMultilingual()`, `active()` (an ordered list of active codes, empty when multilingualism is disabled), `assertReadable($lang)` and `assertWritable($lang)`; the value of the switch is read from `app_settings` through `AppSettings` (the key `content.multilingual`) with a cache within the request, reset on change.

Rules: `default` is always allowed. Another language for writing: multilingualism is enabled + the language exists + `is_active`. For reading/selection: multilingualism is enabled + the language exists (active or not). A violation → `ValidationException`. Switching the switch and deactivating do not change the stored values.

Deleting an `AppLanguage` checks the absence of values of this language in the 7 tables (`exists` on each) and rejects the deletion if they exist. The language code on creation is normalized to the canonical BCP-47 case (the first subtag in lowercase, the 4-letter script subtag capitalized — `Hant`, the 2-letter region in uppercase — `BR`, the rest in lowercase; `PT-br` → `pt-BR`), and only then checked: the format `^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$`, length ≤16 (the column size), not `default`, uniqueness. With the same method `ContentLanguages` normalizes each incoming `lang` before the check, writing, reading and selection, so `pt-br` and `pt-BR` are one language, and only the normalized form is stored in the DB. The columns `app_languages.code` and `lang` have a binary collation (`utf8mb4_bin`), so that the comparison in MySQL matches SQLite in the tests.

**Alternative:** a case-insensitive collation without normalization — rejected: different records of the same code in the DB and a mismatch between MySQL and SQLite.

**Why the DB and not `config/`:** both the languages and the switch must be changeable by the administrator without a deployment. **Why `app_settings` and not a `content_*` table:** the switch is global and does not belong to a content type, and the storage must be shared for future modules; a key-value storage is convenient to extend. **Alternative:** an FK `lang → app_languages.code` — rejected because of `default`. **Independence from the admin:** the service does not interact with `users.locale`, the locale middleware and the `lang/*` of the admin (see `docs/admin-i18n.md`); this is checked by a test.

### 3a. The `app_settings` storage

The service `AppSettings` (`app/Support/AppSettings.php`, singleton): `get(string $key, mixed $default = null)`, `has($key)`, `set($key, $value)` (upsert), `forget($key)`, `prefix(string $prefix): array`. The key is validated by the regular expression `^[a-z0-9_-]+(\.[a-z0-9_-]+)+$` (≥2 segments) and a length ≤191 (the PK size). The value is encoded as JSON, so `null` differs from an absent key (`has`). Reads are cached in process/request memory; `set`/`forget` update the cache (consistency within the request); a cross-process cache (a Cache store) is not introduced — the decision on it is deferred until the storage becomes "hot". No facade/helper is added; modules get the service through the container.

**Alternative:** the package `spatie/laravel-settings` or a table per module — rejected: an extra dependency and a migration per module, whereas the requirement is one simple shared storage. **Convention:** each module uses its own prefix (`content.`, later `seo.`, `mail.`, etc.).

### 4. Indexes

In addition to the unique one (`content_node_id`, `content_type`, `field_key`, `lang`, `weight`) — it serves reading the values of a node and the prefix of the composite FK on the node — an index (`content_type`, `field_key`, `lang`, `value`) (also the prefix of the composite FK on the field) is added for filtering/sorting by field, language and value in the tables string, integer, decimal, boolean, date, datetime. For `text` there is no index on `value` (LONGTEXT), only (`content_type`, `field_key`, `lang`). `LIKE '%x%'` does not use an index — acceptable at this stage.

### 5. The registry of field types

The interface `FieldType` (in `app/Content/FieldTypes/`): `key()`, `table()`, `rules(ContentField): array`, `cast(mixed): mixed` (from the DB to the PHP type), `prepare(mixed): mixed` (into a value for the DB), `operators(): array`, `searchable(): bool`. A common base `AbstractFieldType`. `FieldTypeRegistry` (singleton) is built from `config/content.php` (`'field_types' => [key => Class]`, also `default_lang => 'default'`), rejects unknown keys. `ContentField` on save checks that `type` is in the registry.

The value tables have no separate Eloquent models: the mapping "type → table" is taken from the registry, the queries are built through the Query Builder. **Alternative:** a model per table with a polymorphic relation — rejected, because it adds a class per type and complicates batch queries.

### 6. Writing, validation, reading

`ContentValueWriter::write(ContentNode, array $values, string $lang = 'default', bool $creating = false)`: checks `lang` through `ContentLanguages::assertWritable`, loads the fields of the content type by key, rejects unknown keys and `lang != default` for non-translatable fields, builds the rules through the registry (`field.key` for a non-multiple one, `field.key.*` + `array` for a multiple one), validates, and on an error throws `ValidationException` by the field keys. Then in one transaction for each specified field: delete the rows (`content_node_id`, `field_key`, `lang`; `content_type` is taken from the node and is not accepted from outside) and insert the new set with `weight` = the index; `null`/`[]` only deletes. With `$creating = true` and `lang = default` all required fields (including `title`) are checked; for other languages the requiredness is not checked.

`ContentValueReader::load(iterable $nodes, ?array $fieldKeys, string $lang = 'default')`: groups the fields by table, one query per table with `whereIn(content_node_id)`, `where(content_type)`, `whereIn(field_key)`, `where(lang)`, `orderBy(weight)`, casts the values through `cast`, returns `[node_id => [field_key => value|list|null]]`. There is no substitution with `default` (a decision for simplicity and the same semantics as the selection; the fallback is a separate future change).

The rules of the `decimal` type reject values with more than 6 digits after the decimal point (the column `DECIMAL(20,6)`), so as not to round silently. Decimal in PHP is a string; date/datetime — `CarbonImmutable`; boolean — `bool`; integer — `int`. A change of the `type` of a field with existing values is rejected in `ContentField` (a check of the existence of values in the table of the old type).

### 7. Node selection

`ContentNodeQuery` over `content_nodes`; the input — a `ContentType` and a DTO: `fields`, `lang` (default `default`), `search` + `searchFields`, `filters[] {field, operator, value}`, `sort {field, direction}`, `page`, `perPage`. The field keys are resolved within the type; unknown ones → `ValidationException`; the operator is checked through `FieldType::operators()`.

- Filter: `whereExists` on the value table with `content_type`, `field_key`, `lang` and the condition (hence "at least one value" for multiple fields, AND between filters, without `DISTINCT`).
- Search: a group `OR` with `whereExists ... LIKE` on each text field; `%`/`_` are escaped.
- Sorting: `LEFT JOIN` of the value table of the field with `lang` and `weight = 0` (unambiguous, because sorting by a multiple field is forbidden), `ORDER BY (v.value IS NULL), v.value dir, content_nodes.id`.
- Pagination `LengthAwarePaginator`; after getting the IDs of the page, the values are loaded by `ContentValueReader` only for the visible fields in the given language.

The `settings` of a type is a JSON object; currently the key `list` is used: `{ "list": { "visible": [keys], "search": [keys], "sort": {"field": key, "direction": "asc|desc"} } }` (the configuration of the list, not translations), validated against the fields of the type on save; the query parameters override it.

### 8. Code structure and documentation

`app/Models/{ContentType,ContentField,ContentNode}.php`, `app/Content/FieldTypes/*`, `app/Models/AppLanguage.php`, `app/Content/{ContentLanguages,FieldTypeRegistry,ContentValueWriter,ContentValueReader,ContentNodeQuery}.php`, `config/content.php`, migrations, factories. Documentation: `docs/app-settings.md` (the storage, the key format, the API for modules), `docs/content-model.md` (the entities, the schema, `title`), `docs/content-languages.md` (content languages, the switch, independence from the admin), `docs/content-values.md` (types, the registry, translations, writing/reading, how to add a type), `docs/content-querying.md` (selection, operators, sorting, `settings.list`); the index — `docs/README.md`.

## Risks / Trade-offs

- [Many JOINs/EXISTS with a large number of filters] → an index (`content_type`, `field_key`, `lang`, `value`) + pagination; a limit on the number of filters in the request (configuration, by default 10).
- [`LIKE '%x%'` does not use an index] → acceptable for a start; full-text search is a separate change.
- [Tests on SQLite, production on MySQL: differences in DECIMAL, NULL ordering, collation] → the NULL order is set explicitly; the key selection scenarios are additionally run against MySQL in Docker.
- [A change of a field type with data is impossible] → a deliberate limitation.
- [A race in slug generation] → a unique index + a retry.
- [The transliteration of `Str::slug` depends on the language `uk`] → covered by a test on Cyrillic titles.
- [A change of slug breaks public links] → the slug does not change automatically; redirects are a future change.
- [The kind defines only the classification, not the behaviour] → the logic of pages/blocks is added by separate changes; until then `page`/`block` behave as `content`.
- [A new field has no values for existing nodes] → treated as `null`; the requiredness is checked only when a node is created.
- [Value rows became wider: `content_type`, `field_key`, `content_node_uuid` per row, wider indexes] → a deliberate trade for independence from `id` and a simple export; `content_node_uuid` is kept consistent with the node at the model level (the `uuid` of the node is immutable), the type and the field — through composite FKs.
- [Cascade deletion destroys values] → according to the specification; the protection — at the UI level in the next changes.
- [No fallback to `default` for a missing translation] → the client may request both languages; the fallback is added by a separate change.
- [`lang` in values has no FK to `app_languages`] → integrity is ensured by the service and by the ban on deleting a language with values; direct intervention in the DB is out of scope.
- [The cache of `AppSettings` is only within the request, not cross-process] → a read from the DB on each request; acceptable for a small number of keys.
- [Disabling multilingualism makes translations unavailable, but does not delete them] → deliberate; re-enabling restores access.
- [`VARCHAR(512)` for a short text] → validation `max` ≤ 512.
- [The index (`content_type`, `field_key`, `lang`, `value`) on `string` ≈ 2624 bytes out of the 3072 allowed by InnoDB (utf8mb4)] → do not increase the lengths of `field_key`, `lang` and `value` without re-checking the limit.
- [Deleting a type bypasses the ban on deleting `title` through the FK cascade] → deliberate, covered by a test.

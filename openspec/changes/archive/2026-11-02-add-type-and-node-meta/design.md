# Design

## Context

The motivation and the scope: [proposal.md](proposal.md). The current state from which the approach starts:

- The values of fields lie in `content_values_{string,text,integer,decimal,boolean,date,datetime}` and are bound to the record by a composite FK `(content_node_id, content_type) -> content_nodes(id, content_type)` and to the definition of the field by the FK `(content_type, field_key) -> content_fields(content_type, key)`; uniqueness `(content_node_id, content_type, field_key, lang, weight)` ([content-values.md](../../../docs/content-values.md)).
- The files in the values `image`/`file` lie in `content_values_string` as the `uuid` of the file, and their accounting is kept by the polymorphic `media_relations (owner_type, owner_id, role, lang)`; `FileRelations` accepts an arbitrary `owner_type`, and `MediaOwner` describes the owner for the media library ([media-library.md](../../../docs/media-library.md)). Today there is only the owner `content_node`.
- `ContentValueWriter` and `ContentValueReader` accept a `ContentNode`; all the queries of selection (`ContentNodeQuery`) and `ContentNodeOwner` connect to the values through `content_node_id`, so the rows without a record do not affect them.
- The system field `title` is created by hard-coded code in `ContentType::booted()` (`created`), `ContentField::RESERVED_KEYS` forbids creating only the keys of the service columns ([content-model.md](../../../docs/content-model.md)).
- `ContentNodeForm` edits only the base language, there is no interface of translations of a record. The settings page of a type is `Pages/Admin/ContentTypes/Edit.tsx` with `ContentTypeForm` ([admin-content-types.md](../../../docs/admin-content-types.md)).
- The tests run on SQLite `:memory:`, the application works on MySQL 8.4: the migrations must work on both (as in the existing migration with the collation).

## Goals / Non-Goals

**Goals:**
- One storage of values for both the records and the types; the same `FieldType`, translations, validation and accounting of files.
- The system fields of metadata and section are described in one place in the code; the creation, the backfill, the reserved keys and the definition of the owner are derived from it.
- No changes in the selection, the filters, the sorting and the search of records.

**Non-Goals:**
- The generalization of the storage to arbitrary entities (users, menus): the fields of a type are bound to `content_types`, and a polymorphic owner without an FK would lose the cascade of deleting a record.
- Custom fields at the level of a type, the column `scope` in `content_fields`.
- The public display, `og:` markup, fallback values (`section_title` -> `name`).
- Multilingualism in the interface: a language switch, translation of individual fields, in particular of images (see [Decision 8](#decisions)); the storage is ready for it.
- The interface of translation of records.

## Decisions

**1. The owner of a value in the same tables.** In each `content_values_*`: `content_node_id` and `content_node_uuid` become nullable, `entity` is added (`'node'` by default for the existing rows, `'type'` for the rows of the type). A row of the type: `content_node_id IS NULL`, `content_type` = the key of the type. The composite FK to `content_nodes` is not checked when `NULL` (MATCH SIMPLE), the FK to `content_fields` remains and acts for both owners. *Alternatives:* (a) mirror tables `content_type_values_*`, rejected because of the duplication of the writer/reader and the queries; (b) polymorphic `entity_type`/`entity_id`/`entity_uuid` instead of the link to the record, rejected, because the FK and the cascade of deleting a record would be lost, and `content_type` is simultaneously the anchor of the definition of the field and cannot become polymorphic; (c) a singleton record «index» of the type, rejected, because it breaks `nodes_count`, the uniqueness of the slug and the lists.

**2. Integrity: CHECK and a functional unique index.** `CHECK ((entity = 'node' AND content_node_id IS NOT NULL AND content_node_uuid IS NOT NULL) OR (entity = 'type' AND content_node_id IS NULL AND content_node_uuid IS NULL))`. The existing unique with `NULL` does not limit the rows of the type, therefore `UNIQUE ((COALESCE(content_node_id, 0)), content_type, field_key, lang, weight)` is added (a functional index; MySQL ≥ 8.0.13, SQLite supports an index on an expression). The Blueprint can neither CHECK nor expressions, so the migration uses raw SQL with a branch by the driver, as the existing migration with the collation. The existing unique remains (for the records it is effective, and the new one strengthens it). MySQL 8.4 in `docker-compose.yml` supports both features. *Alternative:* leave only `entity` without CHECK, rejected, because the table would allow contradictory rows.

**3. The values of the type do not get into the selection of records.** The queries `ContentNodeQuery` and `ContentNodeOwner` connect through `content_node_id`, so there are no changes there (it is checked by a test). Two places without a binding to a record change deliberately: `ContentField::hasStoredValues` stays for both owners (a field with values of the type cannot be deleted), and `ContentFieldController::valuedKeys` gets `entity = 'node'`. The rule for the future code: a query «all values of a field» must explicitly choose the owner.

**4. The owner in the writer and the reader — a small object.** An owner value object is introduced with two named constructors (a record, a type); it gives `entity`, `content_node_id`, `content_node_uuid`, `content_type`, `owner_type`/`owner_id` for `FileRelations` and the list of suitable fields. `ContentValueWriter::write/validate` and `ContentValueReader::load` accept a record (as now) or an owner-type; `replace()` builds the rows from the object. The public signatures for the records remain compatible. For the type the reader reads `entity = 'type' AND content_type = ?` in the required language. *Alternative:* a separate `ContentTypeValueWriter`, rejected because of the duplication.

**5. The registry of system fields in the code.** One class (next to `FieldTypeRegistry`) contains for each system field: the key, the value type, the name, the kinds of types, the owners (`node`, `type`), the translatability, the requiredness and the weight. It:
- creates the fields together with the type (replaces the hard-coded `title` in `ContentType::booted()`; everything is executed in the same transaction of `save()`);
- gives the list of keys for the `RESERVED_KEYS` checks (`StoreContentFieldRequest` and `ContentField::assertValid()` reject a new non-system field with the key of any system field; the creation of the system row itself bypasses this rule, because `is_system = true`);
- answers whether a field belongs to the owner (`title` and the non-system ones only to the records).
There is no column `scope`: the scope is needed only by the system fields. *Alternative:* a column `scope` (`node/type/both`), rejected as a redundant entity. The names of the system fields (`name`) are in English, as `title`; the translation of the labels of the interface is done by the client part by the key, not by the name from the DB.

**6. The backfill in the migration.** A separate migration adds seven fields to the existing types according to the matrix of kinds, with a snapshot of the list inside (it does not depend on the evolution of the class of the registry), in a transaction, with `weight` after the largest weight of the type. If a type already has a user field with a key of a system one, the migration before any changes fails with a list of conflicts (as the migration `user_id`) and the user renames the field. The rollback removes the system fields of metadata together with the values.

**7. The files of the values of the type.** `FileRelations::sync` is called with `owner_type = 'content_type'`, `owner_id = content_types.id`, the role — the key of the field. In `config('files.owners')` the `ContentTypeOwner` is registered (the label — the name of the type, the URL — the settings page of the kind `content`). The hook `deleted` of the type calls `forgetOwner('content_type', $id)`; since the cascade of the type is executed by the DB and the events do not fire, the hook is placed on `deleting`, when the type still exists (next to the existing `nodes_count` restriction).

**8. The languages: only `default` in the UI.** All seven fields have `is_translatable = true`, and the storage of values and the accounting of files already support `lang`, so the writer and the reader accept a language, and `lang = default` is the default value and a bookmark for the future. No screen of this change has a language switch or passes another language: the forms of the record and of the type edit only the base values. How the translation of each field (in particular of images) will look is decided by a separate change about multilingualism.

**9. The fields of the form of a record.** `ContentNodeController::formFields()` returns the fields that belong to the record (`SystemFields` filters `section_*`), and marks `meta_*` with the sign of the panel (`panel: 'seo'`). `ContentNodeForm` renders such fields in the panel «SEO» by the same `NodeFieldInput`/`NodeFieldList`; the data and the saving do not change, because the meta fields are ordinary fields of the record. The page of the fields of a type (`/fields`) shows the system fields as the other system ones (without deletion).

**10. The panels of the type.** `ContentTypeController::edit` adds for the kind `content` the prop `typeFields` (the definitions of the fields `section_*` and `meta_*` and their base values). The saving is a separate route `PUT /admin/content-types/{key}/values` (a request with `values`; the language is always `default`), which calls the writer with the owner-type; the main form of the type and `UpdateContentTypeRequest` do not change. The panels use the shared component with the panel «SEO» of the record; how exactly `NodeFieldInput` is reused (its input props are bound to the record) is decided at the beginning of the corresponding task.

**11. Reading the values of the type.** A public method of the reading service (`ContentValueReader::loadForType`) returns the values of the type for the language, the missing ones as `null`, without substitution of the base one; an unknown key or a key foreign to the owner gives a `ValidationException`, as for the records.

## Risks / Trade-offs

- [The migration changes nullable columns and indexes in seven tables; SQLite rebuilds the tables] → the migration has branches for MySQL and SQLite, it is checked by `migrate:fresh` in the SQLite tests and manually by `docker compose exec app php artisan migrate:fresh` against MySQL; the rollback (`down`) removes the rows of the type, `entity`, the CHECK and the functional index.
- [The FK to the record is not checked for the rows of the type, the CHECK and the functional index — raw SQL] → tests on both drivers where possible; the documentation describes the invariant; any future query «all values of a field» must choose the owner.
- [A user field with the key of a system one already exists] → the migration fails before the changes with a list of conflicts; the user renames the field.
- [A field with the scope of the type only is shown in the settings of the fields] → it is a system field, it cannot be deleted.
- [Two places of truth about the system fields: the registry and the snapshot in the migration] → the snapshot is deliberately unchanged; a new key requires a new backfill migration.
- [The form of a record has no translations, so the `meta_*` of the record are edited only in the base language] → the storage of translations is ready, the UI is a separate change.

## Migration Plan

1. The migration of the schema of `content_values_*` (nullable owner, `entity`, CHECK, the functional unique).
2. The backfill migration of the system fields.
3. The code: the registry, the owner, writer/reader, `ContentType`/`ContentField`, the controllers, the UI.
4. The rollback: in the reverse order; the rows of the type and the system fields of metadata are deleted.

## Open Questions

There are no blocking ones. Minor details (the names of the classes, the reuse of `NodeFieldInput` in the panels of the type) are decided at the beginning of the corresponding tasks.

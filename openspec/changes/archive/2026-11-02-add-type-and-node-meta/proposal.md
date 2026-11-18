# Proposal

## Why

For the public site the records and the sections need metadata (`title`, `description`, `image` for SEO and social networks), and the section of a content type also needs its own visible description (a heading, a text, a cover). Today the system can store values only for a record, and a type has only service columns and `settings`, so the metadata of a type with translations and files has nowhere to be stored. Only one system field is created, `title`, and it is hard-coded in `ContentType::booted()`.

## What Changes

- **System fields of metadata.** A new source of truth in the code, the registry of system fields, describes the key, the value type, the name, the kinds of types, the owners (the record, the type) and the translatability. The registry creates the fields together with the type (today the hard-coded `title` does this) and the backfill for the existing types.

  | field | value | `content` | `page` | `block` |
  | --- | --- | --- | --- | --- |
  | `title` (exists) | string, required | record | record | record |
  | `meta_title` | string | record and type | record | - |
  | `meta_description` | text | record and type | record | - |
  | `meta_image` | image | record and type | record | - |
  | `section_title` | string | type | - | - |
  | `section_description` | richtext | type | - | - |
  | `section_cover` | image | type | - | - |

  All new fields are optional, non-multiple, system (not deleted, `type`, `is_multiple`, `is_required` do not change) and translatable. The owner of a field, the record or the type, is derived from the registry, not from a column; a non-system field always belongs to the record.
- **The values of the type in the same tables of values.** In all `content_values_*` the columns `content_node_id` and `content_node_uuid` become nullable, the column `entity` (`node`/`type`) is added. A row of the type has no record, and its `content_type` equals the key of this type. The consistency of `entity` with `content_node_id` is protected by a CHECK, the uniqueness of the rows of the type by a functional index (a migration with a branch for MySQL and SQLite).
- **Writing and reading for two owners.** `ContentValueWriter` and `ContentValueReader` accept a record or a type; a field that does not belong to the owner by the registry is rejected. Reading the values of a type (by the language, without substitution of the base one) becomes part of the API.
- **Files of the values of the type.** `meta_image` and `section_cover` are accounted in `media_relations` with `owner_type = 'content_type'`; deleting a type removes these links.
- **Reserved keys.** `ContentField::RESERVED_KEYS` is split into the keys of the service columns and the keys of the system fields, which only the system creates (`meta_*`, `section_*`): the user cannot create a new field with such a key.
- **The form of a record.** The fields `meta_*` leave the main list of the fields into a separate panel «SEO».
- **The settings of a type `content`.** The panels «Розділ» (`section_*`) and «SEO» (`meta_*`) appear on the settings page (the base language only).
- **`valuedKeys`** (the fields with values) counts only the values of the records.

**Not included:** the public display (the choice of a fallback heading of the section, `og:` markup), additional metafields (`og_*`, `canonical`, `robots`), polymorphic entities beyond the record and the type (users, menus), custom fields at the level of a type. Multilingualism in the interface (a language switch, translation of individual fields, in particular of images, substitution of the base value) is designed separately: here all forms work only with the base language `default`, and the storage already supports `lang` (`content_values_*.lang`, `media_relations.lang`).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-model`: the registry of system fields instead of the hard-coded `title`; the fields of metadata and section and their visibility by kind; the reserved keys of system fields; the values of the type in the cascade of deletion.
- `content-values`: the shared tables of values have two owners (the record, the type) with CHECK and uniqueness; writing and reading for the type; rejection of a field that does not belong to the owner.
- `media-library`: the owner of files `content_type`; the accounting and the cleaning of the links of the values of the type.
- `admin-content-fields`: the keys of the system fields of metadata and section are among the reserved ones on creation of a field.
- `admin-content-types`: the panels «Розділ» and «SEO» on the settings page of a type `content`.
- `admin-content-nodes`: the panel «SEO» in the form of a record; `meta_*` do not get into the main list of the fields of the form.

## Impact

- **Code:** migrations (nullable owner and `entity`, CHECK, the functional unique for `content_values_*`), the new class of the registry of system fields and the backfill migration, `app/Models/ContentType.php` (the creation of fields from the registry), `app/Models/ContentField.php` (`RESERVED_KEYS`, deletion, the file hook), `app/Content/ContentValueWriter.php` and `ContentValueReader.php` (the owner), `app/Files/FileRelations.php` and `app/Files/Owners/*` (the owner `content_type`), `app/Http/Controllers/Admin/ContentTypeController.php` (passing and saving the values), `ContentNodeController.php` (the division of the fields of the form), `ContentFieldController.php` (`valuedKeys`), the validation requests, React: the settings page of a type, `ContentNodeForm`, the shared component of the panel of fields, the translations `lang/en.json` and `lang/uk.json`.
- **Tests:** Feature tests of the schema and the owners, the registry and the backfill, the writing/reading of the type, the cleaning of files, the forms of the type and of the record; e2e specs of the areas «типи контенту» and «записи» (the exact set by the table in `docs/e2e-testing.md`).
- **Documentation (docs/, in Ukrainian):** update `content-model.md`, `content-values.md`, `content-querying.md` (reading of the type, isolation by the owner), `media-library.md`, `admin-content-types.md`, `admin-content-nodes.md`, `admin-content-fields.md` (the reserved keys), `e2e-testing.md`; the index `docs/README.md` remains valid, no new files are expected.
- **Dependencies:** none new. The migrations change all seven tables `content_values_*`; on a non-empty DB the backfill adds the fields to the existing types.

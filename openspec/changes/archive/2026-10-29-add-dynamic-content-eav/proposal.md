# Proposal

## Why

The CMS must let the administrator create content types, add fields and configure lists of content nodes without writing code. This requires a foundation of dynamic content: a data model in which the structure of content is data (configuration records), not migrations. Without this foundation neither the admin UI, nor the block builder, nor the public output can be built.

## What Changes

- New entities: a content type (`content_types`), a field definition (`content_fields`), a content node (`content_nodes`).
- Typed value tables `content_values_{type}` (EAV) with a shared column contract: the link to the node (`content_node_id` and `content_node_uuid`) and to the field (the pair `content_type` + `field_key`), the language `lang` (from the list `app_languages`), the weight `weight` (the order of values of multiple fields), `value` of the native DB type, an optional JSON `options`. The initial types: short text, long text, integer, decimal number, boolean, date, date and time.
- Translations are stored as separate value rows with a different `lang`, not in JSON. The default language is `default`.
- Management of content languages: a common system table `app_languages` (the available languages; a new language is inactive by default) and a global multilingualism switch stored in the common storage `app_settings` (the key `content.multilingual`). They affect only the content and the frontend part, not the language of the admin interface.
- A common application settings storage `app_settings` (key—value with JSON values and namespaces `module.name`), used by the content core and by any future modules.
- For ordering, instead of `position` the column/attribute `weight` is used everywhere (a smaller weight — earlier).
- The kind of a content type `kind` (`content`, `page`, `block`): pages and blocks use the same fields, values, translations and selection as ordinary content, and differ only in the kind of the type. A page tree, block placement and routing are separate future changes.
- The hierarchy of content types: `content_types.parent_type` (a self-reference to the type `key`) for grouping types into a tree.
- A mandatory unique `slug` in `content_nodes` for all kinds: a public path (for `content` and `page`) that is automatically formed from `title` if not given explicitly; an identifier for templates with the prefix `block-` (for `block`), which is set manually or generated automatically.
- A stable identifier `uuid` (UUID v7, unique, immutable) in `content_nodes` for future import/export between environments; a type is identified by a unique immutable `key`, a field — by the pair (`content_type`, `key`), and the relations to the type (`parent_type`, `content_type`) refer to `content_types.key`, while the relations of values remain on `id`. The import/export itself and relations between nodes are separate future changes.
- Each content type automatically receives on creation the system field `title` (short text), which cannot be deleted.
- A registry of field types: each type describes its own value table, validation rules, type casting, filtering and sorting operations. Adding a new type does not change the common mechanism.
- A single mechanism for reading, writing and validating the values of a node by the configuration of its fields (single, multiple, by language).
- Indexes for selecting the values of a node and for filtering by field, language and value.
- A node selection mechanism: configurable visible fields, search, filters and sorting by dynamic fields taking into account the field type and the language; the default list settings are stored in the JSON `settings` of the content type.
- Out of scope: the admin UI, the block builder, the migration of data from the old system.

## Capabilities

### New Capabilities
- `content-model`: content types with the `kind` and the `parent_type` hierarchy, nodes with a mandatory unique `slug` (with the mandatory system field `title`), a stable `uuid` of nodes, relations to the type by `key`, field definitions (key, type, multiplicity, requiredness, translatability, settings), content nodes, configuration integrity rules.
- `app-settings`: a common application settings storage for the core and for future modules.
- `content-languages`: the list of content languages, disabling/deactivation, the multilingualism switch, independence from the admin language.
- `content-values`: typed storage of values, translations through `lang`, the registry of field types, reading/writing/validation of node values, multiplicity.
- `content-querying`: node selection with visible fields, search, filters, sorting and pagination by dynamic fields and language.

### Modified Capabilities

None.

## Impact

- Code: new models, migrations (only the structural tables, once), the registry of field types and services in `app/`; the registry configuration in `config/`; tests in `tests/Feature` and `tests/Unit`.
- DB: the tables `content_types`, `content_fields`, `content_nodes`, `app_languages`, `app_settings` and 7 tables `content_values_*`. New content types and fields are subsequently created as records without migrations.
- Dependencies: no new packages.
- Documentation (in Ukrainian): create `docs/app-settings.md`, `docs/content-model.md`, `docs/content-languages.md`, `docs/content-values.md`, `docs/content-querying.md`; update the index `docs/README.md`.

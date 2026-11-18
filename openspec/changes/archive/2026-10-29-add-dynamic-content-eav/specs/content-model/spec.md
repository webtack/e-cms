# Spec Delta

## Purpose

Describes how the administrator defines the structure of content as data: content types, their fields and content nodes, without changes to the DB schema and code.

## ADDED Requirements

### Requirement: A content type is defined by a record
The system SHALL allow creating a content type as a record with a unique machine key, a name and an optional text description (`description`), without new migrations and code changes. The type key SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters) and SHALL NOT change after creation; the name and the description may be changed.

#### Scenario: Creating a type
- **WHEN** a content type with the key `article` and the name "Article" is created
- **THEN** the type is saved, and it can be used to create content nodes; the description is optional

#### Scenario: Type description
- **WHEN** a content type is created or changed with an arbitrary text description
- **THEN** the description is saved and returned on reading; a missing description is `null`

#### Scenario: Duplicate key
- **WHEN** a content type is created with a key that already exists
- **THEN** the system rejects the creation with a uniqueness error

#### Scenario: Invalid key format
- **WHEN** a content type is created with the key `My Type` or `1news`
- **THEN** the creation is rejected with a validation error

#### Scenario: Attempt to change the type key
- **WHEN** the `key` of an existing content type is changed
- **THEN** the change is rejected, the key remains the previous one; changing the name is allowed

### Requirement: Kind of a content type
The system SHALL store for each content type its kind `kind` from the list `content`, `page`, `block` (by default `content`), and SHALL reject any other value. All kinds SHALL use the same fields, values, translations and selection mechanism. The kind SHALL NOT change after the type is created, regardless of the presence of nodes.

#### Scenario: Default kind
- **WHEN** a content type is created without specifying a kind
- **THEN** its kind is `content`

#### Scenario: Page and block types
- **WHEN** types with the kinds `page` and `block` are created
- **THEN** they have the same field, translation and value capabilities as the type of the kind `content`, and the automatic field `title`

#### Scenario: Invalid kind
- **WHEN** a type with the kind `section` is created
- **THEN** the creation is rejected with a validation error

#### Scenario: Attempt to change the kind
- **WHEN** the kind of an existing type is changed (with nodes or without)
- **THEN** the change is rejected, the kind remains the previous one

### Requirement: Hierarchy of content types
The system SHALL allow specifying for a content type a parent type (`parent_type`, the `key` of the parent), forming a tree of types of any depth. The parent type SHALL have the same kind `kind`; cycles SHALL be forbidden; a type with child types SHALL NOT be deleted. The hierarchy is only organizational: the fields of the parent type are not inherited by the child types.

#### Scenario: Child type
- **WHEN** a type `news` with the parent type `article` of the same kind is created
- **THEN** the type is saved as a child; its fields are defined by itself only

#### Scenario: Different kind in the parent
- **WHEN** the parent type has a different kind (`page` for a type of the kind `block`)
- **THEN** the save is rejected with a validation error

#### Scenario: Cycle
- **WHEN** a type is assigned as the parent of itself or of its descendant
- **THEN** the save is rejected with a validation error

#### Scenario: Deleting a type with children
- **WHEN** a type that has child types is deleted
- **THEN** the deletion is rejected

### Requirement: Stable uuid of nodes
Each content node SHALL have a `uuid`, unique within the nodes table. The system SHALL generate it automatically on creation if it is not given, accept an explicitly given valid free `uuid` (for import), forbid its change after creation, and allow finding a node by `uuid`. A content type and a field do NOT have a `uuid`: a type is identified by the unique immutable `key`, and a field — by the pair (`content_type`, `key`) with an immutable `key`; all relations to a type (`content_types.parent_type`, `content_fields.content_type`, `content_nodes.content_type`) SHALL refer to the `key` of the type, not to the numeric `id`.

#### Scenario: Automatic generation
- **WHEN** a node is created without a `uuid`
- **THEN** the node has a generated valid unique `uuid`

#### Scenario: Explicit uuid
- **WHEN** a node is created with a valid free `uuid`
- **THEN** exactly this `uuid` is saved

#### Scenario: Duplicate or invalid uuid
- **WHEN** a node is created with a `uuid` that already exists or is not a valid UUID
- **THEN** the creation is rejected

#### Scenario: Immutability
- **WHEN** the `uuid` of an existing node is changed
- **THEN** the change is rejected, the `uuid` remains the previous one

#### Scenario: Search by uuid
- **WHEN** a node is requested by `uuid`
- **THEN** the corresponding node is returned, and for an unknown `uuid` — `null`

#### Scenario: Search of a type by key
- **WHEN** a type is requested by `key`
- **THEN** the corresponding type is returned, and for an unknown `key` — `null`

#### Scenario: Stability when the slug changes
- **WHEN** the `slug` of a node is changed
- **THEN** the `uuid` of the node remains the same

#### Scenario: Relation by the type key
- **WHEN** a field, a node or a child type is created
- **THEN** the `content_type` of the field or node stores the `key` of its type, and the `parent_type` of the child type — the `key` of the parent; for a root type `parent_type` is empty

#### Scenario: Attempt to change the type of a field or node
- **WHEN** the `content_type` of an existing field or node is changed
- **THEN** the change is rejected, the relation remains the previous one

### Requirement: Mandatory unique slug of a node
Each content node SHALL have a non-empty `slug`, unique among all nodes of all types and kinds (case-insensitively). The slug SHALL consist of lowercase Latin letters, digits and hyphens without leading, trailing and repeated hyphens, up to 190 characters long. The slug SHALL be shared for all languages (it is not translated). A change of the slug of an existing node is allowed only by an explicit specification and SHALL keep uniqueness.

#### Scenario: Missing slug in the data
- **WHEN** at the node save level the slug is empty or has invalid characters
- **THEN** the save is rejected with a validation error

#### Scenario: Duplicate slug
- **WHEN** a node is created with a slug that another node already has (of any type or kind)
- **THEN** the save is rejected with a uniqueness error

### Requirement: Automatic slug for pages and content
For nodes of types with the kind `content` or `page` the system SHALL, when creating without an explicit slug, form it from `title` in the language `default` (transliteration of Cyrillic, lowercase, the separator `-`), and on a match with an existing slug SHALL add a numeric suffix (`-2`, `-3`, …). An explicitly given slug SHALL be checked for validity and uniqueness without automatic changes. Changing the `title` of an existing node does NOT change its slug. The prefix `block-` is NOT restricted for these kinds and the automatic prefix is NOT added to the slug.

#### Scenario: Slug from title
- **WHEN** a node of the type `page` with the `title` "Про нас" is created without a slug
- **THEN** the slug is formed from the title (for example `pro-nas`)

#### Scenario: Slug match
- **WHEN** the slug formed from the `title` already exists
- **THEN** a numeric suffix is added to it (`pro-nas-2`)

#### Scenario: Explicit slug
- **WHEN** a node `content` is created with the explicit slug `about`
- **THEN** exactly `about` is saved; if it is taken, the creation is rejected

#### Scenario: Title without suitable characters
- **WHEN** the `title` contains no character suitable for a slug
- **THEN** the fallback slug `node` is used, with a numeric suffix if needed

#### Scenario: Slug with the prefix block- for a page
- **WHEN** a node of the type `page` with the `title` "Block-1" is created without a slug
- **THEN** the slug `block-1` is saved without changes and without an additional prefix

#### Scenario: Change of title
- **WHEN** the `title` of an existing node is changed
- **THEN** its slug remains unchanged

### Requirement: Identifier of a block
For nodes of types with the kind `block` the slug SHALL be an identifier for explicit use in templates and SHALL always start with `block-`. It can be specified manually (complying with the slug format); if it is not specified, the system SHALL generate `block-` + 8 random lowercase Latin letters and guarantee uniqueness (on a match the generation is repeated).

#### Scenario: Automatic identifier
- **WHEN** a node of the kind `block` is created without a slug
- **THEN** the slug has the form `block-` and 8 lowercase Latin letters and is unique

#### Scenario: Manual identifier
- **WHEN** a block is created with the slug `block-footer-menu`
- **THEN** exactly `block-footer-menu` is saved

#### Scenario: Manual identifier without the prefix
- **WHEN** a block is created with the slug `footer-menu`
- **THEN** the save is rejected with a validation error

### Requirement: Mandatory system field title
The system SHALL, on creation of any content type, automatically create in it a field `title` of the type "short text" (required, single-valued, translatable, of the lowest weight (`weight = 0`)), and SHALL forbid deleting this field, changing its type, multiplicity and requiredness (the key is immutable for all fields); the name, the weight and the settings may be changed.

#### Scenario: Automatic creation
- **WHEN** a new content type is created
- **THEN** it immediately contains the field `title` of the type "short text"

#### Scenario: Attempt to delete title
- **WHEN** the field `title` is deleted
- **THEN** the deletion is rejected, the field and the values remain

#### Scenario: Forbidden changes of title
- **WHEN** the type, multiplicity or requiredness of the field `title` is changed
- **THEN** the change is rejected

#### Scenario: Deleting a type
- **WHEN** a content type is deleted
- **THEN** it is deleted together with the field `title` (the ban on deleting `title` does not block deleting the whole type)

### Requirement: A field is defined by a record
The system SHALL allow adding to a content type a field with a key (unique within the type), a name, a value type from the registry, the flags of multiplicity, requiredness and translatability, a weight (`weight`) and optional settings (JSON). The field key SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters) and SHALL NOT change after creation; the name, the weight and the settings may be changed.

#### Scenario: Adding a field
- **WHEN** a field `views` of the type "integer" is added to the type `article`
- **THEN** the field is saved, and the nodes of this type can have values for it

#### Scenario: Unknown value type
- **WHEN** a field with a type that is not in the registry of field types is added
- **THEN** the system rejects saving the field with a validation error

#### Scenario: Duplicate key within a type
- **WHEN** a second field with an existing key is added to the same type
- **THEN** the save is rejected; the same key in another content type is allowed

#### Scenario: Invalid field key format
- **WHEN** a field with the key `My Field` or `1price` is added to a type
- **THEN** the save is rejected with a validation error

#### Scenario: Attempt to change a field key
- **WHEN** the `key` of an existing field is changed
- **THEN** the change is rejected, the key remains the previous one; changing the name is allowed

### Requirement: Immutability of the value type of a field
The system SHALL forbid changing the value type of a field for which values already exist, and SHALL allow changing its name, weight and settings.

#### Scenario: Attempt to change the type
- **WHEN** the type of a field that has stored values is changed
- **THEN** the change is rejected, the existing values do not change

### Requirement: A content node belongs to a content type
The system SHALL store a content node as a record belonging to exactly one content type, and SHALL delete together with it all its values in all languages.

#### Scenario: Deleting a node
- **WHEN** a node with values is deleted
- **THEN** all its values in all typed tables and in all languages are also deleted

### Requirement: Cascade deletion of configuration
The system SHALL delete the values of a field when this field is deleted, and when a content type is deleted — its fields, nodes and their values.

#### Scenario: Deleting a field
- **WHEN** a non-system field is deleted
- **THEN** the values of this field in all nodes and languages are deleted, the values of other fields are kept

### Requirement: Settings of a content type and of the default list
The system SHALL store in the settings of a content type (`settings`) the configuration of the default list: the set of visible fields, the search fields and the default sorting, and SHALL reject settings that refer to fields not existing in this type.

#### Scenario: Reference to a foreign field
- **WHEN** the list settings of the type `article` specify a field key that does not exist in this type
- **THEN** saving the settings is rejected with a validation error

#### Scenario: Type settings without a list
- **WHEN** a content type is created without settings
- **THEN** `settings` is empty, and the selection uses its own default values

# content-model Specification

## Purpose
Describes how the administrator defines the content structure with data: content types, their fields, and content nodes, without changes to the DB schema and the code.

## Requirements

### Requirement: A content type is defined by a row in the DB
The system SHALL allow creating a content type as a row in the DB with a unique machine key, a name, and an optional text description (`description`), without new migrations and code changes. The key of a type SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters), SHALL NOT match a reserved key (`content`, `pages`, `blocks`, `fields`, `menus`, `errors`: the names of the service folders of site templates), and SHALL NOT change after creation; the name and the description may be changed. The prohibition of reserved keys SHALL apply to new types and SHALL NOT change or break existing types.

#### Scenario: Creating a type
- **WHEN** a content type is created with the key `article` and the name "Article"
- **THEN** the type is saved, and it can be used to create content nodes; the description is not required

#### Scenario: Description of a type
- **WHEN** a content type is created or changed with an arbitrary text description
- **THEN** the description is saved and returned on reading; an absent description is `null`

#### Scenario: Duplicate key
- **WHEN** a content type is created with a key that already exists
- **THEN** the system rejects the creation with a uniqueness error

#### Scenario: Invalid key format
- **WHEN** a content type is created with the key `My Type` or `1news`
- **THEN** the creation is rejected with a validation error

#### Scenario: Reserved key
- **WHEN** a type of any kind is created with the key `content`, `pages`, `blocks`, `fields`, `menus`, or `errors`
- **THEN** the creation is rejected with a validation error for the key `key`, and the type is not created

#### Scenario: A key similar to a reserved one
- **WHEN** a type is created with the key `pages_list` or `page`
- **THEN** the type is created, because the match is checked only against the full reserved key

#### Scenario: Existing type with a reserved key
- **WHEN** the DB already has a type whose key is among the reserved ones, and its name, description, or settings are changed
- **THEN** the change is saved, because the prohibition applies only at creation

#### Scenario: Attempt to change the key of a type
- **WHEN** the `key` of an existing content type is changed
- **THEN** the change is rejected, and the key remains the previous one; a change of the name is allowed

### Requirement: Kind of a content type
The system SHALL store for each content type its kind `kind` from the list `content`, `page`, `block` (default `content`), and SHALL reject any other value. All kinds SHALL use the same fields, values, translations, and querying mechanism. The kind SHALL NOT change after the type is created, regardless of the existence of records.

#### Scenario: Default kind
- **WHEN** a content type is created without specifying a kind
- **THEN** its kind is `content`

#### Scenario: Page and block types
- **WHEN** types with the kinds `page` and `block` are created
- **THEN** they have the same field, translation, and value capabilities as the type of kind `content`, and the automatic field `title`

#### Scenario: Invalid kind
- **WHEN** a type with the kind `section` is created
- **THEN** the creation is rejected with a validation error

#### Scenario: Attempt to change the kind
- **WHEN** the kind of an existing type is changed (with or without records)
- **THEN** the change is rejected, and the kind remains the previous one

### Requirement: Hierarchy of content types
The system SHALL allow specifying for a content type a parent type (`parent_type`, the `key` of the parent), forming a tree of types of any depth. The parent type SHALL have the same kind `kind`; cycles SHALL be forbidden; a type with child types SHALL NOT be deleted. The hierarchy is only organizational: the fields of the parent type are not inherited by the children.

#### Scenario: Child type
- **WHEN** the type `news` is created with the parent type `article` of the same kind
- **THEN** the type is saved as a child; its fields are defined only by itself

#### Scenario: Other kind for the parent
- **WHEN** the parent type has a different kind (`page` for a type of kind `block`)
- **THEN** saving is rejected with a validation error

#### Scenario: Cycle
- **WHEN** a type is assigned as the parent of itself or of its descendant
- **THEN** saving is rejected with a validation error

#### Scenario: Deleting a type with children
- **WHEN** a type that has child types is deleted
- **THEN** the deletion is rejected

### Requirement: Stable uuid of nodes
Each content node SHALL have a `uuid` that is unique within the table of nodes. The system SHALL generate it automatically on creation, if it is not specified, accept an explicitly specified valid free `uuid` (for import), forbid its change after creation, and allow finding a node by `uuid`. A content type and a field have no `uuid`: a type is identified by the unique immutable `key`, and a field — by the pair (`content_type`, `key`) with an immutable `key`; all relations with a type (`content_types.parent_type`, `content_fields.content_type`, `content_nodes.content_type`) SHALL refer to the type's `key`, not to the numeric `id`.

#### Scenario: Automatic generation
- **WHEN** a node is created without `uuid`
- **THEN** the node has a generated valid unique `uuid`

#### Scenario: Explicit uuid
- **WHEN** a node is created with a valid free `uuid`
- **THEN** exactly this `uuid` is saved

#### Scenario: Duplicate or invalid uuid
- **WHEN** a node is created with a `uuid` that already exists or is not a valid UUID
- **THEN** the creation is rejected

#### Scenario: Immutability
- **WHEN** the `uuid` of an existing node is changed
- **THEN** the change is rejected, and the `uuid` remains the previous one

#### Scenario: Search by uuid
- **WHEN** a node is requested by `uuid`
- **THEN** the corresponding node is returned, and for an unknown `uuid` — `null`

#### Scenario: Search of a type by key
- **WHEN** a type is requested by `key`
- **THEN** the corresponding type is returned, and for an unknown `key` — `null`

#### Scenario: Stability when the slug changes
- **WHEN** the `slug` of a node is changed
- **THEN** the `uuid` of the node remains the same

#### Scenario: Relation by the key of a type
- **WHEN** a field, a node, or a child type is created
- **THEN** the `content_type` of the field or node stores the `key` of its type, and the `parent_type` of the child type stores the `key` of the parent; for a root type `parent_type` is empty

#### Scenario: Attempt to change the type of a field or a node
- **WHEN** the `content_type` of an existing field or node is changed
- **THEN** the change is rejected, and the relation remains the previous one

### Requirement: Mandatory unique slug of a node
Each content node SHALL have a non-empty `slug`, unique among all nodes of all types and kinds (case-insensitively). The slug SHALL consist of lowercase Latin letters, digits, and hyphens without leading, trailing, and repeated hyphens, up to 190 characters in length. The slug SHALL be shared by all languages (not translated). A change of the slug of an existing node is allowed only by an explicit specification and SHALL preserve uniqueness.

#### Scenario: Missing slug in the data
- **WHEN** at the level of saving a node the slug is empty or has invalid characters
- **THEN** saving is rejected with a validation error

#### Scenario: Duplicate slug
- **WHEN** a node is created with a slug that another node already has (of any type or kind)
- **THEN** saving is rejected with a uniqueness error

### Requirement: Automatic slug for pages and content
For records of types with the kind `content` or `page` the system SHALL, on creation without an explicit slug, form it from the `title` in the `default` language (transliteration of Cyrillic, lowercase, the separator `-`), and on a match with an existing slug SHALL add a numeric suffix (`-2`, `-3`, …). For records of kind `page` a match SHALL also include the slug of any type `content` and the reserved site path, so the generated slug never violates the requirement "Top-level public paths without collisions". An explicitly specified slug SHALL be checked for admissibility and uniqueness without automatic changes. Changing the `title` of an existing node does NOT change its slug. The prefix `block-` is not restricted for these kinds, and an automatic prefix is not added to the slug.

#### Scenario: Slug from the title
- **WHEN** a record of type `page` is created with the title "About us" without a slug
- **THEN** the slug is formed from the name (for example `about-us`)

#### Scenario: Slug match
- **WHEN** the slug formed from the `title` already exists
- **THEN** a numeric suffix is added to it (`about-us-2`)

#### Scenario: Match with a reserved path
- **WHEN** a record of kind `page` is created with the title "Admin" without a slug
- **THEN** the slug equals `admin-2`, not `admin`

#### Scenario: Match with a type slug
- **WHEN** a record of kind `page` is created with the title "News" without a slug, and a type of kind `content` already has the slug `news`
- **THEN** the slug equals `news-2`

#### Scenario: A content record does not depend on type slugs
- **WHEN** a record of kind `content` is created with the title "News" without a slug, and the type has the slug `news`
- **THEN** the slug of the record equals `news`, because a `content` record is not on the first level of the path

#### Scenario: Explicit slug
- **WHEN** a record of kind `content` is created with the explicit slug `about`
- **THEN** exactly `about` is saved; if it is taken, the creation is rejected

#### Scenario: Title without suitable characters
- **WHEN** the `title` contains no character suitable for a slug
- **THEN** the fallback slug `node` is used, with a numeric suffix if needed

#### Scenario: Slug with the prefix block- for a page
- **WHEN** a record of type `page` is created with the title "Block-1" without a slug
- **THEN** the slug `block-1` is saved without changes and without an additional prefix

#### Scenario: Change of the title
- **WHEN** the `title` of an existing node is changed
- **THEN** its slug remains unchanged

### Requirement: Identifier of a block
For records of types with the kind `block` the slug SHALL be an identifier for explicit use in templates and SHALL always begin with `block-`. It can be specified manually (following the format of the slug); if it is not specified, the system SHALL generate `block-` + 8 random lowercase Latin letters and guarantee uniqueness (on a match the generation is repeated).

#### Scenario: Automatic identifier
- **WHEN** a record of kind `block` is created without a slug
- **THEN** the slug has the form `block-` and 8 lowercase Latin letters, and is unique

#### Scenario: Manual identifier
- **WHEN** a block is created with the slug `block-footer-menu`
- **THEN** exactly `block-footer-menu` is saved

#### Scenario: Manual identifier without the prefix
- **WHEN** a block is created with the slug `footer-menu`
- **THEN** saving is rejected with a validation error

### Requirement: Mandatory system field title
The system SHALL, on creation of any content type, automatically create in it a field `title` of the type "short text" (required, single-value, translatable, with the lowest weight (`weight = 0`)), and SHALL forbid deleting this field, changing its type, multiplicity, and requiredness (the key is immutable for all fields); the name, weight, and settings may be changed. The field `title` belongs only to nodes. Additional system fields depending on the kind are defined by the requirement "System metadata and section fields".

#### Scenario: Automatic creation
- **WHEN** a new content type is created
- **THEN** it immediately contains the field `title` of the type "short text"

#### Scenario: Attempt to delete title
- **WHEN** the deletion of the field `title` is performed
- **THEN** the deletion is rejected, and the field and its values remain

#### Scenario: Forbidden changes to title
- **WHEN** the type, multiplicity, or requiredness of the field `title` is changed
- **THEN** the change is rejected

#### Scenario: Deleting a type
- **WHEN** a content type is deleted
- **THEN** it is deleted together with the field `title` (the prohibition on deleting `title` does not block the deletion of the whole type)

### Requirement: A field is defined by a row in the DB
The system SHALL allow adding to a content type a field with a key (unique within the type), a name, a value type from the registry, flags of multiplicity, requiredness, and translatability, a flag that hides the label of the field on the public page (`hide_label`, off by default), a weight (`weight`), and optional settings (JSON). The key of a field SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters), SHALL NOT match a reserved key, and SHALL NOT change after creation; the name, weight, the label flag, and settings may be changed. The reserved keys: the keys of the service columns of the node table (`uuid`, `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`) and the keys of the system metadata and section fields (`meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`), which only the system creates. The prohibition of reserved keys SHALL apply only at the creation of a field by the user: fields that already have such a key remain valid and are edited as before, and the keys of the service columns of the node table are not offered as columns.

#### Scenario: Adding a field
- **WHEN** a field `visits` of the type "integer" is added to the type `article`
- **THEN** the field is saved, and the nodes of this type can have values for it

#### Scenario: Label flag default
- **WHEN** a field is added without the label flag
- **THEN** the field is saved with `hide_label` off

#### Scenario: Unknown value type
- **WHEN** a field with a type that is not in the registry of field types is added
- **THEN** the system rejects saving the field with a validation error

#### Scenario: Duplicate key within a type
- **WHEN** a second field with an existing key is added to the same type
- **THEN** saving is rejected; the same key in another content type is admissible

#### Scenario: Invalid format of a field key
- **WHEN** a field with the key `My Field` or `1price` is added to a type
- **THEN** saving is rejected with a validation error

#### Scenario: Reserved field key
- **WHEN** a field with the key `slug`, `uuid`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, or `views` is added to a type
- **THEN** saving is rejected with a validation error next to the key, and the field is not created

#### Scenario: Key of a system metadata field
- **WHEN** the user adds a field with the key `meta_title` or `section_cover` to a type
- **THEN** saving is rejected with a validation error next to the key, and the field is not created

#### Scenario: Existing field with a reserved key
- **WHEN** the name or weight of a field is updated, whose key was created before the reservation was introduced (for example, `status`)
- **THEN** the change is saved without an error

#### Scenario: Attempt to change the key of a field
- **WHEN** the `key` of an existing field is changed
- **THEN** the change is rejected, and the key remains the previous one; a change of the name is allowed

### Requirement: Immutability of the value type of a field
The system SHALL forbid changing the value type of a field after its creation, regardless of whether saved values exist, and SHALL allow changing its name, weight, and settings. Saving a field with the same type SHALL NOT be considered a type change.

#### Scenario: Attempt to change the type
- **WHEN** the type of a field that has saved values is changed
- **THEN** the change is rejected, and the existing values do not change

#### Scenario: Attempt to change the type of a field without values
- **WHEN** the type of a field that has no saved values is changed
- **THEN** the change is rejected, and the type of the field remains the previous one

#### Scenario: Changing other properties
- **WHEN** the name, weight, or settings of a field are changed, and the type remains the same
- **THEN** the changes are saved

### Requirement: A node of a content type belongs to the content type
The system SHALL store a content node as a row in the DB that belongs to exactly one content type, and SHALL delete with it all its values in all languages.

#### Scenario: Deleting a node
- **WHEN** a node with values is deleted
- **THEN** all its values in all typed tables and all languages are also deleted

### Requirement: Cascading deletion of the configuration
The system SHALL forbid deleting a field that has saved values (of nodes or of the type), for any deletion path (the admin panel, code, import), leaving the field and its values unchanged; a field without values SHALL be deleted. When a content type is deleted, the system SHALL delete its fields, nodes, node values, the values of the type itself, and the relations of files of this type.

#### Scenario: Deleting a field
- **WHEN** a non-system field without saved values is deleted
- **THEN** the field is deleted, and the values of the other fields are kept

#### Scenario: Deleting a field with values
- **WHEN** a field for which saved values exist (in at least one node or language, or the values of the type itself) is deleted
- **THEN** the deletion is rejected, and the field and all its values remain unchanged

#### Scenario: Deleting a content type
- **WHEN** a content type whose fields have values is deleted
- **THEN** its fields, nodes, and their values are deleted

#### Scenario: Deleting a type with section values
- **WHEN** a type `content` is deleted that has values `section_*` and `meta_*` and a selected image
- **THEN** the values of the type and the relations of its files are deleted, and the files themselves remain

### Requirement: Settings of a content type and the default list
The system SHALL store in the settings of a content type (`settings`) the default list configuration: the set of visible fields, search fields, and default sorting, and SHALL reject settings that refer to fields that do not exist in this type.

#### Scenario: Reference to a field of another type
- **WHEN** the list settings of the type `article` specify a field key that does not exist in this type
- **THEN** saving the settings is rejected with a validation error

#### Scenario: Type settings without a list
- **WHEN** a content type is created without settings
- **THEN** `settings` is empty, and the querying uses its own default values

### Requirement: Public slug of a content type
A content type of kind `content` SHALL have an optional `slug` — the public site path that opens the list of nodes of this type. The slug SHALL consist of one or more segments separated by `/`; each segment SHALL consist of lowercase Latin letters, digits, and hyphens without leading, trailing, and repeated hyphens, and the whole slug SHALL be up to 190 characters in length, without a leading, trailing, or repeated `/`. The slug SHALL be unique among all types (case-insensitively); a type without a slug has no public list. For types of kinds `page` and `block` the slug SHALL be empty, and any attempt to specify it SHALL be rejected. The type slug SHALL allow a change after creation (unlike `key` and `kind`), with all the checks preserved, including clearing. The type slug SHALL NOT match the slug of any page, and its first segment SHALL NOT be a reserved site path (see the requirement "Top-level public paths without collisions"). The slug of a type that has a parent type is also bound by the requirement "Hierarchy of type slugs".

#### Scenario: A type with a slug
- **WHEN** a type of kind `content` is created with the slug `news`
- **THEN** the type is saved, and the slug `news` belongs to exactly this type

#### Scenario: A type with a slug of several segments
- **WHEN** a type of kind `content` is created with the slug `common/institutions`
- **THEN** the type is saved, and the slug `common/institutions` belongs to exactly this type

#### Scenario: A type without a slug
- **WHEN** a type of kind `content` is created without a slug
- **THEN** the type is saved, and its slug is empty (there is no public list)

#### Scenario: Duplicate slug
- **WHEN** a type is created with the slug `News`, and another type already has the slug `news`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Duplicate slug of several segments
- **WHEN** a type is created with the slug `Common/Institutions`, and another type already has the slug `common/institutions`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Invalid format
- **WHEN** a type is saved with the slug `About us`, `news_1`, `-news`, or `a--b`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Invalid separators
- **WHEN** a type is saved with the slug `/news`, `news/`, `a//b`, or `a/-b`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Slug for a non-content type
- **WHEN** a type of kind `page` or `block` is created or changed with a slug
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Change of slug
- **WHEN** the slug of an existing type of kind `content` is changed to a free admissible value
- **THEN** the change is saved, and the `key` and `kind` of the type remain unchanged

#### Scenario: Clearing the slug
- **WHEN** the slug of an existing type of kind `content` is cleared
- **THEN** the slug becomes empty, and the type is saved

### Requirement: Purpose of the slug and the uuid of a node
The `slug` of a content node SHALL be the public identifier of the node for the site frontend (page addresses, references from templates), and SHALL NOT be used as the identifier of the node in the admin panel and in internal links: they SHALL rely on the immutable `uuid`. A change of the slug SHALL NOT change the `uuid` and SHALL NOT break the admin links to the node.

#### Scenario: Admin link after a slug change
- **WHEN** the slug of a node is changed
- **THEN** the admin addresses of this node, built by `uuid`, remain valid

#### Scenario: Public use of the slug
- **WHEN** the frontend needs to find a node by its public path
- **THEN** it looks for the node by slug, and the admin panel — by `uuid`

### Requirement: Service attributes of a node
Each content node SHALL have service attributes: `published_at` (the date and time of publication, required), `unpublished_at` (the date and time of unpublishing, optional), `is_active` (boolean, default `true`), `user_id` (the author, required, a reference to a user), and `views` (the view counter, a non-negative integer; a node created through the application starts with `1`, the creation counting as the first view). `published_at`, `unpublished_at`, and `is_active` SHALL be editable. The author SHALL be set by the system from the user who creates the node, SHALL NOT accept a value from the request, and SHALL NOT change after creation. A user who is the author of a node SHALL NOT be deleted. If `published_at` is not specified on creation, the system SHALL substitute the current moment; clearing `published_at` of an existing node SHALL be impossible. Past and future values of `published_at` SHALL be admissible. An empty `unpublished_at` SHALL mean "no unpublishing date", and clearing it for an existing node SHALL be possible. When `unpublished_at` is set, it SHALL be later than `published_at` (equality is not admissible); this SHALL apply also when `published_at` is substituted by the system. A past date of `unpublished_at` SHALL be admissible. Disabling a node (`is_active = false`) SHALL NOT change `published_at` and `unpublished_at`. For nodes that existed before these attributes appeared, `published_at` SHALL equal their creation date, `unpublished_at` SHALL be empty, and the author SHALL be the administrator from `ADMIN_EMAIL`.

The `views` counter SHALL belong to nodes of every kind (`content`, `page`, `block`), SHALL NOT depend on the language, and SHALL NOT be a field of the type. It SHALL NOT be accepted from a request (the node forms of the admin panel ignore it), but the code that creates a node SHALL be able to set the initial value (for example, when importing nodes from another site). A negative value SHALL be rejected with a validation error of the field `views`. The counter SHALL be increased atomically by a given amount (one by default): concurrent increases are not lost, and increasing the counter SHALL NOT change `updated_at`, `published_at` or any other attribute of the node. Nodes that existed before the counter appeared SHALL have `views = 0`.

#### Scenario: Default values
- **WHEN** a node is created without `published_at`, `unpublished_at`, and `is_active`
- **THEN** `is_active` equals `true`, `published_at` is the moment of creation, and `unpublished_at` is empty

#### Scenario: Author from the current user
- **WHEN** an authenticated user creates a node
- **THEN** this user becomes the author of the node

#### Scenario: Author does not change
- **WHEN** an update request of a node contains a different `user_id`
- **THEN** the author of the node remains the previous one, and there is no error

#### Scenario: Author is not accepted on creation
- **WHEN** a creation request contains the `user_id` of another user
- **THEN** the author becomes the current user

#### Scenario: A node without an author is impossible
- **WHEN** a node is created without a user (neither an explicit author nor the current user)
- **THEN** the creation is rejected, and the node is not saved

#### Scenario: Deleting an author
- **WHEN** an attempt is made to delete a user who is the author of at least one node
- **THEN** the user is not deleted

#### Scenario: Disabling keeps the date
- **WHEN** a node with `published_at` in the future and a set `unpublished_at` is disabled (`is_active = false`)
- **THEN** `published_at` and `unpublished_at` do not change

#### Scenario: Unpublishing date later than the publication date
- **WHEN** a node is saved with `published_at` 2026-12-01 10:00 and `unpublished_at` 2026-12-31 10:00
- **THEN** the node is saved with both dates

#### Scenario: Unpublishing date not later than the publication date
- **WHEN** a node is saved with an `unpublished_at` that equals or precedes `published_at`
- **THEN** saving is rejected with a validation error of the field `unpublished_at`, and the node is not changed

#### Scenario: Unpublishing date against the substituted publication date
- **WHEN** a node is created without `published_at`, and `unpublished_at` is in the past relative to the moment of creation
- **THEN** the creation is rejected with a validation error of the field `unpublished_at`

#### Scenario: Clearing the unpublishing date
- **WHEN** the date of a node with `unpublished_at` is cleared and saved
- **THEN** `unpublished_at` is empty, and the node is again without an unpublishing date

#### Scenario: Migration of existing nodes
- **WHEN** a migration is performed over a table with existing nodes
- **THEN** each node has `published_at` = the creation date, `unpublished_at` empty, `is_active = true`, and the author — the administrator from `ADMIN_EMAIL`

#### Scenario: No administrator for existing nodes
- **WHEN** a migration is performed over a non-empty table of nodes, and there is no user with `ADMIN_EMAIL`
- **THEN** the request is rejected with a validation error

#### Scenario: Default view counter
- **WHEN** a node of any kind (`content`, `page` or `block`) is created without a counter value
- **THEN** its `views` equals `1`

#### Scenario: Initial counter set by the creating code
- **WHEN** code creates a node and passes the initial `views` 1250
- **THEN** the node is saved with `views` equal to 1250

#### Scenario: Counter is not accepted from a request
- **WHEN** a create or update request of a node in the admin panel contains `views`
- **THEN** the value is ignored: a new node has `views = 1`, and an existing node keeps its counter

#### Scenario: Negative counter
- **WHEN** a node is saved with a negative `views`
- **THEN** saving is rejected with a validation error of the field `views`, and the node is not changed

#### Scenario: Increasing the counter
- **WHEN** the counter of a node with `views = 5` is increased by one, and then by 10
- **THEN** `views` equals 16

#### Scenario: Increasing does not touch other attributes
- **WHEN** the counter of a node is increased
- **THEN** `updated_at`, `published_at`, `is_active` and the values of the node do not change

#### Scenario: Migration gives existing nodes a zero counter
- **WHEN** the migration that adds the counter is performed over a table with existing nodes
- **THEN** each of them has `views = 0`, and the rollback of the migration removes the counter

### Requirement: System metadata and section fields
The system SHALL create system fields automatically on creation of a type according to a list that depends on the kind of the type, and SHALL have one list of system fields (key, value type, name, kinds, value owners, translatability) as the single source of truth for creation, backfilling, reserved keys, and the definition of the owner of a field. All system fields of metadata and sections SHALL be optional, single-value, translatable, and `is_system`; they cannot be deleted, their `type`, `is_multiple`, `is_required`, and key cannot be changed; the name, weight, description, and settings may be changed.

The list:

| key | value type | `content` | `page` | `block` |
| --- | --- | --- | --- | --- |
| `meta_title` | "short text" | node and type | node and type | none |
| `meta_description` | "text" | node and type | node and type | none |
| `meta_image` | "image" | node and type | node and type | none |
| `section_title` | "short text" | type | none | none |
| `section_description` | "formatted text" | type | none | none |
| `section_cover` | "image" | type | none | none |

"Node" means the field has values in the nodes of this type; "type" means the field has values of the type itself. A field that is absent from the column of a kind is not created for types of this kind. A non-system field SHALL belong only to nodes. A change of owners requires no new fields: types `page` already have `meta_*`.

#### Scenario: Content type
- **WHEN** a type of kind `content` is created
- **THEN** it has the fields `title`, `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`

#### Scenario: Page types
- **WHEN** a type of kind `page` is created
- **THEN** it has the fields `title`, `meta_title`, `meta_description`, `meta_image`, and has no `section_*` fields

#### Scenario: Block types
- **WHEN** a type of kind `block` is created
- **THEN** it has only the field `title`

#### Scenario: Forbidden changes to a system field
- **WHEN** the value type, multiplicity, or requiredness of a system metadata field is changed, or it is deleted
- **THEN** the change is rejected, and the field and its values remain

#### Scenario: Backfilling existing types
- **WHEN** a migration is applied to a database with existing types `content` and `page`
- **THEN** each type receives the missing system fields according to the list of its kind, and the existing fields and values do not change

#### Scenario: Key conflict during backfilling
- **WHEN** an existing type already has a user field with the key of a system metadata field
- **THEN** the migration fails with a list of the conflicting types and keys before any changes

### Requirement: Owners of field values
A field value SHALL have an owner: a node or a type. The owners of a field the system SHALL determine from the list of system fields, not from a column: a system field belongs to the owners specified in the list for the kind of its type, any other field belongs only to nodes. An attempt to write a value of a field to an owner to which it does not belong SHALL be rejected.

#### Scenario: Type value for a node field
- **WHEN** a type value is written for a user field or for `title`
- **THEN** the write is rejected with a validation error, and the value is not saved

#### Scenario: Node value for a section field
- **WHEN** a node value is written for `section_title`
- **THEN** the write is rejected with a validation error

#### Scenario: Meta field of both owners
- **WHEN** in a type of kind `content` `meta_title` is written for a node and for the type itself
- **THEN** both values are saved separately and independently

#### Scenario: Meta field of a page type
- **WHEN** in a type of kind `page` `meta_description` is written for the type itself
- **THEN** the value is saved for the type and is not mixed with the values of the nodes of this type

#### Scenario: Meta field of a block type
- **WHEN** in a type of kind `block` `meta_title` is written for the type itself
- **THEN** the write is rejected with a validation error (a block has no such field)

### Requirement: Public display settings of a type
The system SHALL store in `content_types.settings.public` the public display settings of the type: `list_fields` (the added card fields in the list), `page_fields` (the added fields of the node's page), `sort` (`field` and `direction` — `asc` or `desc`), `per_page` (an integer ≥ 0), and `pagination` (boolean). "Added" fields are the fields with `is_system = false` that hold node values and have a non-reserved key. The system SHALL reject references to other fields, to fields of another type, a non-sortable field in `sort.field`, and `per_page` outside the admissible bounds via `ValidationException`. Saving `public` SHALL NOT change the other keys of `settings`.

#### Scenario: Saving admissible settings
- **WHEN** a type is saved with `settings.public` = `{ "list_fields": ["price"], "sort": { "field": "published_at", "direction": "desc" }, "per_page": 10, "pagination": true }`, and the field `price` is an added field of the type
- **THEN** the settings are saved, and the key `settings.list` remains unchanged

#### Scenario: System or foreign field is rejected
- **WHEN** `list_fields` or `page_fields` contains `meta_title`, `title`, or a key that is absent from the fields of the type
- **THEN** saving ends with a `ValidationException` with the key `settings`

#### Scenario: Invalid direction or count
- **WHEN** `sort.direction` is not `asc`/`desc`, or `per_page` is negative or greater than `content.max_per_page`
- **THEN** saving ends with a `ValidationException`

### Requirement: Default values of the public settings
Without settings (`settings.public` is absent or empty) the system SHALL apply: in the card — only `title` (an empty `list_fields`); on the page — all added fields of the type, sorting by `published_at` in descending order, `per_page = 0` (all nodes). An absent key `page_fields` SHALL mean "all added fields" at the moment of reading, so a new added field immediately enters the page; an explicitly saved `page_fields` (even an empty one) SHALL mean exactly that list.

#### Scenario: New field appears on the page
- **WHEN** a type has no `page_fields` key, and a new field is added
- **THEN** the effective set of page fields includes the new field without changing the settings

#### Scenario: Explicit list does not grow by itself
- **WHEN** `page_fields` is saved as `["price"]`, and a new field is added
- **THEN** the effective set of page fields remains `["price"]`

### Requirement: Public settings depend on the kind of the type
The system SHALL accept `list_fields`, `sort`, `per_page`, and `pagination` only for types of kind `content`; for `page` and `block` an attempt to specify them SHALL end with a `ValidationException`, and `page_fields` is accepted for all kinds. The meta fields, the service attributes of the node, and the section values of the type SHALL remain available to the template regardless of the settings.

#### Scenario: List for a page is rejected
- **WHEN** a type of kind `page` is saved with `settings.public.per_page`
- **THEN** saving ends with a `ValidationException`

#### Scenario: Reference to a deleted field
- **WHEN** a field mentioned in `list_fields` is deleted
- **THEN** reading the settings drops this key and does not fail

### Requirement: Top-level public paths without collisions
The slug of a node of kind `page`, the slug of a type of kind `content`, and the reserved site paths SHALL share one namespace of the first segment of the public address, and SHALL NOT match one another (case-insensitively). A type slug of several segments SHALL be compared with the reserved paths by its first segment, and with the slugs of pages as a whole (a page slug has one segment, so only a type slug of one segment can match it). The reserved paths are the service paths of the application and the directories of the public directory: at least `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`; the list SHALL be configurable, and each first segment of a service route of the application SHALL be in it. The slug of a page SHALL NOT equal the slug of any type `content` or a reserved path; the slug of a type `content` SHALL NOT equal the slug of any page, and its first segment SHALL NOT be a reserved path. The slugs of nodes of kinds `content` and `block` do NOT pass this check. The check SHALL be performed on creation of a node or a type and on a change of the slug (and not on every save), in the forms and at the model level, so that an existing match does not block other edits. A violation SHALL reject saving with a validation error of the field `slug`.

#### Scenario: Page with the slug of a type
- **WHEN** a node of kind `page` with the slug `news` is created or changed, and a type of kind `content` has the slug `news`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Type with the slug of a page
- **WHEN** a type of kind `content` is created or gets the slug `about-us`, and a node of kind `page` already has the slug `about-us`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Comparison case-insensitively
- **WHEN** a type is saved with the slug `News`, and a page has the slug `news`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved path for a page
- **WHEN** a node of kind `page` is saved with the slug `admin`, `login`, or `storage`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved path for a type
- **WHEN** a type of kind `content` is saved with the slug `admin`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved first segment of a nested slug
- **WHEN** a type of kind `content` is saved with the slug `admin/reports`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved word inside a nested slug
- **WHEN** a type of kind `content` is saved with the slug `news/admin`
- **THEN** the type is saved, because only the first segment is compared with the reserved paths

#### Scenario: Page with the first segment of a nested type
- **WHEN** a node of kind `page` has the slug `common`, and a type has the slug `common/institutions` and no type has the slug `common`
- **THEN** both are saved, because the page `/common` and the type list `/common/institutions` are different addresses

#### Scenario: Nodes content and block are not checked
- **WHEN** a node of kind `content` is saved with the slug `news` or `admin`, and the type has the slug `news`
- **THEN** the saving passes, because these slugs are not on the first level of the path

#### Scenario: A match in existing data does not block other edits
- **WHEN** a page and a type already have an identical slug (created before this requirement), and the `title` or another field of the page or the name of the type is changed without changing the slug
- **THEN** the change is saved

#### Scenario: Service route without protection
- **WHEN** a service route appears in the application whose first segment is absent from the list of reserved paths
- **THEN** an automatic test fails and requires adding the segment to the list

#### Scenario: Language prefixes
- **WHEN** the slug of a page `en` or `uk` is checked
- **THEN** the requirement does not take language codes into account (they will be added by a separate change about site languages)

### Requirement: Hierarchy of type slugs
A type of kind `content` that has a parent type and a non-empty slug SHALL have a slug that starts with the slug of the parent type, then `/`, and then a non-empty tail of one or more segments. The parent SHALL have a slug for this: a type with a slug whose parent has no slug SHALL be rejected. A type with a parent and without a slug SHALL remain admissible. A type without a parent MAY have any admissible slug. The comparison with the parent's slug SHALL be case-insensitive. The rule SHALL be checked when the type is created and when its slug or its parent type changes, and not on every save, so that data created earlier does not block other edits. A violation SHALL reject saving with a validation error of the field `slug`.

Changing or clearing the slug of a type SHALL be rejected while at least one of its direct children has a slug; the error SHALL say that the slugs of the child types must be changed first. There SHALL be no automatic change of the slugs of the children. A type whose children have no slugs MAY change its slug freely. The slug of a type MAY be changed when its children have no slug.

#### Scenario: Child slug with the prefix of the parent
- **WHEN** the type `common` has the slug `common`, and a child type is created with the slug `common/institutions`
- **THEN** the child is saved

#### Scenario: Deeper tail
- **WHEN** the child of `common` is created with the slug `common/city/awards`
- **THEN** the child is saved

#### Scenario: Slug without the prefix of the parent
- **WHEN** a child of the type with the slug `common` is created with the slug `institutions` or `other/institutions`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Slug equal to the slug of the parent
- **WHEN** a child of the type with the slug `common` is created with the slug `common`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: A prefix that is not a whole segment
- **WHEN** a child of the type with the slug `common` is created with the slug `commonplace/x`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Parent without a slug
- **WHEN** a child with the slug `common/institutions` is created, and its parent has no slug
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Child without a slug
- **WHEN** a child type is created without a slug, and its parent has a slug
- **THEN** the child is saved without a public list

#### Scenario: Changing the parent
- **WHEN** the parent type of an existing type with the slug `common/institutions` is changed to a type with the slug `collectives`
- **THEN** the change is rejected with a validation error of the field `slug`, and the parent remains the previous one

#### Scenario: Removing the parent
- **WHEN** an existing type with the slug `common/institutions` is saved without a parent type
- **THEN** the type is saved, and its slug remains

#### Scenario: Changing the slug of a parent that has children with slugs
- **WHEN** the slug of the type `common` is changed to `info`, and a child has the slug `common/institutions`
- **THEN** the change is rejected with a validation error of the field `slug` saying that the slugs of the child types must be changed first, and the slugs of both types remain

#### Scenario: Clearing the slug of a parent that has children with slugs
- **WHEN** the slug of the type `common` is cleared, and a child has a slug
- **THEN** the change is rejected with a validation error of the field `slug`

#### Scenario: Changing the slug of a parent whose children have no slugs
- **WHEN** the slug of the type `common` is changed, and its children have no slug
- **THEN** the change is saved

#### Scenario: A type of a deeper level
- **WHEN** a type `deep` has the parent `common/institutions` (itself a child of `common`), and `deep` gets the slug `common/institutions/deep`
- **THEN** the type is saved

#### Scenario: Existing data does not block other edits
- **WHEN** an existing child type has a slug without the prefix of its parent (created before this requirement), and its name is changed without changing the slug or the parent
- **THEN** the change is saved

### Requirement: A node address does not hide a type list
The public address of a node of kind `content` is the slug of its type, `/`, and the slug of the node. When this address equals the slug of a type of kind `content`, the public site shows the list of that type, so the node would be unreachable. Saving such a node SHALL be rejected with a validation error of the field `slug` when the node is created or its slug changes. Likewise, giving a type a slug SHALL be rejected with a validation error of the field `slug` when the slug equals the address of an existing node (the slug of its type, `/`, and the node's slug), or when the type slug of a type with nodes is changed so that the address of one of its nodes becomes equal to the slug of another type. An existing match SHALL NOT block other edits.

#### Scenario: Node address equal to a type slug
- **WHEN** a node with the slug `institutions` is created in the type with the slug `common`, and another type has the slug `common/institutions`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Type slug equal to a node address
- **WHEN** a type is created with the slug `common/work-plan`, and the type with the slug `common` has a node with the slug `work-plan`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Node of a type without a slug
- **WHEN** a node is created in a type without a slug
- **THEN** the check does not apply, and the node is saved

#### Scenario: Node address that is not a type slug
- **WHEN** a node with the slug `news` is created in the type with the slug `news`, and no type has the slug `news/news`
- **THEN** the node is saved

#### Scenario: Changing the slug of a type with nodes
- **WHEN** the slug of a type with a node `work-plan` is changed to `common`, and another type has the slug `common/work-plan`
- **THEN** the change is rejected with a validation error of the field `slug`

### Requirement: Legacy slugs of imported nodes
The strict format of a node slug SHALL stay the rule for every slug entered by a user or generated by the system. The code that imports nodes from the legacy site MAY, by an explicit option of node creation, save a slug in the legacy format instead: lowercase Latin letters, digits, hyphens (also repeated, leading or trailing) and underscores, up to 190 characters, at least one character. All other node rules SHALL still apply to such a node (uniqueness case-insensitively, the `block-` prefix of blocks, the page restrictions, and the rule that the address of a node does not hide a type list). A node with a legacy slug SHALL be saved again without change of the slug, and the legacy format SHALL NOT be accepted for a new or changed slug outside the import.

#### Scenario: Import keeps a legacy slug
- **WHEN** the import creates a node with the slug `volnovaha---misto-geroy-ukrayini` or `jam-session-` or `etno_wave-poltava`
- **THEN** the node is saved with this slug

#### Scenario: Strict format outside the import
- **WHEN** a node is created or its slug is changed without the import option, with the slug `a---b`, `jam-session-` or `etno_wave`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Other rules apply to a legacy slug
- **WHEN** the import creates a node with a legacy slug that another node already has (in any case), or a block with a slug without `block-`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Unsafe characters
- **WHEN** the import creates a node with the slug `a/b`, `a b` or `Ab`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Saving a legacy node again
- **WHEN** a node with a legacy slug is saved again with the same slug
- **THEN** saving passes

### Requirement: Translated field labels
A field SHALL be able to hold translations of its label (the base label is its `name`). Translations SHALL be stored in the field's `settings` under the key `labels`, an object that maps a normalized language code to the translated text (up to 255 characters). The key SHALL be optional; an empty object SHALL NOT be stored. A label SHALL be readable for a language without any fallback (a missing label reads as missing). Cloning a type SHALL copy the translated labels of its fields. A language for which a translated label exists SHALL NOT be deletable, as with any other stored translation.

#### Scenario: Stored shape
- **WHEN** the labels `en` = "Opening hours" and `pl` = "Godziny otwarcia" are saved for a field
- **THEN** the field's settings hold both under `labels` keyed by normalized codes

#### Scenario: Missing label
- **WHEN** the label of a field is read in a language with no stored label
- **THEN** it reads as missing

#### Scenario: Cloning
- **WHEN** a type whose field has translated labels is cloned
- **THEN** the field of the clone has the same translated labels

#### Scenario: Deleting a language with a label
- **WHEN** a language that has a translated label of a field is deleted
- **THEN** the deletion is rejected

#### Scenario: Deleting a field
- **WHEN** a field with translated labels is deleted
- **THEN** its labels are deleted with it

### Requirement: Language codes are reserved first segments
The codes of the content languages (the base language code and every additional language, active or not) SHALL be part of the namespace of the first segment of public addresses and SHALL NOT match, case-insensitively, the slug of a page, the first segment of the slug of a type of kind `content`, or a reserved site path. Saving a page, a type or the list of languages that would create such a match SHALL be rejected with an error on the field that causes it (`slug` of the page or the type; `code` of the language, or the base language code).

#### Scenario: Page named like a language
- **WHEN** the language `en` exists and a page with the slug `en` is saved
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Type named like a language
- **WHEN** the language `pl` exists and a type of kind `content` is saved with the slug `pl/news`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Language named like a page
- **WHEN** a page with the slug `faq` exists and a language with the code `faq` is added
- **THEN** adding is rejected with an error of the field `code`

#### Scenario: Base language code
- **WHEN** the base language is `uk` and a page with the slug `uk` is saved
- **THEN** saving is rejected with an error of the field `slug`

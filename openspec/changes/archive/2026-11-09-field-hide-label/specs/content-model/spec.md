## MODIFIED Requirements

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

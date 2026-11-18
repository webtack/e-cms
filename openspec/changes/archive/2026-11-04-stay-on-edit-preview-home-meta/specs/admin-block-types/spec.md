# Spec Delta

## MODIFIED Requirements

### Requirement: Редагування типу блоку
The admin SHALL have the form `/admin/block-types/{key}/edit` with the fields of the name, the parent type and the description, and the key read-only. Saving SHALL change only the name, the parent type and the description, and the user SHALL stay on the edit form; `key` and `kind` do not change. A type itself or its descendant cannot be chosen as the parent. For a key that is not among the types of kind `block` (in particular a key of a type `content` or `page`), all section routes SHALL respond with 404, and `/admin/content-types/{key}/**` and `/admin/page-types/{key}/**` for a type `block` — also 404.

#### Scenario: Successful saving
- **WHEN** the user changes the name of a block type and saves
- **THEN** the name is updated, and the user stayed on the edit form of the type with a message

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/block-types/{key}/edit` for the key of a type of kind `content` or `page`
- **THEN** the response is 404

#### Scenario: Other sections do not open a block type
- **WHEN** the user opens `/admin/content-types/{key}/edit` or `/admin/page-types/{key}/edit` for the key of a type of kind `block`
- **THEN** the response is 404

#### Scenario: Descendant as the parent
- **WHEN** the edit request names a descendant of the type as its parent
- **THEN** the saving is rejected with an error next to the field of the parent type

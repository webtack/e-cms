## ADDED Requirements

### Requirement: Clone item in the row menu
The "⋮" menu of each row in the content, page and block type tables SHALL have a "Clone" item, placed after "Nodes"/"Preview" and before the divider that precedes "Delete". Selecting it SHALL open the clone dialog for that type. The item SHALL be available only to users who have the role right `types`.

#### Scenario: Item is present
- **WHEN** the user opens the "⋮" menu in the row of a type
- **THEN** the menu contains the active item "Clone" with a translated label

#### Scenario: Dialog opens
- **WHEN** the user selects "Clone" in the row of the type with the key `news`
- **THEN** a dialog opens with the fields name, key and (for content types) slug

### Requirement: Clone dialog and validation
The clone dialog SHALL ask for `name`, `key`, `parent type` and, only for content types, `slug`. `name`, `key` and `slug` SHALL start empty so that the user must choose new values; `parent type` SHALL be pre-filled with the parent of the source type and the user MAY change it or choose "No parent type" (only types of the same kind are offered). The values SHALL be validated by the same rules as the create form of a type of that kind (required name, key format, uniqueness and reserved keys, slug format, uniqueness, reserved paths, a parent of the same kind, and the slug hierarchy for the chosen parent). Errors SHALL be shown next to the fields and the entered values SHALL be kept.

#### Scenario: Key collision
- **WHEN** the user submits the dialog with a key that already belongs to another type
- **THEN** no type is created and an error is shown next to the key field

#### Scenario: Reserved key
- **WHEN** the user submits a key from the reserved type keys
- **THEN** the error "This key is reserved" is shown next to the key field

#### Scenario: Slug of a content type
- **WHEN** the user submits a slug that is already used by another type, by a page or by a node address
- **THEN** no type is created and the usual slug error is shown next to the slug field

#### Scenario: Page and block types have no slug
- **WHEN** the user opens the clone dialog in the page or block section
- **THEN** the dialog has no slug field

### Requirement: Result of cloning
Cloning SHALL create a new type of the same kind and with the parent type chosen in the dialog, named, keyed and slugged as entered, with an empty description, and SHALL copy every field definition of the source type to it: key, name, description, value type, multiplicity, maximum items, requiredness, translatability, label visibility, order and the field settings. The system fields of the new type SHALL be those every new type of the kind gets, and their editable attributes (name, description, order, label visibility, settings) SHALL equal those of the source. The type settings (node list settings and public display settings) of the source SHALL be copied to the new type unchanged. The operation SHALL be atomic: on any failure no type and no field is created. Nodes, field values, the type's own values (SEO), the description, role levels and favorites SHALL NOT be copied, and the source type SHALL stay unchanged. After success the user SHALL be taken to the fields page of the new type with a `success` message.

#### Scenario: Fields are copied
- **WHEN** the type `news` has the fields `title`, `body` (required, translatable) and `cover` (an image with settings), and the user clones it as `press`
- **THEN** the type `press` exists with the same three fields, the same order and the same required, translatable and settings values, and the user sees the fields page of `press`

#### Scenario: Type settings are copied
- **WHEN** the type `news` has node list settings (visible columns, sort) and public display settings (list fields, per page) and is cloned
- **THEN** the new type has the same settings

#### Scenario: Parent can be changed
- **WHEN** the source has the parent `common` and the user clears the parent in the dialog
- **THEN** the new type has no parent

#### Scenario: Nothing else is copied
- **WHEN** the type `news` has nodes, SEO values and a description and is cloned
- **THEN** the new type has no nodes, no values of its own and an empty description, and `news` keeps all its nodes and values

#### Scenario: Kind is kept
- **WHEN** a page type is cloned from the page types section
- **THEN** the new type is of kind `page` and appears in the page types table

#### Scenario: Atomic failure
- **WHEN** creating any field of the new type fails
- **THEN** the new type is not created either and an error is reported

#### Scenario: Source in another section
- **WHEN** a request clones a type through the route of a section other than the kind of the type
- **THEN** the response is 404

#### Scenario: Access
- **WHEN** a user without the role right `types` sends a clone request
- **THEN** the response is 403

# Spec Delta

## MODIFIED Requirements

### Requirement: Result of cloning
Cloning SHALL create a new type of the same kind and with the parent type chosen in the dialog, named, keyed and slugged as entered, with an empty description, and SHALL copy every field definition of the source type to it: key, name, description, value type, multiplicity, maximum items, requiredness, translatability, label visibility, order and the field settings. The system fields of the new type SHALL be those every new type of the kind gets, and their editable attributes (name, description, order, label visibility, settings) SHALL equal those of the source. The type settings (node list settings and public display settings) of the source SHALL be copied to the new type unchanged. Cloning SHALL also copy the theme templates bound to the key of the source type, in every theme that has them, to the key of the new type, as described in the requirement "Theme templates of a cloned type". The operation SHALL be atomic: on any failure no type, no field and no theme file is created. Nodes, field values, the type's own values (SEO), the description, role levels and favorites SHALL NOT be copied, and the source type SHALL stay unchanged. After success the user SHALL be taken to the fields page of the new type with a `success` message.

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
- **THEN** the new type is not created either, no theme file is created and an error is reported

#### Scenario: Source in another section
- **WHEN** a request clones a type through the route of a section other than the kind of the type
- **THEN** the response is 404

#### Scenario: Access
- **WHEN** a user without the role right `types` sends a clone request
- **THEN** the response is 403

## ADDED Requirements

### Requirement: Theme templates of a cloned type
When a type is cloned, the system SHALL copy, in every theme under `themes/`, the templates bound to the key of the source type to the key of the new type, and SHALL copy only what that theme has:
- the directory `views/{source}/` SHALL be copied to `views/{new}/` with its files, except the files bound to one node, whose names contain `--` followed by the node id (for example `preview--7.blade.php`);
- the file `views/blocks/block-{source}.blade.php` SHALL be copied to `views/blocks/block-{new}.blade.php`; the files bound to one block, `views/blocks/block-{source}--{slug}.blade.php`, SHALL NOT be copied.

Files SHALL be copied as they are: their content SHALL NOT be read or changed. A theme without such templates SHALL be left untouched, and the source templates SHALL stay unchanged. The copy SHALL NOT overwrite anything: if a target directory or file already exists, or a file cannot be written, the clone SHALL fail as a whole (no type, no field, no theme file remains, and the files already created by this clone are removed) and an error SHALL be reported to the user, who stays on the clone dialog's page with the entered values kept. Templates of page types (`pages/page-{source}`) and the type keys written inside theme code are not covered.

#### Scenario: Type directory is copied in every theme
- **WHEN** two themes have the directory `views/news/` with `list`, `preview` and `details` templates and the type `news` is cloned as `press`
- **THEN** both themes get `views/press/` with the same three files, and `views/news/` is unchanged

#### Scenario: Node-bound files are skipped
- **WHEN** `views/news/` contains `details.blade.php` and `details--7.blade.php` and the type is cloned as `press`
- **THEN** `views/press/` contains `details.blade.php` and does not contain `details--7.blade.php`

#### Scenario: Block template is copied
- **WHEN** the block type `contacts` has `views/blocks/block-contacts.blade.php` and `views/blocks/block-contacts--footer.blade.php` and is cloned as `office`
- **THEN** `views/blocks/block-office.blade.php` exists with the content of `block-contacts.blade.php`, and there is no `block-office--footer.blade.php`

#### Scenario: Content is not changed
- **WHEN** a copied template contains the word `news` in its comments or markup
- **THEN** the copy has exactly the same bytes as the source

#### Scenario: Nothing to copy
- **WHEN** no theme has templates bound to the key of the cloned type
- **THEN** the clone succeeds and no theme file is created

#### Scenario: Target already exists
- **WHEN** `views/press/` already exists in a theme and the type `news` is cloned as `press`
- **THEN** the clone fails with an error, the type `press` is not created, nothing in the existing `views/press/` is changed, and the files this clone already copied into other themes are removed

#### Scenario: Theme directory is not writable
- **WHEN** a theme directory cannot be written during the clone
- **THEN** the clone fails with an error, the type is not created and no file copied by this clone remains

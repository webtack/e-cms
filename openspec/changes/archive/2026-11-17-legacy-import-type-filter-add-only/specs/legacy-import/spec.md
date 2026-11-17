## MODIFIED Requirements

### Requirement: Import command and its options
The system SHALL provide the console command `legacy:import` that reads the legacy database through a dedicated connection configured in `.env` and the legacy files from a configurable directory (by default `storage/app/public/_old`). The command SHALL accept `--only` (a comma-separated list of steps: `users`, `types`, `files`, `nodes`), `--type` (a comma-separated list of content type keys), `--dry-run`, `--fresh`, `--limit` (a maximum number of nodes per kind, for trial runs) and `--source` (the files directory). Without `--only` all steps SHALL run in this order: users, types and fields, files, blocks and pages, then content nodes. The command SHALL fail with a clear message, writing nothing, when the legacy connection cannot be opened or the files directory does not exist (except for steps that need no files).

#### Scenario: Whole import
- **WHEN** `legacy:import` runs against a reachable legacy database and an existing files directory
- **THEN** users, types, fields, files and nodes are imported, and a report is printed

#### Scenario: A single step
- **WHEN** `legacy:import --only=types` runs
- **THEN** only the missing types and fields are created, and no node or file is touched

#### Scenario: Legacy database unavailable
- **WHEN** the legacy connection fails
- **THEN** the command stops with a message naming the connection, and nothing is written

#### Scenario: Missing files directory
- **WHEN** the files directory does not exist and the `files` or `nodes` step is requested
- **THEN** the command stops with a message naming the directory, and nothing is written

#### Scenario: Trial run limited by count
- **WHEN** `legacy:import --only=nodes --limit=20` runs
- **THEN** at most 20 nodes of each kind are imported

### Requirement: Repeatable import
Running the import again SHALL NOT duplicate anything and SHALL NOT change anything that already exists: nodes SHALL be matched by the legacy `uuid`, types by the key, fields by the type and key, users by the email, and files by a stored map of legacy file records to the imported media. A node that already exists SHALL be skipped as a whole (its values, state, dates, author and view counter stay as they are) and counted as `skipped`; only nodes that do not exist yet SHALL be created. An account that already exists SHALL NOT be changed (name, role, password, state) and SHALL be counted as `skipped`; it still serves as the author of the nodes it wrote. A repeated run SHALL NOT import a file that is already mapped. Each node SHALL be imported in its own transaction, so a failing node is reported and skipped without rolling back the others. The command SHALL NOT delete anything except with `--fresh`.

#### Scenario: Second run
- **WHEN** the import runs twice without changes in the source
- **THEN** the second run creates nothing, imports no files again, and reports every node as skipped

#### Scenario: New nodes only
- **WHEN** the legacy site gained a news item after the first run and the import runs again
- **THEN** only that news item is created, and every other node stays as it was

#### Scenario: Editors' changes survive
- **WHEN** an editor changed the title of an imported node and the import runs again
- **THEN** the title of that node is not changed

#### Scenario: Existing account is kept
- **WHEN** an account with the email of a legacy editor already exists with another name and role, and the import runs
- **THEN** the account is not changed, and the nodes of that editor are created with it as the author

#### Scenario: Author not in the database
- **WHEN** the author of a node is not imported and no account with the configured email exists
- **THEN** the first administrator of `legacy.users` becomes the author of the node

#### Scenario: A failing node
- **WHEN** one node fails validation
- **THEN** it is listed in the report with the reason, and the other nodes are imported

#### Scenario: View counters are not reset
- **WHEN** a node was imported, its counter on the new site was increased, and the import runs again
- **THEN** the counter of the node keeps its current value

#### Scenario: Files added later
- **WHEN** files missing during the first run are copied into the directory and the import runs again
- **THEN** the nodes imported earlier are skipped and keep the values without these files, and only nodes created by this run get the files that exist

### Requirement: Users
The import SHALL create the two editor accounts of the legacy site that do not exist yet, the legacy user with id 2 as an administrator and the legacy user with id 5 as a manager, keeping the name, email, and password hash. The manager role SHALL be taken by the name `Manager` and created, when absent, as an ordinary role with the level `any` for every imported node type and for the media library. Other legacy users SHALL NOT be imported. An existing account with the same email SHALL NOT be changed at all (password, name, role and state stay), SHALL be counted as `skipped`, and SHALL still serve as the author of the nodes of that editor.

#### Scenario: Accounts are created
- **WHEN** the import runs on a site without these emails
- **THEN** the administrator and the manager accounts exist with the legacy password hashes, and the editor can sign in with the old password

#### Scenario: Spam accounts
- **WHEN** the legacy table contains other registrations
- **THEN** they are not imported

#### Scenario: Existing account
- **WHEN** an account with the legacy email already exists
- **THEN** no second account is created, and the account is not changed

#### Scenario: Manager rights
- **WHEN** the import finishes
- **THEN** the manager role has the level `any` for every imported content, page and block type and for the media library

## ADDED Requirements

### Requirement: Type filter
With `--type=<key>[,<key>]` the command SHALL import only the content nodes whose legacy type is one of the listed keys (exact keys; the child types of a type are not included), together with what these nodes need: the listed types, their parent types and their fields; the blocks that the nodes refer to through block-reference fields, with the types and fields of these blocks; the files of the selected nodes and blocks, their YouTube previews and metadata images, and the images of their rich text. Pages and all other nodes SHALL NOT be imported, and files that nothing selected refers to SHALL NOT be copied. The step `users` SHALL run as without the option. An unknown key (a legacy content type that does not exist) SHALL make the command stop with a message naming it, writing nothing. `--type` SHALL combine with `--only`, `--dry-run` and `--limit`; `--limit` counts the selected nodes of each kind. `--type` SHALL NOT be combined with `--fresh`: the command SHALL stop with a message and write nothing.

#### Scenario: One type
- **WHEN** `legacy:import --type=news` runs and the type `news` and its nodes are missing on the new site
- **THEN** the type `news` with its parent types and fields is created, only the news nodes are created, and only their files are copied

#### Scenario: Referenced blocks come along
- **WHEN** `legacy:import --type=institutions` runs and an institution refers to the block `block-pole-poltavskoyi-bitvi`
- **THEN** that block and its block type are created when missing, and the field `contacts_reference` of the institution refers to it

#### Scenario: Other kinds are left out
- **WHEN** `legacy:import --type=news` runs
- **THEN** no page, no node of another type, and no block that no news item refers to is imported

#### Scenario: Unknown type
- **WHEN** `legacy:import --type=bogus` runs
- **THEN** the command stops with a message naming `bogus`, and nothing is written

#### Scenario: Not with fresh
- **WHEN** `legacy:import --type=news --fresh` runs
- **THEN** the command stops with a message, and nothing is deleted or written

#### Scenario: Existing news are kept
- **WHEN** `legacy:import --type=news` runs and some news items were imported earlier
- **THEN** those items are skipped, and only the missing ones are created

### Requirement: Existing types and fields are not changed
The import SHALL NOT change a content type, block type or page type that already exists (name, description, slug, parent, type values `meta_title`, `meta_description`, `meta_image` and `section_title`, and the list of page fields), and SHALL NOT change a field that already exists (name, description, required flag, weight and settings). A type that does not exist SHALL be created with its fields, type values and, when it has hidden legacy fields, its list of page fields. A field that does not exist in an existing type SHALL be added, so that nodes can store their values; it SHALL NOT change the list of page fields of that type. Existing types and fields SHALL be counted as `unchanged`.

#### Scenario: Edited type is kept
- **WHEN** an editor renamed the type `news` and the import runs again
- **THEN** the type keeps the new name

#### Scenario: Edited field is kept
- **WHEN** an editor changed the label of the field `body` of `news` and the import runs again
- **THEN** the field keeps the new label

#### Scenario: New field in an existing type
- **WHEN** the legacy values contain a field of `news` that does not exist in the new type `news`
- **THEN** the field is added to the type, and the existing fields are not changed

#### Scenario: Type values are written once
- **WHEN** the type `news` is created by the import
- **THEN** its `meta_title`, `meta_description` and `section_title` are set from the legacy data, and a later run does not rewrite them

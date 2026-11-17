# Spec Delta

## Purpose
The command that moves the content, files, view counters and editors' accounts of the legacy site (kultura-poltava) into the content model of this CMS, repeatably and with a report.

## ADDED Requirements

### Requirement: Import command and its options
The system SHALL provide the console command `legacy:import` that reads the legacy database through a dedicated connection configured in `.env` and the legacy files from a configurable directory (by default `storage/app/public/_old`). The command SHALL accept `--only` (a comma-separated list of steps: `users`, `types`, `files`, `nodes`), `--dry-run`, `--fresh`, `--limit` (a maximum number of nodes per kind, for trial runs) and `--source` (the files directory). Without `--only` all steps SHALL run in this order: users, types and fields, files, blocks and pages, then content nodes. The command SHALL fail with a clear message, writing nothing, when the legacy connection cannot be opened or the files directory does not exist (except for steps that need no files).

#### Scenario: Whole import
- **WHEN** `legacy:import` runs against a reachable legacy database and an existing files directory
- **THEN** users, types, fields, files and nodes are imported, and a report is printed

#### Scenario: A single step
- **WHEN** `legacy:import --only=types` runs
- **THEN** only types and fields are created or updated, and no node or file is touched

#### Scenario: Legacy database unavailable
- **WHEN** the legacy connection fails
- **THEN** the command stops with a message naming the connection, and nothing is written

#### Scenario: Missing files directory
- **WHEN** the files directory does not exist and the `files` or `nodes` step is requested
- **THEN** the command stops with a message naming the directory, and nothing is written

#### Scenario: Trial run limited by count
- **WHEN** `legacy:import --only=nodes --limit=20` runs
- **THEN** at most 20 nodes of each kind are imported

### Requirement: What is imported and what is not
The import SHALL carry over: the content types of the legacy `node_type` (kind `content`), `block_type` (kind `block`) and `page_type` (kind `page`) with their hierarchy; the fields of these types from `node_fields`, `block_fields` and `page_fields`, and the fields that have values but no definition; the nodes of `node`, `block` and `page` with the original `uuid`, `slug`, publication state, creation and update dates, view counter and author; the values of all fields; the files the imported data refers to; the metadata of nodes and of types; and the users with ids 2 and 5. The import SHALL NOT carry over menus, site settings, the activity log, telegram messages, comments, the sitemap settings, OAuth tables, image styles, other users, files that nothing refers to, and the legacy `styles/` and `default/` directories.

#### Scenario: Types and hierarchy
- **WHEN** the legacy type `institutions` has the parent `common` and the path `common/institutions`
- **THEN** the new type has the key `institutions`, the kind `content`, the parent type `common`, and the slug `common/institutions`

#### Scenario: Type of another kind
- **WHEN** the legacy block type `contacts` and the page type `static_pages` are imported
- **THEN** they become types of kind `block` and `page` and have no slug

#### Scenario: Not imported
- **WHEN** the import finishes
- **THEN** no menu, site setting, activity record, telegram message or comment appears in the new site, and the legacy accounts other than ids 2 and 5 are not created

### Requirement: Nodes keep their identity and state
An imported node SHALL keep the legacy `uuid`, the `slug` as it is, `published_at` equal to the legacy creation date (the update date when the creation date is empty), `is_active` equal to the legacy `status`, the legacy `views` as the view counter, and the legacy creation and update dates as `created_at` and `updated_at`. The author SHALL be the new user imported from the legacy user (the administrator for id 2, the manager for id 5). Blocks SHALL keep their slugs that begin with `block-`. The page `contacts` SHALL be imported as a node of the page type `static_pages`.

#### Scenario: Original identity
- **WHEN** a legacy news item with the uuid `U`, the slug `osin-zdivuy-mene`, the status 1 and 1250 views is imported
- **THEN** the new node has the uuid `U`, that slug, `is_active = true`, 1250 views, and the legacy creation date as `published_at` and `created_at`

#### Scenario: Disabled node
- **WHEN** a legacy node has the status 0
- **THEN** the imported node is disabled (`is_active = false`) and not visible on the site

#### Scenario: Author mapping
- **WHEN** a legacy node was created by the user with id 5
- **THEN** its author is the manager account created by the import

### Requirement: Value mapping
The import SHALL map the legacy values to the fields of the new types as follows. The title (`node_title`, `block_title`, `page_title`) SHALL become the system field `title`. Long text (`field_textarea`) SHALL become a rich text value. Short text (`field_text`) SHALL become a string. A link (`field_link`: address and title) SHALL become a link value with its title. A YouTube video (`field_youtube`: link, preview image, title, description) SHALL become a YouTube value whose preview is an imported image file. A date (`field_date`: begin and end) SHALL become the begin value of one multiple field `date` of the type `datetime` with at most two values, which is required when the legacy field is required; the end SHALL be added as a second value, after the begin, only when it is not empty and differs from the begin. A reference to a type (`field_reference_node_type`) SHALL become a type reference to the type with that key. A reference to a block (`field_reference_block`, whose value is the block slug) SHALL become a node reference to the block with that slug. A file (`field_file`) SHALL become an image value when the field is defined as an image or, for a field without a definition, when the file is an image, otherwise a file value; the order of files SHALL be kept. The language `ua` SHALL become the base language `default`. The multiplicity of a field defined in the legacy type SHALL be kept; for a field without a definition it SHALL be multiple when at least one existing node has more than one value.

#### Scenario: Title and text
- **WHEN** a news item has the title "Осінь, здивуй мене" and a text with paragraphs
- **THEN** the new node has this `title` and the `body` as rich text

#### Scenario: Event dates
- **WHEN** an event has the legacy date from 2024-05-01 18:00 to 2024-05-01 20:00
- **THEN** its field `date` holds two values in this order: 2024-05-01 18:00 and 2024-05-01 20:00, and the field is multiple, allows at most two values, and is required because the legacy field of events is required

#### Scenario: Event date with the same end
- **WHEN** an event has the legacy date from 2024-05-01 18:00 to 2024-05-01 18:00
- **THEN** its field `date` holds one value: 2024-05-01 18:00

#### Scenario: Event date without an end
- **WHEN** an event has a legacy begin date and an empty end
- **THEN** its field `date` holds one value, the begin

#### Scenario: Gallery order
- **WHEN** a news item has three gallery files with weights 0, 1 and 2
- **THEN** the field `gallery` holds the three new files in this order

#### Scenario: Reference to a block
- **WHEN** an institution refers to the block with the slug `block-pole-poltavskoyi-bitvi`
- **THEN** its field `contacts_reference` refers to the imported block with this slug

#### Scenario: Reference to a type
- **WHEN** an institution refers to the type `collectives_ckd`
- **THEN** its field `collectives_reference` refers to the type with the key `collectives_ckd`

#### Scenario: Link with a title
- **WHEN** a contact block has the link `https://www.facebook.com/x` titled "Facebook"
- **THEN** the block has a link value with this address and the title "Facebook"

#### Scenario: YouTube video
- **WHEN** a news item has a YouTube link with a preview file, a title and a description
- **THEN** the node has a YouTube value with these title and description, and the preview is an imported image

#### Scenario: Field without a definition
- **WHEN** the legacy values contain the field `preview` of news that is absent from `node_fields`
- **THEN** the new type `news` gets the field `preview` of the type `image`, and the report lists it as a field found only in the data

### Requirement: Legacy field settings
The import SHALL carry the legacy field setting "hidden label" (`config.hidden_label`) into the field setting "Hide label": the label of the new field SHALL be hidden exactly when the legacy value is `true`, and shown when the value is `false` or the key is absent. The legacy `show_label` SHALL NOT be used. The import SHALL carry the legacy field flag "hidden" into the "Public display" of the type: for a type that has at least one hidden field and has no saved list of page fields, the import SHALL save the list of page fields with every user field of the type except the hidden ones, in the order of the field weights. The import SHALL NOT replace a list of page fields that already exists, SHALL NOT save one for a type without hidden fields, and SHALL import the values of a hidden field like those of any other field. The report SHALL list the types that received a list of page fields.

#### Scenario: Hidden label
- **WHEN** the legacy field `news.body` has `hidden_label` set to true and `news.cover` has it set to false
- **THEN** the new field `body` hides its label and the field `cover` shows it

#### Scenario: Missing key
- **WHEN** a legacy field has no `hidden_label` key
- **THEN** the new field shows its label

#### Scenario: Unused show_label
- **WHEN** a legacy field has `show_label` set to false and no `hidden_label`
- **THEN** the new field shows its label

#### Scenario: Hidden field
- **WHEN** the legacy type `gratulation` has the fields `title_text`, `body` and `date`, and `date` is flagged hidden
- **THEN** the new type `gratulation` shows `title_text` and `body` on its pages, in weight order, and its nodes still hold the values of `date`

#### Scenario: Type without hidden fields
- **WHEN** a legacy type has no hidden field
- **THEN** the new type gets no list of page fields, so every field of it is shown

#### Scenario: Editors' choice kept
- **WHEN** the import runs again after the editors changed the page fields of such a type
- **THEN** their list is not replaced

### Requirement: Type and node metadata
The import SHALL carry the legacy metadata record of a node (title, description, image) into the system fields `meta_title`, `meta_description` and `meta_image` of that node, and the legacy metadata of a type and its short title into the type's own `meta_title`, `meta_description`, `meta_image` and `section_title`. The metadata record has the columns `title`, `description`, `keywords` and `image`; they SHALL be read into `meta_title`, `meta_description` and `meta_image`, and the short title is the `field_text` row of the type with `entity_field = title`. All these values SHALL be written in the base language `default` (the legacy language `ua`). The legacy keywords and the metadata of the site configuration SHALL NOT be imported. A metadata image SHALL be imported as a file. The legacy data has no source for the type values `section_description` and `section_cover`, so the import SHALL leave them empty. The display settings of a type (the `public` settings) SHALL NOT be imported.

#### Scenario: Node metadata
- **WHEN** a news item has a metadata record with a title, a description and an image
- **THEN** the node has the same `meta_title`, `meta_description` and `meta_image`, in the language `default`

#### Scenario: Type metadata
- **WHEN** the legacy type `news` has a metadata record and the short title "Новини"
- **THEN** the type `news` has the same type values `meta_title`, `meta_description` and `section_title`, in the language `default`

#### Scenario: Section description and cover
- **WHEN** a content type is imported
- **THEN** its type values `section_description` and `section_cover` are empty, because the legacy data has no source for them

### Requirement: Files
The import SHALL copy through the file storage (as `image` or `document`, with a new identifier and a media record) only the files that the imported data refers to: files of `field_file`, metadata images and YouTube previews, and the images used inside rich text. The source of a file SHALL be its legacy path (`uri`) under the files directory. The legacy file name SHALL become the original name and the legacy description the description of the media record; the author of the media record SHALL be the mapped user. A file that is absent from the directory, is rejected by the profile, or cannot be read SHALL NOT stop the import: the value that refers to it is left out and the file is listed in the report. An orphan record (a file whose owner node does not exist) SHALL NOT be copied and SHALL be counted in the report. Images keep being downscaled by the profile.

#### Scenario: Referenced file
- **WHEN** a news item refers to `news/gallery/a.jpg` that exists in the files directory
- **THEN** the file is stored under a new identifier, a media record with the legacy name is created, and the field refers to it

#### Scenario: Missing file
- **WHEN** a legacy record refers to a file that does not exist in the directory
- **THEN** the import continues, the value is left out, and the report lists the path

#### Scenario: Orphan file
- **WHEN** a legacy file record has an owner node that does not exist or the owner id 0
- **THEN** the file is not copied, and the report counts it as an orphan

#### Scenario: File over the old limit
- **WHEN** a legacy image is larger than 5 MB and smaller than 30 MB
- **THEN** it is imported and downscaled by the image profile

#### Scenario: Unreferenced file
- **WHEN** a file lies in the directory and nothing refers to it
- **THEN** it is not imported

### Requirement: Rich text images and sanitizing
In the imported rich text, an image whose address is a legacy storage path (`/storage/{type}/{field}/{name}`), either relative or absolute on a host listed in `config/legacy.php` (`site_hosts`, by default only `kultura-poltava.gov.ua`, with `http` or `https`), SHALL be rewritten to the address of the imported file, importing that file when it is not yet imported. Any other absolute address of an image (including the old site host `old.kultura-poltava.gov.ua`), every link address, and `iframe` frames SHALL stay as they were. The text SHALL be saved through the usual sanitizing of rich text, and the report SHALL count the nodes whose text was changed by the sanitizing and list the removed tags, attributes and frames by their counts.

#### Scenario: Image in the text
- **WHEN** a text contains `<img src="/storage/info_blocks/gallery/E5N.jpg">` and the file exists
- **THEN** the saved text contains an image with the address of the new file, and the file is in the media library

#### Scenario: Image with an absolute address on the site host
- **WHEN** a text contains `<img src="https://kultura-poltava.gov.ua/storage/news/gallery/2d5.jpg">` and the file exists
- **THEN** the saved text contains an image with the address of the new file, and the file is in the media library

#### Scenario: Image on the old site host
- **WHEN** a text contains `<img src="https://old.kultura-poltava.gov.ua/pic/e.gif">`
- **THEN** the address is not rewritten and no file is imported (the sanitizing removes this image, as it removes any image outside the storage)

#### Scenario: Image whose file is missing
- **WHEN** an image in the text refers to a file that does not exist
- **THEN** the sanitizing removes the image, and the report lists the file

#### Scenario: Frames and absolute links
- **WHEN** a text has a YouTube frame, a Google Maps frame and a link to the old site domain
- **THEN** the frames and the link remain in the saved text

#### Scenario: Removed markup is reported
- **WHEN** the sanitizing removes an attribute or a tag from a text
- **THEN** the report counts the node and the kind of removal

### Requirement: Users
The import SHALL create (or update by email) the two editor accounts of the legacy site, the legacy user with id 2 as an administrator and the legacy user with id 5 as a manager, keeping the name, email, and password hash. The manager role SHALL be taken by the name `Manager` and created, when absent, as an ordinary role with the level `any` for every imported node type and for the media library. Other legacy users SHALL NOT be imported. An existing account with the same email SHALL keep its password and have its name and role updated only when it is not an administrator.

#### Scenario: Accounts are created
- **WHEN** the import runs on a site without these emails
- **THEN** the administrator and the manager accounts exist with the legacy password hashes, and the editor can sign in with the old password

#### Scenario: Spam accounts
- **WHEN** the legacy table contains other registrations
- **THEN** they are not imported

#### Scenario: Existing account
- **WHEN** an account with the legacy email already exists
- **THEN** no second account is created, and its password is not changed

#### Scenario: Manager rights
- **WHEN** the import finishes
- **THEN** the manager role has the level `any` for every imported content, page and block type and for the media library

### Requirement: Repeatable import
Running the import again SHALL NOT duplicate anything: nodes SHALL be matched by the legacy `uuid`, types by the key, fields by the type and key, users by the email, and files by a stored map of legacy file records to the imported media. A repeated run SHALL rewrite the values of existing nodes from the legacy data (but SHALL NOT change their view counter, which belongs to the new site once the node exists), SHALL NOT import a file that is already mapped, and SHALL import what was missing (for example after files were added to the directory). Each node SHALL be imported in its own transaction, so a failing node is reported and skipped without rolling back the others.

#### Scenario: Second run
- **WHEN** the import runs twice without changes in the source
- **THEN** the second run creates nothing, imports no files again, and reports every item as unchanged or updated

#### Scenario: A failing node
- **WHEN** one node fails validation
- **THEN** it is listed in the report with the reason, and the other nodes are imported

#### Scenario: View counters are not reset
- **WHEN** a node was imported, its counter on the new site was increased, and the import runs again
- **THEN** the counter of the node keeps its current value

#### Scenario: Files added later
- **WHEN** files missing during the first run are copied into the directory and the import runs again
- **THEN** the values that refer to them are filled, and nothing else changes

### Requirement: Dry run
With `--dry-run` the command SHALL write nothing to the database and the disk and SHALL NOT copy files. It SHALL still read the legacy data and report what would be imported: counts by type, the files that are missing or rejected by a profile limit, the orphans, the legacy slugs that do not match the strict format, slug collisions, the fields found only in the data, and the rich text changed by the sanitizing.

#### Scenario: Nothing is written
- **WHEN** `legacy:import --dry-run` runs
- **THEN** no type, node, user, file or map record is created, and the report shows the expected counts and problems

#### Scenario: Problems are visible before the real run
- **WHEN** some referenced files are missing
- **THEN** the dry run lists them

### Requirement: Fresh start
With `--fresh` the command SHALL first delete the content of the new site that the import owns (all content types with their fields, nodes and values, the media records with their files on disk and file relations, the import map) and restart their identifiers from 1, and only after a confirmation. Users, roles, menus and site settings SHALL NOT be deleted. In non-interactive mode the confirmation SHALL require `--force`. `--fresh` with `--dry-run` SHALL delete nothing.

#### Scenario: Confirmation
- **WHEN** `legacy:import --fresh` runs interactively
- **THEN** the command asks for confirmation and deletes nothing if the answer is no

#### Scenario: Non-interactive without force
- **WHEN** `legacy:import --fresh --no-interaction` runs without `--force`
- **THEN** the command stops and deletes nothing

#### Scenario: Clean state
- **WHEN** the fresh start is confirmed
- **THEN** the old test types, nodes and files are gone, and the imported rows get identifiers from 1

#### Scenario: Users are kept
- **WHEN** the fresh start finishes
- **THEN** the users and roles that existed before remain

### Requirement: Report
At the end the command SHALL print a summary and write the same information as a file under `storage/app/legacy-import/` named with the run time. The report SHALL contain, for every step, the numbers created, updated, unchanged, skipped and failed; the list of failed nodes with reasons; the missing and rejected files; the orphans by kind; the fields found only in the data; the nodes changed by the sanitizing with the kinds of removal; and the slugs kept in the legacy format. The exit code SHALL be non-zero when the run could not start or when any item failed, and zero otherwise.

#### Scenario: Summary and file
- **WHEN** the import finishes
- **THEN** the summary is printed and a report file exists in `storage/app/legacy-import/`

#### Scenario: Exit code
- **WHEN** at least one node failed
- **THEN** the exit code is non-zero, and the report names the failed node

## ADDED Requirements

### Requirement: A file new in a node must be one the user may attach
The system SHALL accept a file as a new value of a node (an `image` or `file` field, a YouTube screenshot, an image in rich text, in any language, including the translation mode) only when the user uploaded that file or the user's access to the media library allows them to change it. A file counts as new when it is not already among the files referenced by the node's saved values; when a node is created, every file is new. A file that is already among the node's saved values SHALL stay valid for every user who may edit the node, whoever uploaded it. A refused file SHALL give a validation error next to the field, and nothing of that save SHALL be stored. A user of the role `admin` SHALL be unrestricted.

#### Scenario: Crafted request with another user's file on create
- **WHEN** a user with the level `own` on a type and no access to the media library submits a node whose file field holds the uuid of a file uploaded by another user
- **THEN** the save is refused with an error next to that field and no node is created

#### Scenario: Crafted request with another user's file on update
- **WHEN** the same user saves their own node with the uuid of another user's file added to a file field, with a new name and description for it
- **THEN** the save is refused with an error next to that field, and the node and the foreign file are unchanged

#### Scenario: Own upload
- **WHEN** a user without access to the media library uploads an image in the node form and saves the node
- **THEN** the node is saved with that image

#### Scenario: Colleague's file already in the node
- **WHEN** a user with the level `any` on a type, without access to the media library, re-saves a node that already holds a file uploaded by a colleague
- **THEN** the node is saved and the file stays in its values

#### Scenario: Media access
- **WHEN** a user with the media level `any`, or `own` for a file they uploaded, attaches a file to a node
- **THEN** the file is accepted; a user with the media level `own` is refused a file uploaded by another user

#### Scenario: Translation mode
- **WHEN** a user submits a translation of a translatable file field with another user's file
- **THEN** the translation is refused with an error next to the field and no translated value is stored

#### Scenario: Administrator
- **WHEN** an administrator attaches any existing file to a node
- **THEN** the file is accepted

### Requirement: Editing a node includes the data of its files
A user who may edit a node SHALL be able to change the name and the description of every file referenced by that node's values, with no further check of the media library access. Data sent for a file that is not among the node's values SHALL be ignored without an error, and the rest of the save SHALL succeed.

#### Scenario: Colleague's file already in the node
- **WHEN** a user with the level `any` on a type, without access to the media library, saves a node with a new name and description for a file uploaded by a colleague that is already in the node
- **THEN** the file gets the new name and description

#### Scenario: File outside the node
- **WHEN** a user saves a node with new data for a file that is not among the node's values
- **THEN** the node is saved and that file keeps its name and description

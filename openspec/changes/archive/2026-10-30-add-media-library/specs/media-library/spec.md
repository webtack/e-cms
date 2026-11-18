# Spec Delta

## Purpose

The media library shows all files of the storage, both used and unused, records the relations "a file is used by an owner in a role", and allows manual deletion of files that are used nowhere.

## ADDED Requirements

### Requirement: Recording file relations
The system SHALL store the relation between a file and the place of its use as a separate record: the file (by `uuid`), the owner (type and identifier), the role (for example, the key of a field) and the language. The combination (file, owner, role, language) SHALL be unique. The system SHALL NOT allow the deletion of a file record that has a relation, at the DB level.

#### Scenario: One relation
- **WHEN** a file is attached to one owner in one role and language twice
- **THEN** there is one relation, not two

#### Scenario: One file in several places
- **WHEN** one file is used in two different records
- **THEN** there are two relations with different owners, and the number of uses of the file equals two

#### Scenario: Protection at the DB level
- **WHEN** the record of a file that has a relation is deleted directly
- **THEN** the deletion is rejected, the record and the file on the disk remain

### Requirement: Synchronizing relations with the record of a value
The system SHALL update the relations of the owner in the same transaction as the writing of the value of a field of the type `image` or `file`: after writing, the set of relations of the owner for this role and language SHALL match the set of files in the stored value. A write error SHALL NOT leave partially updated relations.

#### Scenario: Attaching a file
- **WHEN** a file is stored in the field `file` of a record
- **THEN** a relation of this file with this record appears, whose role equals the key of the field

#### Scenario: Replacing a file
- **WHEN** in the field `file` of a record file A is replaced by file B
- **THEN** the relation with A is deleted, the relation with B is created, and the record of file A and the file itself remain

#### Scenario: Clearing a field
- **WHEN** the value of the field `file` is cleared
- **THEN** the relation is deleted, and the file remains in the storage

#### Scenario: Multiple field
- **WHEN** three files are stored in the multiple field `file`
- **THEN** there is one relation for each of the three files

#### Scenario: Write error
- **WHEN** the writing of values fails on validation
- **THEN** the relations of the record do not change

### Requirement: Cleaning up relations when the owner is deleted
The system SHALL delete the relations of a content record when this record is deleted, and the relations of a field whose values disappear when this field is deleted. The file itself SHALL remain in this case.

#### Scenario: Deleting a record
- **WHEN** a content record that uses a file is deleted
- **THEN** the relations of this record are deleted, the number of uses of the file decreases, the file remains in the storage

#### Scenario: Deleting a field
- **WHEN** a field of the type `image` or `file` is deleted together with its values
- **THEN** the relations of this field are deleted, the files remain in the storage

### Requirement: Media library page
The admin SHALL provide the media library page only for authenticated users with a confirmed email, which shows all files of the storage in a table with pagination, search and sorting. For each file SHALL be displayed: a preview (for images), the original name, the type, the size, the user who uploaded it, the upload date and the number of uses. A file without relations SHALL have 0 uses. The page SHALL show files regardless of who uploaded them.

#### Scenario: All files
- **WHEN** the storage has files uploaded by different users, both used and unused
- **THEN** the page shows all of them, and the number of uses matches the number of relations of each file

#### Scenario: Unused file
- **WHEN** a file is uploaded but used nowhere
- **THEN** in the table it has 0 uses

#### Scenario: File without an owner
- **WHEN** the user who uploaded a file is deleted
- **THEN** the file remains in the table, and in the user column there is an empty value

#### Scenario: Guest
- **WHEN** a guest opens the media library page
- **THEN** they are redirected to the login

#### Scenario: Empty media library
- **WHEN** the storage has no files
- **THEN** the page shows a message that there are no files

### Requirement: Where a file is used
The media library page SHALL show, for the selected file, the list of places of its use: the owner (with a link to its edit page, if there is one), the role and the language.

#### Scenario: Used file
- **WHEN** the user opens the details of a file that is used in the field `cover` of the record "Hello"
- **THEN** the details show this record (with a link to its edit page), the field `cover` and the language

#### Scenario: Unused file
- **WHEN** the user opens the details of a file without relations
- **THEN** the details report that the file is not used

### Requirement: Manual deletion of unused files
The system SHALL allow an authorized administrator to delete a file (the record and the file on the disk) from the media library only when there is no relation to it, after confirmation. The check SHALL be performed by the server, regardless of the state of the interface. Deleting a file that is in use SHALL be rejected with a message, and the record and the file on the disk remain.

#### Scenario: Deleting an unused file
- **WHEN** the administrator confirms the deletion of a file without relations
- **THEN** the file record and the file on the disk are deleted, and the file disappears from the table

#### Scenario: Attempt to delete a used file
- **WHEN** a deletion request concerns a file that has relations (in particular sent directly, outside the interface)
- **THEN** the deletion is rejected with a message, the record and the file on the disk remain

#### Scenario: File missing on the disk
- **WHEN** the administrator deletes an unused file that is already missing on the disk
- **THEN** the record is deleted without an error

#### Scenario: Cancellation
- **WHEN** the administrator cancels the deletion confirmation
- **THEN** the file remains unchanged

### Requirement: Access to the media library without roles
While the system has no roles and permissions, the media library SHALL be available to every authenticated user with a confirmed email who has access to the admin.

#### Scenario: Regular administrator
- **WHEN** an authenticated user with a confirmed email opens the media library
- **THEN** the page opens, without additional role checks

# media-library Specification

## Purpose
The media library shows all storage files, used and unused, records the relations "the file is used by the owner in the role", and allows manually deleting files that are not used anywhere.

## Requirements

### Requirement: Recording file relations
The system SHALL store the relation between a file and the place of its use as a separate record: the file (by `uuid`), the owner (type and identifier), the role (for example, the field key), and the language. The combination (file, owner, role, language) SHALL be unique. The system SHALL NOT allow, at the DB level, the deletion of a file record that has a relation.

#### Scenario: One relation
- **WHEN** a file is bound to one owner in one role and language twice
- **THEN** there is one relation, not two

#### Scenario: One file in several places
- **WHEN** one file is used in two different nodes
- **THEN** there are two relations with different owners, and the usage count of the file equals two

#### Scenario: Protection at the DB level
- **WHEN** the record of a file that has a relation is deleted directly
- **THEN** the deletion is rejected, and the record and the file on the disk remain

### Requirement: Synchronization of relations with the node value
The system SHALL update the owner's relations in the same transaction as the write of the value of an `image` or `file` type field: after the write, the set of the owner's relations for this role and language SHALL match the set of files in the saved value. A write error SHALL NOT leave partially updated relations.

#### Scenario: Binding a file
- **WHEN** a file is saved in a `file` field of a node
- **THEN** a relation of this file with this node appears, whose role equals the field key

#### Scenario: Replacing a file
- **WHEN** in a `file` field of a node the file A is replaced with the file B
- **THEN** the relation with A is deleted, the relation with B is created, and the record of file A and the file itself remain

#### Scenario: Clearing a field
- **WHEN** the value of a `file` field is cleared
- **THEN** the relation is deleted, and the file remains in the storage

#### Scenario: Multiple field
- **WHEN** three files are saved in a multiple `file` field
- **THEN** there is one relation for each of the three files

#### Scenario: Write error
- **WHEN** the writing of values fails on validation
- **THEN** the relations of the node do not change

### Requirement: Removing relations when the owner is deleted
The system SHALL delete the relations of a content node when this node is deleted, and the relations of a field whose values disappear when this field is deleted. The file itself SHALL remain.

#### Scenario: Deleting a node
- **WHEN** a content node that uses a file is deleted
- **THEN** the relations of this node are deleted, the usage count of the file has decreased, and the file remains in the storage

#### Scenario: Deleting a field
- **WHEN** a field of type `image` or `file` is deleted together with its values
- **THEN** the relations of this field are deleted, and the files remain in the storage

### Requirement: Media library page
The admin panel SHALL provide the media library page only to authenticated users with a verified email, which shows all storage files in a table with pagination, search, and sorting. For each file SHALL be displayed: the preview (for images), the original name, the type, the size, the user who uploaded it, the upload date, and the number of usages. A file without relations SHALL have the number of usages 0. The page SHALL show files regardless of who uploaded them.

#### Scenario: All files
- **WHEN** the storage has files uploaded by different users, both used and unused
- **THEN** the page shows all of them, and the number of usages matches the number of relations of each file

#### Scenario: Unused file
- **WHEN** a file is uploaded but not used anywhere
- **THEN** in the table it has the usage count 0

#### Scenario: File without an owner
- **WHEN** the user who uploaded a file is deleted
- **THEN** the file remains in the table, and in the user's column there is an empty value

#### Scenario: Guest
- **WHEN** a guest opens the media library page
- **THEN** they are redirected to the login

#### Scenario: Empty media library
- **WHEN** the storage has no files
- **THEN** the page shows a message that there are no files

### Requirement: Where a file is used
The media library page SHALL show, for the selected file, the list of places of its use: the owner (with a link to its edit page, if it exists and the viewer may open it), the role, and the language. A place whose owner the viewer may not open SHALL be shown only as a neutral entry "Used elsewhere" without a title, a link, an owner type, a field, or a language, and SHALL still be counted. The viewer may open: a node when their level on the node's type is not `none` (the link only when they may edit that node); a user with the right to manage users; the site settings with the right to the settings; a term with the right to taxonomy; a menu item with the right to menus; a type with the right to types. An owner of an unknown kind SHALL never be shown. A user of the role `admin` SHALL see every place. The number of usages and the refusal to delete a file that is in use SHALL NOT depend on what the viewer may open.

#### Scenario: Used file
- **WHEN** the user opens the details of a file used in the field `cover` of the node "Hello" of a type where they have the level `any`
- **THEN** the details show this node (with a link to its editing), the field `cover`, and the language

#### Scenario: Unused file
- **WHEN** the user opens the details of a file without relations
- **THEN** the details report that the file is not used

#### Scenario: Node of a type without access
- **WHEN** a user with the media level `own` and the level `none` on the type `secret` opens the details of a file used by a node of the type `secret`
- **THEN** the usage is shown as "Used elsewhere" without the node's title, link, type, field, or language, and the usage count is unchanged

#### Scenario: Node of another author at the level own
- **WHEN** a user with the level `own` on a type opens the details of a file used by another author's node of that type
- **THEN** the node's title is shown without a link to its edit form

#### Scenario: User and settings owners
- **WHEN** a user without the right to manage users opens the details of a file that is another user's avatar, or without the settings right opens a file that is a site logo
- **THEN** each of these usages is shown as "Used elsewhere"

#### Scenario: Administrator
- **WHEN** an administrator opens the details of any used file
- **THEN** every place of use is shown with its title, link, field, and language

#### Scenario: Deletion is not affected
- **WHEN** a user attempts to delete a file whose only usage is hidden from them
- **THEN** the deletion is rejected because the file is in use

### Requirement: Manual deletion of unused files
The system SHALL allow an authorized administrator to delete a file (the record and the file on the disk) from the media library only when there is no relation to it, after confirmation. The check SHALL be performed by the server, regardless of the state of the interface. Deleting a file that is in use SHALL be rejected with a message, and the record and the file on the disk remain.

#### Scenario: Deleting an unused file
- **WHEN** the administrator confirms the deletion of a file without relations
- **THEN** the file record and the file on the disk are deleted, and the file disappears from the table

#### Scenario: Attempt to delete a used file
- **WHEN** a deletion request concerns a file that has relations (including one sent directly, bypassing the interface)
- **THEN** the deletion is rejected with a message, and the record and the file on the disk remain

#### Scenario: File missing on the disk
- **WHEN** the administrator deletes an unused file that is already missing from the disk
- **THEN** the record is deleted without an error

#### Scenario: Cancellation
- **WHEN** the administrator cancels the deletion confirmation
- **THEN** the file remains unchanged

### Requirement: Access to the media library without roles
While the system has no roles and rights, the media library SHALL be available to every authenticated user with a verified email who has access to the admin panel.

#### Scenario: Ordinary administrator
- **WHEN** an authenticated user with a verified email opens the media library
- **THEN** the page opens, without additional role checks

### Requirement: File type in the media library
The table of the media library and the file details panel SHALL show the file type by the short extension label (for example, `docx`, `pdf`, `jpg`), not by the full MIME type. Sorting by the type column SHALL remain working.

#### Scenario: Type column
- **WHEN** the storage has a Word file
- **THEN** in the "Type" column of this file `docx` is shown

#### Scenario: File details
- **WHEN** the user opens the details of a JPEG image
- **THEN** the subtitle of the panel shows `jpg`

### Requirement: Media library filter by type
The toolbar of the media library SHALL contain a dropdown list of types next to the search field, with the options "All types" and the short labels of the types accepted by the upload profiles. The choice of a type SHALL show only the files of this type, add a chip of the active filter under the panel (removable with a cross), and be stored in the address of the page. The "Reset" button SHALL reset the type too. The filter SHALL be executed on the server before pagination and work together with the search and sorting. A label that is not in the list SHALL be ignored without an error.

#### Scenario: Choosing a type
- **WHEN** the storage has files `docx`, `pdf`, and `jpg`, and the user chooses the type `pdf` in the list
- **THEN** the table shows only the `pdf` files, a filter chip appears under the panel, and the total count and the pages match this choice

#### Scenario: All types
- **WHEN** a type is chosen, and the user chooses "All types"
- **THEN** the filter is removed, the chip disappears, and the table shows all files

#### Scenario: Removing the chip
- **WHEN** the user removes the chip of the type filter
- **THEN** the table shows all files, and the type list returns to "All types"

#### Scenario: Reset
- **WHEN** a type is chosen and a search is entered, and the user clicks "Reset"
- **THEN** both the type and the search are reset

#### Scenario: Filter together with search
- **WHEN** the user chooses the type `pdf` and searches for "report"
- **THEN** the table shows only the `pdf` files whose name contains "report"

#### Scenario: Filter in the address
- **WHEN** the user opens the address of the page with the type filter `docx`
- **THEN** the list shows `docx`, the chip is visible, and the table contains only the `docx` files

#### Scenario: Unknown value
- **WHEN** the address contains a type filter for a type that is not in the list
- **THEN** the filter is ignored, and the table shows all files

### Requirement: File description
A storage file SHALL have an optional text description, which the user sets in the node's edit form. The file details panel in the media library SHALL show the description if there is one, and SHALL not show an empty row if there is none. The original name changed in the node form SHALL be displayed in the media library (the "Name" column, the details panel heading) like any other.

#### Scenario: Description in details
- **WHEN** a file has a description, and the user opens its details in the media library
- **THEN** the panel shows this description

#### Scenario: File without a description
- **WHEN** a file has no description
- **THEN** the details panel does not show a description row

#### Scenario: Renamed file
- **WHEN** the user changed the name of a file in the node edit form
- **THEN** the media library shows the new name in the table and in the details heading, and the search by name finds the file by the new name

### Requirement: Relations of images of rich text
The system SHALL maintain the relations of files also for images in a `richtext` field, in the same transaction as the write of the value: after the write, the set of the owner's relations for the role (the field key) and the language SHALL match the set of files referenced by the images of the saved value. Deleting an image from the text SHALL remove the relation, and the file remains in the storage. A write error SHALL NOT leave partially updated relations.

#### Scenario: Binding
- **WHEN** a value with two different images is saved in a `richtext` field of a node
- **THEN** there are two relations of this node with the role equal to the field key

#### Scenario: The same image twice
- **WHEN** the same image is inserted into the text twice
- **THEN** there is one relation

#### Scenario: Deleting an image from the text
- **WHEN** the node is saved after an image has been deleted from the text
- **THEN** the relation with this file is deleted, and the file and its record remain

#### Scenario: Separate languages
- **WHEN** an image is inserted in the translation `uk`, and it is absent in the base language
- **THEN** the relation exists only for the language `uk`

#### Scenario: Deleting a node or a field
- **WHEN** a node or a `richtext` field with images is deleted
- **THEN** the relations are removed, and the files remain in the storage

### Requirement: Relations of files of type values
The system SHALL track the files in the values of the type itself (`meta_image`, `section_cover`) in the same way as in node values: by a relation whose owner is the type (its identifier), with the role equal to the field key and the language of the value. The relations of the type SHALL be updated in the same transaction as the value write, and SHALL be removed when the type is deleted; the files themselves remain. The page "Where the file is used" SHALL show the usage in a type value, with the name of the type and a link to its settings page.

#### Scenario: Binding a file to a type
- **WHEN** a file is saved in `section_cover` of the type `news`
- **THEN** there is a relation of this file with the type `news`, the role `section_cover`, the language of the value, and the usage count of the file has increased

#### Scenario: Replacing a file in a type value
- **WHEN** the file A is replaced with the file B in `meta_image` of a type
- **THEN** the relation with A is deleted, one with B is created, and the files remain

#### Scenario: Deleting a type
- **WHEN** a type that uses a file in `section_cover` is deleted
- **THEN** the relations of this type are deleted, the usage count of the file has decreased, and the file remains in the storage

#### Scenario: Usage on the media library page
- **WHEN** the user opens "Where it is used" for a file selected as `section_cover` of the type `news`
- **THEN** they see the type `news` with a link to its settings page

### Requirement: Files of the site settings
The system SHALL track the site logo files by a relation whose owner is the site settings, with the role equal to the logo key (`logo_large`, `logo_small`), and the language of the value. The relations SHALL be updated in the same transaction as the write of the settings. The page "Where the file is used" SHALL show such usage with a label like "Site settings" and a link to the site settings page.

#### Scenario: Logo file in the list of usages
- **WHEN** the user opens "Where it is used" for a file selected as the large logo
- **THEN** the list has a row with the owner "Site settings", the role of the large logo, the language `default`, and the link to `/admin/settings`

#### Scenario: Deleting a used logo
- **WHEN** the user attempts to delete from the media library a file that is the logo of the site
- **THEN** the deletion is rejected, and the file and the relation remain

#### Scenario: Freed file
- **WHEN** the logo has been replaced with another file
- **THEN** the previous file has no relations and can be deleted from the media library

### Requirement: Thumbnail in the media library table
The preview column of the media library table SHALL show for an image the `thumb` style instead of the full-size original. A file that is not an image SHALL have no preview. The details panel and the URL field SHALL continue to show the address of the original.

#### Scenario: Thumbnail of an image
- **WHEN** the media library is opened with an image file
- **THEN** the preview address of the row is the address of the `thumb` style of this file

#### Scenario: Details show the original
- **WHEN** the details of an image file are opened
- **THEN** the URL in the details is the address of the original, not of the style

#### Scenario: Document
- **WHEN** the table has a PDF file
- **THEN** there is no preview

### Requirement: Preview in the media library table opens the original
The thumbnail of an image in the preview column SHALL be a link to the original image that opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`), and SHALL have a pointer cursor (`cursor-pointer`). A file that is not an image SHALL remain an icon without a link.

#### Scenario: Clicking the thumbnail
- **WHEN** the table has an image file
- **THEN** the thumbnail is wrapped in a link whose `href` is the address of the original, and `src` is the address of the `thumb` style

#### Scenario: Document without a link
- **WHEN** the table has a PDF file
- **THEN** an icon is shown instead of the thumbnail, and there is no link

### Requirement: Uploader avatar in the media table
The "uploaded by" cell of the media library table SHALL show the uploader's avatar next to their name when the uploader has one, and their initials otherwise. A file whose uploader is unknown SHALL show no avatar.

#### Scenario: Uploader with an avatar
- **WHEN** a file was uploaded by a user who has an avatar
- **THEN** the media table row shows that picture next to the uploader's name

#### Scenario: Uploader without an avatar or unknown uploader
- **WHEN** the uploader has no avatar, or the uploader is unknown
- **THEN** the row shows the initials next to the name, or nothing for an unknown uploader

### Requirement: Translated file names and descriptions
A storage file SHALL be able to have a display name and a description per additional language besides its base name and description. A translation belongs to the file and is shared by every place that uses the file. The translated name is for display only: the original name (`original_name`), the path and the extension of the file SHALL never change. A translated name or description SHALL be stored only when it differs from the base one and SHALL be removed when emptied; a missing translation reads as missing (the original name or the base description is shown instead, and the public fallback is a separate rule). Deleting a file SHALL delete its translations. A language that has a translated name or description SHALL NOT be deletable. The details panel of the media library SHALL show, besides the base name and description, the translations with their language names, and SHALL show no row for a file without any translation or description.

#### Scenario: Stored translation
- **WHEN** the English description "Front cover" and the English name "Cover" are saved for a file whose base description is "Лицьова обкладинка"
- **THEN** the file has both descriptions and both names, and the original name, the path and the base description are unchanged

#### Scenario: Equal to the base
- **WHEN** an English name or description equal to the base one is saved
- **THEN** nothing is stored for English

#### Scenario: Details panel
- **WHEN** a file has a base description and an English description and the user opens its details
- **THEN** the panel shows the base description and the English name and description labelled with the language

#### Scenario: Deleting the file
- **WHEN** an unused file with translations is deleted
- **THEN** its translations are deleted too

#### Scenario: Deleting a language
- **WHEN** a language that has a translated file name or description is deleted
- **THEN** the deletion is rejected

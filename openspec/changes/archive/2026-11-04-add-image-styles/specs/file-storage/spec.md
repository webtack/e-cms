# file-storage Specification (delta)

## ADDED Requirements

### Requirement: Створення пресетів зображення при збереженні
The system SHALL, after saving an image, create its styles marked in the configuration for creation at the upload (see `image-styles`). An error in the creation of a style SHALL NOT reject the saving of the file.

#### Scenario: Saving an image
- **WHEN** an image is saved under the profile `image`
- **THEN** the original and the file of the preset `thumb` lie on the disk (according to the setting of the format of the styles — WebP or the format of the original)

#### Scenario: Saving a document
- **WHEN** a document is saved
- **THEN** no styles are created

### Requirement: Видалення файла прибирає його стилі
The system SHALL, when deleting a file of the storage, also delete from the disk all its created image styles (`styles/{style}/{uuid}.*`, regardless of the extension). Missing files of styles SHALL NOT be an error. The rules of protection of the used files SHALL remain unchanged: if the deletion of a file is rejected, the styles are not deleted.

#### Scenario: Deleting a file with styles
- **WHEN** a file is deleted for which the styles `thumb` and `300x250` were created
- **THEN** the original and the files of both styles are absent from the disk

#### Scenario: File without styles
- **WHEN** a file is deleted for which no styles were created
- **THEN** the deletion completes without an error

#### Scenario: Deletion rejected
- **WHEN** the deletion of a used file is rejected
- **THEN** the original and its styles remain on the disk

### Requirement: Мініатюра зображення у віджеті завантаження
The upload widget SHALL show for the chosen image the style `thumb` (the field `preview` of the data of the file; if there is none — the address of the original), wrapped in a link to the original with `target="_blank"` and `rel="noopener noreferrer"`; the thumbnail SHALL have `cursor-pointer`. The data of the file that the server passes to the widget (the response of the upload and the values of the forms of a record, a term, a menu item and the settings of the site) SHALL contain `preview`: the address of `thumb` for an image and `null` for another file. A file that is not an image SHALL be shown as an icon without a link.

#### Scenario: Image just uploaded
- **WHEN** the user uploads an image into the widget
- **THEN** the widget shows a thumbnail with the style `thumb`, whose link leads to the original and opens in a new tab

#### Scenario: Saved image in the form of editing
- **WHEN** the form of a record with a saved image is opened
- **THEN** the thumbnail has the address `thumb`, and its link has the address of the original

#### Scenario: Document
- **WHEN** a PDF is chosen in the widget
- **THEN** the widget shows an icon without a link, `preview` in the data is `null`

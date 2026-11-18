# media-library Specification (delta)

## ADDED Requirements

### Requirement: Мініатюра в таблиці медіатеки
The preview column of the table of the media library SHALL show for an image the style `thumb` instead of the full-size original. A file that is not an image SHALL have no preview. The details panel and the URL field SHALL continue to show the address of the original.

#### Scenario: Thumbnail of an image
- **WHEN** the media library with an image file is opened
- **THEN** the preview address of the row is the address of the style `thumb` of this file

#### Scenario: Details show the original
- **WHEN** the details of an image file are opened
- **THEN** the URL in the details is the address of the original, not of the style

#### Scenario: Document
- **WHEN** the table has a PDF file
- **THEN** there is no preview

### Requirement: Превʼю в таблиці медіатеки відкриває оригінал
The thumbnail of an image in the preview column SHALL be a link to the original image that opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`), and SHALL have the pointer cursor (`cursor-pointer`). A file that is not an image SHALL remain an icon without a link.

#### Scenario: Click on a thumbnail
- **WHEN** the table has an image file
- **THEN** the thumbnail is wrapped in a link whose `href` is the address of the original, and whose `src` is the address of the style `thumb`

#### Scenario: Document without a link
- **WHEN** the table has a PDF file
- **THEN** instead of a thumbnail there is an icon, with no link

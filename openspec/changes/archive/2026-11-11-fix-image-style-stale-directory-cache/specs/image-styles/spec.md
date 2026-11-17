# Spec Delta

## MODIFIED Requirements

### Requirement: Lazy generation of a style
The system SHALL create a style file on the first request to its address, when the file does not yet exist (arbitrary sizes, presets without creation on upload, files uploaded before the styles appeared, and presets after cleaning): write it to the disk of the `image` profile and return it in the response. Subsequent requests SHALL be served without re-creation. The creation SHALL work without any web server rules, except the standard redirection of non-existent files to the application's front controller, and SHALL NOT require access to the web server configuration. The creation SHALL also succeed when the directory of the style was removed (by hand, or by the cleaning command) while the application's long-lived worker processes still remember it: the directory SHALL be created again, and no request SHALL answer 404 only because of such a stale memory. A failure to write the style file SHALL be recorded in the application log as a warning with the disk, the original and the style; no partially written file SHALL remain. The original SHALL be looked up by `uuid` among the allowed image extensions, and the extension of the requested style file SHALL match the format of the style of this original. If the original does not exist, or the file name does not have the form `{uuid}.{extension}`, or the format is not processed, or the extension does not match the format of the style, the system SHALL respond with 404 and not create any files.

#### Scenario: First request
- **WHEN** the address of the style `thumb` is requested for an existing original, and the style file does not yet exist
- **THEN** the response 200 gives the image of the style size, and the style file appears on the disk

#### Scenario: Repeated request
- **WHEN** the address of the style is requested a second time
- **THEN** the file on the disk is not re-created, and the same content is served

#### Scenario: Style directory removed
- **WHEN** the whole directory `styles/thumb/` is deleted from the disk, and the address of the style `thumb` is requested for an existing original
- **THEN** the response is 200, the directory and the style file are created again, and this holds for every request right after the deletion, whichever worker process serves it

#### Scenario: Style file removed
- **WHEN** only one style file is deleted, and its address is requested
- **THEN** the response is 200, and the file is created again

#### Scenario: Write failure
- **WHEN** the style file cannot be written to the disk
- **THEN** the response is 404, a warning with the disk, the original and the style is written to the log, and no partially written file remains

#### Scenario: Original does not exist
- **WHEN** the address of a style is requested for a `uuid` that is not on the disk
- **THEN** the response is 404, and the style file is not created

#### Scenario: Not a storage file
- **WHEN** the address of a style is requested with a name that does not have the form `{uuid}.{extension}` (for example, with `..`)
- **THEN** the response is 404, and nothing is written

#### Scenario: Not an image
- **WHEN** a style is requested for a document file
- **THEN** the response is 404

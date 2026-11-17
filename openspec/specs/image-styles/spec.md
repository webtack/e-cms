# image-styles Specification

## Purpose
Provides derived image sizes (styles), which a theme template requests as needed: presets from the developer's configuration and arbitrary sizes, created lazily on the first request without depending on the web server settings.

## Requirements

### Requirement: Image style
The system SHALL support image styles of two kinds: a **preset** — a named size from the developer's configuration (at the start there is the preset `thumb`) — and an **arbitrary size** — a string `{width}x{height}`, `{width}x` or `x{height}`, where the size in pixels is a positive integer, and the letter `x` is Latin, lowercase or uppercase (normalized to lowercase). The name of a preset SHALL NOT match the format of an arbitrary size.

A style with both sides SHALL give an image of exactly the given width and height: the original is proportionally fitted inside, centered, without cropping (an original smaller than the style is enlarged), and the empty margins are filled with a blurred copy of the same original, scaled so as to cover the whole area. If the proportions of the original match the proportions of the style, there are no empty margins. If the image has transparent pixels, the empty margins SHALL remain transparent without blur. A style with one side SHALL proportionally change the size by this side (enlarging a smaller original) without empty margins. Animated GIF and WebP SHALL be served with the content of the original. The application of the EXIF orientation SHALL match the rules of downscaling on upload.

The format of the style file SHALL be determined by the developer's setting: by default `webp` — JPEG, PNG, and WebP originals are served as WebP with transparency preserved, and the style file has the extension `webp`; the value `original` keeps the format of the original. GIF originals SHALL always remain GIF, in any mode.

#### Scenario: Arbitrary size, original wider than the style
- **WHEN** the style `300x250` is requested for an opaque image 1200×600
- **THEN** the result has the size 300×250: the image 300×150 in the center, with the top and bottom margins filled by a blurred copy of the original

#### Scenario: Portrait orientation in a landscape style
- **WHEN** the style `300x250` is requested for an opaque image 600×1200
- **THEN** the result has the size 300×250: the image 125×250 in the center, with the left and right margins filled by a blurred copy of the original

#### Scenario: Same proportions
- **WHEN** the style `300x250` is requested for an image 1200×1000
- **THEN** the result has the size 300×250 without filling, the image is simply reduced

#### Scenario: Original smaller than the style
- **WHEN** the style `300x250` is requested for an opaque image 200×100
- **THEN** the result has the size 300×250: the image is enlarged to 300×150 in the center, with the top and bottom margins filled by a blurred copy

#### Scenario: Transparent image
- **WHEN** the style `300x250` is requested for a PNG 600×1200 with transparent pixels
- **THEN** the result has the size 300×250, the empty margins on the left and right are transparent, there is no blurred background, and the transparent pixels of the image itself are preserved

#### Scenario: Size by width only
- **WHEN** the style `300x` is requested for an image 1200×600
- **THEN** the result has the size 300×150 without empty margins

#### Scenario: Size by height only
- **WHEN** the style `x300` is requested for an image 600×1200
- **THEN** the result has the size 150×300 without empty margins

#### Scenario: Original smaller than one side
- **WHEN** the style `300x` is requested for an image 200×100
- **THEN** the result is enlarged to 300×150

#### Scenario: Uppercase letter
- **WHEN** the style `300X250` is requested
- **THEN** it is treated as `300x250` and gives the same addresses and files

#### Scenario: Preset
- **WHEN** the developer sets in the configuration the preset `thumb` with the sizes 200×200, and the style `thumb` is requested
- **THEN** the result corresponds to the size of this preset, without changes to other parts of the system

#### Scenario: Invalid style
- **WHEN** the style `0x0`, `300х250` with a Cyrillic "х", `abc` (which is not among the presets), or `300` without the letter `x` is requested
- **THEN** the style is rejected, and no derived file is created

#### Scenario: Animated GIF
- **WHEN** a style is requested for an animated GIF
- **THEN** the result has the content of the original without changes and the extension `gif`

#### Scenario: WebP format by default
- **WHEN** any style is requested for a JPEG original
- **THEN** the result is a WebP image with the extension `webp`

#### Scenario: Preserving the format of the original
- **WHEN** the developer sets the format `original` and a style is requested for a JPEG original
- **THEN** the result is a JPEG with the extension `jpg`

#### Scenario: GIF in WebP mode
- **WHEN** the format `webp` and a style is requested for a still GIF
- **THEN** the result remains a GIF

### Requirement: Presets on upload
The system SHALL create the files of the presets that the developer has marked in the configuration for creation on upload (at the start — `thumb`), immediately after the image is saved, before the response to the upload. A failure of the creation of such a preset SHALL NOT reject the upload: the file is saved, and the preset will be created lazily on the first request. Other presets and arbitrary sizes SHALL be created only lazily.

#### Scenario: Uploading an image
- **WHEN** an image is uploaded with the `image` profile
- **THEN** the file `styles/thumb/{uuid}.{extension}` of the preset size already exists

#### Scenario: Preset without the flag
- **WHEN** the configuration has a preset without the flag of creation on upload
- **THEN** on upload its file is not created, and it is created on the first request

#### Scenario: Creation failure
- **WHEN** the creation of a preset on upload fails with an error
- **THEN** the upload is successful, the original is saved, and the preset is created lazily later

#### Scenario: Document
- **WHEN** a file is uploaded with the `document` profile
- **THEN** styles are not created

### Requirement: Location and address of a style
The system SHALL store the style file on the disk of the `image` profile in the directory `styles/{style}/`, under the name `{uuid}.{extension}`, where `{uuid}` matches the original, `{extension}` is determined by the format of the style (see "Image style"), and `{style}` is the name of the preset or the normalized arbitrary size. The address of the style file SHALL be built from the address of the original by inserting `styles/{style}/` before the file name, with the extension replaced according to the format of the style, so the styles lie next to the originals and are served in the same way.

#### Scenario: Preset
- **WHEN** the original is `/storage/{uuid}.jpg`, the style `thumb`, the format `webp`
- **THEN** the style file lies on the disk at the path `styles/thumb/{uuid}.webp`, and its address is `/storage/styles/thumb/{uuid}.webp`

#### Scenario: Arbitrary size
- **WHEN** the style `300x250`, the original `{uuid}.png`, the format `webp`
- **THEN** the style file lies at the path `styles/300x250/{uuid}.webp`

#### Scenario: Format `original`
- **WHEN** the format `original`, the original `{uuid}.jpg`, the style `thumb`
- **THEN** the style file lies at the path `styles/thumb/{uuid}.jpg`

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

### Requirement: Protection against arbitrary sizes
The system SHALL create the file of an arbitrary size only on a request with a valid signature, which the template helper adds to the address (the parameter `itok`, computed from the application secret, the style, and the file name). The presets from the configuration SHALL not need a signature. A request for an arbitrary size without a signature or with a signature of another style or file SHALL be rejected with 404 without creating the file. The side of an arbitrary size SHALL be not greater than the limit from the developer's configuration (by default 4096 pixels); a style larger than the limit SHALL be rejected in the same way.

#### Scenario: Signed arbitrary size
- **WHEN** the template requests the style `300x250` through the helper, and the browser requests the obtained address
- **THEN** the file is created and served

#### Scenario: Without a signature
- **WHEN** the address `/storage/styles/300x250/{uuid}.webp` is requested without a signature
- **THEN** the response is 404, and the file is not created

#### Scenario: Signature of another size
- **WHEN** a signature computed for `400x400` is added to the address of the style `300x250`
- **THEN** the response is 404, and the file is not created

#### Scenario: Too large size
- **WHEN** the style `99999x99999` is requested even with a valid signature
- **THEN** the response is 404, and the file is not created

#### Scenario: Preset without a signature
- **WHEN** the address of the preset `thumb` is requested without a signature
- **THEN** the file is created and served

### Requirement: Template helper
The system SHALL provide in theme templates the function `image_style($url, $style)`, which accepts the address of a storage image (or the value `{uuid}.{extension}`) and a style (a preset or an arbitrary size) and returns the address of the style file, without queries to the database and the disk. For an arbitrary size the address SHALL contain a signature. An address that does not point to a storage image file (empty, external, of an unexpected form), and an invalid style SHALL be returned unchanged, so that the template does not break. The returned address SHALL keep the beginning of the original (the scheme and the host, if they were present).

#### Scenario: Preset
- **WHEN** the template calls `image_style('/storage/{uuid}.jpg', 'thumb')`
- **THEN** the result is `/storage/styles/thumb/{uuid}.webp` (the format `webp`)

#### Scenario: Arbitrary size
- **WHEN** the template calls `image_style('/storage/{uuid}.jpg', '300x250')`
- **THEN** the result is `/storage/styles/300x250/{uuid}.webp` with the signature in the parameter `itok`

#### Scenario: A value instead of an address
- **WHEN** the template calls `image_style('{uuid}.png', 'thumb')`
- **THEN** the result is the address `styles/thumb/{uuid}.webp` on the disk of the `image` profile

#### Scenario: Absolute address
- **WHEN** the original is `http://localhost:8080/storage/{uuid}.jpg`
- **THEN** the result keeps the scheme and the host: `http://localhost:8080/storage/styles/thumb/{uuid}.webp`

#### Scenario: GIF
- **WHEN** the template calls `image_style('/storage/{uuid}.gif', 'thumb')`
- **THEN** the result is `/storage/styles/thumb/{uuid}.gif`

#### Scenario: External address
- **WHEN** the template calls `image_style('https://cdn.example.com/photo.jpg', 'thumb')`
- **THEN** the result is the same address without changes

#### Scenario: Empty value
- **WHEN** the template calls `image_style(null, 'thumb')`
- **THEN** the result is `null`

### Requirement: Cleaning of styles
The system SHALL provide the console command `image-styles:flush`, which deletes the created style files: all styles without an argument, or only the specified style by its name. The command SHALL NOT touch the originals and SHALL NOT delete anything except the directories `styles/`. The deleted styles SHALL be created again on the next request.

#### Scenario: All styles
- **WHEN** `image-styles:flush` is run without an argument
- **THEN** the directory `styles/` of the disk of the `image` profile is empty, and the originals are in place

#### Scenario: One style
- **WHEN** `image-styles:flush thumb` is run
- **THEN** the directory `styles/thumb/` is deleted, and the other styles remain

#### Scenario: Re-creation
- **WHEN** after cleaning the address of a style is requested
- **THEN** the file is created again according to the current settings of the preset

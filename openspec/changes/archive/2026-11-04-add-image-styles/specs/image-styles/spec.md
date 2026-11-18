# image-styles Specification

## Purpose
Provides derived sizes of images (styles) which the template of the theme requests when necessary: the presets from the configuration of the developer and the arbitrary sizes, created lazily on the first request without dependence on the settings of the web server.

## ADDED Requirements

### Requirement: Стиль зображення
The system SHALL support image styles of two kinds: a **preset** — a named size from the configuration of the developer (at the start the preset `thumb` exists) and an **arbitrary size** — a string `{width}x{height}`, `{width}x` or `x{height}`, where the size in pixels is a positive integer, and the letter `x` is Latin, lower or upper case (normalized to lower case). The name of a preset SHALL NOT match the format of an arbitrary size.

A style with both sides SHALL give an image of exactly the set width and height: the original is proportionally fitted inside in the centre without cropping (an original smaller than the style is enlarged), and the empty fields are filled with a blurred copy of the same original, scaled so as to cover the whole area. If the proportions of the original match the proportions of the style, there are no empty fields. If the image has transparent pixels, the empty fields SHALL remain transparent without blurring. A style with one side SHALL proportionally change the size by this side (enlarging a smaller original) without empty fields. Animated GIF and WebP SHALL be served with the contents of the original. The application of the EXIF orientation SHALL match the rules of the reduction during the upload.

The format of the file of a style SHALL be defined by the developer's setting: by default `webp` — the JPEG, PNG and WebP originals are served as WebP with the transparency kept, and the file of the style has the extension `webp`; the value `original` keeps the format of the original. GIF originals SHALL always remain GIF, in any mode.

#### Scenario: Arbitrary size, original wider than the style
- **WHEN** the style `300x250` is requested for an opaque image 1200×600
- **THEN** the result has the size 300×250: the image 300×150 in the centre, the fields above and below are filled with the blurred copy of the original

#### Scenario: Portrait orientation in a landscape style
- **WHEN** the style `300x250` is requested for an opaque image 600×1200
- **THEN** the result has the size 300×250: the image 125×250 in the centre, the fields to the left and to the right are filled with the blurred copy of the original

#### Scenario: Equal proportions
- **WHEN** the style `300x250` is requested for an image 1200×1000
- **THEN** the result has the size 300×250 without filling, the image is simply reduced

#### Scenario: Original smaller than the style
- **WHEN** the style `300x250` is requested for an opaque image 200×100
- **THEN** the result has the size 300×250: the image is enlarged to 300×150 in the centre, the fields above and below are filled with the blurred copy

#### Scenario: Transparent image
- **WHEN** the style `300x250` is requested for a PNG 600×1200 with transparent pixels
- **THEN** the result has the size 300×250, the empty fields to the left and to the right are transparent, there is no blurred background, the transparent pixels of the image itself are kept

#### Scenario: Size by width only
- **WHEN** the style `300x` is requested for an image 1200×600
- **THEN** the result has the size 300×150 without empty fields

#### Scenario: Size by height only
- **WHEN** the style `x300` is requested for an image 600×1200
- **THEN** the result has the size 150×300 without empty fields

#### Scenario: Original smaller than one side
- **WHEN** the style `300x` is requested for an image 200×100
- **THEN** the result is enlarged to 300×150

#### Scenario: Capital letter
- **WHEN** the style `300X250` is requested
- **THEN** it is treated as `300x250` and gives the same addresses and files

#### Scenario: Preset
- **WHEN** the developer sets in the configuration the preset `thumb` with the sizes 200×200 and the style `thumb` is requested
- **THEN** the result corresponds to the size of this preset without changes to the other parts of the system

#### Scenario: Invalid style
- **WHEN** the style `0x0`, `300х250` with the Cyrillic «х» (Cyrillic letter, looks like Latin x), `abc` (which is absent among the presets) or `300` without the letter `x` is requested
- **THEN** the style is rejected and no derived file is created

#### Scenario: Animated GIF
- **WHEN** a style is requested for an animated GIF
- **THEN** the result has the contents of the original without changes and the extension `gif`

#### Scenario: WebP format by default
- **WHEN** any style is requested for a JPEG original
- **THEN** the result is a WebP image with the extension `webp`

#### Scenario: Keeping the format of the original
- **WHEN** the developer sets the format `original` and a style is requested for a JPEG original
- **THEN** the result is a JPEG with the extension `jpg`

#### Scenario: GIF in the WebP mode
- **WHEN** the format `webp` is set and a style is requested for a static GIF
- **THEN** the result remains a GIF

### Requirement: Пресети при завантаженні
The system SHALL create the files of the presets that the developer marked in the configuration for creation at the upload (at the start — `thumb`), right after saving the image, before the response to the upload. An error in the creation of such a preset SHALL NOT reject the upload: the file is saved, and the preset is created lazily on the first request. The other presets and the arbitrary sizes SHALL be created only lazily.

#### Scenario: Uploading an image
- **WHEN** an image is uploaded under the profile `image`
- **THEN** the file `styles/thumb/{uuid}.{extension}` of the size of the preset exists immediately

#### Scenario: Preset without the mark
- **WHEN** the configuration has a preset without the mark of creation at the upload
- **THEN** at the upload its file is not created, and it is created on the first request

#### Scenario: Failure of creation
- **WHEN** the creation of a preset at the upload ends with an error
- **THEN** the upload is successful, the original is saved, and the preset is created lazily later

#### Scenario: Document
- **WHEN** a file is uploaded under the profile `document`
- **THEN** no styles are created

### Requirement: Розташування й адреса стилю
The system SHALL store the file of a style on the disk of the profile `image` in the directory `styles/{style}/`, under the name `{uuid}.{extension}`, where `{uuid}` matches the original, `{extension}` is defined by the format of the style (see «Image style»), and `{style}` is the name of a preset or the normalized arbitrary size. The address of the file of a style SHALL be built from the address of the original by inserting `styles/{style}/` before the file name with the replacement of the extension by the format of the style, so the styles lie next to the originals and are served in the same way.

#### Scenario: Preset
- **WHEN** the original `/storage/{uuid}.jpg`, the style `thumb`, the format `webp`
- **THEN** the file of the style lies on the disk by the path `styles/thumb/{uuid}.webp`, and its address is `/storage/styles/thumb/{uuid}.webp`

#### Scenario: Arbitrary size
- **WHEN** the style `300x250`, the original `{uuid}.png`, the format `webp`
- **THEN** the file of the style lies by the path `styles/300x250/{uuid}.webp`

#### Scenario: Format `original`
- **WHEN** the format `original`, the original `{uuid}.jpg`, the style `thumb`
- **THEN** the file of the style lies by the path `styles/thumb/{uuid}.jpg`

### Requirement: Лінива генерація стилю
The system SHALL create the file of a style on the first request to its address, when the file does not exist yet (the arbitrary sizes, the presets without creation at the upload, the files uploaded before the styles appeared, and the presets after a clearing): write it to the disk of the profile `image` and serve it in the response. The next requests SHALL be served without re-creation. The creation SHALL work without any rules of the web server, except the standard redirection of non-existent files to the front controller of the application, and SHALL NOT require access to the configuration of the web server. The original SHALL be searched by `uuid` among the allowed image extensions, and the extension of the requested file of a style SHALL match the format of the style of this original. If the original does not exist, or the file name does not have the form `{uuid}.{extension}`, or the format is not processed, or the extension does not match the format of the style, the system SHALL respond 404 and not create files.

#### Scenario: First request
- **WHEN** the address of the style `thumb` is requested for an existing original, and the file of the style does not exist yet
- **THEN** the response 200 serves the image of the size of the style, and the file of the style appears on the disk

#### Scenario: Repeated request
- **WHEN** the address of the style is requested a second time
- **THEN** the file on the disk is not re-created, and the same contents is served

#### Scenario: Original does not exist
- **WHEN** the address of the style is requested for a `uuid` which is absent from the disk
- **THEN** the response is 404 and the file of the style is not created

#### Scenario: Not a file of the storage
- **WHEN** the address of the style is requested with a name that does not have the form `{uuid}.{extension}` (for example, with `..`)
- **THEN** the response is 404 and nothing is written

#### Scenario: Not an image
- **WHEN** a style is requested for a file of a document
- **THEN** the response is 404

### Requirement: Захист від довільних розмірів
The system SHALL create the file of an arbitrary size only on a request with a valid signature, which the template helper adds to the address (the parameter `itok`, computed from the secret of the application, the style and the file name). The presets from the configuration SHALL not require a signature. A request for an arbitrary size without a signature or with a signature of another style or file SHALL be rejected with 404 without creating a file. The side of an arbitrary size SHALL be not greater than the limit from the configuration of the developer (by default 4096 pixels); a style larger than the limit SHALL be rejected in the same way.

#### Scenario: Signed arbitrary size
- **WHEN** the template requests the style `300x250` through the helper and the browser requests the obtained address
- **THEN** the file is created and served

#### Scenario: Without a signature
- **WHEN** the address `/storage/styles/300x250/{uuid}.webp` is requested without a signature
- **THEN** the response is 404 and the file is not created

#### Scenario: Signature of another size
- **WHEN** a signature computed for `400x400` is added to the address of the style `300x250`
- **THEN** the response is 404 and the file is not created

#### Scenario: Too large a size
- **WHEN** the style `99999x99999` is requested even with a valid signature
- **THEN** the response is 404 and the file is not created

#### Scenario: Preset without a signature
- **WHEN** the address of the preset `thumb` is requested without a signature
- **THEN** the file is created and served

### Requirement: Хелпер для шаблонів
The system SHALL provide in the templates of the theme the function `image_style($url, $style)`, which takes the address of an image of the storage (or the value `{uuid}.{extension}`) and a style (a preset or an arbitrary size) and returns the address of the file of the style, without access to the database and the disk. For an arbitrary size the address SHALL contain the signature. An address that does not point to a file of the storage of images (empty, external, of an unexpected form) and an invalid style SHALL be returned without changes, so that the template does not break. The returned address SHALL keep the beginning of the original (the scheme and the host, if they were present).

#### Scenario: Preset
- **WHEN** the template calls `image_style('/storage/{uuid}.jpg', 'thumb')`
- **THEN** the result is `/storage/styles/thumb/{uuid}.webp` (the format `webp`)

#### Scenario: Arbitrary size
- **WHEN** the template calls `image_style('/storage/{uuid}.jpg', '300x250')`
- **THEN** the result is `/storage/styles/300x250/{uuid}.webp` with the signature in the parameter `itok`

#### Scenario: Value instead of an address
- **WHEN** the template calls `image_style('{uuid}.png', 'thumb')`
- **THEN** the result is the address `styles/thumb/{uuid}.webp` on the disk of the profile `image`

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

### Requirement: Очищення стилів
The system SHALL provide the console command `image-styles:flush`, which deletes the created files of the styles: all the styles without an argument or only the given style by its name. The command SHALL NOT touch the originals and SHALL NOT delete anything except the directories `styles/`. The deleted styles SHALL be created again on the next request.

#### Scenario: All styles
- **WHEN** `image-styles:flush` is run without an argument
- **THEN** the directory `styles/` of the disk of the profile `image` is empty, and the originals are in place

#### Scenario: One style
- **WHEN** `image-styles:flush thumb` is run
- **THEN** the directory `styles/thumb/` is deleted, the other styles remain

#### Scenario: Re-creation
- **WHEN** after the clearing the address of a style is requested
- **THEN** the file is created again according to the current settings of the preset

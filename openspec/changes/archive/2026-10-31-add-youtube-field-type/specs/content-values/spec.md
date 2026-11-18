## ADDED Requirements

### Requirement: Запис і читання додаткових даних значення
The system SHALL support field types whose value consists of a scalar value and additional data. The scalar value SHALL be stored in the value column of the type's table, and the additional data — in the JSON column `options` of the same row. Reading SHALL collect from them the same composed value that the write accepts. For types without additional data the column `options` SHALL remain empty, and the write and the read do not change. Additional data SHALL be replaced and deleted together with the value to which they belong, by the general rules of writing, multiplicity and translations.

#### Scenario: Saving and reading a composed value
- **WHEN** a composed value is written into a field of a type with additional data
- **THEN** on reading the same composed value is returned, the scalar part lies in the value column, the rest in `options`

#### Scenario: Type without additional data
- **WHEN** a value of a field of the type `string` is written
- **THEN** the column `options` is empty, and the reading returns the value as before

#### Scenario: Clearing
- **WHEN** `null` is passed to a field with a composed value
- **THEN** the row of the value is deleted together with `options`

#### Scenario: Multiple field with composed values
- **WHEN** a list of composed values is written into a multiple field
- **THEN** each element is stored in a separate row with its own `options`, the order is kept, and the reading returns the list in the same order

### Requirement: Тип поля «відео YouTube»
The system SHALL support the field type `youtube`. Its value SHALL consist of the full link to the video (the scalar value, in the table of string values) and of additional data: the video identifier (`video_id`), the title, the description and the screenshot. The identifier SHALL be determined from the link during the write, stored next to the value, and SHALL NOT be accepted from the input separately. The link SHALL be stored as it was entered (after trimming the spaces), together with all its parameters, in particular the timecode `t`. The accepted input forms: `watch?v=`, `youtu.be/`, `embed/`, `live/`, `shorts/`, including `m.youtube.com` and `youtube-nocookie.com`, and also the 11 characters of the identifier alone (they SHALL be converted to `https://www.youtube.com/watch?v={id}`), where `{id}` is the 11-character identifier of the video. The only conversion of the entered link — a link to Shorts (`youtube.com/shorts/{id}`) SHALL be converted to the ordinary `https://www.youtube.com/watch?v={id}` with the other parameters kept. The length of the link is not more than 512 characters. The title, the description and the screenshot SHALL be optional and editable; the screenshot is a reference `{uuid}.{extension}` to a file of the profile `image`. Saving SHALL NOT contact YouTube: it writes only what was sent. For a multiple field the general rules of multiple values SHALL apply, and translations SHALL NOT be supported. Since one video can be written by different links, the filter by this type SHALL support only the search by inclusion (`contains`), by which the video is found by its identifier, and equality SHALL NOT be supported. A value without a link SHALL be considered empty.

#### Scenario: Saving and reading a video
- **WHEN** the link `https://www.youtube.com/watch?v=dQw4w9WgXcQ`, a title, a description and a screenshot are written into the field `youtube`
- **THEN** on reading the link `https://www.youtube.com/watch?v=dQw4w9WgXcQ`, the title, the description and the screenshot are returned

#### Scenario: Different forms of the link
- **WHEN** `https://youtu.be/dQw4w9WgXcQ?t=10` or `https://www.youtube.com/embed/dQw4w9WgXcQ` is written into the field
- **THEN** the entered link is saved exactly, in particular `https://youtu.be/dQw4w9WgXcQ?t=10` with the timecode, without changes

#### Scenario: Link to Shorts
- **WHEN** `https://www.youtube.com/shorts/dQw4w9WgXcQ` is written into the field
- **THEN** the ordinary link `https://www.youtube.com/watch?v=dQw4w9WgXcQ` is saved

#### Scenario: Identifier only
- **WHEN** `dQw4w9WgXcQ` is written into the field
- **THEN** `https://www.youtube.com/watch?v=dQw4w9WgXcQ` is saved

#### Scenario: Timecode is kept
- **WHEN** `https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s` is written into the field
- **THEN** on reading the link with `t=42s` is returned

#### Scenario: Link that is too long
- **WHEN** the link is longer than 512 characters
- **THEN** the record is rejected with an error next to the field

#### Scenario: Identifier of the video next to the value
- **WHEN** any accepted form of a link to a video is written into the field
- **THEN** on reading the identifier of the video is returned (`dQw4w9WgXcQ` for `https://youtu.be/dQw4w9WgXcQ?t=10`), determined from the link, even if a different one was passed in the request

#### Scenario: Only the title entered manually
- **WHEN** a link without a title, a description and a screenshot is written into the field
- **THEN** the value is saved, the absent title, description and screenshot are read as `null`, and the identifier of the video is determined

#### Scenario: Invalid link
- **WHEN** a text is written into the field that is neither a link to a YouTube video nor an identifier of a video (in particular a link to a channel or a playlist)
- **THEN** the record is rejected with an error next to the field, nothing is saved

#### Scenario: Screenshot not from the image profile
- **WHEN** a link to a non-existent file or to a file of another profile is passed as the screenshot
- **THEN** the record is rejected with an error next to the field

#### Scenario: Saving without contacting YouTube
- **WHEN** a record is saved with a value of the field `youtube`
- **THEN** no request to YouTube is executed

#### Scenario: Required field
- **WHEN** a required field `youtube` is left without a link
- **THEN** the record is rejected with an error next to the field

#### Scenario: Search by a video
- **WHEN** records are filtered by the field `youtube` with the inclusion of the identifier of the video
- **THEN** the records with this video are returned, in whatever form of the link it was entered

#### Scenario: Equality is not supported
- **WHEN** records are filtered by the field `youtube` by equality
- **THEN** the filter is rejected as an unsupported operator

#### Scenario: The type does not change the general mechanism
- **WHEN** the type `youtube` is added
- **THEN** only its class, a row in the registry of types and the shared mechanism of writing `options` need changes; there are no new tables

### Requirement: Файл скріна відео в зв'язках файлів
The screenshot file of a value of the type `youtube` SHALL be tracked as a file used by the record: the link with the record is created during the saving of the value and is removed when the screenshot is replaced or cleared, or the value is deleted. A file that has such a link SHALL be considered used and SHALL NOT be removed by the media library or by the cleanup of free files. The file itself SHALL remain in the storage after the link is removed, until it is deleted by the media library.

#### Scenario: Screenshot in use
- **WHEN** a record with a video with a screenshot is saved
- **THEN** in the media library the screenshot file has one usage by this record and field

#### Scenario: Replacing the screenshot
- **WHEN** the screenshot of a video is replaced with another image and the record is saved
- **THEN** the new file is bound to the record, the link with the old one is removed, the old file remains in the storage

#### Scenario: Screenshot that was not saved
- **WHEN** a screenshot is uploaded, but the record with it is not saved
- **THEN** the file remains without a link and is removed by the cleanup of free files by the standard rule

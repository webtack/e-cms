# Proposal

## Why

Records need a field for a YouTube video: the editor pastes a link and receives the title, the description and the screenshot from the YouTube Data API, and then can edit them. All existing types have one scalar value, so a composed value (the link plus the data from the API) requires the first use of the column `options`, which all value tables have but which nobody reads or writes yet.

## What Changes

- A new value type `youtube`: `value` = the full link to the video, as it was entered (together with the timecode `t` and other parameters), in `content_values_string`; the video identifier `video_id`, the title, the description and the screenshot lie in `options` (JSON). There are no new tables.
- A link to Shorts (`youtube.com/shorts/{id}`) is turned into the ordinary `https://www.youtube.com/watch?v={id}` (YouTube plays Shorts by `watch?v=`), and the data from the API is read from it. Other forms (`youtu.be/`, `embed/`, `live/`, `m.youtube.com`, `youtube-nocookie.com`) are stored as entered; the identifier for the API is only extracted from them.
- The screenshot is stored as a file in the media library (the profile `image`), in `options` lies the `{uuid}.{ext}` of the file. The file is tracked in `media_relations`, so it is not considered free and is not removed by `files:prune`.
- A minimal extension of the shared mechanism: a type can give, alongside the value, `options` and collect a composed value back. `ContentValueWriter` writes `options`, `ContentValueReader` reads them. Types without this capability work as before.
- A separate request of the admin «get data by link»: it reads the YouTube Data API v3, downloads the largest available screenshot to the media library and returns the title, the description and the screenshot. It does not change the record. The record itself only saves what the form sent, and does not go to the API.
- If the video is unavailable (an invalid link, the video does not exist or is private, the key is missing, the API does not respond or the quota is exhausted), the request creates nothing, and the corresponding message is shown next to the field.
- The widget in the record form: a list of cards of videos (screenshot, title-link to the video that opens in a new tab, description) with the buttons for adding, editing and deleting. Adding and editing happen in a modal window: the field of the link with the button «Отримати дані», the editable title, the editable description and a block with the screenshot (preview, replacement with another image, deletion).
- The API key is taken from `config('services.youtube.key')` (`YOUTUBE_API_KEY` in `.env`, already defined).
- Multiple values are supported by the general rules. Translations are not planned.

Not included: translations of the title and the description, background updates of the data from YouTube, playing the video on the site, videos of other services, a cache of the API responses.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-values`: the new type `youtube` (value + `options`, the screenshot as a file), writing and reading `options` for the types that use them, tracking of the screenshot file.
- `admin-content-nodes`: the widget of the field `youtube` in the record form, the request to get the data by link with the messages about an unavailable video, the display of the value in the table of records.

## Impact

- Code (PHP): the new `App\Content\FieldTypes\YoutubeType`, a new contract of a composed value (`FieldType` remains unchanged), `ContentValueWriter` and `ContentValueReader` (writing and reading `options`), a service of the YouTube API client and the download of the screenshot, the controller of the request «get data» and the route in `routes/web.php`, `ContentNodeController` (the form: the data of the screenshot file; the table: the display of the value), `FileController`/`UploadRules`/`StoreFileRequest` (upload of an own screenshot for the field `youtube`), the lines in `config/content.php` (`field_types`) and `config/services.php` (`youtube.key`).
- Code (frontend): `Components/Admin/ContentNodes/NodeFieldInput.tsx` + a new component `YoutubeField` (the list of cards) and `YoutubeFieldDialog` (a modal window on `ui/dialog`), translations `lang/*.json`. There are no new npm dependencies.
- Tests: Feature tests of the type, writing/reading `options`, the file link of the screenshot and the request (with HTTP substitution); e2e `admin-content-nodes` (and `admin-content-fields` for choosing the type).
- Documentation: update `docs/content-values.md` (the registry of types, the section on composed values and `options`), `docs/admin-content-nodes.md` (the widget and the request), `docs/admin-content-fields.md` (the type in the list), `docs/file-storage.md` (the screenshot as a file of the field), `docs/e2e-testing.md` (scenarios and tables). `docs/README.md` remains valid, there are no new files.

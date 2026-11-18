# Design

## Context

See proposal.md (why and what). The current state that defines the approach:

- Each field type gives one scalar value: `FieldType::prepare()` returns what goes into the column `value` of the table `content_values_*`. `ContentValueWriter::replace()` writes only `value`, `ContentValueReader::load()` reads only `value` and calls `cast()`.
- All value tables have a JSON column `options` for additional data of a value (`docs/content-values.md`), which nobody reads or writes. By the owner's decision all additional data of composed values lie exactly there (separate tables and JSON in `value` were rejected).
- The writer treats an array as a **list** of values: `normalizeAll()` filters its elements, `singleValue()` rejects an array for a non-multiple field, `replace()` expands the array into rows. A composed value (an associative array) contradicts these rules.
- File types implement `TracksFiles`: after a write the writer synchronizes `media_relations`. `fileUuids()` receives the value in the form in which it was passed to the write, and in the form in which it is read.
- The upload of a field file: `FileController`, `UploadRules::forField()` and `StoreFileRequest` have a separate branch `instanceof RichTextType` («a field without file settings, but with the image profile»).
- Files (`FileStorage::store`) accept an `UploadedFile` and a profile; free files without links are removed by `files:prune`.
- `.env` already contains `YOUTUBE_API_KEY`; `config/services.php` has no entry `youtube`.
- Documentation: `docs/content-values.md` (the registry of types and the table schema), `docs/admin-content-nodes.md` (the record form), `docs/admin-content-fields.md` (types), `docs/file-storage.md` (files and links), `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- The type `youtube` without new tables and without changes for the existing types.
- The smallest extension of the core needed for `options`: an optional contract of a composed value.
- Saving does not depend on the network: only an explicit request «Отримати дані» may go to YouTube.
- The screenshot under the control of the media library (usage, cleanup).

**Non-Goals:**
- Translations, caching of API responses, background updates, playing the video on the site.
- A general mechanism "all types can have `options`": the contract is needed only by the types that implement it.
- Editing the name and the description of the screenshot file from the widget (this is done by the media library).

## Decisions

### D1. The optional contract of a composed value `ComposedValue`

A new interface (by analogy with `TracksFiles`), which `YoutubeType` implements:

```
split(mixed $input): array   // [$scalar, ?array $options]; the input — a link string or a form array
compose(mixed $scalar, ?array $options): mixed   // the composed value for reading
```

- Writer in `replace()`: for a type with `ComposedValue` it takes `[$scalar, $options]` from `split()` and writes `value = $scalar`, `options = json_encode($options)` (or `NULL`); for other types `options` = `NULL`, `prepare()` works as before.
- Reader: selects `options` together with `value`; for a type with `ComposedValue` it calls `compose($row->value, json_decode($row->options))` instead of `cast()`.
- `FieldType` does not change, the other types are not touched.

Rejected: (a) add `options` to `FieldType::prepare()` and `cast()` for all types: breaks the contract and all implementations; (b) the type writes to the DB itself: bypasses the transaction and `TracksFiles`.

### D2. A composed value is an associative array, not a list

The form of the value (PHP, the JSON of the form, reading): `{ url, video_id, title, description, screen }`. On input `url` is any accepted form of a link or the 11 characters of `id`; the entered link is stored (spaces trimmed), except Shorts and the bare `id`, which become `https://www.youtube.com/watch?v={id}`. On input `video_id` is ignored: it is always determined by `split()` from `url`, so it does not diverge from the link. The `options` hold `video_id` (always) and the non-empty `title`, `description`, `screen`. For reading `compose()` returns all five keys, the absent ones as `null`. `video_id` in `options` is not filtered by the core (filters work only on the column `value`), so the search for a video stays `contains` on `value`; `video_id` is needed for reading without parsing the link (rendering, a request to the API, a preview) and as a stable key.

Because of this the writer must distinguish "one composed value" from "a list": in `normalizeAll()`, `singleValue()` and `replace()` the condition "the value is a list" becomes "an array and (the type is not `ComposedValue` or `array_is_list`)". For a multiple field `youtube` the value is a list of associative arrays, as expected. These are the only three places of the writer that touch the form of the value.

The link is stored in `value` (the owner's decision), not in `options`, so there is no duplicated state. The parameters of the link, in particular the timecode `t`, are kept (the owner's decision).

### D3. Value, filter and validation

- `table()` = `content_values_string`, `value` = the entered link (a limit of 512 characters by the column `string(512)`; the rule `max:512`). One video can lie in different forms, so `value` does not identify the video unambiguously.
- `operators()` = `['contains']`; `searchable()` = `false`. Equality is not supported, because the forms of a link to one video differ; a video is found by the input `id` through `LIKE` (the operator `contains` of the core, `prepare()` is not called for it). This is a consequence of the decision to store the entered link; an index on `value` is not used for `LIKE '%…%'`, which is acceptable for the admin search.
- `rules()` for a write: the value is a string or an array with the keys `url`, `title`, `description`, `screen` (others are ignored); `url` is recognized as a link to a video or an `id`; `title` ≤ 255 characters, `description` ≤ 10000 characters (chosen limits, not configurable; the type has no `settings` of the field); `screen` — an existing file of the profile `image` (the rule `FileValue` without narrowing). The length limits can be changed in one line of code.
- `isBlank()`: `null`, `''` or an array without a non-empty `url`.
- Parsing of a link: one function of the type extracts the 11-character `id` from the forms `watch?v=`, `youtu.be/`, `embed/`, `shorts/`, `live/` (hosts `youtube.com`, `www.`, `m.`, `music.`, `youtube-nocookie.com`, `youtu.be`) or accepts exactly 11 characters `[A-Za-z0-9_-]` and for a bare `id` builds `https://www.youtube.com/watch?v={id}`. A link to Shorts (`/shorts/{id}`) is rewritten to the ordinary `watch?v={id}` with the other parameters kept, because YouTube plays the same clip by `watch?v=`, and the Data API `videos.list` identifies Shorts in the same way as an ordinary video, by `id`. The network is not used. Links to channels, playlists and `watch` without `v` are rejected.

### D4. The file of the screenshot: `TracksFiles` without a change of the contract

`YoutubeType` implements `TracksFiles`. `fileUuids()` accepts both the input and the read value: both have the key `screen`; since one composed value is an associative array, the method distinguishes it from a list through `array_is_list` (otherwise the standard `is_array ? $v : [$v]` would expand the keys). Therefore an extension of `TracksFiles` that seemed necessary is not needed. The writer continues to synchronize the links in the same transaction. A screenshot that was uploaded and not saved becomes a free file and is removed by `files:prune`.

### D5. The request «Отримати дані»: a separate endpoint and service

- The route `POST /admin/youtube/lookup` (the name `youtube.lookup`) in the group `auth` + `verified` next to `files.store`; `YoutubeLookupController` with a `FormRequest` (`url` is a required string).
- The service `App\Youtube\YoutubeLookup` does: parsing of the link (the same function as in the type; Shorts is rewritten to the ordinary one), from it the `id` → `GET https://www.googleapis.com/youtube/v3/videos?part=snippet&id={id}&key=…` through `Http` with a timeout → choice of the largest screenshot (`maxres`, `standard`, `high`, `medium`, `default`; if the download fails, the next smaller one) → download into a temporary file → `FileStorage::store()` through an `UploadedFile` from the temporary file (the profile `image`, the user of the request), after which the temporary file is deleted. A separate `FileStorage` method for bytes is not needed.
- Key: `config('services.youtube.key')` (`env('YOUTUBE_API_KEY')`); in `.env.example` the key is added without a value. The response and the Inertia props do not contain the key.
- Response `200`: `{ url, title, description, screen: { value, uuid, name, mime, size, url } }`. Errors: `422 { message, reason }` for `invalid_url` and `not_found`; `503 { message, reason }` for `not_configured` and `unavailable` (network, quota, 403/5xx). The texts go through `__()`, the keys are in `lang/en.json` and `lang/uk.json`. Saving the screenshot to the media library is the last step, so with any error nothing is created or changed.

Rejected: calling the API in `saving`/the writer, as in the old CMS (dependence of the write on the network, unexpected overwriting of manual edits), and oEmbed (there is no description).

### D6. Upload of an own screenshot: a shared `HasUploadProfile`

To replace the screenshot the widget uses the existing `FileUpload` of the field (`POST /admin/files` with `content_type` + `field_key`). To avoid a fourth `instanceof` branch, a small interface `HasUploadProfile { uploadProfile(): string }` is introduced, which `RichTextType` and `YoutubeType` implement; `FileController`, `UploadRules::forField()` and `StoreFileRequest` replace `instanceof RichTextType` with it. The behaviour of richtext does not change. A side effect: `ContentNodeController::formFields` gives those fields the `mimes`/`max_size` of the profile (for richtext they are not used).

### D7. The record form and the table

- `NodeFieldInput` receives a branch `case 'youtube'` with the component `YoutubeField`: a list of cards of videos (screenshot, title-link, description) with the buttons «Додати відео», «Редагувати», «Видалити». The value of the form is an object `{ url, title, description, screen }` (the form does not send `video_id`, the server determines it itself). The screenshot of a card is taken from the existing `files` (`fileInfo()`).
- The title of a card is a plain `<a href={url} target="_blank" rel="noopener noreferrer">` (not a button); without a title the text of the link is the address.
- Adding and editing happen in `YoutubeFieldDialog` on `ui/dialog`. The window holds a local draft `{ url, title, description, screen }` and hands it to the form only on «Готово»; «Скасувати» or closing discards the draft. In the window: an `Input` of the link with the button «Отримати дані», an `Input` of the title, a `Textarea` of the description, a block of the screenshot on `FileUpload`. The button calls `lookup` through `fetch` (as `FileUpload`), the window holds the loading state and the error; success overwrites `title`, `description`, `screen` of the draft, an error changes nothing. A change of `url` without pressing the button does not touch the other fields. The server part does not change.
- `ContentNodeController`: `fileInfo()` additionally collects the `screen` of the values `youtube`, so that the edit form has a preview without additional requests; `text()` for `youtube` returns the `title` or the link; `sortable()` for `youtube` = `false`.
- Records that already store composed values get them in the form as objects (`formValue()` does not change arrays).

## Risks / Trade-offs

- [Three places of the writer change, a risk of a regression for the existing types] → the change only adds a branch for `ComposedValue`; it is mandatory to run the existing tests of write/read and `FieldTypeExtensibilityTest`.
- [The YouTube Data API quota (10000 units per day, `videos.list` costs 1)] → the request is executed only on an explicit press, the quota message is distinguished; there is no cache.
- [Screenshots that the user got but did not save clutter the media library] → they are free files without links; `files:prune` removes them by the standard rule. A mass collection of screenshots by third parties is not possible: the request is only for authenticated users.
- [SSRF: the server downloads an image by a URL from the response of the API] → the address is taken only from the response of the YouTube API, not from user input; additionally only the host `i.ytimg.com` (and `img.youtube.com`) and `https` are allowed.
- [The type of the screenshot: YouTube serves JPEG, `FileStorage` looks at the content and the profile] → if the content is not an image or exceeds the limit of the profile, it is an unsuccessful download of the screenshot, that is "the video is unavailable" without a partial result (fixed in the spec).
- [A screenshot received in the modal window and cancelled remains a free file] → `files:prune` removes it by the standard rule.
- [The edited title/description is not updated when it changes on YouTube] → deliberately: an update only by a repeated «Отримати дані».

## Migration Plan

There are no migrations: the existing table `content_values_string` and the column `options` are used. Deployment: add `YOUTUBE_API_KEY` to the environment (on this stand it is already there). Rollback: remove the row `youtube` from `config/content.php`; the values in the DB remain harmless, but fields of this type will not work after the rollback, so they must be deleted before the rollback.

## Open Questions

- The length limits of `title` (255) and `description` (10000) are chosen as reasonable defaults; they change in one line and do not affect the specifications.

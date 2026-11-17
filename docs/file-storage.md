# File storage

The single shared mechanism for saving files: it accepts a file, checks it against a profile, puts it on disk, keeps metadata in the `media` table, and provides links. Consumers (fields, avatars, settings) store only the file's `uuid` (in content fields — `{uuid}.{extension}`) and do not work with the disk themselves. What is in the storage and where a file is used is shown by the [media library](media-library.md).

## Model `media`

The model `Webtack\Ecms\Models\File` (table `media`). Migrations: `2026_10_30_100000_create_files_table.php` (creation as `files`), `2026_10_30_110000_rename_files_table_to_media.php` (renaming), `2026_10_30_110100_shorten_media_uuid.php` (`uuid` without hyphens), `2026_10_30_110200_add_user_id_to_media_table.php` (the uploader), `2026_10_30_120000_add_description_to_media_table.php` (the description).

| Column | Type | Purpose |
| --- | --- | --- |
| `id` | bigint | The internal numeric identifier; not exposed outside |
| `uuid` | char(32), unique | The only external identifier: UUID v7 **without hyphens** (`0-9a-f`), generated on creation (`File::newUuid()`); it is what consumers store |
| `user_id` | bigint, null | The user who uploaded the file; FK to `users`, `nullOnDelete` (empty for old files and after the user is deleted); relation `File::uploader()` |
| `profile` | string(64) | The upload profile key from `config/files.php` |
| `disk` | string(64) | The disk the file lies on (recorded in the row, so a configuration change does not move existing files) |
| `path` | string(512) | The path on the disk: `{uuid}.{extension}` — the file lies at the root of the disk, without a directory |
| `original_name` | string(255) | The original file name — metadata only; the user can change it in the [node form](admin-content-nodes.md#image-and-file-fields) (the extension and the path on disk do not change) |
| `description` | text, null | An optional description; set in the node form and shown in the details of the [media library](media-library.md#media-library-page). The name and description belong to the file, so they are the same everywhere the file is used |
| `mime` | string(127) | The type determined by the content |
| `size` | unsigned bigint | The size in bytes |
| `hash` | char(64) | sha256 of the content; not unique |
| `width`, `height` | unsigned int, null | Dimensions in pixels, only for images |
| `created_at`, `updated_at` | timestamp | — |

The migration `shorten_media_uuid` removes hyphens from existing `uuid`s, moves files from the profile directories (`images/`, `documents/`) to the root of the disk, and updates `path`; a file missing from the disk does not stop the migration. The reverse conversion is impossible, so `down` throws an exception.

### Translated names and descriptions (`media_translations`)

Migrations `2026_11_17_110000_create_media_translations_table.php` and `2026_11_17_120000_add_name_to_media_translations_table.php` (the display name), model `Webtack\Ecms\Models\FileTranslation` (`File::translations()`). One row per file and additional language: `file_uuid` (char(32), FK to `media.uuid`, `cascadeOnDelete`), `lang` (binary collation, like `content_values_*`), `name` (string 255, null), `description` (text, null; a row holds at least one of them), unique (`file_uuid`, `lang`). `media.original_name` and `media.description` stay the base values. The translated name is **for display only**: the original name, the path and the extension never change, and the original name is shown wherever no translated name is defined. A translation belongs to the file, so every place that uses the file shows the same one.

`Webtack\Ecms\Files\FileDescriptions` reads and writes one language. A read returns only the stored translation (no fallback to the base description; the public fallback is a separate rule). A save stores a name or a description only when its trimmed text differs from the base one (the original name, the base description) and removes it when emptied; a key that is absent in the saved entry leaves that stored value alone, and a row left with neither value is deleted. The language must be an active additional one (`ContentLanguages::assertWritable()`; the base language is rejected with an error under `lang`).

```php
$descriptions = app(\Webtack\Ecms\Files\FileDescriptions::class);

$descriptions->save($uuid, 'en', 'Front cover', 'Cover');  // description and name; equal to the base or empty = removed
$descriptions->saveMany([$uuid => ['name' => 'Cover'], $other => ['description' => '']], 'en'); // one transaction; unknown uuids are ignored
$descriptions->forFile($uuid, 'en');                      // 'Front cover' or null
$descriptions->forFiles([$uuid, $other], 'en');           // [$uuid => 'Front cover']
$descriptions->namesForFiles([$uuid], 'en');              // [$uuid => 'Cover']
$descriptions->translations($uuid);                       // ['en' => ['name' => 'Cover', 'description' => 'Front cover']]
$descriptions->clear([$uuid], 'en');
```

Deleting a file deletes its translations (the foreign key cascade). `Webtack\Ecms\Content\LanguageUsages\FileDescriptionsUsage` registers the table with the [language usage registry](admin-content-languages.md), so a language with a translated name or description cannot be deleted.

The same content uploaded twice gives two separate rows with different `uuid`s and the same `hash`: there is no deduplication, so each row has its own file and independent deletion.

## PHP and nginx limits

- PHP: `docker/php/uploads.ini` (`upload_max_filesize=150M`, `post_max_size=200M`) is copied into the `app` image via `docker/php/Dockerfile`. The values have a margin above the largest profile (30 MB). After a change the image is rebuilt: `docker compose up -d --build app`.
- nginx: `client_max_body_size 64M` (`docker/nginx/default.conf`) does not change.
- The real limit of each profile is enforced by the `FileStorage` service; the PHP/nginx limits are only the upper bound of the transport.
- For public files the `public` disk needs a symbolic link: `docker compose exec app php artisan storage:link`.

## Profiles

The upload rules are described by the developer in `config/files.php` (key `profiles`); the user does not choose the disk or the rules. The `Webtack\Ecms\Files\FileProfiles` singleton reads the profiles: `keys()`, `has($key)`, `get($key)` (for an unknown key it throws `InvalidArgumentException`).

| Profile field | Value |
| --- | --- |
| `disk` | The disk key from `config/filesystems.php`; files lie at its root, and the location is set by the disk's own settings |
| `mimes` | Allowed types in the form `mime => extension of the saved file` |
| `max_size` | The maximum size, KB; checked against the uploaded file before any processing |
| `max_width`, `max_height` | Optional, only for profiles with images: the maximum width and height in pixels. A larger image is proportionally downscaled on save ([image processing](#image-processing-imageresizer)); a missing limit (`null`) does not limit the side |

At the start there are three profiles: `image` (JPEG, PNG, WebP, GIF; 30 MB; by default downscaled to 1920×1920 pixels; disk `public`), `avatar` (JPEG, PNG, WebP; 2 MB; by default downscaled to 512×512 pixels; disk `public`; used for [user avatars](admin-profile.md)) and `document` (PDF, DOC, DOCX, XLSX; 30 MB; disk `public`; has no image size limits).

### Settings from `.env`

The limits, profile sizes, and image processing parameters are read from environment variables in `config/files.php` (`env()` is called only there, so the configuration cache works). Default values apply when the variable is absent from `.env`; the complete list with comments is in `.env.example`.

| Variable | Configuration key | Default | Purpose |
| --- | --- | --- | --- |
| `FILES_IMAGE_MAX_SIZE` | `profiles.image.max_size` | `30720` | The maximum size of an image, KB |
| `FILES_DOCUMENT_MAX_SIZE` | `profiles.document.max_size` | `30720` | The maximum size of a document, KB |
| `FILES_AVATAR_MAX_SIZE` | `profiles.avatar.max_size` | `2048` | The maximum size of an avatar, KB |
| `FILES_AVATAR_MAX_WIDTH` | `profiles.avatar.max_width` | `512` | The maximum avatar width, px; `null` removes the limit |
| `FILES_AVATAR_MAX_HEIGHT` | `profiles.avatar.max_height` | `512` | The maximum avatar height, px; `null` removes the limit |
| `FILES_IMAGE_MAX_WIDTH` | `profiles.image.max_width` | `1920` | The maximum width, px; `null` removes the limit |
| `FILES_IMAGE_MAX_HEIGHT` | `profiles.image.max_height` | `1920` | The maximum height, px; `null` removes the limit |
| `FILES_IMAGE_DRIVER` | `image.driver` | `imagick` | The Intervention Image driver: `imagick` or `gd` |
| `FILES_IMAGE_JPEG_QUALITY` | `image.quality.jpeg` | `85` | JPEG quality when re-encoding (1–100) |
| `FILES_IMAGE_WEBP_QUALITY` | `image.quality.webp` | `85` | WebP quality when re-encoding (1–100) |
| `FILES_IMAGE_MAX_PIXELS` | `image.max_pixels` | `100000000` | The limit of `width × height`, above which an image is rejected before decoding |

The profile sizes must be smaller than the PHP (`docker/php/uploads.ini`) and nginx limits: otherwise the upload is cut off before the profile validation. The `image` block in `config/files.php` (`driver`, `quality`, `max_pixels`) does not belong to a profile: it configures the processing for all profiles with images. The test `FileProfilesTest` checks the presence of the keys and that `null` in `.env` gives `max_width === null`.

**Adding a profile** — one block in `config/files.php`, no other code changes:

```php
'profiles' => [
    // ...
    'logo' => [
        'disk' => 'public',
        'mimes' => ['image/png' => 'png', 'image/webp' => 'webp'],
        'max_size' => 1024, // KB
    ],
],
```

The test `FileProfilesTest` checks that the disk of each profile is in `config('filesystems.disks')`.

### Short type labels

The admin panel shows the file type as a **short extension label** (`doc`, `docx`, `xlsx`, `pdf`, `jpg`), not as a long MIME string. The label is given by `Webtack\Ecms\Files\FileTypes` based on the `mimes` of the profiles (the extension of the saved file), so the source is one — `config/files.php`:

| Method | Result |
| --- | --- |
| `FileTypes::label($mime)` | the extension from the profile (`image/jpeg` → `jpg`); for a type that is in no profile — the part of the MIME after `/` (`text/plain` → `plain`) |
| `FileTypes::labels()` | `mime => label` for all profiles |
| `FileTypes::options()` | the unique labels in profile order — the options of the [media library filter](media-library.md#media-library-page) |
| `FileTypes::mimesFor($label)` | all MIME types with this label (there may be several); empty for an unknown one |

The label is for display and filtering only: in `media.mime`, in the field's `settings.mimes`, and in the validation rules the full MIME types remain. The labels are shown by the "Type" column and the details panel of the [media library](media-library.md) (the field `type` next to `mime` in the rows and in the JSON of the details) and by the list of allowed types in the [file field settings](admin-content-fields.md) (`fileTypes[...].labels`). The test — `tests/Feature/Files/FileTypesTest.php`.

## FileStorage

`Webtack\Ecms\Files\FileStorage` (a singleton, no facade) — the single point of saving, addressing, and deleting files.

```php
$storage = app(FileStorage::class);

$file = $storage->store($request->file('file'), 'image'); // Webtack\Ecms\Models\File
$storage->url($file);                                     // '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'
$storage->delete($file);                                  // the row first, then the file on disk
```

### `store(UploadedFile $upload, string $profile, ?User $user = null, ?UploadRules $rules = null): File`

The checks are performed **before** writing to disk; on rejection `ValidationException` is thrown with the key `file`, and nothing is created:

1. the upload is valid (`isValid()`);
2. the type is determined by the **content** (`finfo`), not by the extension or the client's header;
3. a blacklist (applies to all profiles, even if the profile allows the type): the types `FileStorage::FORBIDDEN_MIMES` (PHP, shell, executable files, `image/svg+xml`) and the extensions of the original name `FileStorage::FORBIDDEN_EXTENSIONS` (`php`, `phtml`, `phar`, `sh`, `exe`, `svg`, etc.);
4. the type is in the profile's `mimes`;
5. the size of the **uploaded** file does not exceed `max_size` (exactly at the limit — accepted); the limit is checked before downscaling, so an 8 MB photo is rejected even though it would be small after downscaling;
6. for `image/*` the content is read as an image (`getimagesize`, the header only), hence `width`/`height`;
7. the pixel limit: `width × height` must not exceed `files.image.max_pixels`, otherwise a refusal ("too many pixels") before decoding;
8. downscaling: if the effective [rules](#uploadrules) have `maxWidth`/`maxHeight` and the image exceeds them, [`ImageResizer`](#image-processing-imageresizer) returns the downscaled content. A library exception (corrupt content) becomes a `ValidationException` "Could not process the image", and the original exception goes to the log (`warning`).

Then the file is written to the root of the profile's disk as `{uuid}.{extension from the profile's mime}` — the original name never gets onto the disk (it is kept only in `original_name`, without directories). If the image was downscaled, the **downscaled** bytes are written to disk; otherwise the uploaded file as is. The `media` row is created; `user_id` is the passed user (optional). The metadata `size`, `hash`, `width`, `height` describe the **saved** file, not the uploaded one; `mime` and the extension remain the same. If the insertion fails, the written file is deleted (the DB transaction cannot roll back the disk).

The order: `isValid → type by content → type and size of the original → getimagesize → pixel limit → ImageResizer → write to disk → media row`.

After the row is created, for an image of the `image` profile the [styles](image-styles.md#creation-on-upload) with `upload => true` (the `thumb` thumbnail) are created. A failure of the creation is logged and does not reject the upload: the preset will be created lazily. All refusals occur before writing, so on refusal there is nothing on disk and in the DB. Tests — `tests/Feature/Files/FileStorageTest.php` (the "Image scaling" block).

The profile must be valid: an unknown key throws `InvalidArgumentException` (the HTTP endpoint checks the key earlier).

**OOXML (DOCX/XLSX).** Verified on the `app` image (libmagic 5.46): `finfo` recognizes valid DOCX/XLSX (with `[Content_Types].xml` in the package) as `application/vnd.openxmlformats-officedocument.*`, and a plain ZIP remains `application/zip` and is rejected. The fallback `application/zip` + extension was not applied; if some editor writes a package that `finfo` does not recognize, such a file will be rejected as a disallowed type.

### `url(File $file): string`

`Storage::disk($file->disk)->url($file->path)`. The disk is taken from the file's row, so a change of the profile's disk in the configuration does not break links to previously uploaded files. The `public` disk requires `storage:link`. Private files (temporary links, a route with a permission check) are not supported here yet.

The address of a derived size (a thumbnail, `300x250`) is obtained by the template from this URL with the helper `image_style()`: see [Image styles](image-styles.md#image_style-helper).

### `previewForValue(string $profile, string $value, string $mime): ?string`

The address of the `thumb` style of the saved value `{uuid}.{ext}` for the preview in the admin panel: if `$mime` starts with `image/` — `image_style(urlForValue(...), 'thumb')`, otherwise `null`. It does not query the DB: `$mime` is taken from the `media` row that the controller already has (an empty string when there is no row). The result is the field `preview` in the file's data, see [Thumbnails in the admin panel](image-styles.md#thumbnails-in-the-admin-panel).

### `delete(File $file): void`

First the row is deleted, then the file on disk. If the file has a usage relation (the FK `RESTRICT` of the table `media_relations`), a `QueryException` is thrown and the disk is not touched. After the file all of its [image styles](image-styles.md#deletion) (`styles/{style}/{uuid}.*`) are deleted too; a rejected deletion of styles does not affect anything. A file that is already missing from the disk is not an error. Consumers do not call `delete` themselves: the administrator deletes unused files manually in the [media library](media-library.md).

## UploadRules

`Webtack\Ecms\Files\UploadRules` — the effective rules of one upload: the profile, the allowed types (`mimes`), `maxSize` (KB), and for images `maxWidth`/`maxHeight` (px, `null` — the side is not limited). The profile from `config/files.php` is the ceiling; a field of type `image`/`file` (and `richtext` and `youtube` — for images, without their own settings) only narrows it through `content_fields.settings`.

| Field setting | How it intersects with the profile |
| --- | --- |
| `mimes` | the intersection with the profile's list; foreign types are ignored |
| `max_size` | the smaller of the two |
| `max_width`, `max_height` | the smaller of the two; if the profile sets no limit, the field's value applies; if neither does — the side is not limited |

Invalid values (not a positive integer: `0`, negative, text, an array) are ignored, so the result is never wider than the profile. Strings from environment variables (`"1920"`) are read as numbers, and an empty string — as a missing limit. Executable files and SVG are never allowed.

```php
UploadRules::forProfile('image');       // the profile limits: 1920×1920
UploadRules::forField($field);          // settings: max_width 500 → maxWidth 500, maxHeight 1920
```

`forField()` reads `settings.max_width`/`max_height` only for types with an image profile; the final check of the saved value is done by [`FileValue`](content-values.md#images-and-files) by type and size in KB (the pixels apply only on upload and do not affect existing files). Tests — `tests/Unit/Files/UploadRulesTest.php`.

## Image processing (ImageResizer)

`Webtack\Ecms\Files\ImageResizer` downscales images to the limits `max_width` × `max_height` of the effective [rules](#uploadrules). It is called by `FileStorage::store()` between the checks and the writing; the class knows nothing about the disk and the database, so it is tested separately (`tests/Unit/Files/ImageResizerTest.php`).

`resize($path, $mime, $maxWidth, $maxHeight)` returns either `null` (save the uploaded file as is) or `['contents' => bytes, 'width' => ..., 'height' => ...]`.

**Algorithm.** `scale = min(maxWidth / width, maxHeight / height, 1)`; a missing limit (`null`) does not limit the side. The side that defines the scale equals its limit exactly, the other is rounded to the nearest integer (at least 1). There is no enlargement. The calculation — `ImageResizer::targetSize()`.

| Example | Result |
| --- | --- |
| 4000×3000, limits 1000×1000 | 1000×750 |
| 3000×4000, limits 1000×1000 | 750×1000 |
| 1600×1200, limits 800×400 | 533×400 (the height is exceeded more) |
| 2000×3000, only `max_width` 500 | 500×750 |
| 640×480, limits 1000×1000 | `null`: the file is saved without re-encoding |
| 100×100, limits 5000×5000 | `null`: not enlarged |

**Exceptions (`null` is returned, the file is saved unchanged):** the image is already within the limits; no limit at all; an animated GIF or WebP (determined from the file header without decoding the pixels: multiple frames in GIF, the animation flag `VP8X` in WebP); a type that the class does not process. A non-animated GIF is downscaled as a regular image.

**What happens on downscaling:**
- EXIF orientation is applied during decoding (`autoOrientation`), so the saved pixels look the way the viewer shows them. For a JPEG rotated by 90° (EXIF 5–8) the width and height for the calculation are swapped, and after decoding the size is taken again from the real image;
- the format stays the same (JPEG, PNG, WebP, GIF), the transparency of PNG and WebP is kept;
- the JPEG and WebP quality is taken from `files.image.quality.*`; PNG is encoded losslessly;
- EXIF metadata (together with GPS) is not kept in the downscaled file;
- the driver — `files.image.driver` (`imagick` by default, `gd` — the fallback).

**Errors.** If the content cannot be decoded (the data after a valid header is corrupt), the library throws an exception; `FileStorage::store()` turns it into a validation error ([order of checks](#filestorage)). A truncated but readable file is partially decoded by Imagick, as a browser does: this is not an error.

The correspondence of the specification scenarios to the `ImageResizerTest` tests:

| Scenario | Test |
| --- | --- |
| The width / height exceeds the limit | `test_a_wide_image_is_scaled_by_its_width`, `test_a_tall_image_is_scaled_by_its_height` |
| Different limits for the sides | `test_the_side_that_exceeds_its_limit_most_defines_the_scale` |
| Only one limit | `test_a_single_limit_is_enough` |
| An image within the limits | `test_an_image_within_the_limits_is_left_alone` |
| No enlargement | `test_an_image_is_never_enlarged` |
| Transparency | `test_png_keeps_its_format_and_transparency` |
| Orientation | `test_exif_orientation_is_applied` |
| Animated GIF / WebP | `test_an_animated_gif_is_left_alone`, `test_an_animated_webp_is_left_alone` |
| The format does not change | `test_webp_keeps_its_format`, `test_a_still_gif_is_scaled` |
| Metadata is not kept, quality, driver, a corrupt file | `test_metadata_is_not_kept`, `test_the_configured_quality_changes_the_jpeg_size`, `test_the_gd_driver_can_be_configured`, `test_a_corrupt_image_throws` |

## File upload

A two-step flow: first the file is uploaded in a separate request, then the form sends only its `uuid` (or `value`) in regular JSON — so writing values (`ContentValueWriter`, `ContentNodeCreator`) does not depend on files.

`POST /admin/files` (`admin.files.store`), `Webtack\Ecms\Http\Controllers\Admin\FileController::store`; the group `auth` + `verified` (a guest — 401 for JSON / a redirect to `/login`, an unverified user — 403). The body is `multipart/form-data`:

| Field | Rule |
| --- | --- |
| `file` | required, a file |
| `profile` | one of the keys of `config('files.profiles')`; required if there is no `field_key` |
| `content_type`, `field_key` | a content field; required together if there is no `profile` |

Specify **either** `profile`, **or** the pair `content_type` + `field_key`; both at once is a validation error (`profile` and `field_key`). For a field the server checks that it exists and has the type `image`, `file`, or a type with the interface `HasUploadProfile` — `richtext`, `youtube` (otherwise the error `field_key`); it takes the type's profile (`image` → `image`, `file` → `document`, `richtext` and `youtube` → `image` via `uploadProfile()`) and narrows it by the field's settings (`mimes`, `max_size`, and for images also `max_width`, `max_height`) via `UploadRules::forField()` (a type with `HasUploadProfile` has no file settings, so it gets the rules of the whole `image` profile); `FileStorage::store()` receives these rules as the fourth argument. The rules are never wider than the profile. The final check of the value on the node save is done by the field type (see [content-values.md](content-values.md#images-and-files)), and the endpoint rejects the file earlier.

The `201` response:

```json
{
  "uuid": "0190a1b27c3e7f0a8b1c2d3e4f5a6b7c", "profile": "image", "name": "logo.png",
  "mime": "image/png", "size": 1234, "width": 640, "height": 480,
  "value": "0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png",
  "url": "/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png",
  "preview": "/storage/styles/thumb/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp"
}
```

The `size`, `width`, and `height` of the response describe the **saved** (possibly downscaled) file. There is no numeric `id` in the response. `value` — the value for the content field (`{uuid}.{extension}`). `preview` — the address of the `thumb` [style](image-styles.md) for an image (requires `FileStorage::previewForValue()`), `null` for a document; the `FileUpload` widget shows it instead of the original. `width`/`height` — `null` for non-images. The uploader (`user_id`) is the authenticated user. Errors are the standard Laravel `422` for AJAX (`errors.file`, `errors.profile`); a rejection by type, size, the number of pixels, or the impossibility of processing an image is reported under the key `file`. The endpoint does not know about the consumer and does not limit the request rate (only admin panel users have access).

## FileOfProfile rule

`Webtack\Ecms\Files\Rules\FileOfProfile` — a rule for the consumer's field: the value must be the `uuid` of an existing file uploaded with the specified profile. The endpoint accepts any profile, so this rule is exactly what does not let, for example, a document into an avatar field.

```php
$request->validate([
    'avatar' => ['nullable', new FileOfProfile('image')],
]);
```

- Only a string of 32 characters `0-9a-f` is accepted; everything else (`'abc'`, a number, an array, a UUID with hyphens, `{uuid}.{ext}`, uppercase) is an error.
- An empty value (`null`, `''`) is not checked by the rule: it is the business of the consumer's `required`/`nullable`.
- The messages are translated (`lang/en.json`, `lang/uk.json`).

**Connecting a new consumer.** The consumer stores the `uuid` in its own column (without a foreign key to `media`) and registers the usage in `media_relations` through the relations service; see [Media library](media-library.md). Deleting a used file is blocked by the FK `RESTRICT` on `media_relations`.

## Lifecycle

A file lives until the administrator deletes it in the [media library](media-library.md) (only an unused one). When a consumer replaces or clears a value, it changes only its own reference and does not delete the file. There is no automatic cleanup. The screenshot of a `youtube` field is the same file with a relation in `media_relations` ([media-library.md](media-library.md#youtube-video-screenshot)); a screenshot obtained by a request but not saved remains a free file, which the administrator deletes.

## Widget and demo page

`resources/js/Components/Admin/FileUpload/FileUpload.tsx` — a reusable component (see also the [frontend structure](admin-shell.md#structure-of-resourcesjs)). Choosing a file or dragging into the zone, the "Uploading…" indication, a validation error, an image preview (the `thumb` style from `preview`, and when it is absent — the original; the thumbnail is a link to the original in a new tab, `target="_blank"`, `cursor-pointer`), a clear button. The file is sent **immediately** in a separate `POST /admin/files` request (`fetch`, CSRF — through the `XSRF-TOKEN` cookie in the `X-XSRF-TOKEN` header), and the form receives the uploaded file through `onChange` and stores only `file.uuid` (the content field — `file.value`).

| Prop | Purpose |
| --- | --- |
| `profile` | The profile key from `config/files.php` |
| `value` | The current file (`UploadedFile`) or `null` (the component is controlled) |
| `onChange(file \| null)` | Called after the upload (the response metadata) and after clearing (`null`) |
| `accept`, `label`, `id` | A hint for the file choice, the label, the `id` of the field |
| `error` | An error from the form; an upload error takes priority |

```tsx
const [avatar, setAvatar] = useState<UploadedFile | null>(null);

<FileUpload
    profile="image"
    accept="image/*"
    label={t('Avatar')}
    value={avatar}
    onChange={(file) => { setAvatar(file); setData('avatar', file?.uuid ?? null); }}
/>
```

#### Multiple mode

The `multiple` prop enables choosing and dragging **several files at once** (`<input multiple>`). Instead of `value` / `onChange`, the mode uses `onUploadedMany(files)`; in this mode the widget does not show a chosen file — the list is drawn by the form.

```tsx
<FileUpload
    multiple
    field={{ contentType: 'article', fieldKey: 'gallery' }}
    accept="image/png"
    maxSize={1024}
    onUploadedMany={(files) => addToList(files)}
/>
```

- **Separate sequential requests.** Each file goes in its own `POST /admin/files` (the same endpoint as for one file; the backend does not change), one after another, not in parallel and not in a shared request. The single upload is performed by the same function `uploadFile()`.
- **Quick check.** Before the request each file is checked against `accept` and `maxSize`; a file rejected this way creates no request.
- **Errors.** A failure of one file (a quick rejection, a server response, a network failure) is recorded as `{ name, message }` and shown as a list "name: reason" (`:name: :message`); the rest of the queue is uploaded further. The list is cleared at the beginning of the next choice.
- **Progress.** While the queue is running, the widget shows "Uploaded N of M" (`role="status"`), the selection field is disabled, and a new choice or dragging is ignored until the queue completes.
- **Passing to the form.** `onUploadedMany` is called **once after the queue completes** with all uploaded files in the order of selection (not called if none was uploaded). In a batch rather than one file at a time, so that successive calls do not lose items through a stale state in the form's closure.
- **Capacity.** The optional `capacity` (how many files the form can still accept) and `capacityMessage` (the reason for files that did not fit): from the batch only the first `capacity` files in the order of selection are uploaded, the rest create no request and get into the list of failures with `capacityMessage`; `undefined` — no limit. This is how the node form respects the field's `max_items`, without leaving files in the storage without a place in the list.
- **The list of failures.** By default the widget shows itself, under the zone, the files that did not fit or were not uploaded (`{testid}-failures`). If `onFailures` is passed, it passes the list outward (after each batch, and at the start of a batch — empty) and does not draw it itself: the node form does this, because it hides the zone at the limit, and the list should not disappear together with it.

The test identifiers: `{root}-progress`, `{root}-failures`, `{root}-zone` (`root` — the widget's `data-testid`).

`accept` is only a browser hint: the type and size are checked by the server by the profile, and the component shows the server's messages (in the interface language) as is. The component does not depend on the Inertia form and works both with `useForm` and with a plain state.

**Props of the single mode `removable`, `description`, `details`, and `actions`.** `description` — the text of the description of the chosen file, drawn between the name and the size (`line-clamp-2`, `data-testid="{root}-description"`); an empty or missing one draws nothing. `removable` (default `true`) shows the "Delete file" button in the block of the chosen file; `false` hides it — so the block of a row of a multiple field leaves the deletion to the row's cross. `details` — an arbitrary `ReactNode` drawn inside the block of the chosen file under the name and size; the [node form](admin-content-nodes.md#image-and-file-fields) previously put the "Name" and "Description" fields there, now it does not use them; the prop remains for compatibility. `actions` — an arbitrary `ReactNode` in the block's button row (before "Delete file", and with `removable={false}` — in its place); the node form puts there the "Edit file data" button. Without these props the behavior is unchanged, so the demo page and other consumers do not change. In `multiple` mode the props do not apply.

**Demo page** `/admin/ui/file-upload` (`admin.ui.file-upload`, `Ui\FileUploadDemoController`, `Pages/Admin/Ui/FileUploadDemo.tsx`) — two widgets (`image`, `document`) showing the `uuid` a form would receive, and a widget in `multiple` mode (only PNG; `data-testid="file-upload-many"`) that shows the names of the files uploaded through it. Outside the navigation; in production it responds with 404. The files uploaded on it are really stored and, having no usages, remain in the media library until manually deleted. Covered by the spec `e2e/admin-file-upload.spec.js` ([e2e-testing.md](e2e-testing.md#file-upload-scenario-admin-file-uploadspecjs)).

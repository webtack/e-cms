# Proposal

## Why

Images are stored on the disk exactly as they were uploaded: a phone photo of 5 MB and 4000×3000 pixels goes to the site as it is. This inflates the disk and slows down the public pages and the media library. The owner of the site can set size limits, and the system brings the images to them during the upload.

## What Changes

- The upload profile `image` receives the optional `max_width` and `max_height` (pixels). If an image is larger than the limit along at least one side, it is reduced proportionally to the first limit that is exceeded: `scale = min(max_width / w, max_height / h, 1)`. There is no enlargement of images.
- The settings of a field of the type `image` (`settings`) receive `max_width` and `max_height`, which only **narrow** the limits of the profile (the smaller value is taken), as `max_size` already does. The form of the field in the admin shows them next to «Максимальний розмір (КБ)» ("Maximum size (KB)").
- An image that already fits within the limits is saved without re-encoding, byte for byte.
- Animated GIF and WebP are saved without changes; the reduction is skipped for them.
- During the reduction the EXIF orientation is taken into account, the transparency is kept, the format stays the same (JPEG, PNG, WebP). The quality of JPEG and WebP during re-encoding is taken from the configuration. The EXIF metadata is not kept in the reduced file.
- The record in `media` describes the processed file: `size`, `hash`, `width`, `height`. The limit `max_size` is still checked against the uploaded original, before the reduction.
- An image with too many pixels (the limit in the configuration) is rejected with a validation error before decoding, instead of a crash due to lack of memory.
- The processing uses `intervention/image` with the driver `imagick`. The extension `imagick` is already added in `docker/php/Dockerfile`.
- Thumbnails (presets, `thumb`/`medium`) are **not** part of this change; they will be a separate change.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `file-storage`: the upload profile describes the size limits of an image; saving an image reduces it to the limits; the metadata of the record correspond to the processed file; the settings of the field narrow the limits of the profile; protection against too large images.
- `admin-content-fields`: the form of the field `image` shows the settings of the maximum width and height, the server checks them.

## Impact

- Code: `app/Files/FileStorage.php` (the call of the processing between the check and the writing), a new image processing class in `app/Files/`, `app/Files/UploadRules.php` and `app/Files/FileProfiles.php` (the limits in the rules), `config/files.php` and `.env.example` (the variables `FILES_IMAGE_*`: `max_width`, `max_height`, the driver, the quality, the pixel limit with default values), `app/Http/Requests/Admin/StoreContentFieldRequest.php` and `app/Http/Controllers/Admin/ContentFieldController.php` (the settings of the field), `resources/js/Components/Admin/ContentFields/ContentFieldForm.tsx` and the type `FileSettings` (the form of the field), the interface translations.
- Dependencies: `intervention/image` (`composer.json`), the extension `imagick` in the image `app` (`docker/php/Dockerfile` is already changed).
- Tests: PHP Feature/Unit for `FileStorage`, `UploadRules`, the settings of the field; an e2e spec of the form of the content field.
- Documentation (in Ukrainian): update `docs/file-storage.md` (the profiles, `store()`, the processing of images), `docs/admin-content-fields.md` (the settings of `image`), `docs/content-values.md` (images and files); `docs/e2e-testing.md` if needed.
- The existing files do not change: the reduction applies only to new uploads.

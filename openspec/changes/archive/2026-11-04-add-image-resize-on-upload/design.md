# Design

## Context

The behaviour is described by the delta specifications `file-storage` and `admin-content-fields` (the motivation is in `proposal.md`). The current state:

- The single point of saving is `FileStorage::store()` (`app/Files/FileStorage.php`): the check of the type and size, `getimagesize`, `putFileAs` of the original, a row `media` with the metadata of the original.
- The upload rules — `UploadRules` (`app/Files/UploadRules.php`): a profile from `config/files.php` (`disk`, `mimes`, `max_size`), which a field can only narrow through `settings` (`mimes`, `max_size`). The field rules are built by `UploadRules::forField()`, the form of the field — `StoreContentFieldRequest::settingsRules()` and `ContentFieldForm.tsx`.
- The profile `image` is used by all consumers, not only by the fields `image`: `richtext`, and other places that call `store()` with this profile. The limits of the profile will therefore apply to them too without changes to their code.
- The `app` image already contains `imagick` (`docker/php/Dockerfile`, verified: the extension 3.8.1, ImageMagick 7.1.1, the formats JPEG, PNG, WEBP, GIF, HEIC, AVIF). The policy of ImageMagick in the container: `area` 256MP, `memory` 1GiB, `width`/`height` 32KP. The PHP `memory_limit` — 128M.
- `intervention/image` is not yet in the project. Version 4: `ImageManager::usingDriver(Driver::class)`, `decodePath()`, `scale()`, `encodeUsingFormat()`, `save()` (checked against the documentation 4.4.0).

The documents described here that change: `docs/file-storage.md` (the profiles, `store()`), `docs/admin-content-fields.md` (the settings of `image`), `docs/content-values.md` (the images and files).

## Goals / Non-Goals

**Goals:**
- All new images of the profile `image` do not exceed the set limits, the proportions are preserved.
- One processing point in `FileStorage::store()`, without changes in the consumers.
- The data in `media` always describe the saved file.
- Too large images are rejected with a controlled error, not with a memory failure.

**Non-Goals:**
- Thumbnails and size presets (a separate change).
- Processing of the already saved files, a command for the mass reduction.
- A change of the format (JPEG → WebP etc.), cropping, watermarks.
- Processing in a queue: the saved file must be final at the moment of the upload response.
- Support of HEIC/AVIF as allowed types: the `mimes` of the profiles do not change.

## Decisions

### 1. A separate class `App\Files\ImageResizer`, called from `FileStorage::store()`

The class takes the path to the file, the MIME and the effective limits, and returns either `null` (do nothing, save the original) or a result (the contents/temporary file, the width, the height). `store()` calls it after `assertAllowed` and `getimagesize`, before the writing. Then:

- `null` → `putFileAs` of the original, as now;
- a result → the writing of the processed bytes through `Storage::disk(...)->put()`, and `size`, `hash`, `width`, `height` are taken from the result.

The alternative — the processing inside `store()` — was rejected: the method is already long, and a class with one task is easy to test without the disk and the DB.

### 2. The limits: the profile + the field, the smaller value is taken

`UploadRules` receives `maxWidth` and `maxHeight` (`?int`). `narrowed()` takes two more parameters and for each limit does: if the profile sets a limit, then `min(profile, field)`; if not, the value of the field; if there is neither, then `null` (the side is not limited). The incorrect values of the field (not a positive integer) are ignored, as for `max_size`. `FileProfiles::get()` and the phpstan type of the profile receive the optional `max_width`, `max_height`.

The profile `richtext` and the direct calls `UploadRules::forProfile('image')` receive the limits of the profile.

### 3. The calculation of the size

`scale = min(maxWidth / w, maxHeight / h, 1)`, where an absent limit is treated as «no limit». The new sides are rounded to the nearest integer, not less than 1; the side that defined the scale equals its limit exactly (so that, for example, 4000×3000 with 1000×1000 gives exactly 1000×750). If `scale == 1`, the processing is not needed and the class returns `null`.

### 4. Processing through `intervention/image` with the driver `imagick`

The driver is taken from `config('files.image.driver')` (by default `imagick`), which allows switching to `gd` without changing the code. The algorithm in `ImageResizer`:

1. Determine the animation before decoding the pixels. For GIF and WebP it is the number of frames through a cheap reading of the header (`Imagick::pingImage`, without unpacking the pixels) or the corresponding method of Intervention, if it does not decode the frames; with more than one frame return `null`.
2. Decode the file, apply the EXIF orientation, reduce, encode in the same format: JPEG and WebP with the quality from the configuration, PNG losslessly.
3. The EXIF metadata is not saved (as a side effect it removes the GPS).

The exact names of the Intervention methods for the orientation, the reduction and the check of the animation are verified during the implementation (task 2.1); in the documentation 4.4.0 only `usingDriver`, `decodePath`, `scale`, `encodeUsingFormat`, `save` are confirmed.

Alternatives:
- Pure `Imagick` without the library: fewer dependencies, but everything by hand (the orientation, the profiles, the switching of the driver). Rejected in favour of the previously agreed `intervention/image`.
- GD: a worse quality of the reduction, the memory `w*h*4` in PHP and the unavailable limit of the resources of ImageMagick. It remains a fallback driver in the configuration.

### 5. Protection of the memory: the limit of pixels before decoding

`getimagesize` in `store()` already reads only the header. After it `w * h` is compared with `config('files.image.max_pixels')` (by default 100 000 000, below the policy of ImageMagick 256MP by `area` and 32KP by the sides); on exceeding — `reject(...)` with a translated message. The check stands **before** the processing and the writing, so nothing is created. It applies to all images, not only to those that are reduced, because it costs almost nothing and gives the same behaviour.

### 6. A processing error → a validation rejection

Any exception of the library during decoding/encoding is converted into a `ValidationException` under the key `file` (the message «Не вдалося обробити зображення» — "Failed to process the image"). The file is not written to the disk. The log receives the original exception.

### 7. The order of the checks in `store()`

```
isValid -> detectMime -> assertAllowed (type, size of the ORIGINAL)
        -> getimagesize -> pixel limit
        -> ImageResizer (null | result)
        -> writing to the disk (the original or the result)
        -> File::create (the metadata of the saved file)
```

The size of the original is checked in the same way as now (the decision of the owner: variant A), so an 8-MB photo is rejected, even though after the reduction it would be small.

### 8. The settings of the field: `max_width`, `max_height` for types with images

`StoreContentFieldRequest::settingsRules()` adds `settings.max_width` and `settings.max_height` (`nullable|integer|min:1`, `max` — the limit of the profile, if it is set) only when the profile of the type of the field has at least one `image/*` in `mimes` (that is, for `image`, not for `file`); `array:` in `settings` is extended accordingly. `ContentFieldController` reduces the empty values to `null`, as for `max_size`. The form `ContentFieldForm.tsx` shows two numeric fields next to the size, only when the profile of the type has images; the type `FileSettings` and the data of the profile that the controller passes receive `max_width`/`max_height`.

The alternative — only the configuration of the profile without the settings of the field — was rejected: it was agreed that the field narrows the limits, as `max_size` does.

### 9. The configuration (`config/files.php` + `.env`)

All processing parameters are read from environment variables with reasonable default values, so that they can be changed without editing the code:

```php
'profiles' => [
    'image' => [
        // ...
        'max_size' => env('FILES_IMAGE_MAX_SIZE', 5 * 1024), // KB
        'max_width' => env('FILES_IMAGE_MAX_WIDTH', 1920),
        'max_height' => env('FILES_IMAGE_MAX_HEIGHT', 1920),
    ],
],
'image' => [
    'driver' => env('FILES_IMAGE_DRIVER', 'imagick'),
    'quality' => [
        'jpeg' => env('FILES_IMAGE_JPEG_QUALITY', 85),
        'webp' => env('FILES_IMAGE_WEBP_QUALITY', 85),
    ],
    'max_pixels' => env('FILES_IMAGE_MAX_PIXELS', 100_000_000),
],
```

- The value `null` in `.env` (`FILES_IMAGE_MAX_WIDTH=null`) removes the limit of this side; an empty or non-numeric value is treated as «no limit» (the same normalization as for the settings of the field).
- `env()` is called only in `config/files.php` (otherwise the production with the configuration cache would not work). `.env.example` receives these variables with the default values and a comment.
- The `max_size` of the profiles is also taken from `.env` in kilobytes: `FILES_IMAGE_MAX_SIZE` (5120) and `FILES_DOCUMENT_MAX_SIZE` (20480) in `profiles.image` and `profiles.document`. The value must be a positive integer; smaller than the limits of PHP (`docker/php/uploads.ini`) and nginx, otherwise the upload is cut off before the validation of the profile.
- The value 1920 (enough for a full-width banner on Full HD; 2560 gives extra megapixels while there are no thumbnails), 85 and 100 000 000 are reasonable starting values, not requirements of the user. The profile `document` has no limits.

## Risks / Trade-offs

- [The recoding of JPEG/WebP loses quality] → the images within the limits are not touched; the quality is moved to the config; for the reduced large photos the difference is invisible.
- [An animated WebP/GIF remains large] → an acceptable compromise of stage 1, recorded in the specification; the reduction of the frames can be a separate change.
- [Large files slow down the upload request, because the processing is synchronous] → the limit `max_size` 5 MB and `max_pixels` limit the time; the queue is deliberately not used (the file must be final in the response).
- [A change of the appearance of PNG: a reduced PNG is encoded again] → PNG is encoded losslessly; the transparency is kept (there is a scenario in the specification).
- [The EXIF metadata disappears, including the author's] → for a site this is an advantage (privacy); recorded in `proposal.md`.
- [`getimagesize` does not recognize some input data that Imagick recognizes, or vice versa] → the behaviour is already so (the refusal «не є коректним зображенням» — "is not a correct image"); we do not add new discrepancies.
- [Existing files are large] → we do not touch them; if needed a separate command for the mass reduction.
- [A dependency on `imagick` in the environment] → the driver is in the configuration; in CI/on the host without `imagick` one can install `gd`. The tests of the reduction are skipped if the corresponding extension is absent.

## Migration Plan

1. `composer require intervention/image` in the container `app`; the image already contains `imagick`.
2. Add the configuration, the code, the translations; there are no new DB migrations (`settings` — the existing JSON column `content_fields`).
3. Deployment: make sure the image `app` is rebuilt with `imagick` (`docker compose build app`), otherwise the upload of images without limits within `max_size` will work as before, and the reduction will fail with a controlled error.
4. Rollback: remove `max_width`/`max_height` from the profile — the images are saved again as they are. The already saved reduced files cannot be restored (the originals are not kept).

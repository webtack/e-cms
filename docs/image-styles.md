# Image styles

An image style is a derived version of the original of a given size: a thumbnail for lists and cards, a picture of the required size in a template. The theme template requests the address of a style with the helper [`image_style()`](#image_style-helper); the file is created on the first request (see [Lazy generation](#lazy-generation)) and then served by the web server as a static file. The code — `src/Files/ImageStyles.php`, `src/Files/ImageResizer.php` (`fit()`), `src/Http/Controllers/ImageStyleController.php`, `src/helpers.php`.

The styles are stored by the [file storage](file-storage.md) on the disk of the `image` profile; the originals do not change.

## Style kinds

| Kind | Example | `itok` signature |
| --- | --- | --- |
| Preset — a named size from the configuration | `thumb` | not needed |
| Arbitrary size | `300x250`, `300x`, `x300` | needed, the helper adds it |

An arbitrary size is `{width}x{height}`, `{width}x` or `x{height}`; the letter `x` is **Latin** only (lowercase or uppercase, `300X250` is normalized to `300x250`). The Cyrillic `х`, `0x0`, `300` without `x`, a name that is not among the presets — an invalid style: no file is created, and the helper returns the input address unchanged. Each side is an integer from 1 to `files.styles.max_dimension` (4096).

The name of a preset cannot look like a size (`300x250`): this is a configuration error, and `ImageStyles::parse()` throws `InvalidArgumentException`.

## Settings

`config/files.php`, the key `styles` (values from `.env`, see `.env.example`):

| Key | `.env` | Default | Purpose |
| --- | --- | --- | --- |
| `presets.thumb.width` / `height` | `FILES_STYLE_THUMB_WIDTH` / `FILES_STYLE_THUMB_HEIGHT` | 200 / 200 | the size of the `thumb` preset |
| `presets.*.upload` | — | `true` for `thumb` | create the preset during upload (see [files in the storage](file-storage.md)) |
| `max_dimension` | `FILES_STYLE_MAX_DIMENSION` | 4096 | the largest side of an arbitrary size |
| `blur` | `FILES_STYLE_BLUR` | 30 | the strength of the blur of the background that fills the margins |
| `format` | `FILES_STYLE_FORMAT` | `webp` | the format of style files: `webp` or `original` |

A new preset — add an entry to `files.styles.presets` (`'card' => ['width' => 400, 'height' => 300]`). After changing the size of an existing preset, the created files remain the old ones until they are cleared by the command `image-styles:flush` (see [below](#flushing)).

## What a style gives

**Both sides** (`300x250`, a preset) — an image of **exactly** this size. The original is proportionally fitted inside, centered: nothing is cropped, and a smaller original is **enlarged**. The empty margins are filled with a blurred enlarged copy of the same original (scaled to cover the whole area). If the proportions match, there are no margins. If the image has transparent pixels (the pixels themselves are checked, not the format: a PNG without transparency gets a background), the margins remain transparent without blur.

**One side** (`300x`, `x300`) — a proportional resize by this side (with enlargement allowed), without empty margins.

**An animated GIF and WebP** are copied without changes.

| Original | Style | Result |
| --- | --- | --- |
| 1200×600, opaque | `300x250` | 300×250: the picture 300×150, a blurred background at the top and bottom |
| 600×1200, opaque | `300x250` | 300×250: the picture 125×250, a blurred background on the sides |
| 1200×1000 | `300x250` | 300×250 without margins |
| 200×100 | `300x250` | 300×250: the picture enlarged to 300×150 |
| 600×1200 PNG with transparency | `300x250` | 300×250, the margins are transparent |
| 1200×600 | `300x` | 300×150 |
| 600×1200 | `x300` | 150×300 |
| 200×100 | `300x` | 300×150 |

The background is built from a copy reduced 4 times, blurred with the strength `blur / 4`, and stretched to the canvas size: this way the blur is cheaper than a full-size one, with the same look (`ImageResizer::fit()`).

### File format

`files.styles.format`:

- `webp` (default): JPEG, PNG, and WebP originals are served as WebP with the quality `files.image.quality.webp` and the extension `webp`; the transparency is kept;
- `original`: the format and the extension of the original.

A GIF original remains a GIF in any mode: the helper does not know (without I/O) whether a GIF is animated, and animation cannot be converted, so the extension must be predictable. An animated WebP is copied under the name `{uuid}.webp`. The EXIF orientation is applied in the same way as for downscaling during upload ([ImageResizer](file-storage.md#image-processing-imageresizer)).

## Location and address

A style file lies on the disk of the `image` profile at the path `styles/{style}/{uuid}.{extension}`: `{style}` — the name of the preset or the normalized size, `{uuid}` — as in the original, the extension by `files.styles.format`.

| Original | Style | Format | Path on disk | Address |
| --- | --- | --- | --- | --- |
| `{uuid}.jpg` | `thumb` | `webp` | `styles/thumb/{uuid}.webp` | `/storage/styles/thumb/{uuid}.webp` |
| `{uuid}.png` | `300x250` | `webp` | `styles/300x250/{uuid}.webp` | `/storage/styles/300x250/{uuid}.webp?itok=…` |
| `{uuid}.jpg` | `thumb` | `original` | `styles/thumb/{uuid}.jpg` | `/storage/styles/thumb/{uuid}.jpg` |
| `{uuid}.gif` | `thumb` | any | `styles/thumb/{uuid}.gif` | `/storage/styles/thumb/{uuid}.gif` |

The address of a style is built from the address of the original by inserting `styles/{style}/` before the file name (with the extension replaced), so the scheme, the host, and the directory of the original are kept.

## `image_style()` helper

A global function (`src/helpers.php`, connected through `autoload.files` in `composer.json`; after adding it `composer dump-autoload` was run). It works without DB or disk access.

```blade
<img src="{{ image_style($node->hero, 'thumb') }}" alt="">
<img src="{{ image_style('/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg', '300x250') }}" alt="">
<img src="{{ image_style($meta['image'] ?? null, 'x300') }}" alt="">
```

| Input | Result |
| --- | --- |
| `image_style('/storage/{uuid}.jpg', 'thumb')` | `/storage/styles/thumb/{uuid}.webp` |
| `image_style('/storage/{uuid}.jpg', '300x250')` | `/storage/styles/300x250/{uuid}.webp?itok=…` |
| `image_style('{uuid}.png', 'thumb')` | the address `styles/thumb/{uuid}.webp` on the disk of the `image` profile (a field value, as in `FileStorage::urlForValue()`) |
| `image_style('https://example.com/storage/{uuid}.jpg', 'thumb')` | `https://example.com/storage/styles/thumb/{uuid}.webp` |
| `image_style('https://cdn.example.com/photo.jpg', 'thumb')` | the same address without changes |
| `image_style('/storage/{uuid}.pdf', 'thumb')` | the same address without changes (not an image) |
| `image_style('/storage/{uuid}.jpg', '300х250')` (Cyrillic `х`) | the same address without changes |
| `image_style(null, 'thumb')` | `null` |

For an original that does not lie in the storage of the `image` profile, the address will have a wrong directory, and the route will respond with 404: the `image` field always writes to the disk of the `image` profile.

## Lazy generation

If the style file is not yet on disk (arbitrary sizes, presets without `upload`, files uploaded before the styles appeared, and presets after `flush`), the first request to its address is handled by the Laravel route `GET {disk URL}/styles/{style}/{file}` (`ImageStyleController`, the name `image-styles.show`). The path is taken from the URL of the disk of the `image` profile (`/storage/styles/…`), not hard-coded.

The controller:

1. checks the file name against `FileValue::PATTERN` (`{uuid}.{ext}`);
2. parses the style: only the normalized spelling (`300x250`, not `300X250` or `0300x250`), a preset or a size within the limits;
3. for an arbitrary size checks the signature `itok` (`hash_equals`);
4. looks for the original by `uuid` among the extensions of the `image` profile; the extension of the requested file must match `ImageStyles::extension()` of the found original;
5. creates the file (`ImageStyles::create()`): the original is read from the disk, `ImageResizer::fit()` builds the image (the animation is copied without changes), the result is written under a temporary name and renamed, so parallel requests do not serve an unfinished file;
6. serves the file. The next requests are served by the web server as a static file; they do not reach PHP.

Any refusal — 404 and nothing is written: the name does not look like `{uuid}.{ext}`, an invalid style, no signature or a foreign one, a size above the limit, no original, the original is not an image (a document, corrupt content), the extension does not match the format. The route does not use the session, cookies, or the site's middleware; the response has no `Set-Cookie`.

### Re-creation after removal

A style directory or file removed from the disk (by `image-styles:flush` or by hand) is created again on the next request. A long-lived PHP-FPM worker keeps resolved paths in the realpath cache (`realpath_cache_ttl`, 120 s by default), so `is_dir()` can keep answering `true` for a directory that is already gone; Flysystem then skips `mkdir` and the write fails. `ImageStyles::create()` therefore calls `clearstatcache(true)` before it checks whether the file exists. The CLI does not show the problem, because its cache dies with the process.

If the style file cannot be written (or the temporary file cannot be moved into place), `create()` writes a `warning` to the log with the context `disk`, `path` and `style` (the same keys as for an exception), removes the temporary file and returns `null`, so the route answers 404. The `public` disk has `throw => false`, so without this entry such a failure would leave no trace.

### Environment requirement

No web server rules are needed beyond the standard redirection of non-existent files to the Laravel front controller: `public/.htaccess` (Apache) and `try_files $uri $uri/ /index.php?$query_string` (nginx). Without this, a request to a not-yet-created file will give a 404 from the web server, and the route will not run. For the `public` disk, `storage:link` is needed.

## Creation on upload

The presets with `upload => true` (at the start `thumb`) are created by `FileStorage::store()` right after the original and the `media` row are written, before the response to the upload, by the same algorithm as lazily (`ImageStyles::create()`). This way the admin panel always has a ready thumbnail. Styles are created only for the `image` profile; a document has no styles.

A failure of the creation (an exception) is caught, written to the log (`warning`), and does not reject the upload: the original is saved, and the preset will be created lazily by the route on the first request. Other presets and arbitrary sizes are created only lazily.

## Deletion

`FileStorage::delete()` after deleting the original calls `ImageStyles::forget()`: in each directory `styles/*` the files `{uuid}.*` are deleted (the extension of the style may differ from the extension of the original). Missing files are not an error. The order "the row first, then the disk" is unchanged: if the deletion is rejected (the file is in use), the styles remain. See [file-storage.md](file-storage.md#deletefile-file-void).

## Thumbnails in the admin panel

The admin panel shows the `thumb` style everywhere, and a click on a thumbnail opens the original in a new tab (`target="_blank"`, `rel="noopener noreferrer"`, `cursor-pointer`). For this, the file data for the client has the field `preview`: the address of `thumb` for `image/*` ([`FileStorage::previewForValue()`](file-storage.md#previewforvaluestring-profile-string-value-string-mime-string)), `null` for a document; `url` remains the address of the original.

| Place | Source of `preview` | Component |
| --- | --- | --- |
| The media library table ([media-library.md](media-library.md)) | `MediaController::row()` (together with the original's `url`) | `Pages/Admin/Media/Index.tsx` |
| The `image` cell in the node list ([admin-content-nodes.md](admin-content-nodes.md)) | `ContentNodeController` (`['url', 'name', 'preview']`) | `Pages/Admin/ContentNodes/Index.tsx` |
| The `FileUpload` widget (the response of `POST /admin/files`, `youtube/lookup`) | `FileController`, `YoutubeLookupController` | `Components/Admin/FileUpload/FileUpload.tsx` |
| The forms of the node, the term, the menu item, the site settings | `ContentNodeController::fileInfo()`, `TermController`, `MenuItemController`, `SiteSettingsController` (`imageInfo()`/`fileInfo()`) | `NodeFieldInput`, `TermForm`, `MenuItemForm`, `Pages/Admin/Settings/Site.tsx` |

The client shows `preview ?? url`: when `preview` is absent (old data that does not yet have the field), the original remains the thumbnail.

## Flushing

```bash
php artisan image-styles:flush          # all styles
php artisan image-styles:flush thumb    # only styles/thumb
php artisan image-styles:flush 300x250  # only styles/300x250
```

The command (`src/Console/Commands/ImageStylesFlushCommand.php`) deletes the directory `styles/` (or `styles/{style}`) on the disk of the `image` profile. The originals and everything outside `styles/` remain. The name of the style goes through the same check as in the route (`300X250` becomes `300x250`), so `..` cannot leave the directory: an invalid style is an error and an exit code 1. The deleted styles are created again on the next request with the current settings, so the command is run after changing the size of a preset. A preset with `upload => true` for old files is also created lazily.

## Signature and limits

An arbitrary size is served only with the signature `itok` — the first 16 hex characters of `hash_hmac('sha256', "{style}|{file}", APP_KEY)`, where the style is normalized and the file is the name of the style file (with the style extension). Without this, anyone could request thousands of sizes and fill the disk and the processor. The helper knows the key and adds the token to the address. Presets are not signed: their number is finite.

- The token is bound to the style and the file, but not to a user and has no expiry: its purpose is to limit the list of sizes, not to hide the file. The query parameter is ignored by the web server for a static file.
- The signature depends on `APP_KEY`: after the key changes, the old addresses of arbitrary sizes for not-yet-created files will give 404; the created files are served statically. The helper will generate new addresses on the next render.
- The side of an arbitrary size is limited by `files.styles.max_dimension`; a larger size is 404 even with a valid signature.

## Tests

| Area | File |
| --- | --- |
| Parsing of a style, the format, the signature, the address | `tests/Unit/Files/ImageStylesTest.php` |
| The composition of `fit()` | `tests/Unit/Files/ImageResizerTest.php` (the block "fit(): image styles") |
| The route and lazy creation | `tests/Feature/Files/ImageStyleRouteTest.php` |
| The helper (including from Blade) | `tests/Unit/Files/ImageStyleHelperTest.php` |
| Creation on upload, deletion | `tests/Feature/Files/FileStorageTest.php` (the block "Image styles") |
| The command `image-styles:flush` | `tests/Feature/Files/ImageStylesFlushCommandTest.php` |

Run: `vendor/bin/phpunit --filter=ImageStyle`.

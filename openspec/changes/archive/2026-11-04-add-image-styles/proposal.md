# Proposal

## Why

Images are served only in their original size (up to 1920×1920): the media library, lists and cards on the public site load full files for the sake of a small preview. Derived sizes are needed, which the template of the theme can request when necessary, without depending on the settings of the web server (on shared hosting there is no access to them).

## What Changes

- A new capability «image styles» (`image styles`): a derived version of the original of a set size. A style is either a **preset** from the configuration (one at the start, `thumb`), or an **arbitrary size** in the form `300x250`, `300x`, `x300` (the letter `x` is only Latin).
- The helper `image_style($url, $style)` for the templates of the theme: takes the URL of an image (or the value `{uuid}.{ext}`) and a style, returns the URL of the derived file. An address that does not look like a file of the storage (external) is returned without changes.
- The files of the styles lie on the disk of the profile `image` in the directory `styles/{style}/{uuid}.{ext}`, where `{style}` is the name of a preset or the normalized size (`300x250`).
- The preset `thumb` is created **during the upload** of an image (the admin uses it). For the other styles, arbitrary sizes and the files uploaded before this change the generation is **lazy**: the first request to a not-yet-created file gets to the Laravel route (the standard fallback of the front controller in `public/.htaccess`, without any rules of the web server), which creates the file, writes it to the disk and serves it. The next requests are served by the web server as a static file.
- Protection against the load: the presets are accepted by name, and the arbitrary sizes — only with a signature (`itok`, an HMAC from `APP_KEY`), which the helper itself adds; the side of the size is limited by the configuration. A request with a foreign size or without a signature — 404.
- A style with both sides (`300x250`, a preset) gives an image of **exactly such a size**: the original is proportionally fitted inside (without cropping; a smaller original is **enlarged**), and the empty fields are filled with the blurred enlarged copy of the same original. If the image has transparent pixels, the fields remain transparent without blurring. A style with one side (`300x`, `x300`) changes the size proportionally by this side and has no empty fields. The animated GIF/WebP is served without changes.
- The format of the file of a style is set by the configuration `files.styles.format` (`.env` `FILES_STYLE_FORMAT`): `webp` by default (the JPEG, PNG and WebP originals are served as WebP, the address has the extension `.webp`) or `original`. GIF always remains GIF.
- The deletion of a file from the media library deletes all its styles too. A new command `image-styles:flush {style?}` removes the created files of the styles (needed when the size of a preset is changed).
- The column «Preview» in `/admin/media` shows the style `thumb` instead of the full original and is a link to the original (a new tab, `cursor-pointer`).
- The admin shows the thumbnail `thumb`, and the click leads to the original, everywhere where the image is already output as a preview: the cell of the field `image` in the list of records (`/admin/{type}`) and the widget `FileUpload` in the forms (a record, a term of the taxonomy, a menu item, the settings of the site). The thumbnail has `cursor-pointer`; in `FileUpload` it becomes a link to the original (`target="_blank"`). For this the data of the file for the client receives the field `preview` (the address of `thumb` for an image, `null` for another one).
- **Not included:** cropping of the original (it is always visible whole), `srcset`/`<picture>`, a change of format (WebP), watermarks, the transfer of the templates of the theme `default` to the helper, other styles in the admin besides `thumb`, the details of the file in the media library (`MediaDetails`, the original remains), the screenshot of YouTube (not a file of the storage), the processing in a queue.

## Capabilities

### New Capabilities

- `image-styles`: the presets and the arbitrary sizes, the scheme of the directories and of the URL, the lazy generation by the route with the signature, the helper `image_style()`, the command `image-styles:flush`.

### Modified Capabilities

- `file-storage`: the upload of an image creates the presets marked for creation at the upload; the deletion of a file removes its styles.
- `media-library`: the column of the preview uses the style `thumb` and is a link to the original.
- `admin-content-nodes`: the cell of the field `image` in the list shows `thumb`, the click leads to the original.

## Impact

- Code: a new `app/Files/ImageStyles.php` (the parsing of a style, the signature, the path, the generation, the deletion), the controller of the route `GET {path of the disk}/styles/{style}/{file}` in `routes/web.php`, the extension of `app/Files/ImageResizer.php` (the size by the style), `app/Files/FileStorage.php` (`store()` creates `thumb`, `delete()` removes the styles), `app/Http/Controllers/Admin/MediaController.php` (`preview`), `resources/js/Pages/Admin/Media/Index.tsx` (the link to the original, `cursor-pointer`), `resources/js/Pages/Admin/ContentNodes/Index.tsx` (`thumb` in the cell), `resources/js/Components/Admin/FileUpload/FileUpload.tsx` (the link to the original, `thumb`), the field `preview` in the data of the file in `FileController`, `ContentNodeController`, `TermController`, `MenuItemController`, `SiteSettingsController` and the client assemblies of the value (`TermForm`, `MenuItemForm`, `NodeFieldInput`), the global helper (a file in `autoload.files` in `composer.json`), the console command, `config/files.php` and `.env.example` (`styles.presets` (with the mark `upload`), `styles.max_dimension`, `styles.blur`, `styles.format`).
- Dependencies: no new ones (`intervention/image` with the driver `imagick` is already there).
- Tests: PHP Unit for the parsing of a style and the signature, Feature for the route (creation, repeated serving, the signature, 404), `FileStorage::delete`, the helper, the command, `MediaController`, `preview` in the responses of `FileController`/`ContentNodeController`/`TermController`/`MenuItemController`/`SiteSettingsController`; e2e — `e2e/admin-media.spec.js` and the specs of the list of records and of the forms with `FileUpload` (which exactly is determined by `docs/e2e-testing.md`).
- Documentation (in Ukrainian): a new `docs/image-styles.md`, update `docs/file-storage.md` (the links and the deletion), `docs/media-library.md` (the preview), `docs/theme-templates.md` (the helper), the index `docs/README.md`.
- The existing files do not change: the styles are created only on request.

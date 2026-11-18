## Why

The `richtext` editor currently cannot insert images: the editor has no such action, and the server drops `img` during sanitization. To illustrate a text, the user has to insert an image from a third-party address, and such pictures do not get into the media library: they are not visible among the files, they have no usage counter, and they can disappear together with a foreign site. The field `image` already can upload a file to the storage and keep links in `media_relations`; the editor must do the same.

## What Changes

- The toolbar of the `richtext` editor gets a button «Зображення»: choosing a file, an instant upload through `POST /admin/files`, and insertion of `<img>` at the cursor position. The rules (type, size) are taken from the profile `image`. When an image in the text is selected, the same button opens a dialog of the parameters instead of the file selection; the selected image is visually highlighted.
- The upload by field (`content_type` + `field_key`) also accepts a field of the type `richtext`: the profile is `image`, the settings of the field have no file restrictions.
- The inserted image can be configured: the width in pixels (the field «Ширина», the button «Початковий розмір»), the alternative text `alt`, and a visible caption (`<figure><img><figcaption>`). The settings are changed in a dialog that opens by a double click on the image (or by the button «Зображення» with the image selected).
- The text gets a relative address without the protocol and the domain: `/storage/{uuid}.{ext}` (and not `http://host/storage/...`).
- The HTML sanitizer allows `img` (the attributes `src`, `alt`) only with the address in this relative form, and `width` (an integer number of pixels within 1–4000); in addition `figure` and `figcaption` are allowed for the caption; any other image (external, `data:`, with another path) is removed.
- The value of `richtext` is checked: each image must reference an existing file of the profile `image`.
- The type `richtext` starts tracking files (`TracksFiles`): during a write the links of the owner (the role — the field key, the language — the language of the value) are synchronized with the files found in `<img>`, in the same transaction. Removing an image from the text removes its link, the file stays in the storage.
- The caption (the text of `figcaption`) is included in the visible text of the value (search, `min`/`max`). A record that contains only an image (without text and without a frame) is considered non-empty.
- An image is never selected by itself (neither when the form is opened, nor after the value is updated): only a user action (a click, insertion) selects it.
- The selected image is aligned by the existing alignment buttons (left, centre, right); the caption is always centred right under the image. The alignment is stored in standard HTML without classes: `figure` has the width of the image and is aligned by margins (`<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center"><img><figcaption>…</figcaption></figure>`; right — only `margin-left: auto`; left — no margins).
- The alignment of the image applies to the image itself, not only to the caption (in the editor `img` inside `figure` is inline, not a block). The form of the record of a node is wider: `max-w-5xl` instead of `max-w-2xl`.
- A newly inserted image is inserted with the width of 500 px by default (`IMAGE_DEFAULT_WIDTH`); in the dialog it can be changed, and «Початковий розмір» removes the width (the natural size). Images already inserted do not change.
- Not included: dragging the resize handle, wrapping the image around text, choosing a file from the media library.
- **BREAKING**: no. The previously saved values do not change; external `img` that might have already been removed by the sanitizer do not come back.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: instead of "Images and files in the editor are not available" the editor `richtext` has a button for inserting an image with an upload to the media library.
- `content-values`: `richtext` allows `img` with a relative address of the storage, checks the existence of the files and the non-emptiness by an image.
- `file-storage`: the upload by field accepts the field `richtext` (the profile `image`).
- `media-library`: the synchronization of links covers the images inserted in `richtext` too.

## Impact

- Backend: `app/Content/HtmlSanitizer.php` (+ new attribute sanitizers of `img`: the address and the width; the allowance of `figure`/`figcaption`), `app/Content/FieldTypes/RichTextType.php` (`TracksFiles`, the rule of checking files), `app/Http/Requests/Admin/StoreFileRequest.php`, `app/Http/Controllers/Admin/FileController.php`, `app/Files/UploadRules.php` (a field without file settings).
- Frontend: `resources/js/Components/Admin/ContentNodes/RichTextToolbar.tsx`, `ContentNodeForm.tsx` (the width of the form), `RichTextEditor.tsx`, `richTextExtensions.ts` (the image node with `alt`, `width`, `caption`), a new panel of the parameters of the image, `NodeFieldInput.tsx` (passing the type of content and the key of the field), translations `uk`.
- Tests: Feature (the sanitizer, the type, the upload, the links), e2e `admin-content-nodes.spec.js` (+ `admin-media.spec.js` for the links).
- Documentation (in Ukrainian): update `docs/admin-content-nodes.md`, `docs/content-values.md`, `docs/file-storage.md`, `docs/media-library.md`, `docs/admin-content-fields.md` (if it describes `richtext`), `docs/e2e-testing.md` (tables when the set of specs changes); `docs/README.md` without new files is not changed.

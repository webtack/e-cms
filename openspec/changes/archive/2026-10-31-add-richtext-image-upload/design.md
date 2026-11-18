# Design

## Context

- The `richtext` editor is Tiptap (`RichTextEditor.tsx`, `RichTextToolbar.tsx`, `richTextExtensions.ts`); the server is the only trusted sanitization layer (`App\Content\HtmlSanitizer`, an allow-list on Symfony HtmlSanitizer with attribute sanitizers `IframeSrcSanitizer`, `StyleAttributeSanitizer`). The tag `img` is currently not allowed.
- The upload is `POST /admin/files` (`FileController`, `StoreFileRequest`): either `profile`, or `content_type` + `field_key`, where the field must be an `AbstractFileFieldType`. The response contains `url` — an absolute address (`Storage::disk('public')->url()` with `APP_URL`), and `value` — `{uuid}.{ext}`.
- The links to the media library are maintained by `ContentValueWriter::replace()`: for a type with `TracksFiles` it calls `FileRelations::sync(owner, id, fieldKey, lang, fileUuids)` in the transaction of the record. Right now `TracksFiles` is implemented only by `AbstractFileFieldType`.
- The files of the profile `image` lie in the root of the disk `public` as `{uuid}.{ext}`; the public path of the disk is `/storage`.
- The motivation and the scope: [proposal.md](proposal.md); the requirements: [specs](specs/).

## Goals / Non-Goals

**Goals:**
- Inserting an image from the editor goes through the same path (`POST /admin/files`, `media`, `media_relations`) as the field `image`.
- The text stores only the relative address `/storage/{uuid}.{ext}`; this is the unchanged form for storage and reading.
- The sanitization remains the only trusted layer: an image outside the storage does not pass, whatever the client sends.

**Non-Goals:**
- Choosing an already uploaded file from the media library, pasting from the buffer, dragging the resize handle, wrapping the image around text, `srcset`/thumbnails, text formatting inside the caption (the caption is plain text).
- Inserting documents (`file`) and arbitrary files.
- Migrating existing values and cleaning up unused files.
- Support for disks other than `public` (S3 etc.): the storage address is taken from the public path of the disk of the profile `image`.

## Decisions

**1. The relative address is derived from the upload response on the client and checked on the server.**
The client takes the `pathname` from the `url` of the response (`new URL(url, window.location.origin).pathname`) and inserts it as `src`. The server is the trusted layer: the sanitizer of `img` leaves `src` only by the pattern `^<disk prefix>/<32 hex>\.<extension>$`, the prefix is taken from `Storage::disk('public')->url('')` through `parse_url(..., PHP_URL_PATH)` (by default `/storage`). The alternative — add a field `path` to the response of `FileController`: rejected; the contract of the endpoint remains unchanged, and the sanitizer must check the form itself anyway.

**2. The sanitizer: `img` with `src`, `alt`, `width`; `ImageSrcSanitizer` and `ImageWidthSanitizer`.**
`HtmlSanitizer` allows `img` with the attributes `src`, `alt`, `width`, and the tags `figure`, `figcaption` without attributes. `ImageWidthSanitizer` leaves `width` only as an integer 1–4000 (otherwise `null`, the attribute disappears, the image stays); `height` is not allowed, so the height is always proportional. `ImageSrcSanitizer` (by analogy with `IframeSrcSanitizer`) returns `src` only by the pattern above, otherwise `null`. As for `iframe`, a tag without `src` is removed (`dropSourcelessFrames` is extended to `img`). Absolute (`http://host/storage/...`), protocol-relative (`//host/...`), `data:` and other paths are rejected: only one canonical form is kept, which does not depend on the domain.
The alternative — rewrite the absolute address of one's own domain into a relative one: rejected as an unnecessary ambiguity; the client inserts the relative one anyway.

**3. `RichTextType` implements `TracksFiles`, `fileUuids()` parses the HTML.**
`fileUuids($value)` takes the cleaned HTML (`ContentValueWriter::replace()` passes the raw value into `fileUuids()`, so the method first calls `$sanitizer->clean()` itself), finds `img[src]` and returns the unique `uuid`s. This repeats the contract of `AbstractFileFieldType::fileUuids`, so `ContentValueWriter` does not change: the same call `FileRelations::sync`, the same role (the field key) and language, the same transaction. Multiple values are handled the same way, because `fileUuids` accepts a list too.
A regular expression over the cleaned HTML is used for parsing (the output of the sanitizer is predictable: `img` only with the canonical `src`); no DOM parser and no new dependency.

**4. The check of the existence of files — a rule in `RichTextType::rules()`.**
A rule like `ImagesExist` (a closure or `App\Files\Rules\...`) is added: all `uuid`s from `fileUuids()` must exist in `media` with `profile = image`. Without this `FileRelations::sync` would fail on the foreign key (`media_relations.file_uuid`) with a 500. The error goes to the field, as in `FileValue`. One query `whereIn` per value.

**5. `isBlank`: an image is content.**
As `hasFrame` for frames, `HtmlSanitizer::hasImage()` makes a value with a single `<img>` non-empty. `text()` adds `</figcaption>` to the block separators, so the caption is included in the visible text (search, `min`/`max`), while the image itself is not. A `figure` without `img` is not considered content: `hasImage()` looks at `img`.

**6. The upload for the field `richtext`.**
`StoreFileRequest::after()` accepts a field that is an `AbstractFileFieldType` **or** a `RichTextType`. `FileController` and `UploadRules::forField()` for `richtext` take `UploadRules::forProfile('image')` (the field has no file settings, so there is no narrowing). The profile is set in the type: `RichTextType::uploadProfile(): string` (`'image'`) instead of a check of the class in two places; `AbstractFileFieldType::profile()` remains. The alternative — an upload with `profile=image` without a field: rejected, because an endpoint by profile is not "an upload for a field", while by field it already applies the rules of the field.

**7. The client: the Tiptap node `Image` and the button in the toolbar.**
`@tiptap/extension-image` (a new dependency `@tiptap/extension-image` of the same version `^3.32.2` as the other `@tiptap/*` in `package.json`) with `inline: false`, attributes `src`, `alt`; in `richTextExtensions.ts`. The button «Зображення» (`lucide: Image`) in `RichTextToolbar` opens a hidden `<input type="file" accept="image/*">`, uploads the file with `fetch`/`axios` the same way as `FileUpload` (CSRF, `content_type`, `field_key`), and inserts `setImage({ src: pathname, alt: name })`. The editor receives the new props `contentType` and `fieldKey` from `NodeFieldInput` (there are `typeKey` and `field.key`). An error (422/413/network) is shown next to the editor by the already existing way of the errors of the field. The alternative — reuse the component `FileUpload`: rejected, it draws the zone and the file block, while only a button is needed here; the shared request logic is moved into a function `uploadFile()` of the module of the widget and used in both places, if it is already separate there, otherwise it is added next to it without changing the behaviour of the widget.
The button is disabled in the «Джерело» mode along with the rest (this already applies to the group `fieldset disabled`) and during the upload. The behaviour depends on the selection: when the node `image` is selected (`editor.isActive('image')`), the button opens the dialog of the parameters (7a), otherwise — the file selection. The selected image is highlighted with a CSS outline on `img.ProseMirror-selectednode` (a ring of the colour `ring`/`primary` with an offset), noticeable enough to make it clear that the image is selected.

**7a. Size, `alt` and the caption — attributes of one node, edited in a dialog.**
An image is one block atom with the attributes `src`, `alt`, `width`, `caption` (plain text). `renderHTML` emits a bare `<img>` when there is no caption, and `<figure><img><figcaption>…</figcaption></figure>` when there is one; `parseHTML` reads both forms, so the markup is preserved without nested editable nodes. The alternative — a nested node `figcaption` with editable text: rejected as a more complex scheme of the document and cursor behaviour for the sake of a plain caption.
There is no separate action in the toolbar. The modal `Dialog` (`@/Components/ui/dialog`, the title «Параметри зображення») opens by a double click on the image (`dblclick` on the node: `handleDOMEvents`/`handleDoubleClickOn` in the plugin or `NodeView`, with the node selected before opening) and by the button «Зображення» when the node is selected; the state of being open is kept by `RichTextEditor`/the toolbar. The alternative — a `Popover` anchored to the image: rejected, because the anchor position to a node in Tiptap is unstable, and the button of the toolbar is not an anchor for a double click. The fields «Ширина (px)», «Альтернативний текст», «Підпис» and the buttons «Готово» (`updateAttributes`) and «Початковий розмір» (`width: null`). The width is checked on the client (an integer 1–4000), the server checks the same. The drag handle is not made: the field gives an exact value and is tested without mouse geometry. In the editor the image is limited by the width of the column (`max-width: 100%`); the public view is out of scope.
The default `alt` is the name of the uploaded file (`name` of the upload response).

**7a-1. The default width.** During insertion (`setImage`) the node gets `width: 500` (the constant `IMAGE_DEFAULT_WIDTH` next to `IMAGE_MAX_WIDTH`), so that large images do not stretch the text. The value is stored in `width` like any width; «Початковий розмір» and an empty field remove `width` (the natural size). Existing images without `width` do not change. Small images (narrower than 500 px) are enlarged to 500 px; the user can change the width in the dialog. `max-w-full` does not let it exceed the container.

**7b. The initial selection.** ProseMirror places the initial selection on the first atomic node, so a document that starts with an image would open with the image selected (an outline; the button «Зображення» would open the dialog). After the editor is created and after `setContent`, no `NodeSelection` remains on the image: we set a `GapCursor` or a text selection instead of the node. The variant is chosen during implementation and checked in e2e. Only a user action selects the image (a click, insertion, a double click).

**7c. Alignment of the image — standard HTML.** The node `image` has the attribute `align` (`left` by default, `center`, `right`). `text-align` on `figure` is unsuitable: `figure` is a full-width block, so `text-align` centers the caption relative to `figure`, and the block image (Tailwind preflight) is not shifted at all. Therefore `figure` has the width of the image and is aligned by margins, and the caption is centred inside it. `renderHTML`: if `align` is not `left` or there is a caption — `<figure style="width: {width}px; max-width: 100%[; margin-left: auto][; margin-right: auto]; text-align: center"><img>[<figcaption>]</figure>` (without `width` on the image — `width: fit-content` instead of `{width}px`) (centre: both margins `auto`; right: only `margin-left: auto`; left: no margins), otherwise a bare `<img>`. `parseHTML` determines `align` by the margins; a `figure` without margins and width, but with `text-align: center|right` (saved earlier) is read as centre/right. `AlignMenu` when `editor.isActive('image')` calls `updateAttributes('image', { align })`, «По ширині» is disabled; without a selected image the menu works as before. Sanitizer: `StyleAttributeSanitizer` for `figure` allows `text-align` (`left|center|right`), `width` (`{N}px` 1–4000, `fit-content`, and `min-content` only for already saved records), `max-width` (only `100%`), `margin-left`/`margin-right` (only `auto`, `0`); `figcaption` without attributes. The width of `figure` is an explicit number of image pixels, not `min-content`: `img` has `max-width: 100%` (Tailwind preflight), and a percentage `max-width` contributes zero to `min-content`, so `figure` shrank to the longest word of the caption and ignored the set width of the image. With `width: {N}px` a long caption wraps within the width of the image and does not change `figure`; `max-width: 100%` does not let it leave the area. Without a set width (the natural size) `fit-content` remains, and a long caption may stretch `figure` to the width of the area. The alternative `data-align` + a CSS class is rejected: it requires a separate CSS on the public side.

**8. Documentation.** Update `docs/admin-content-nodes.md` (the button, the relative address), `docs/content-values.md` (`img` in the allow-list, the check of files, non-emptiness), `docs/file-storage.md` (the upload for the field `richtext`), `docs/media-library.md` (the links of images of `richtext`), `docs/admin-content-fields.md` (if it describes `richtext`), `docs/e2e-testing.md` (the description of the changes in the block `admin-content-nodes.spec.js`).

## Risks / Trade-offs

- An image that is inserted and removed before the record is saved stays in the media library without links → the field `image` behaves the same way; it is removed manually in the media library.
- The relative address is bound to `/storage` and the disk `public` → a change of the disk of the profile `image` breaks the existing images in the texts; document it. Out of scope.
- One image inserted twice has one link → removing one copy does not remove the link while the second remains (this is desirable).
- Regular-expression parsing of HTML → safe, because it works on the already cleaned output of the sanitizer with the single form of `img`; cover "noisy" inputs with tests (quotes, case, nesting).
- Existing values with `<img>` saved before the change are already cleaned of it → nothing is migrated.
- An initial value of the form that contains an `img` of a file that no longer exists → the save is rejected with an error of the field (the existence rule); the user removes the image.

## Migration Plan

No DB migrations. Deployment: the backend and the frontend together (`npm run build`). Rollback: revert the changes. The `<img>` already saved stay in the DB (the sanitization happens at writing, not at reading); after a rollback the editor does not recognize them, and the next saving of the record removes them.

## Open Questions

None.

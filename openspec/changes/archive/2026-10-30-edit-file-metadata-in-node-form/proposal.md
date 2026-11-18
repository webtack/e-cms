# Proposal

## Why

In the record edit form the fields "image" / "file" have superfluous controls: in a multiple field there is both an "Add item" button (an empty widget) and a separate × inside the file block next to the row's ×. Separately: the record editor has no hint about what exactly to enter in the field. Meanwhile the user cannot correct the original name of an uploaded file (often it is a service name like `IMG_0042.jpg`) and has nowhere to store a description of the file.

## What Changes

- The create and edit record forms: in the multiple fields `image` and `file` remove the "Add item" button — items are added only by uploading files (choosing or dragging several files). For the other multiple fields (string, number, etc.) the button stays.
- The create and edit record forms: in the row of a multiple field `image` / `file` remove the separate × "Remove file" inside the file block — the row's × "Remove item" is enough. For a single (non-multiple) field the × of the block stays, because otherwise the field cannot be cleared.
- The create and edit record forms: under the saved file (the file block in a single field and in each row of a multiple one) the fields "Name" (the original file name) and "Description" appear. The changes are saved together with the record (on creation or update), in one transaction.
- A new field `description` (optional text) for the file of the storage (`media`). The description is shown in the file details panel in the media library.
- A new optional field `description` (up to 1000 characters) for the field of the content type: it is set in the create and edit field forms; the record form shows it right after the label (legend) of the field as helper instructional text.
- The name and the description belong to the file, not to the record: the change is visible everywhere the file is used and in the media library.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: the fields `image` / `file` (the file block without a separate removal in the row of a multiple field), the multiple upload (without "Add item" in the file fields), new requirements about the name and the description of a file and about showing the description of the field in the record form.
- `admin-content-fields`: a new requirement about the description of a field in the create and edit field forms.
- `media-library`: a new requirement about the metadata of a file — the name and the description, the description in the file details.

## Impact

- Backend: the migrations `media.description` and `content_fields.description` (nullable text); the model `File` (`description` in `Fillable` and PHPDoc); `StoreContentNodeRequest` / `UpdateContentNodeRequest` (validation of the file metadata); `ContentNodeController` (`fileInfo()` returns `description`, `store()` and `update()` save the name and the description in the record's transaction; `formFields()` returns the `description` of the fields); the model `ContentField` and `StoreContentFieldRequest::sharedRules()` (the rule `description`), `ContentFieldController` (returns and saves the description); `MediaController::show` returns `description`.
- Frontend: `Components/Admin/FileUpload/FileUpload.tsx` (the block of the selected file: an optional ×, and the fields of name / description), `ContentNodes/NodeFieldInput.tsx`, `NodeFieldList.tsx`, `ContentNodeForm.tsx`, `ContentNodes/types.ts`, `Components/Admin/ContentFields/ContentFieldForm.tsx`, `Components/Admin/Media/MediaDetails.tsx`; `lang/uk.json` and `lang/en.json` (new labels).
- Tests: Feature tests of `ContentNodeController` (saving the name and the description, validation, the file of a foreign record is not changed without a relation) and `MediaController::show`; Feature tests of `ContentFieldController` (the description of a field); e2e `admin-content-fields` (the field "Description"), `admin-content-nodes` (the description of the field after the label; update the scenarios of the multiple field and add editing of the metadata), `admin-media` (the description in the details), `admin-file-upload` — only if the behaviour of the shared widget without props changes (see design).
- Documentation (`docs/`, in Ukrainian), to update: `admin-content-fields.md` (the description of a field), `admin-content-nodes.md` (the description of a field, the fields `image` / `file`, multiple fields), `file-storage.md` (the column `description`, the widget), `media-library.md` (the details of a file, the sharing of metadata), `e2e-testing.md` (the description of the scenarios `admin-content-nodes`, `admin-media`); `docs/README.md` unchanged, because no files are added.
- Assumptions accepted without questions to the owner: (1) the metadata of a file is edited both in the create form and in the edit form of a record; (2) "Add item" is removed only in the fields `image` / `file`, in the other multiple fields it stays; (3) "Removal inside the file block" is the × "Remove file" in the row of a multiple field; in a single field it is needed for clearing and stays; (4) the name is only the metadata `original_name`, the extension of the saved file and the path on the disk do not change, the name cannot be empty; (5) editing the metadata in the media library itself is not part of this change; (6) the description of a field is plain text without formatting, up to 1000 characters, changeable also for system fields; (7) the name of the change is not changed, although it covers the description of a field too.

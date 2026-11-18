# Proposal

## Why

After the appearance of the file storage and the media library, the admin shows file types as long MIME strings (`application/vnd.openxmlformats-officedocument.wordprocessingml.document`), which break the layout and are hard to read. The media library has no filter by type, multiple fields "image" / "file" force adding files one at a time, and the fields of the record form are not grouped the way the settings of the file field are already grouped.

## What Changes

- The file type everywhere it is shown (the column "Type" of the media library, the subtitle of the file details panel, the list of allowed types in the settings of the file field) is shown with a short extension label — `docx`, `xlsx`, `pdf`, `jpg` — not with the full MIME. The label is taken from the extensions already set in the profiles of `config/files.php`; for a type outside the profiles — a fallback (the part of the MIME after `/`).
- The media library gets a filter by type as a drop-down list (`Select`) in the toolbar next to the search field, with the option "All types". Choosing a value gives a chip of the active filter under the panel; the chip is removed with its ×, and the button "Reset" resets it too. For this, `DataTable` gets a common capability: a column of type `select` can be marked as a "quick filter", and then it is shown in the panel as a select (without the "Filter" button and without the constructor of filters). Filtering is performed in the database by `mime`, the value — the short label; search, sorting and pagination work together with it.
- The multiple fields `image` and `file` in the record form get multiple upload: the user selects or drags several files at once, the frontend uploads them with **separate sequential requests** `POST /admin/files` (one per file) and adds each successful file as a new item of the list. Errors of individual files are shown with the file name and do not block the rest. The backend endpoint does not change.
- Each field of the record form (the slug and each field of a type) is wrapped in its own frame container (`fieldset` with a `legend` — the name of the field), modelled on the block of the "File settings" in the field form; the buttons "Save" / "Cancel" stay under the containers.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `file-storage`: a new requirement about the short label of a file type; the upload widget can accept several files and upload them with separate requests.
- `media-library`: the type in the table and the details is the short label; the filter of the media library by type (a select in the panel).
- `admin-data-table`: the quick filter — a select of a column in the toolbar panel next to the search.
- `admin-content-fields`: the list of allowed types in the settings of the file field is shown with short labels.
- `admin-content-nodes`: the fields of the record form in a frame container; the multiple upload for the multiple fields `image` / `file`.

## Impact

- Backend: a new helper for type labels (`app/Files/FileTypes.php`) based on `config/files.php`; `MediaController` (the column/filter `type`, the field `type` in the rows and the details), `ContentFieldController` (the labels in `fileTypes`).
- Frontend: `Components/Admin/DataTable/*` (the quick filter in `DataTableToolbar`, the flag of the column in `types.ts`), `Pages/Admin/Media/Index.tsx`, `Components/Admin/Media/MediaDetails.tsx`, `Components/Admin/ContentFields/ContentFieldForm.tsx`, `Components/Admin/FileUpload/FileUpload.tsx` (the multiple mode), `Components/Admin/ContentNodes/NodeFieldList.tsx`, `NodeFieldInput.tsx`, `ContentNodeForm.tsx`; translation files `lang/uk.json` / `lang/en.json` (new labels).
- Tests: Feature tests of `MediaController` and `FileTypes`, updates of the e2e specs `admin-data-table`, `admin-media`, `admin-content-fields`, `admin-content-nodes`, `admin-file-upload`.
- Documentation (`docs/`, in Ukrainian): update `admin-data-table.md` (the quick filter), `file-storage.md` (the labels of types, the multiple mode of the widget), `media-library.md` (the column and the filter), `admin-content-fields.md` (`fileTypes` with labels), `admin-content-nodes.md` (the container of the fields, the multiple upload), `e2e-testing.md` (the description of the scenarios `admin-media`, `admin-content-nodes`, `admin-file-upload`, the dependency tables if needed); the index `docs/README.md` unchanged, if no files are added.
- Assumptions accepted without questions to the owner: a logical field has its own container, the legend is the name of the field (for the slug — "Slug"); for a boolean field whose label stands next to the checkbox, a legend is not needed — the label stays next to the checkbox inside the frame; in a multiple field `image` / `file` the selection of several files is added next to the existing button "Add item" (which adds one empty widget); the upload is sequential, not parallel.

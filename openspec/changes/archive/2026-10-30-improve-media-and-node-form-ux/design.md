# Design

## Context

The profiles in `config/files.php` already define `mime => extension` (`FileStorage` writes the file as `{uuid}.{ext}`), so a source of short labels exists. `File.mime` in `media` is the full MIME. Today the MIME is shown in: the column "Type" (`Pages/Admin/Media/Index.tsx`, the row from `MediaController::row`), the subtitle of `MediaDetails` (JSON `MediaController::show`) and the checkboxes in `ContentFieldForm` (`fileTypes[].mimes` from `ContentFieldController::fileTypes`). `FileUpload` does not show the MIME.

`DataTable` in server mode already has filters: the prop `filterable` and columns with `filterable: true`; the type `select` with `options: {value, label}[]` gives a drop-down list of values, the server parses `filters[N][c|o|v]` in `TableRequest`. `MediaController` does not apply filters yet. The record form (`ContentNodeForm`) draws the slug and the fields as separate blocks `space-y-2`; the block of the file settings in `ContentFieldForm` is `fieldset.rounded-md.border.p-4` with a `legend`. A multiple field — `NodeFieldList` (an item = one `NodeFieldInput`, for `image`/`file` — `FileUpload`); the upload — `POST /admin/files` one file at a time.

## Goals / Non-Goals

**Goals:**
- One source of truth for the short labels, used by both the backend and the frontend.
- A type filter without new DataTable components.
- Multiple upload without a change of the upload API.

**Non-Goals:**
- A constructor of complex filters in the media library (the "Filter" button stays disabled).
- A change of the stored value `mime`, `settings.mimes` or the validation rules (everything stays the full MIME).
- Parallel uploads, progress in bytes, cancelling the queue.
- A new page for choosing files from the media library.
- One shared container for the whole record form.

## Decisions

**1. Labels of types on the server: `App\Files\FileTypes`.** The class reads `config/files.php` and provides `label(string $mime): string` (the extension from the profile, otherwise the part after `/`), `labels(): array<mime, ext>` and `mimesFor(string $ext): list<string>` (the reverse mapping for the filter; `image/jpeg` → `jpg`, several MIMEs can share an extension). The backend gives the ready `type` in the rows and the details of the media library and `labels` in `fileTypes` of the field. *Alternative:* a shared Inertia prop or a TS table — rejected: it would duplicate the configuration, and `MediaDetails` already gets the data from JSON. If two MIMEs with the same extension appear in the profiles, the map gives one label, and the filter finds both.

**2. The filter of the media library — a quick filter of `DataTable` (a select next to the search).** A separate `Select` in the toolbar, but controlled by the common state of the table, not a local one: `Column` receives an optional flag `quickFilter` (only for `type: 'select'`). `DataTableToolbar` for each such column renders a `Select` next to the search field: the value is taken from `state.filters` (an `eq` filter on this column, or "All"), choosing a value calls `controller.setFilters` (replaces/adds the `eq` filter of the column, "All" removes it). Therefore the chips (`DataTableFilterChips`), "Reset", the address and the reset of the page work without changes; removing a chip updates the select, because the select reads the state. Check that the chips show also the filters of columns without `filterable`. The flag `filterable` of the `DataTable` itself is NOT enabled in the media library (the "Filter" button is not needed). On the server the column `mime` is described as `select`, `filterable: true` with `options` — the short label; `MediaController::index` applies `whereIn('mime', FileTypes::mimesFor($value))` to the count and to the page. The client mode is supported by the same code (the state in memory). *Alternative:* the standard constructor of filters (column → operator → value) — rejected: too many steps for one obvious filter. *Alternative:* a local `Select` on the media page with its own `router.get` — rejected: it would duplicate the state, the chips and the reset.

**3. The type in the `select` column and sorting.** Sorting stays by `mime` in the DB (the order by the full MIME, not by the short label); for the user the difference is not noticeable, and the query does not become more complicated.

**4. The multiple mode of `FileUpload`.** The upload of one file is moved out of the component into a module function `uploadFile(file, target): Promise<UploadedFile>` (it throws an error with the server's message), used by both the single and the multiple modes. The prop `multiple` enables `<input multiple>`, the dragging of several files and a sequential loop `for … await uploadFile()`; the result is the callback `onUploadedMany(files)` (each successful file right after each request), errors are collected as `{name, message}[]` and are shown as a list. In `multiple` mode the component does not show the selected value (the form draws the list itself). *Alternative:* a separate component `MultiFileUpload` — rejected: duplication of the upload, of the checks of `accept`/`maxSize` and of the drag zone.

**5. Integration into `NodeFieldList`.** For the multiple fields `image`/`file` a multi-zone `FileUpload multiple` appears above the button "Add item"; the successful files are appended to `value` and to `ids` (client keys), and `onFile` remembers the metadata in `known` of the form. The button "Add item" stays (it adds an empty single widget). Sequential `onChange` calls of one selection must accumulate the values, so the callback receives the batch of files after the queue completes, not one by one, so as not to lose items through a stale `value` in the closure (the alternative — `useRef` with the current value; the batch was chosen).

**6. The container of each field of the record form.** In `ContentNodeForm` the block of the slug and the block of each field of a type become `<fieldset className="space-y-2 rounded-md border p-4">` with `<legend className="px-1 text-sm font-medium">` — the same classes as in `ContentFieldForm`. The legend is the name of the field with the required mark (`<Label>` now; for the non-multiple fields `htmlFor` is kept, for the multiple ones `id` + `aria-labelledby` stays, `role="group"` can be removed, because the `fieldset` is already a group). A boolean field: the checkbox and the label in one line inside the frame without a legend. Hints and error messages stay inside the frame. The buttons stay outside the frames. There are no new translation keys for the frame (the legend — the names of the fields); new keys are needed for the multiple upload: the label of the zone, the progress ("Uploaded :done of :total") and the file error (":name: :message"), and "All types" / "Усі типи" for the quick filter.

**7. Documentation.** The changed components are described in `docs/file-storage.md` (the labels of types, the multiple mode of the widget), `docs/media-library.md` (the column, the filter), `docs/admin-data-table.md` (the quick filter), `docs/admin-content-fields.md` (`fileTypes` with labels), `docs/admin-content-nodes.md` (the container, the multiple upload), `docs/e2e-testing.md` (the scenarios and the dependency tables).

## Risks / Trade-offs

- [Two MIMEs with one extension in the profiles] → `mimesFor` returns a list, the filter uses `whereIn`; covered by a Feature test.
- [Files with a stored MIME that is no longer in the profiles do not appear in the list of types of the filter] → the column shows the fallback label; such files cannot be filtered until the type is added to a profile (acceptable: the profiles are changed by the developer).
- [A stale `value` in the closure during sequential uploads] → hand the batch over through one `onChange`, not one file at a time.
- [A long queue blocks the form] → the upload is sequential, the widget shows "processed N of M"; the save button is not blocked, but the partially uploaded list is saved as it is.
- [Empty items in the list on rejection] → a rejected file does not become an item.
- [Changing the markup of the record form breaks the e2e selectors (`getByLabel`, `role=group`)] → update the selectors in the spec; run `admin-content-nodes`.
- [The quick filter gets out of sync with the chips or the constructor] → the select only reads `state.filters` and writes through `setFilters`; covered by the e2e `admin-data-table`.

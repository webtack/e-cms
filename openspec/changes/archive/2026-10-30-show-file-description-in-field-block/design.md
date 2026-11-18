# Design

## Context

`NodeFieldInput` builds `selected: UploadedFile` from the dictionary `files` (the initial files of the page plus the ones uploaded in the form), and `FileUpload` draws the name and the size from it. The name and the description that the user applied with the button «Готово» are stored in `fileMeta.values[uuid]` (the state of `useForm.file_meta`), so the block does not see the changes. The description is in `files` (`NodeFileInfo` from `fileInfo()`), but `FileUpload` does not output it.

## Decisions

1. **The source of truth for the block is `fileMeta.values[uuid]`.** `NodeFieldInput` takes the name and the description from there when `fileMeta` is passed and the record exists; otherwise (no `fileMeta`) — `info.name` and no description. This way the block matches what will go in the request, and it does not need a duplication of the state.
2. **`FileUpload` receives `description?: string`** (only in the single mode), which is drawn as a `<p>` between the name and the size; empty/absent — nothing. The alternative — put the description into `details`: rejected, because `details` is drawn under the whole row (after the buttons), while the requirement is above the size in the column of the name.
3. **A long description** is cut by `line-clamp-2`, the full text is visible in the window. The value does not change.
4. **The counter in the row with the label.** `Label` is `display: flex`, so a `span` with the counter inside `legend` falls under it. We make the `legend` of the multiple field `flex items-center` (the counter stays a child of `legend`, `ml-2` → `gap`), without changing `data-testid` and the text "n / max". The shared `Label` is not touched.
5. **No changes in the backend, in the `file_meta` contract or in the modal window.**

## Risks / Trade-offs

- If `fileMeta.values` does not yet contain the uuid (theoretically for a just uploaded file before `setData`), the fallback from `files` is used; `rememberFile` adds the metadata at the same time as the upload, so there is no discrepancy in the UI.
- A change of the name in the block, now without saving, may differ from the table of records until «Зберегти» — this is expected (the draft of the form).

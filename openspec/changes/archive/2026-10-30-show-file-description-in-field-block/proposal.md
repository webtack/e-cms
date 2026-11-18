# Proposal

## Why

The file block in the `image` and `file` fields shows the name and the size, but does not show the description. In addition, the name in the block is taken from the list of known files (`files`), not from the state of the form (`file_meta`), so after «Готово» in the window «Редагувати дані файлу» the block still shows the old name, and the description is not visible at all: the user does not see the result of their change until they save the record and open the form again.

## What Changes

- In the file block of the `image` and `file` fields (the single field and each row of a multiple one, in the create and edit forms) the file description is shown as text **above the size**: name → description → size. An empty description draws nothing.
- The name and the description in the block show the **current state of the form**: after «Готово» in the edit window the block immediately displays the new name and description, without saving the record. «Скасувати» does not change the block. A just uploaded file shows its initial name and an empty description.
- The counter «n / max» of the multiple field with a limit stands **in one line with the label of the field**. Right now it wraps under the label, because `Label` is a block `flex` element inside `legend`.
- The widget `FileUpload` receives an optional prop `description`; without it the behaviour does not change (the demo page, other consumers).
- Saving (`file_meta`), the modal window and the server side do not change.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-content-nodes`: the requirement «Назва й опис файлу у формі запису» — the file block shows the current name and description; the requirement «Ліміт елементів множинного поля у формі запису» — the counter in one line with the label.

## Impact

- Code (frontend only): `Components/Admin/FileUpload/FileUpload.tsx` (the prop `description`), `Components/Admin/ContentNodes/NodeFieldInput.tsx` (the name and the description from the state `fileMeta`), `Components/Admin/ContentNodes/ContentNodeForm.tsx` (the markup of `legend` with the counter).
- Documentation (updates): `docs/admin-content-nodes.md` (the section «Поля `image` і `file`»), `docs/file-storage.md` (the prop `description` of the widget), `docs/e2e-testing.md` (the description of the scenarios `admin-content-nodes`).
- Tests: e2e `admin-content-nodes.spec.js` (`admin-file-upload.spec.js` if needed); PHP does not change.

## Assumptions

- "Immediately" means the moment the changes are applied with the button «Готово» (until then the window edits a draft, which «Скасувати» discards), not a per-keystroke update of the block during typing. This keeps the current model "draft → Готово / Скасувати" (confirmed by the owner).
- The description is shown in the block in full as plain text (React escapes HTML); a long description is limited to a few lines (`line-clamp`) without changing the saved value.
- The display of the counter is only moved to one line with the label; its value, the logic of the limit and its colour do not change.
- The column «Опис» in the media library or in the list of records is not part of this change.

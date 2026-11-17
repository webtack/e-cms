## Why

An auth audit found two admin-panel leaks around the media library, both confirmed by throwaway probes. (F3) A user with the level `own` on a type and no `media` right can put a foreign file into a node and then rename it or rewrite its description through the node form's file data; the change is stored on the shared file and shows everywhere. (F2) The file details of the media library list every place where the file is used, with titles and edit links of nodes of types the viewer has no access to and with names of users (avatar owners), which breaks the rule that a level `none` shows neither the type nor its nodes.

## What Changes

- **Files attached to a node.** A node form has no picker of existing library files; new files get into a node only by upload, which records the uploader. The server now enforces what the form already guarantees: a file that is new in a node's values (on create every file; on save, a file not already in the node) must be a file the user uploaded or one the user's media access lets them change. Otherwise the save is refused with a validation error under the field and nothing is saved. This covers every file-bearing value (`image`/`file` fields, YouTube screenshots, images in rich text) and every language, including the translation mode. Files already in the node stay valid for any user who may edit the node.
- **File data in node forms.** The rule stays: whoever may edit a node may change the name and description of the files in that node's values. Data sent for a file outside the node's values is still ignored. With the rule above, this can no longer reach a file the user never attached.
- **Where a file is used.** In the media file details, a place of use that the viewer may not open is shown as a neutral "Used elsewhere" entry: no title, no link, and no owner type, field, or language. The number of usages and the ban on deleting a file in use do not change. Which owners a viewer may see: nodes — by a level other than `none` on the node's type (with a link only when the viewer may edit that node); users — the `users` right; site settings — `settings`; terms — `taxonomy`; menu items — `menus`; types — `types`; an unknown owner type is never shown. An administrator sees every known owner as before.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `media-library`: the requirement "Where a file is used" now limits the described places by what the viewer may open.
- `admin-content-nodes`: adds the rule that a file new in a node's values must be one the user uploaded or may change by media access, and that the right to edit a node includes changing the data of the files in its values.

## Impact

- Code: a new guard service under `app/Files` called by `app/Http/Controllers/Admin/ContentNodeController.php` (`store`, `update`, `saveTranslation`) inside their transactions; `app/Http/Controllers/Admin/MediaController.php` (`show`), a new usage-visibility service under `app/Files`, and `resources/js/Components/Admin/Media/MediaDetails.tsx` (the neutral entry). `applyFileMeta` and `TranslationSaver` keep their rules and are expected to need no change.
- Documentation (English): `docs/media-library.md` (usage visibility, details panel), `docs/admin-content-nodes.md` (the attachment rule, the `file_meta` rule, translation mode), `docs/access-control.md` (media section, one paragraph).
- Tests: `tests/Feature/Admin/ContentNodeFilesTest.php`, `tests/Feature/Admin/ContentNodeFileDescriptionTranslationTest.php`, `tests/Feature/Admin/MediaLibraryTest.php`, `tests/Feature/Access/MediaAccessTest.php`; e2e `admin-media` for the details panel.
- No migrations, no new dependencies, no change for administrators or for users who upload and edit their own files. Other places that accept a file by uuid (type values, site logos, terms, menu items, the profile avatar) sit behind their own system rights or touch only the user's own account and are out of scope.

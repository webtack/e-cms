# Proposal

## Why

The application has no mechanism for storing files: no `Storage::`/`UploadedFile` in the code, no table for files. A field of type `file`, a user avatar, a logo in the settings and other future consumers need one shared place that accepts a file, checks it, puts it on the disk, stores the metadata and gives a link, so that each consumer does not reinvent this (and does not drag paths and checks into its own columns).

## What Changes

- The table `files` (id, uuid, profile, disk, path, original name, mime, size, hash, width/height for images) — a file has its own identity; consumers store `file_id`.
- The service `FileStorage`: `store()` (a check by profile → writing to the disk → a row in `files`), `url()` (the single point for obtaining a link), `delete()` (the disk + the row).
- Upload profiles in `config/files.php`: the developer describes the disk, the directory, the allowed mimes and the maximum size; at the start `image` and `document`. The user does not choose the disk.
- Basic security rules for all profiles: the type is determined by content (`fileinfo`), executable files and SVG are forbidden, the name on the disk is a uuid, the original name is only in the DB.
- A two-step flow: `POST /admin/files` (multipart, parameter `profile`) returns the metadata and the `id`; the forms then send only `file_id` as regular JSON, so `ContentValueWriter`/`ContentNodeCreator` do not change.
- A validation rule "the file exists and belongs to the profile", which consumers connect to their own fields.
- The lifecycle: the references to `files` are foreign keys `RESTRICT`; a consumer does not delete the file itself. The command `files:prune` (in the scheduler) deletes files without references that are older than 24 hours; the list of reference columns is in `config/files.php`.
- For images only the original and the dimensions are stored (level 1); thumbnails are a separate change.
- A React upload widget (selection/drag-and-drop, state, image preview) for reuse in forms.
- A demo page of the widget `/admin/ui/file-upload` (following `/admin/ui/data-table`: outside the navigation, 404 in production) — for developers and e2e, because the first consumer will appear in a separate change.
- Docker: raise `upload_max_filesize`/`post_max_size` in PHP (currently 2M/8M with 64M in nginx), so that the profile limits are reachable.

Not included: the field type `file`, an avatar, a logo (separate changes that use the mechanism); private files and a delivery route with an access check; thumbnails and derived sizes; deduplication by hash (the hash is stored, two identical uploads give two rows); the media library as a separate admin section; roles and permissions (access is had by everyone who already has access to the admin).

## Capabilities

### New Capabilities
- `file-storage`: the table `files`, upload profiles, the service `FileStorage`, the upload endpoint, the validation rule for consumers, the lifecycle and the cleanup `files:prune`.

### Modified Capabilities
<!-- None: no existing requirements change. -->

## Impact

- **Code (PHP):** the migration `files`; the model `File`; `app/Files/*` (the service, profiles, the validation rule); `FileController` + `StoreFileRequest`; the route in the group `admin`; the command `files:prune` and the entry in the scheduler (`routes/console.php`); `config/files.php`.
- **Code (React):** the upload component in `resources/js/Components/Admin/`; the demo page `Pages/Admin/Ui/FileUploadDemo.tsx` and its controller in `Admin/Ui`; translations of the widget labels (`lang/`).
- **Infrastructure:** `docker/php/` — an ini file with PHP limits and its inclusion in the `Dockerfile`; the `app` image must be rebuilt. Nginx (`client_max_body_size 64M`) does not change. For public files `storage:link` is needed (the disk `public` already exists in `config/filesystems.php`).
- **Dependencies:** no new packages (`gd`, `fileinfo`, `exif` are already in PHP).
- **Tests:** Feature/Unit tests of the service, the endpoint, the validation rule, `files:prune` (on `Storage::fake`); the e2e spec of the upload — together with the first consumer-widget; the row in `docs/e2e-testing.md` (the tables "Area → spec" and "Shared code → specs") is updated if a spec is added.
- **Documentation (`docs/`, in Ukrainian):** the new `docs/file-storage.md` (the model, profiles, the upload flow, `FileStorage`, `files:prune`, how to connect a new consumer); a line in the index `docs/README.md`; cross-references from `docs/content-values.md` (the future type `file`) and `docs/admin-shell.md` (the widget).

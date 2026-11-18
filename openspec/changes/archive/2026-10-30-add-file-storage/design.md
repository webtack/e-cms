# Design

## Context

The motivation and scope are in [proposal.md](proposal.md), the requirements are in [specs/file-storage/spec.md](specs/file-storage/spec.md).

What is in the project (checked in the code):

- `config/filesystems.php`: the disks `local` (`storage/app/private`), `public` (`storage/app/public`, URL `APP_URL/storage`, the link `public/storage` via `storage:link`) and `s3`. There is no file code.
- Field values are EAV: `content_values_{type}` with the column `value` and composite FKs; a new field type = a class + a migration + a line in `config/content.php`. Writing goes through `ContentValueWriter`, which works with simple values. The type `file` (value = `files.id`) will be added by the next change; the write core does not depend on this change.
- Admin routes are in the group `auth`+`verified` with the prefix `admin` (`routes/web.php`). Forms are Inertia `useForm` (JSON). There is a sample demo page of a component: `Ui/DataTableDemoController` (404 in production).
- Scheduler: `routes/console.php` contains only `inspire`.
- The PHP extensions `gd`, `fileinfo`, `exif` are present; `imagick` and image processing packages are not. The PHP limits in the container — `upload_max_filesize=2M`, `post_max_size=8M` (the ini is not set in `docker/php/Dockerfile`); nginx `client_max_body_size` — 64M.
- Tests: PHPUnit on in-memory SQLite; foreign keys in SQLite are enabled in Laravel.

## Goals / Non-Goals

**Goals:**
- One service through which all consumers store, address and delete files; a consumer knows only the `file_id`.
- The rules (disk, directory, types, size) are described by the developer in the configuration; the user does not choose or bypass them.
- The integrity is ensured by the DB (FK `RESTRICT`), and the cleanup is safe even with an error in the configuration.

**Non-Goals:**
- Private files and a delivery route with an access check: the service `url()` leaves a place for this, but there is no route.
- Thumbnails, derived sizes, image processing, deduplication, a media library, roles and permissions.
- The field type `file`, an avatar, a logo — separate changes.

## Decisions

### D1. The table `files`, consumers store `file_id`

Columns: `id`, `uuid` (UUID v7, as in `content_nodes`), `profile` (string 64), `disk` (string 64), `path` (string 512), `original_name` (string 255), `mime` (string 127), `size` (unsigned bigint), `hash` (char 64, sha256), `width`/`height` (nullable unsigned int), `timestamps`. An index on `created_at` (for `files:prune`). The model `App\Models\File`.

Alternative — only the path in the consumer's column: rejected (the metadata is scattered, there is no single cleanup, reuse is impossible). Deduplication by `hash` is not done: the hash is stored, but a row is always created — this simplifies the lifecycle (each row has its own path and independent deletion). There is no unique index on `hash`.

### D2. Profiles in `config/files.php`

```php
'profiles' => [
    'image' => ['disk' => 'public', 'directory' => 'images', 'mimes' => [image/jpeg, image/png, image/webp, image/gif], 'max_size' => 5 * 1024],   // KB
    'document' => ['disk' => 'public', 'directory' => 'documents', 'mimes' => [application/pdf, ...docx, ...xlsx], 'max_size' => 20 * 1024],
],
'references' => [],   // [['table' => ..., 'column' => ...], ...]
'prune_after_hours' => 24,
```

`FileProfiles` (a class that reads the configuration) returns a profile by key or throws `InvalidArgumentException`; the endpoint checks the key beforehand with the rule `Rule::in(array_keys(...))`. The disk and the directory are taken from the profile, not from the request. A new profile is one block in the configuration.

Alternative: rules (mimes, max) from the client — rejected, the client cannot be trusted. One disk for the whole application — rejected: different consumers may need different disks, and `files.disk` is already in each row.

### D3. `FileStorage` — the single point

```
store(UploadedFile $file, string $profile): File
url(File $file): string
delete(File $file): void
```

`store`:
1. The profile from `FileProfiles`.
2. A check before writing to the disk: size ≤ `max_size`; the mime by content (`finfo` via `UploadedFile::getMimeType()`, which reads the content) is in the `mimes` of the profile; **a global blacklist**: the mime/extensions of executable files (`php`, `phtml`, `phar`, `sh`, `exe` etc.) and `image/svg+xml` are rejected even if the profile allowed them; a failure → `ValidationException` with the key `file`.
3. For images `getimagesize` gives the width/height; if the content cannot be read as an image with the mime `image/*` → rejection.
4. The name on the disk: `{directory}/{uuid}.{extension from the mime}`; the extension is taken from the matching allowed mime, not from the original name. Writing to the disk — `Storage::disk($disk)->putFileAs(...)`.
5. A row in `files`. If the insert failed — the file on the disk is deleted (compensation, because a DB transaction does not roll back the disk).

`url`: `Storage::disk($file->disk)->url($file->path)` — for `public` this is `/storage/...`, for `s3` — the URL of the disk. The private mode (`temporaryUrl`/a route) is added later in this same method. `delete`: first the row in the DB (an FK may refuse → an exception, the disk is not touched), then the file on the disk; a file missing on the disk is not an error.

The service is a singleton in the container, without a facade (following the sample `AppSettings`).

### D4. The endpoint `POST /admin/files`

`App\Http\Controllers\Admin\FileController::store` + `StoreFileRequest` (`file` required + `profile` with `Rule::in`). The route is in the existing group `auth`+`verified`, the name `admin.files.store`. The JSON response 201: `id`, `uuid`, `profile`, `name` (the original name), `mime`, `size`, `width`, `height`, `url`. Errors — the standard 422 of Laravel for AJAX. The endpoint knows nothing about the consumer; whether the file belongs to the profile of a field is checked by the consumer with the rule from D5.

No throttle is added: access is given only to authenticated admin users, a separate limit policy is not part of this change.

### D5. A validation rule for consumers

`App\Files\Rules\FileOfProfile` (`ValidationRule`): the value must be an integer, the row `files.id` must exist, and `files.profile` must match the expected one. Usage: `new FileOfProfile('image')`. This is what the later `file` field type and an avatar will use. The message — through `lang/`.

### D6. Integrity and cleanup

The consumer creates a column `file_id` with `->constrained('files')->restrictOnDelete()` (FK `RESTRICT`) and adds its `table.column` to `config('files.references')`. The consumer never calls `FileStorage::delete` when its value changes.

`php artisan files:prune` (`App\Console\Commands\PruneFiles`): selects `files` older than `prune_after_hours` (by `created_at`), for which for each element of `references` there is no row with such a `file_id` (`whereNotExists`, in chunks of 500); for each one it calls `FileStorage::delete`. An error of a single attempt (`QueryException` through the FK — a failure of the `references` configuration; an exception of the disk) is logged, the file is skipped, the rest continues. It outputs the number of deleted files. The schedule: `Schedule::command('files:prune')->daily()` in `routes/console.php`.

The double protection is deliberate: `references` is needed for an efficient selection (not to try to delete all used files each time), the FK `RESTRICT` — so that an error in the configuration does not lead to data loss.

Alternative (rejected): the consumer deletes the file immediately — on reuse it requires a reference counter and a check in each consumer. The "temporary → confirmed" binding (a `confirmed_at` field) — rejected: it does not cover releasing a file after the binding.

While `references` is empty (there are no consumers yet), `files:prune` deletes any file older than 24 hours — this is expected: no one can use it.

### D7. The widget `FileUpload` and the demo page

`resources/js/Components/Admin/FileUpload/FileUpload.tsx`: props — `profile`, `value` (the file metadata or `null`), `onChange(file | null)`, `accept`/`label`/`error` for the display. Uploading — a multipart `POST`. In `resources/js` there are no own CSRF requests outside Inertia (the search for `axios`/`csrf`/`XSRF` is empty), so the way the CSRF token is passed (the header `X-XSRF-TOKEN` from the cookie or an Inertia request) is chosen in the implementation after checking the Inertia version and the Laravel behaviour; progress is not required beyond a "loading" indicator. For `image/*` — a preview by `url`. The form gets `file.id` through `onChange` and then sends JSON. The component does not know about the Inertia form: it works both with `useForm` and with any state.

The demo: `GET /admin/ui/file-upload` (`admin.ui.file-upload`), the controller `Ui\FileUploadDemoController`, the page `Pages/Admin/Ui/FileUploadDemo.tsx`; outside production, not in the navigation; two instances of the widget (`image`, `document`). The breadcrumbs/title — following the sample demo table (`navigation.ts`).

### D8. Docker

`docker/php/uploads.ini` (`upload_max_filesize=25M`, `post_max_size=30M`) is copied into `$PHP_INI_DIR/conf.d/` in the `Dockerfile`; the values have a margin above the largest profile (20 MB). The image must be rebuilt: `docker compose up -d --build app`. The real limits for each profile are in any case enforced by `FileStorage`; the PHP limit is only the upper bound of the transport.

## Risks / Trade-offs

- **OOXML through `finfo`**: DOCX/XLSX are ZIP containers; depending on the libmagic version `finfo` may return `application/zip` instead of `application/vnd.openxmlformats-officedocument.*`. → A test with real generated DOCX/XLSX on the `app` image; if needed, the allowed `application/zip` is added for `document` only if the original extension is `docx`/`xlsx` (a decision within this same implementation; the specification does not change).
- **The file and the DB are not atomic**: the disk does not roll back together with the transaction. → Compensation in `store` (D3) and `files:prune` as insurance against leftovers; a file on the disk without a row in the DB (a failure between the steps) is not found by this mechanism — the risk is accepted, it is unlikely.
- **Orphaned files live up to a day** (the owner's decision): the disk may temporarily contain unattached files.
- **A forgotten `references`**: a new consumer without an entry in `config('files.references')` does not lose data (FK `RESTRICT` + skipping in `files:prune`), but its files each time get into an attempt to delete and a log → the consumer's documentation requires adding a line.
- **Raising PHP limits wider than needed** increases the attack surface for oversized requests → values with a margin only above the largest profile.
- **`config/files.php` vs `config/filesystems.php`**: profiles refer to disks by name; an unsupported disk will show up at the first upload → a test of the validity of the profile configuration.

## Migration Plan

1. The migration `files` (a new table, existing data is not affected); rollback — `dropIfExists`.
2. Rebuild the `app` image (PHP limits) and run `php artisan storage:link` (if not done yet).
3. The schedule `files:prune` is enabled by the code itself; the environment must run `schedule:run` (cron/worker) — if not, the command is run manually.

Rollback: remove the route/schedule, migration `down`. The saved files on the disk are removed manually (there are no consumers yet).

## Documentation

The description is in the new `docs/file-storage.md` (the model, profiles, the upload flow, `FileStorage`, the rule `FileOfProfile`, `files:prune`, how to connect a new consumer, the PHP/nginx limits); the index — `docs/README.md`; the demo page and the widget — `docs/file-storage.md` with a link from `docs/admin-shell.md`; the e2e spec — `docs/e2e-testing.md`.

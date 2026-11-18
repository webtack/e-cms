# Tasks

## 1. Infrastructure and the table `files`

- [x] 1.1 Add `docker/php/uploads.ini` (`upload_max_filesize=25M`, `post_max_size=30M`), include it in `docker/php/Dockerfile` (copying into `$PHP_INI_DIR/conf.d/`), rebuild the image `docker compose up -d --build app`; check: `docker compose exec app php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size");'` prints `25M 30M`
- [x] 1.2 The migration `files` (columns and index per design D1: `uuid`, `profile`, `disk`, `path`, `original_name`, `mime`, `size`, `hash`, `width`, `height`, `timestamps`, an index on `created_at`) and the model `App\Models\File` with JSDoc/PHPDoc in English and `@example`; check: `docker compose exec app php artisan migrate`, the migration/model test passes (`docker compose exec app php artisan test --filter=File`), Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`
- [x] 1.3 Create `docs/file-storage.md` (in Ukrainian) with a section on the model `files` and the PHP/nginx limits, add a row in `docs/README.md`; check: the links in the index open, the description matches the migration

## 2. Profiles and `FileStorage`

- [x] 2.1 `config/files.php` (profiles `image` and `document`, `references` = `[]`, `prune_after_hours` = 24) and `App\Files\FileProfiles` (reading a profile, an error for an unknown key) with comments in English; check: a Unit test of the profiles (existing, unknown, the validity of the disks of all profiles in `config('filesystems.disks')`)
- [x] 2.2 `App\Files\FileStorage` (`store`, `url`, `delete`, a singleton in the container) per design D3: a check of size and type by content, the blacklist of executable files and SVG, the generated name on the disk, the metadata of images, compensation on an insert failure, `delete` first the row, then the disk, a missing file is not an error; check: Feature tests on `Storage::fake` cover all scenarios of the requirements "File records", "Upload profiles", "Checking a file against the profile", "Security of stored files", "Link to a file" and "Protection of files in use", including OOXML files (DOCX/XLSX), genuine and with a faked extension
- [x] 2.3 Check on the `app` image which mime `finfo` returns for generated DOCX and XLSX; if it is `application/zip`, apply the decision from the design "Risks" (allowing `application/zip` only with the extension `docx`/`xlsx`) and record the result with a test; check: the test with real DOCX/XLSX passes
- [x] 2.4 The sections "Profiles", "FileStorage" in `docs/file-storage.md` (examples of `store`/`url`/`delete`, how to add a profile); check: the examples match the code

## 3. Endpoint and validation rule

- [x] 3.1 `StoreFileRequest`, `Admin\FileController::store` and the route `POST /admin/files` (`admin.files.store`) in the group `auth`+`verified`; a 201 response with the fields per design D4; check: the Feature tests (success, guest, missing file/profile, unknown profile, disallowed type, file too large) pass
- [x] 3.2 `App\Files\Rules\FileOfProfile` and the translations of the messages in `lang/`; check: a Unit test (the required profile, another profile, a non-existing file, a non-numeric value) passes, the translation keys are in `lang/en.json` and `lang/uk.json`
- [x] 3.3 The sections "File upload" and "The rule FileOfProfile" in `docs/file-storage.md` (the request, the response, the errors, how a consumer connects the rule and the FK `restrictOnDelete`); check: the examples match the code

## 4. Lifecycle: `files:prune`

- [x] 4.1 The command `files:prune` per design D6 (a selection of files older than `prune_after_hours` without references per `config('files.references')`, chunks of 500, skipping and logging on error, a summary) and the entry `Schedule::command('files:prune')->daily()` in `routes/console.php`; check: Feature tests — orphaned, fresh, in use (a temporary test table with an FK `RESTRICT` and a record in `references`), missing on the disk, an error of one file does not stop the rest; `docker compose exec app php artisan schedule:list` shows the command
- [x] 4.2 The section "Lifecycle and cleanup" in `docs/file-storage.md` (the consumer rule: do not delete the file yourself, add a line in `references`, `schedule:run` is needed); check: the section matches the code of the command

## 5. Widget and demo page

- [x] 5.1 The component `Components/Admin/FileUpload/FileUpload.tsx` (selection and drag-and-drop, indication, validation errors, image preview, clearing, calling `onChange`) with JSDoc in English and `@example`, translations of the labels in `lang/en.json` and `lang/uk.json`; check: `npm run build` without errors, the JSDoc is present
- [x] 5.2 The demo page: `Ui\FileUploadDemoController` (404 in production), the route `admin.ui.file-upload` (`/admin/ui/file-upload`), `Pages/Admin/Ui/FileUploadDemo.tsx` with two widgets (`image`, `document`), a line in `navigation.ts` for the title; check: the Feature test (guest, authenticated, production → 404) passes
- [x] 5.3 E2E `e2e/admin-file-upload.spec.js` (an allowed file, a disallowed type, a file that is too large, clearing, guest) and the rows in the tables "Area → spec" and "Shared code → specs" in `docs/e2e-testing.md`; check: `npm run test:e2e -- e2e/admin-file-upload.spec.js` passes
- [x] 5.4 The section "Widget and demo page" in `docs/file-storage.md`, a link from `docs/admin-shell.md`; check: the links work in both directions

## 6. Integration check

- [x] 6.1 `docker compose exec app php artisan test` (the whole PHPUnit) and Pint pass without errors; `openspec validate add-file-storage` passes

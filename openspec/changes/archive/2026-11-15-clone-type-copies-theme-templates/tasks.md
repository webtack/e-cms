# Tasks

## 1. Copy of theme templates

- [x] 1.1 Check that `themes/` is writable from the `ecms_app` container (`docker compose exec app sh -c 'test -w themes && echo ok'`) and record the result in design.md under Risks
- [x] 1.2 Add the file-copy collaborator in `app/Content/` (themes from `config('theme.path')`; the selection rules for `views/{old}/` and `views/blocks/block-{old}.blade.php`; plan-then-copy without overwrite; cleanup of created paths on failure) with English doc comments and `@example`; verify with a unit/Feature test on a temporary theme root
- [x] 1.3 Call it from `ContentTypeCloner::clone` after the settings are saved, with cleanup on any exception; verify `docker compose exec app php artisan test --compact --filter=ContentTypeClone` passes
- [x] 1.4 Catch the failure exception in `ContentTypeController::clone` and return back with input and a translated `error` message (add the string to the language files); verify with a Feature test of the response and the kept input

## 2. Tests and docs

- [x] 2.1 Extend `tests/Feature/Admin/ContentTypeCloneTest.php` with the scenarios of "Theme templates of a cloned type" (directory in two themes, `--{id}` skipped, block copied and `--{slug}` skipped, byte-equal content, nothing to copy, target exists with cleanup in other themes, unwritable target) using a temporary theme root; verify `docker compose exec app php artisan test --compact --filter=ContentTypeClone`
- [x] 2.2 Update `docs/admin-content-types.md` ("Cloning") and `docs/site-themes.md` in English with cross-links, and check that the `docs/README.md` index links stay valid
- [x] 2.3 Run `docker compose exec app vendor/bin/pint --dirty --format agent` and verify it reports no remaining issues; e2e is skipped because the clone dialog is unchanged

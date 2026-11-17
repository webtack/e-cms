# Tasks

## 1. Re-creation after the style directory is removed

- [x] 1.1 In `ImageStyles::create()` (`app/Files/ImageStyles.php`) call `clearstatcache(true)` after the early validation and before the existence check, with an English comment explaining the stale realpath cache of a long-lived PHP-FPM worker; verify with `docker compose exec app vendor/bin/pint --dirty --format agent` and by reading the changed method.
- [x] 1.2 When `put()` or `move()` return `false`, write `Log::warning` with the same context keys (`disk`, `path`, `style`) as the existing `catch` branch, keep returning `null`, and keep deleting the temporary file after a failed move; verify the new warning test in 1.3 passes.
- [x] 1.3 Add Feature tests in `tests/Feature/Files/ImageStyleRouteTest.php` (and the unit-level file for `ImageStyles` if it exists): the whole style directory removed from the disk and the address requested gives 200 and creates the file; one removed file is re-created; a disk whose write fails gives 404, a logged warning and no `.tmp` file left. Verify with `docker compose exec app php artisan test --compact --filter=ImageStyle`.
- [x] 1.4 Update `docs/image-styles.md` (Lazy generation: re-creation after `flush` or manual removal, the realpath-cache reason, the warning on a failed write; keep `docs/README.md` links valid) and verify the text matches the code.
- [x] 1.5 Manually verify in real PHP-FPM: repeat six times `rm -rf storage/app/public/styles/thumb` followed by an immediate `curl -s -o /dev/null -w "%{http_code} " http://localhost:8080/storage/styles/thumb/<uuid>.webp` for an existing image; every answer is 200 (before the fix: a mix of 200 and 404). e2e is skipped: no browser behavior changes.

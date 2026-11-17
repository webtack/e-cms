# Tasks

## 1. Server: unique flash id

- [x] 1.1 In `app/Http/Middleware/HandleInertiaRequests.php` add the lazy `flash.id`: a fresh `(string) Str::uuid()` when the session holds `success` or `error`, otherwise `null`. Add an English comment. Verify: `docker compose exec app php artisan test --compact --filter=test_flash_messages_are_shared_once`.
- [x] 1.2 Extend `test_flash_messages_are_shared_once` in `tests/Feature/Admin/DashboardTest.php`: `flash.id` is a non-empty string when a message is flashed and `null` on the next request; add a test that two requests with the same flashed text get different ids. Verify: `docker compose exec app php artisan test --compact tests/Feature/Admin/DashboardTest.php`, then `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 2. Client: toast on every new flash

- [x] 2.1 Add `id: string | null` to the `flash` type in `resources/js/types/index.d.ts` and change `FlashToaster.tsx` to one effect with `[flash.id]` that shows `toast.success` / `toast.error` (with the existing durations) for the messages present. Update the JSDoc in English. Verify: `npx tsc --noEmit` and `npm run build` succeed.
- [x] 2.2 Add an e2e check that saving the same edit form twice in a row shows a success toast both times. Put it in the spec that covers an edit form with a success toast (`e2e/admin-content-fields.spec.js`, `-g` for the new test; pick the area in the "area → spec" table of `docs/e2e-testing.md`). Verify: `npm run test:e2e -- e2e/admin-content-fields.spec.js` passes. Other specs are skipped: the change does not alter the other pages' behavior.
- [x] 2.3 Update `docs/admin-shell.md`: in "Toasts" describe that a toast is triggered by `flash.id` (a new toast for every flash message, even with equal text) and in "Routes and shared props" add `flash.id`. Verify: the text matches the code and the cross-links stay valid.

# Tasks

## 1. Automatic reset of the language before a run

- [x] 1.1 Create `e2e/global-setup.js` with an English JSDoc: using `docker compose exec -T app php artisan tinker` it sets `locale = 'en'` for the user with `ADMIN_EMAIL` (by default `admin@example.com`), the email is passed as a JSON literal; on error it throws a clear exception. Check: manually set `uk` (`docker compose exec app php artisan tinker --execute="App\Models\User::query()->update(['locale' => 'uk'])"`), run `npm run test:e2e -- e2e/smoke.spec.js` and make sure `users.locale` became `en`.
- [x] 1.2 Connect `globalSetup: './e2e/global-setup.js'` in `playwright.config.js` with an English comment about the reason. Check: `npx playwright test --list` works, and stopping the `app` containers gives a clear reset error (not dozens of failures).
- [x] 1.3 Check the recovery after an interrupted run: set `uk`, run the full `npm run test:e2e` (`workers: 1`) and make sure all tests pass without a manual reset.

## 2. Documentation

- [x] 2.1 Update `docs/e2e-testing.md` (in Ukrainian): in the section «Структура» mention `e2e/global-setup.js` and `globalSetup` in the config; in the sections on multilingualism and on the fields of a content type replace «поверніть мову вручну» with a description of the automatic reset, leaving the manual command as a fallback. Check: read the sections and make sure the relative links work, and `docs/README.md` needs no changes.
- [x] 2.2 Run `openspec validate reset-e2e-user-locale --strict` and make sure the changes in the specification are valid.

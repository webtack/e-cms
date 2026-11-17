## 1. Query

- [x] 1.1 In `ContentNodeQuery::applySearch()` add the slug branch (escaped `LIKE` on `slug`) to the OR group when `searchFields` is not passed; keep an explicit `searchFields` (including `[]` → empty result) unchanged; for the default path with no text fields search by slug only. Update the class docblock (search section). Verify: JSDoc/PHPDoc in English is in place.
- [x] 1.2 Add Feature tests in `tests/Feature/Admin/ContentNodeIndexTest.php` and the `ContentNodeQuery` search tests: slug match, case-insensitive, slug found when `settings.list.search` is `["title"]`, `%`/`_` literal in the slug, type without text fields, explicit `searchFields` does not add the slug. Run `docker compose exec app php artisan test --compact --filter=ContentNodeIndexTest` and the query test class; then `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 2. Documentation

- [x] 2.1 Update `docs/admin-content-nodes.md` (Search, sorting, pagination), `docs/content-querying.md` (Search: slug on the default path, explicit `searchFields` exact) and `docs/admin-content-types.md` (the "Search" bullet: the slug is always searched, not a checkbox). Keep cross-links valid.

## 3. e2e

- [x] 3.1 Add a search-by-slug scenario to `e2e/admin-content-nodes.spec.js` next to the existing search test; run only it: `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "search"` (check names with `npx playwright test --list`).

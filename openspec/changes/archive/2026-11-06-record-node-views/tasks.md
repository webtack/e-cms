## 1. Counter

- [x] 1.1 Add the `views` block to `config/content.php` (`enabled` from `CONTENT_VIEWS_ENABLED`, default true; `ttl` seconds, default 86400; `bot_agents` list) and the variable to `.env.example`. Verify: `docker compose exec app php artisan config:show content.views` prints the block.
- [x] 1.2 Add the migration with an index on `content_nodes.views` (`add_views_index_to_content_nodes_table`). Verify: `docker compose exec app php artisan migrate` and `migrate:rollback --step=1` succeed.
- [x] 1.3 Add `App\Content\ViewCounter` (`record(Request, ContentNode)`: checks in the order of design decision 2, atomic `Cache::add` key, `incrementViews()`, all failures logged and swallowed); English doc blocks with `@example`. Verify: `tests/Feature/Content/ViewCounterTest.php` (counted, repeated visitor, another visitor, another node, after the period via `Carbon::setTestNow`, bot and empty User-Agent, `HEAD`, authenticated user, switch off, `updated_at` unchanged, cache failure and update failure leave the result unaffected) passes with `--filter=ViewCounterTest`.
- [x] 1.4 Call `ViewCounter` from `PublicNodeController` in the details and page branches after the response is built (not for lists and 404). Verify: `tests/Feature/Public/PublicViewsTest.php` (the scenarios of "Counting views of public node pages": content node, page, list, 404, invisible node, nested type slug address, response unchanged on failure) passes with `--filter=PublicViewsTest`, and `--filter=PublicRoutesTest` still passes.

## 2. Templates and sorting

- [x] 2.1 Make sure `views` reaches templates for details, page and list cards (the query selects the column); add a guard test. Verify: a case in the theme render tests (find with `grep -rl "content/details" tests/Feature/Theme`) prints `node.views`; `--filter` of that class passes.
- [x] 2.2 Add `views` to `NodeListSettings::SERVICE_COLUMNS` (number, sortable), `ContentType::LIST_SERVICE_KEYS` and `LIST_SORT_SERVICE_KEYS`, `ContentNodeQuery::SORTABLE_SERVICE_KEYS` and `applyServiceSort` (numeric order, ties by title then id). Verify: new cases in `tests/Feature/Content/ContentNodeQueryTest.php` (9, 10, 100 ordered numerically; zero first ascending; ties) and the list-settings tests (`grep -rl "NodeListSettings\|settings.list" tests`) pass with `--filter`.
- [x] 2.3 Accept `views` as the public sort field in `PublicDisplaySettings` and cover it. Verify: tests of the public display settings (`grep -rl "PublicDisplaySettings" tests`) include the "popular nodes first" scenario and pass with `--filter`.

## 3. Admin

- [x] 3.1 Show the "Views" column (integer, sortable) in `Pages/Admin/ContentNodes/Index.tsx` and offer "Views" in the column and sort options of `ContentTypeForm.tsx`; add "Views" to `lang/uk.json` and `lang/en.json`. Verify: `tests/Feature/Admin/ContentNodeIndexTest.php` and `ContentTypeWriteTest.php` (or the classes found by `grep -rl "settings.list" tests/Feature/Admin`) cover the scenarios of the delta specs and pass with `--filter`; `npm run build` on the host succeeds.

## 4. Reserved key and fixtures

- [x] 4.1 Add `views` to `ContentField::RESERVED_KEYS` and rename the `views` field fixtures to `visits` in the tests found by `grep -rlw views tests e2e` (about 20 files: ContentField*Test, ContentNodeWriteTest, FileFieldTypesTest, ContentTypeTest, CascadeTest, SchemaTest, ContentNodeQueryTest, BuildsArticleType, ContentValueWriterTest, ContentValueReaderTest, TypeValuesTest, TermFilterTest, theme tests, `e2e/admin-content-fields.spec.js`) without changing what they check; add a case that a field with the key `views` is rejected in create. Verify: `--filter` runs of each touched class pass.
- [x] 4.2 This change adds a migration and changes a model constant used by many tests, so run the full PHP suite once: `docker compose exec app php artisan test --compact`, and `docker compose exec app vendor/bin/pint --dirty --format agent`. Verify: both pass.

## 5. Documentation and e2e

- [x] 5.1 Update `docs/public-site.md` (counting rules, config, templates, public sort), `docs/content-model.md` (reserved key), `docs/content-querying.md` and `docs/content-values.md` (rename the `views` examples, `views` sort key), `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md` (reserved key), and the `views` mentions in `docs/e2e-testing.md` and `docs/theme-templates.md` if they use a `views` field. Verify: relative links resolve.
- [x] 5.2 Run the minimal e2e on the host: `npm run test:e2e -- e2e/admin-content-fields.spec.js` (renamed fixture) and `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "<columns/sorting>"` (names from `npx playwright test --list`), plus `e2e/admin-content-types.spec.js -g "<list settings>"`. Verify: they pass; the other specs are skipped because nothing else in the browser changes.

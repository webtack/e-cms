# Tasks

## 1. Settings model and reserved path

- [x] 1.1 Add validation of `settings.sitemap` to `ContentType` (keys `xml` / `web`, booleans, closed `changefreq` list, `priority` 0.0–1.0 in steps of 0.1, no `type` for `page`, no `settings.sitemap` for `block`, `changefreq` / `priority` only with an XML checkbox on), with English JSDoc/PHPDoc and `@example`. Verify with new cases in `tests/Feature/Content/ContentTypeTest.php`: `docker compose exec app php artisan test --compact --filter=ContentTypeTest`
- [x] 1.2 Add a case to the cloning tests that `settings.sitemap` is copied unchanged, and verify: `docker compose exec app php artisan test --compact --filter=ContentTypeCloner` (use the actual cloning test class name found with `grep -rl ContentTypeCloner tests`)
- [x] 1.3 Add `sitemap` to `reserved_paths` in `config/content.php` and to `PublicPaths::SITE_PATHS`; verify with new cases in `tests/Feature/Content/ReservedPathsGuardTest.php` (page slug `sitemap`, type slug `sitemap/archive` rejected) and the site 404 of `/sitemap/anything` in `tests/Feature/Public`: `docker compose exec app php artisan test --compact --filter=ReservedPathsGuardTest`
- [x] 1.4 Check the database for a page or a content type that already uses the slug `sitemap` (`docker compose exec app php artisan tinker --execute=...`, read-only) and report the result; verify the report names the check and its outcome
- [x] 1.5 Because 1.3 changes configuration, run the full suite once: `docker compose exec app php artisan test --compact`, and fix regressions; then `docker compose exec app vendor/bin/pint --dirty --format agent`

## 2. "Sitemap" tab backend

- [x] 2.1 Add the routes `GET|PUT /{content-types|page-types}/{contentType}/sitemap` (names `{section}.sitemap`, `{section}.sitemap.update`, `can:types`, `kind` default) in the same loop as the SEO routes in `routes/web.php`, so blocks answer 404; add the controller and form request that read and write only `settings.sitemap` (everything off removes the key, other keys of `settings` stay, `changefreq` / `priority` dropped with the XML checkboxes), with English docblocks and `@example`. Verify with a new `tests/Feature/Admin/ContentTypeSitemapTest.php` covering: page props with defaults, saving and reloading, "everything off", other keys kept, validation errors, `page` without `type`, 403 without `types`, 404 for a block: `docker compose exec app php artisan test --compact --filter=ContentTypeSitemapTest`
- [x] 2.2 Pass the data the page needs (kind, whether the type has a slug, stored values with the defaults `weekly` / `0.5`) and verify with the same test file
- [x] 2.3 Document the tab (routes, saving rules, permissions, 404 for blocks) in `docs/admin-content-types.md` and `docs/admin-page-types.md` with cross-links to `docs/type-sitemap.md`; verify the links resolve (`grep -n "type-sitemap" docs/*.md`)
- [x] 2.4 Run Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`

## 3. "Sitemap" tab frontend

- [x] 3.1 Add the item `sitemap` (after `seo`, hidden for `block`, label "Sitemap") to `resources/js/Components/Admin/ContentTypes/typeSectionItems.ts` and update its JSDoc `@example`; verify the tab and the row-menu item appear for `content` and `page` and not for `block` (`npm run build` on the host succeeds, then the e2e in 3.4)
- [x] 3.2 Add `Pages/Admin/ContentTypes/Sitemap.tsx` and `Components/Admin/ContentTypes/SitemapSettingsSection.tsx` (two bordered blocks, the "Content type" checkbox only for `content`, the two selects, the no-slug hint, one sticky "Cancel / Save"), built from `FormSection` / `FieldGroup` / `FormActionBar`, with JSDoc and `@example`; verify with `npm run build` and by opening the tab at `http://localhost:8080/admin/content-types/<key>/sitemap`
- [x] 3.3 Add the English and Ukrainian strings ("Sitemap" / "Карта сайту", the block titles, "Content type", "Content", "Change frequency", "URL priority", the hint and the success message) to `lang/en.json` and `lang/uk.json`; verify the tab shows Ukrainian labels with the Ukrainian locale
- [x] 3.4 Update the e2e menu-composition assertions that list exact items (`e2e/admin-content-types.spec.js` lines with `'Edit', 'Display', 'SEO', ...` in English and Ukrainian, `e2e/admin-page-types.spec.js`) and add tests for the tab (open from the menu, save and reload, page type without "Content type", block has no tab). Run `npx playwright test --list e2e/admin-content-types.spec.js`, then `npm run test:e2e -- e2e/admin-content-types.spec.js` and `npm run test:e2e -- e2e/admin-page-types.spec.js`; `admin-block-types` runs with `-g "SEO|menu"` only to confirm the block menu is unchanged
- [x] 3.5 Update the "area → spec" row for the content types section in `docs/e2e-testing.md` (mention the "Sitemap" tab) and the "shared code" row of `typeSectionItems`; verify the tables still name existing specs

## 4. XML sitemap

- [x] 4.1 Add the service that reads `settings.sitemap` of all `content` / `page` types once and the XML builder (home first with `monthly` / `1.0`, types ordered by key, list address then nodes by `updated_at` desc, visible nodes only, types without a slug skipped, `lastmod` with offset, list `lastmod` from the latest visible node, de-duplication by address, cut at 50 000 URLs with a logged warning), with docblocks and `@example`. Verify the query count stays fixed regardless of the number of types (assert it in a Feature test)
- [x] 4.2 Add the public route `GET /sitemap.xml` before `/{path}` and a controller returning `application/xml; charset=utf-8`; verify with a new `tests/Feature/Public/SitemapXmlTest.php` covering: empty configuration (only home), list and records, only list, hidden / scheduled / expired nodes, pages, type without a slug, unconfigured and web-only types, `lastmod` values, 120 records all present, the 50 000 cut with the warning (lower the limit through the builder's parameter in the test), well-formed XML: `docker compose exec app php artisan test --compact --filter=SitemapXmlTest`
- [x] 4.3 Create `docs/type-sitemap.md` (the settings shape and defaults, the XML rules, the limit and what to do beyond it, the reserved path, differences from the legacy module) and add it to the `docs/README.md` index; verify the index link resolves
- [x] 4.4 Run Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`

## 5. Web sitemap

- [x] 5.1 Add the tree builder (home, flat pages by title, content types by `parent_type` with the promote-to-parent rule, type without a slug skipped but children visited, titles in the reading language then the base language then the slug, guard against a `parent_type` cycle) and verify the query count is fixed in a Feature test
- [x] 5.2 Add the public route `GET /sitemap` before `/{path}` and the controller that renders candidate `sitemap/page` with hook `sitemap`, title "Sitemap" and `items` (`title`, `url`, `children`); verify with a new `tests/Feature/Public/SitemapWebTest.php` covering: 200 in the shell, nothing configured (only "Home"), nested child types, records only, parent off with the child on, pages order, reading-language titles, hidden nodes, `/sitemap/anything` gives the site 404: `docker compose exec app php artisan test --compact --filter=SitemapWebTest`
- [x] 5.3 Add `sitemap/page` (with a recursive list partial) to `themes/default/views/sitemap/` and `themes/dcculture`, matching each theme's markup and CSS; verify with `docker compose exec app php artisan theme:check`, `npm run theme:build` on the host and a visual check of `http://localhost:8080/sitemap` in both themes (no theme tests, by project rule)
- [x] 5.4 Document the template (`sitemap/page`, hook `sitemap`, the `items` structure) in `docs/theme-templates.md` and the two routes and the reserved path in `docs/public-site.md`, linking to `docs/type-sitemap.md`; verify the links resolve
- [x] 5.5 Run Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`

## 6. Integration check

- [x] 6.1 Add a note to `docs/legacy-import.md` that the sitemap settings still do not move and are set per type on the "Sitemap" tab; verify the table row still reads correctly
- [x] 6.2 With the containers running, configure one `content` type and one `page` type on the "Sitemap" tab, then check `curl -s http://localhost:8080/sitemap.xml` and `http://localhost:8080/sitemap` against the settings; verify both match the rules in `specs/type-sitemap/spec.md`
- [x] 6.3 Run `openspec validate add-type-sitemap --strict`; verify it reports the change as valid

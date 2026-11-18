# Tasks

## 1. Backend: the kind `block` from the route

- [x] 1.1 Add `'block-types' => 'block'` to the route registration loop in `routes/web.php` and replace the ternary expression in `ResolvesTypeSection::segment()` with a mapping of kinds to segments; update the PHPDoc/comments that mention only `content`/`page`; check: `docker compose exec app php artisan route:list --name=block-types` shows the full set of routes, `--name=page-types` and `--name=content-types` unchanged
- [x] 1.2 Feature tests in `tests/Feature/Admin/BlockTypeTest.php` modelled on `PageTypeTest.php` (the guest, the list of `block` only without slug, creation with the kind `block`, a parent of another kind, editing without changing `key`/`kind`, a descendant as the parent, 404 between `content-types`/`page-types`/`block-types`, deletion with nodes/children, fields: list, creation, the ban on deleting `title`, order, a foreign key); check: `docker compose exec app php artisan test --filter=BlockType` passes, and `--filter=PageType` and `--filter=ContentType` are not broken
- [x] 1.3 Pint for the changed PHP files; check: `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks

## 2. Frontend: the kind `block` in the shared pages

- [x] 2.1 Extend `TypeSection['kind']` to `'block'` and add the entry `block` in `sectionLabels` (`section.ts`, JSDoc in English with `@example`); add the keys to `lang/uk.json` and `lang/en.json`; check: `npm run build` without type errors, `grep -rn "kind === 'page'" resources/js` finds no checks that exclude `block`
- [x] 2.2 Add the item "Block Types" (icon `Blocks`) to `navigation.ts` after Page Types, the entries in `extraTitles`/`patternTitles` and generalize the field templates to `(content|page|block)-types`; check: the sidebar leads to `/admin/block-types`, the breadcrumbs are correct on create/edit/fields

## 3. E2E and documentation

- [x] 3.1 Add `e2e/admin-block-types.spec.js` modelled on `admin-page-types.spec.js` (the list, creation, editing, the section menu with `aria-current`, fields: creation/deletion/order, deletion of a type, the sidebar item) and a row "Block types" in the table "area → spec" in `docs/e2e-testing.md`; check: `npm run test:e2e -- e2e/admin-block-types.spec.js` is green
- [x] 3.2 As the shared navigation (the sidebar) changes, run the full `npm run test:e2e`; check: all specs are green
- [x] 3.3 Create `docs/admin-block-types.md` (the routes, the differences, a reference to the kind mechanism in `docs/admin-page-types.md`), update `docs/admin-page-types.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md` (the new item) and the index `docs/README.md`; check: all relative links are valid

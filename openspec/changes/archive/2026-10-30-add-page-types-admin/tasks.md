# Tasks

## 1. Backend: the kind from the route

- [x] 1.1 Register the routes `/admin/page-types/**` (types and fields, names `admin.page-types.*`) alongside the existing ones with `defaults('kind', …)` and bind `contentType` by the kind from the route; check: `docker compose exec app php artisan route:list --name=page-types` shows all routes, and `--name=content-types` is unchanged
- [x] 1.2 Generalize `ContentTypeController` (list, creation, editing, deletion, `parentOptions`, redirect names) and `ContentFieldController` by the kind, with JSDoc/PHPDoc in English and `@example`; check: the existing `tests/Feature/Admin/ContentType*Test.php` and `ContentField*Test.php` pass without changes
- [x] 1.3 Generalize `StoreContentTypeRequest::sharedRules()` and `UpdateContentTypeRequest` (a parent of the same kind, `slug` only for `content`); check: a Feature test rejects a parent of another kind and ignores `slug`/`kind` for `page`
- [x] 1.4 Feature tests for `page` in `tests/Feature/Admin/PageType*Test.php` (the list only `page`, guest, creation with the kind `page`, editing, deletion with nodes/children, 404 for `content`/`block` and vice versa, fields: list, creation, deletion of `title`, order); check: `docker compose exec app php artisan test --filter=PageType` passes
- [x] 1.5 Pint for the changed PHP files; check: `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks

## 2. Frontend: shared pages with `basePath`

- [x] 2.1 Pass `basePath` and `kind` from the controllers into the Inertia props and replace the hard-coded paths `/admin/content-types` in `ContentTypeForm`, `ContentTypeRowActions`, `ContentTypeNav`, `ContentFieldForm`, `ContentFieldRowActions`, `Pages/Admin/ContentTypes/*`, `Pages/Admin/ContentFields/*`; check: `grep -rn "/admin/content-types" resources/js` finds no paths outside `navigation.ts`, `npm run build` without type errors
- [x] 2.2 Hide the field and the slug column for `kind !== 'content'` and substitute the headers/dialogs "Page Types", "Create page type", "Delete page type" by the kind; add the keys to `lang/uk.json` and `lang/en.json`; check: in the browser `/admin/page-types` has no slug column, the deletion dialog names "page type"
- [x] 2.3 Add the item "Page Types" to `navigation.ts` and generalize `patternTitles`/`extraTitles` for `page-types`; check: the sidebar leads to `/admin/page-types`, the breadcrumbs are correct on create/edit/fields

## 3. E2E and documentation

- [x] 3.1 Add `e2e/admin-page-types.spec.js` (the list, creation, editing, the section menu with `aria-current`, fields: creation/deletion/order, deletion of a type, the sidebar item) and make sure the existing e2e for `content` pass; check: `npm run test:e2e` on the host is green
- [x] 3.2 Create `docs/admin-page-types.md` (routes, differences from `content`, the mechanism of the kind from the route, components with `basePath`), update `docs/admin-content-types.md`, `docs/admin-content-fields.md` (generalization by kind), `docs/admin-shell.md` (the new item) and the index `docs/README.md`; check: all relative links are valid

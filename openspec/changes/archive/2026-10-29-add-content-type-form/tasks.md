# Tasks

## 1. Model: type slug

- [x] 1.1 Add the migration `add_slug_to_content_types_table` (`string('slug', 190)->nullable()->unique()` after `name`, `down()` removes the index and the column); check `docker compose exec app php artisan migrate` and `migrate:rollback --step=1` without errors
- [x] 1.2 In `App\Models\ContentType` add `slug` to `#[Fillable]`, `@property`, the constant `SLUG_PATTERN`; in `assertValid()` — the slug rules (nullable, format, `max:190`, uniqueness excluding itself, a ban for `kind !== 'content'`); add `descendantKeys()` (the keys of all descendants) with PHPDoc in English and `@example`
- [x] 1.3 Update `ContentTypeFactory` (`slug = null` by default), so that the existing tests remain green
- [x] 1.4 Add tests to `tests/Feature/Content/ContentTypeTest.php`: a type with a slug, without a slug, a duplicate (including another case), an invalid format, a slug for `page` and `block`, changing and clearing the slug keep `key`/`kind`, `descendantKeys()` on a tree of several levels; `docker compose exec app php artisan test --filter=ContentTypeTest` passes
- [x] 1.5 Update `docs/content-model.md` (the section on the type slug: format, uniqueness, `content` only, empty = no public list, `descendantKeys()`) and check the links; `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks

## 2. List for content only, description under the name

- [x] 2.1 In `ContentTypeController::index` apply `ofKind('content')`, remove `kind` from the rows and from `columns()`, add the column `slug` (text, sortable) and the service `children_count` into the rows; make all columns non-`filterable`; keep the search over `name`, `description`, `slug`, `key` (`description` — a server column without a client counterpart); update the PHPDoc example
- [x] 2.2 Update `tests/Feature/Admin/ContentTypeIndexTest.php`: only `content`, the `slug` column, `children_count`, search by name, description, slug and key (not by parent type), any filter in the address (`kind`, `nodes_count`, `slug`) is ignored; remove the checks of the kind column/filter, filters by the counter and the separate description column; `php artisan test --filter=ContentTypeIndexTest` passes
- [x] 2.3 In `Pages/Admin/ContentTypes/Index.tsx` remove `kindLabels`, the `kind` and `description` columns and all `filterable`, add the `slug` column, give the `name` column a `cell` with the description on a second line (`text-xs text-muted-foreground`, `line-clamp-2`, `title` with the full text; an empty description — no line), update the type `ContentTypeRow`; `npm run build` passes
- [x] 2.4 Add the optional prop `filterable?: boolean` (default `false`) to `DataTableBaseProps` with JSDoc in English and `@example`; `DataTableToolbar` renders `DataTableFilters` only with it; pass `filterable` to all existing tables (both in `DataTableDemo`); `npm run build` and `npm run test:e2e -- admin-data-table` pass without changes to the existing tests, and on `/admin/content-types` there is no filter button
- [x] 2.5 Update the sections "Columns" and "Data source" in `docs/admin-content-types.md` (`content` only, description under the name, search by name/description/slug/key, no filters) and `docs/admin-data-table.md` (the table prop `filterable`, disabled by default; an example); check the links

## 3. Creating a type

- [x] 3.1 Add `App\Http\Requests\Admin\StoreContentTypeRequest` (rules per design.md, item 6; `kind` is not accepted) with the shared rules for Store/Update and PHPDoc in English with `@example`
- [x] 3.2 Add `ContentTypeController::create` (passes `parentOptions` — the `content` types by name) and `store` (`ContentType::create([... , 'kind' => 'content'])`, a redirect to the list with `success`); register `admin.content-types.create` and `admin.content-types.store` in the group `auth`+`verified`; check `docker compose exec app php artisan route:list --name=content-types`
- [x] 3.3 Feature tests `tests/Feature/Admin/ContentTypeCreateTest.php`: the guest redirect (GET and POST), the form props and the composition of `parentOptions` (without `page`/`block`), successful creation (kind `content`, the system field `title`, flash), minimal filling, required fields, a taken/invalid key, a taken/invalid slug, a parent type of another kind and a non-existent one, `kind` in the request is ignored; `php artisan test --filter=ContentTypeCreateTest` passes
- [x] 3.4 Create the shared `Components/Admin/ContentTypes/ContentTypeForm.tsx` (`useForm`, modes `create`/`edit`, fields name/key/slug/parent type/description, errors next to the fields, "Save" with `disabled` during submission, a "Cancel" link) and `Pages/Admin/ContentTypes/Create.tsx`, with JSDoc in English and `@example`; `npm run build` passes
- [x] 3.5 In `Index.tsx` make "Create" an active link (`Button asChild` + `Link`); in `navigation.ts` add the title for `/admin/content-types/create` ("Create content type"); check the navigation and the breadcrumbs in the browser
- [x] 3.6 Add the translation keys (fields, hints about the key and slug format, "Save", "Cancel", "Create content type", "Content type created.", "No parent type") to `lang/uk.json` and `lang/en.json` in alphabetical order; check the form in Ukrainian and English
- [x] 3.7 Add the section "Creation" to `docs/admin-content-types.md` (routes, fields, rules, `kind` is always `content`); check the links

## 4. Editing a type

- [x] 4.1 Add `UpdateContentTypeRequest` (without `key`/`kind`; `slug` with `ignore($type)`; `parent_type` is not equal to itself and not from `descendantKeys()`) with PHPDoc in English
- [x] 4.2 Add to the controller `edit` (404 for a non-existent key and for types that are not `content`; `parentOptions` without itself and its descendants) and `update` (only `name`, `slug`, `parent_type`, `description`; a redirect to the list with `success`); register `admin.content-types.edit` / `.update` with the binding by `key`; check `route:list`
- [x] 4.3 Feature tests `tests/Feature/Admin/ContentTypeEditTest.php`: the guest, the form props and `parentOptions` without itself and its descendants, 404 for a non-existent and for `page`/`block`, a successful change, one's own slug does not conflict, the slug of another type, clearing the slug, `key`/`kind` in the request are ignored, a cycle in the parent type; `php artisan test --filter=ContentTypeEditTest` passes
- [x] 4.4 Create `Pages/Admin/ContentTypes/Edit.tsx` on `ContentTypeForm` (the key — `readOnly` with a hint, `form.put`); in `navigation.ts`/`getBreadcrumbs` ensure the breadcrumb for `/admin/content-types/{key}/edit` (extend the function and its test if needed); `npm run build` passes
- [x] 4.5 In `ContentTypeRowActions` pass `row`, make "Edit" a link to `edit` (leave the "Fields" item `disabled`); add the translations (`Edit content type`, `The key cannot be changed.`, `Content type updated.`) to both `lang/*.json`; check in the browser
- [x] 4.6 Add the section "Editing" to `docs/admin-content-types.md` (the routes, what can be changed, the exclusion of descendants); check the links

## 5. Deleting a type

- [x] 5.1 Add the shadcn `alert-dialog` (`Components/ui/alert-dialog.tsx` in the style of the existing ui components, on `radix-ui`); `npm run build` passes
- [x] 5.2 Add `ContentTypeController::destroy` (404 for non-`content`; a ban when there are nodes or child types → `back()->with('error', ...)` without changes; otherwise `delete()` and a redirect to the list with `success`); register `admin.content-types.destroy`; check `route:list`
- [x] 5.3 Feature tests `tests/Feature/Admin/ContentTypeDestroyTest.php`: the guest, a successful deletion (the type and its fields are gone), a type with nodes, a type with child types, 404 for `page`/`block` and a non-existent one; `php artisan test --filter=ContentTypeDestroyTest` passes
- [x] 5.4 In `ContentTypeRowActions` connect the deletion dialog (the state in the parent component; the reason for the ban from `nodes_count`/`children_count`; confirmation via `router.delete`); add the translations of the dialog, the reasons and the messages (`Content type deleted.`, `Cannot delete a content type that has nodes.`, `Cannot delete a content type that has child types.`) to both `lang/*.json`; check in the browser
- [x] 5.5 Add the section "Deleting" to `docs/admin-content-types.md` (the ban rules, the dialog, the cascade of fields) and update the section on the row actions (instead of "skeleton"); check the links

## 6. E2E and integration check

- [x] 6.1 Update `e2e/admin-content-types.spec.js`: remove the checks of the kind and description columns, the filters and the inactive "Create"/"Edit"/"Delete"; add the description under the name, the absence of the filter button, search by name/description/slug/key, creation with a slug (the type appears in the list), validation errors (an empty form, a duplicate key/slug), editing (the key is readOnly, changing the name and slug), deletion with the dialog (cancel, success, the ban for a type with nodes and children), uk/en, a narrow screen; `npm run test:e2e -- admin-content-types` passes
- [x] 6.2 The full `npm run test:e2e` and `docker compose exec app php artisan test` pass; `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks; update `docs/e2e-testing.md` if needed
- [x] 6.3 `openspec validate add-content-type-form` without errors

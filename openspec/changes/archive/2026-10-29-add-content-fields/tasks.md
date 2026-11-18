# Tasks

## 1. Backend: routes, controller, validation

- [x] 1.1 Add to `routes/web.php` the routes `admin.content-types.fields.{index,create,store,edit,update,destroy}` under `content-types/{contentType}` and `PUT …/fields/order` (declared before `{field}`) and `Route::bind('field', …)` (the pair type + key, 404 for a foreign/missing key); check `docker compose exec app php artisan route:list --path=fields`
- [x] 1.2 Create `StoreContentFieldRequest` (with a public static `sharedRules()`), `UpdateContentFieldRequest` (without `key`, `is_system` is not accepted) and `ReorderContentFieldsRequest` (`order`: an array of unique strings; in the `after` hook the set of keys equals the set of keys of the type's fields) with PHPDoc in English and `@example`; shared rules: `name`, `type` (`Rule::in` of the registry keys), three `boolean`, `weight` (`integer`, 0–65535)
- [x] 1.3 Create `App\Http\Controllers\Admin\ContentFieldController`: `index` (rows with `has_values`, grouped queries per value table), `create` (`typeOptions`, `defaultWeight`), `store`, `edit`, `update`, `destroy` (a system field or a field with `has_values` → `back()` with `error`; otherwise deletion and `success`), `reorder` (in a transaction `weight = index`, redirect back); English PHPDoc with `@example` on the class and on non-trivial methods
- [x] 1.4 Add the server messages ("Field created.", "Field updated.", "Field deleted.", "A system field cannot be deleted.", "Cannot delete a field that has values.", "Field order updated.") to `lang/uk.json` and `lang/en.json`
- [x] 1.5 Pest tests in `tests/Feature/Admin/ContentField{Index,Create,Edit,Destroy}Test.php`: guest/unverified; the list gives fields by `weight`; 404 for `page`/`block` and a foreign field key; creation (success, duplicate key, key format, unregistered type, weight out of range, `is_system` ignored); editing (changing name/weight, key ignored, the type of a field with values rejected, the type without values changed, forbidden changes of `title`); deletion (ordinary; a field with values rejected and the values remain; `title` rejected); order (weights become 0…n-1, an incomplete set / a repeat / a foreign key rejected, 404 for `page`); `docker compose exec app php artisan test --filter=ContentField` passes
- [x] 1.6 Create `docs/admin-content-fields.md` (routes, the `index` response, validation rules, the limits of the system field and of fields with values) in Ukrainian with cross-references to [docs/content-model.md](../../../docs/content-model.md) and [docs/content-values.md](../../../docs/content-values.md); add a line to `docs/README.md`; `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks

## 2. Frontend: the list of fields

- [x] 2.1 Create `resources/js/Pages/Admin/ContentFields/Index.tsx`: the title with the type name, a link back to the types, `DataTable mode="client"` (without `filterable`) with the columns from design.md (item 4), a "Create" button → `/fields/create`; JSDoc in English
- [x] 2.2 Create `Components/Admin/ContentFields/ContentFieldRowActions.tsx` (the "Edit"/"Delete" menu; the delete dialog, which for `has_values` shows the reason without a confirm button; "Delete" is inactive for a system field) modelled on `ContentTypeRowActions`; JSDoc with `@example`
- [x] 2.3 Activate the "Fields" item in `ContentTypeRowActions.tsx` (a `Link` to `/admin/content-types/{key}/fields`), update its JSDoc
- [x] 2.4 Add the title and breadcrumb templates for `…/fields`, `…/fields/create`, `…/fields/*/edit` in `navigation.ts` (and update its JSDoc/`@example`); add the UI translations to `lang/uk.json` and `lang/en.json`
- [x] 2.5 Update `docs/admin-content-fields.md` (the list page, columns, menu) and `docs/admin-content-types.md` (the "Fields" item is active) with cross-references; `npm run build` without errors

## 3. Dragging rows (`reorder` in DataTable)

- [x] 3.1 Add the dependencies `@dnd-kit/core` and `@dnd-kit/sortable` on the host (`npm install`), check `npm run build`
- [x] 3.2 Add the optional prop `reorder` with `onReorder(orderedIds)` to `DataTable` (the types `ClientProps` in `types.ts`/`DataTable.tsx`); with it the table body is wrapped in `DndContext`/`SortableContext` (mouse and keyboard), `DataTableRow` becomes sortable and renders the handle through `rowHandle`; the handles are inactive with a hint when a search/sorting is active or `meta.total > perPage`; without the prop the markup does not change. JSDoc in English with `@example`
- [x] 3.3 Connect `reorder` to the client demo table (`DataTableDemo`) for verification; add e2e checks to `e2e/admin-data-table.spec.js` (dragging, the keyboard, inactive handles on search/sorting, no handles without the prop)
- [x] 3.4 Update `docs/admin-data-table.md` (the `reorder` prop, the `rowHandle` slot, the activity conditions, accessibility) and check the links

## 4. Frontend: create and edit forms

- [x] 4.1 Add the shadcn `Checkbox` to `Components/ui`, if it is not there yet
- [x] 4.2 Create `Components/Admin/ContentFields/ContentFieldForm.tsx` (modes `create`/`edit`, blocking the key, the type for a system field/field with values, "multiple"/"required" for a system field) and the pages `Create.tsx`, `Edit.tsx` (a hint about dragging next to the weight); JSDoc with `@example`
- [x] 4.3 Update `docs/admin-content-fields.md` (the form, blocked fields) and `docs/content-model.md` (a link to the admin page of fields); check the validity of the links in `docs/README.md`

## 5. Field order on the page

- [x] 5.1 In `Pages/Admin/ContentFields/Index.tsx` connect `reorder`: a local copy of `fields`, an optimistic update, `router.put('/admin/content-types/{key}/fields/order', { order }, { preserveScroll: true, only: ['fields'] })`, a rollback and a toast on `errors.order`
- [x] 5.2 Update `docs/admin-content-fields.md` (the order: conditions, weights 0…n-1, rollback on error)

## 6. Browser check

- [x] 6.1 Add `e2e/admin-content-fields.spec.js` (data preparation via tinker, as in `admin-content-types.spec.js`): opening from the type's menu, the absence of the filter button, client search, creation, editing, deletion with the dialog, a field with values (the dialog without confirmation), `title` without deletion, dragging with persistence after a reload; `npm run test:e2e -- admin-content-fields` passes
- [x] 6.2 Run the existing tests (`docker compose exec app php artisan test`, `npm run test:e2e`) and Pint; update `docs/e2e-testing.md` if new limits appeared

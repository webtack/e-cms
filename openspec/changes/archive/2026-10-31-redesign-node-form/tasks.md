# Tasks

## 1. Schema, model and author

- [x] 1.1 Add the migration of `content_nodes`: `published_at` (datetime), `is_active` (boolean, default true), `user_id` (FK to `users`, `restrictOnDelete`), index `(content_type, published_at)`; backfill `published_at = created_at` and `user_id` with the administrator from `ADMIN_EMAIL` (as in `DatabaseSeeder`), an explicit error for a non-empty table without an administrator, skip of the backfill for an empty one; after the backfill the columns `published_at` and `user_id` become NOT NULL. Check: `docker compose exec app php artisan migrate` and `migrate:rollback` pass on a DB with data and on an empty one; on a DB with records but without an administrator the migration fails with an explanation; PHPDoc/JSDoc in English.
- [x] 1.2 Update `App\Models\ContentNode`: cast `published_at` (datetime), `is_active` (boolean), `published_at` in `#[Fillable]`, `user_id` — not mass-assignable from HTTP, relation `author()` (`BelongsTo User`), `scopeVisible()` and `findBySlug($slug, bool $onlyVisible = false)`; PHPDoc with `@example`. Check: `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks.
- [x] 1.3 Update `ContentNodeFactory` (`user_id => User::factory()`, `published_at => now()`, `is_active => true`) and `ContentNodeCreator::create()` (parameters `?int $authorId`, `?CarbonInterface $publishedAt`, `bool $isActive`; the author by default `auth()->id()`, without an author — `LogicException`; an empty date → `now()`). Check: the updated existing tests that create records pass (`docker compose exec app php artisan test --compact`).
- [x] 1.4 Add the model tests (`tests/Feature/Content/ContentNodeTest.php` and new ones): default values, the author from the current user, the error without an author, the prohibition of deleting the author, `published_at` does not change on disabling, the backfill migration (with an administrator and without). Check: the tests are green.
- [x] 1.5 Document the service attributes in `docs/content-model.md` (columns, rules, migration, `ADMIN_EMAIL`), with a link to `docs/admin-content-nodes.md`. Check: the links in the docs open, `docs/README.md` is valid.

- [x] 1.6 Reserve the field keys: `ContentField::RESERVED_KEYS = ['uuid', 'slug', 'status', 'published_at']`; the rule `not_in` in `StoreContentFieldRequest` (the message about the reserved key, translation in `lang/uk.json` and `lang/en.json`) and the check in `ContentField::assertValid()` only for new fields (`! $this->exists`), so that existing fields do not break on update. Check: the tests `ContentFieldTest`/`ContentFieldStoreTest` — creation with each of the four keys is rejected, `views` passes, updating the name of an existing field with the key `status` passes, `TranslationParityTest` is green; before the migration run a query on `content_fields.key` in `docker compose exec app php artisan tinker` and record the result in the report.
- [x] 1.7 Describe the reservation in `docs/content-model.md` and `docs/admin-content-fields.md` (the list, the reason, the behaviour for existing fields, how to add a new key). Check: the list in the docs matches the constant.

## 2. Visibility in the selection

- [x] 2.1 Add `onlyVisible` in `NodeQueryParams` and apply `scopeVisible()` in `ContentNodeQuery` to the list and the counter; the admin behaves as before. Check: the tests in `tests/Feature/Content/ContentNodeQueryTest.php` — published, deferred, disabled, the combination with filters/search/sorting, without the mode everything is visible.
- [x] 2.2 Tests of `findBySlug(..., onlyVisible: true)` for a disabled and a deferred record. Check: the tests are green.
- [x] 2.3 Describe the visibility in `docs/content-querying.md` (the parameter, the formula, an example). Check: the example in the docs runs in `docker compose exec app php artisan tinker`.

## 3. Admin: saving and passing the data

- [x] 3.1 Update `StoreContentNodeRequest`/`UpdateContentNodeRequest`: the rules `is_active` (boolean), `published_at` (`date_format:Y-m-d\TH:i`; `nullable` in store, `required` in update), without `user_id`. Check: the request tests (an incorrect date, a cleared date in update, an ignored `user_id`).
- [x] 3.2 Update `ContentNodeController`: pass in `create`/`edit` an object `node` with `slug`, `is_active`, `published_at`, `updated_at`, `author`; in `store` — the service attributes and the author from `auth()`; in `update` — `is_active`, `published_at`, `touch()` after writing the values and the file metadata, `user_id` does not change; `forForm()` does not add the prefix `values.` for service errors; `listSettings()` takes the list of reserved keys from `ContentField::RESERVED_KEYS` (see 1.6), and the controller's own constant is removed. Check: Feature tests `ContentNodeWriteTest` — deferred publication, disabling keeps the date, the author does not change in `PUT`, the author substitution is ignored in `POST`, `updated_at` changes when only a value changes.
- [x] 3.3 Add to the table of records the columns `status` (the derived state) and `published_at` (sorting by date, secondarily by `id`) in `row()`/`tableColumns()`/`index()`. Check: `ContentNodeIndexTest` — three states, sorting by date, the columns next to `settings.list.visible`, a field of a type with the key `status` or `published_at` (created before the reservation) does not duplicate the service column.
- [x] 3.4 Update `docs/admin-content-nodes.md`: the service fields, the page props, the saving rules, the table columns. Check: the sections and the links match the code.

## 4. Frontend: layout and the service panel

- [x] 4.1 Add the hook `useMediaQuery` (or generalize `use-mobile`) and the function `nodeStatus()` with a testable format `Y-m-d\TH:i`; English JSDoc with `@example`. Check: `npm run build` without type errors.
- [x] 4.2 Create the component `NodeServicePanel` (the switch «Показувати на сайті», the badge, the publication date, the slug, «Оновлено», «Автор»; errors inside) and add the types in `types.ts`. Check: the component renders in the form, `npm run build` without errors.
- [x] 4.3 Rework `ContentNodeForm`: the two-row grid 70/30 from `lg`, a sticky `aside`, the service fields in `useForm` (`is_active`, `published_at`), remove the slug fieldset from the main column, one render of the panel (aside on `lg`, `Sheet` below), the sticky bottom action bar with the buttons «Службова інформація» (the badge and the error dot) and «Зберегти», `pb-24` on narrow screens. Update `Create.tsx`/`Edit.tsx` for the new props. Check: `npm run build`, manual check at 1280 px and 390 px.
- [x] 4.4 The state and publication date columns in `Pages/Admin/ContentNodes/Index.tsx` (the badge, sorting). Check: the list page shows the badges and sorts by date.
- [x] 4.5 Add the translations in `lang/uk.json` and `lang/en.json`. Check: `docker compose exec app php artisan test --compact --filter=TranslationParityTest`.
- [x] 4.6 Update the e2e `e2e/admin-content-nodes.spec.js`: the two-row layout on the desktop, the mobile panel (the button, opening, values survive closing, the error indicator of the slug, the absence of a duplicate `#slug`), creation with a deferred date and the badge «Заплановано», disabling, the creation date is absent; adapt the existing slug locators to the new placement. Run only `npm run test:e2e -- e2e/admin-content-nodes.spec.js` (if needed — `e2e/admin-data-table.spec.js`, if the shared table was touched); in `docs/e2e-testing.md` update the tables «Область → спек» and «Спільний код → спеки», if new consumers appeared. Check: the specs pass, the report names the specs run and skipped.
- [x] 4.7 Update `docs/admin-content-nodes.md` with a section on the layout (desktop/mobile, the components `NodeServicePanel`, `useMediaQuery`, `nodeStatus`) and the rationale. Check: the description matches the implementation; `vendor/bin/pint --dirty --format agent` without remarks.
- [x] 4.8 Fix the overlap of the service column by the `youtube` field: add `min-w-0` to `FRAME` in `ContentNodeForm.tsx` (the `fieldset` expands because of unbreakable content). Add to `e2e/admin-content-nodes.spec.js` a check that the right border of the YouTube card does not exceed the left border of the `aside` at 1280 px; run only `npm run test:e2e -- e2e/admin-content-nodes.spec.js`; update the section on the layout in `docs/admin-content-nodes.md`. Check: the spec is green, `npm run build` without errors.

## 5. Shared sticky action bar of forms

- [x] 5.1 Create the component `FormActionBar` in `resources/js/Components/Admin/` (sticky `bottom-0`, `-mx-4 md:-mx-6`, safe-area, «Скасувати» + «Зберегти», slot `children`); English JSDoc with `@example`. Check: `npm run build` without errors.
- [x] 5.2 Move `ContentNodeForm` to `FormActionBar`: remove the buttons from the flow and `hidden lg:inline-flex`, pass «Службова інформація» into the slot only on `< lg`, the bar outside the grid of columns. Check: manual check at 1280 px and 390 px.
- [x] 5.3 Apply `FormActionBar` in `ContentTypeForm` and `ContentFieldForm`. Check: the create/edit forms of the type and the field have a sticky bar.
- [x] 5.4 Update the e2e `admin-content-nodes`, `admin-content-types`, `admin-content-fields`: the bar is visible without scrolling on a long form, «Скасувати» returns to the list, «Зберегти» works. Run only these specs.
- [x] 5.5 Update `docs/admin-content-nodes.md` and, if needed, the tables in `docs/e2e-testing.md` («Спільний код → спеки» for `FormActionBar`).
- [x] 5.6 Final check: `npm run build`, `openspec validate redesign-node-form`.

## 6. Rework after review

- [x] 6.1 `ContentNodeForm`: make the parameters button in the `FormActionBar` slot an icon without text and without the status badge, with `aria-label`/`title` «Службова інформація»; keep the error indicator. Check: `npm run build`, manual check at 390 px.
- [x] 6.2 `ContentTypeForm` and `ContentFieldForm`: wrap the inner `div` with `max-w-xl` in `rounded-md border` (with inner padding); the action bar stays outside the frame. Check: `npm run build`.
- [x] 6.3 Add a small indent between the content of the forms (record, type, field) and the `FormActionBar`. Check: the form does not stick to the bar after scrolling to the end.
- [x] 6.4 Update the e2e `admin-content-nodes`, `admin-content-types`, `admin-content-fields`: find the parameters button by its accessible name, remove the badge checks on the button. Run only these three specs.
- [x] 6.5 Update `docs/admin-content-nodes.md` and `docs/admin-shell.md` (the icon button, the frame of the forms of the type and the field, the indent).
- [x] 6.6 Final check: `npm run build`, `openspec validate redesign-node-form`.

## 7. Rework after review (2)

- [x] 7.1 Rename «Службова інформація» to «Деталі» (en: «Details», the same key `Details` as in the media library; its Ukrainian translation changes from «Докладніше» to «Деталі», update the e2e `admin-media` and `docs/media-library.md`): `NodeServicePanel`, `ContentNodeForm`, `lang/uk.json`, `lang/en.json` (remove the key `Service information`). Check: `TranslationParityTest`, `npm run build`.
- [x] 7.2 A multiple field `image`/`file` at the limit does not show the upload zone (there is an explanation about the limit); after removing an item the zone returns; the list of rejected files is output by `NodeFieldList` (the widget passes it through `onFailures`), so that it survives the disappearance of the zone. Check: the code of `NodeFieldList` matches the requirement «Ліміт елементів множинного поля у формі запису».
- [x] 7.3 Update the e2e `admin-content-nodes`: find the button by its accessible name «Details», the test of the image limit (`galleryInput` at the limit is absent, not `disabled`; after removal it returns). Run only this spec.
- [x] 7.4 Update `docs/admin-content-nodes.md`, `docs/admin-shell.md` (the name of the block) and `docs/file-storage.md` (the zone hides at the limit).
- [x] 7.5 Final check: `npm run build`, `openspec validate redesign-node-form`.

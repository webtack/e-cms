# Design

## Context

The motivation and the boundaries are in `proposal.md`. The current state (read in the code):

- The tab «Розділ» («Section») — `GET /content-types/{key}/section` (`ContentTypeController::sectionTab()`, the page `Section.tsx`), registered separately from the loop of kinds in `routes/web.php` and only for `content`. The form of the tab — `TypeValuesPanels` (the panel `section`), saves `PUT /content-types/{key}/values` through `ContentTypeValuesController` → `ContentValueWriter`, the errors come as `values.{key}`.
- «Публічне відображення» («Public display») — `PublicDisplaySettingsSection` in `ContentTypeForm`, the data `publicOptions` come from `edit()`, saved together with the form through `ContentTypeController::update()` (`mergeSettings()` → `mergePublicSettings()`), the rules `settings.public.*` are in `StoreContentTypeRequest::sharedRules()`, the keys are checked by `ContentType::assertValidPublicSettings()` on `save`.
- The menu — `ContentTypeNav` (the items `settings | section | seo | fields | nodes`, `section` only for `content`).

The documentation that describes these components: `docs/admin-content-types.md` (the menu, the routes, «Публічне відображення у формі»), `docs/admin-page-types.md`, `docs/admin-block-types.md`, `docs/content-model.md`, `docs/content-querying.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- The tab «Відображення» («Display») for `content`, `page`, `block` with a single saving of the values of the section and `settings.public`.
- A settings form without `settings.public`.

**Non-Goals:**
- Changing the format of `settings.public`, the default values, the restrictions by kind and the validation of keys.
- Changing the tab «SEO» and the panel `section` as a system panel of fields (`SystemFields::panel()` keeps the identifier `section` — this is an internal key, not a label).
- Public routes and templates.

## Decisions

**1. The routes `display` — in the loop of kinds.** `GET /{section}/{key}/display` (`admin.{section}.display`) and `PUT /{section}/{key}/display` (`admin.{section}.display.update`) are added to `$typeRoutes`, so the kind and `kind` are taken from the usual mechanism (`ResolvesTypeSection`, `Route::bind` within the kind). The separate route `/content-types/{key}/section` is removed without a redirect (the admin is closed, there are no bookmarks to it; the 404 is recorded in the specification). *Alternative:* keep `/section` for `content` and add `/display` only for `page`/`block` — rejected, because it splits one tab into two addresses.

**2. A single saving — a separate `PUT …/display`, not an extension of `PUT …/values`.** The new `ContentTypeDisplayController::update` accepts `values` (only `content`; for `page`/`block` the key is ignored, because they have no section fields) and `settings.public`. The request `UpdateContentTypeDisplayRequest` checks the form of `values` (as `UpdateContentTypeValuesRequest`) and the rules `settings.public.*`, which move from `StoreContentTypeRequest::sharedRules()`. `ContentTypeValuesController` remains for «SEO». *Alternative:* add `settings.public` to `PUT …/values` — rejected: that endpoint serves SEO, and mixing breaks its contract «saves only the fields of its tab», and the route `values` exists only for `content`.

**3. Atomicity.** The controller performs the writing of the section values (`ContentValueWriter::write`, which throws `ValidationException` with keys without prefix, which the controller renames to `values.{key}`) and `ContentType::update(['settings' => …])` in a `DB::transaction`. An error in any step rolls back both, so «nothing is saved» holds also when `assertValidPublicSettings()` throws an exception after the values have been written.

**4. Moving the merge logic.** `mergePublicSettings()`, `replaceSetting()` (shared with `list`) and `PublicDisplaySettings::formOptions()` remain as they are in content; `mergePublicSettings` moves to the code of the new controller (or to `PublicDisplaySettings`, if it reduces duplication — decided in the implementation); `ContentTypeController::update()` no longer touches `settings.public`, `edit()` does not give `publicOptions`. `settings` in `UpdateContentTypeRequest` is narrowed to `array:list`, so `settings.public` in the edit request gives a validation error (a scenario in the specification).

**5. Frontend.** `Section.tsx` → `Display.tsx`; the new form of the tab `TypeDisplayForm` has one `useForm` (`values` + `settings.public`), renders `NodeFieldsPanel name="section"` (for `content`, the heading «Section»), `PublicDisplaySettingsSection` and one `FormActionBar`. The page receives `publicOptions` and (for `content`) `typeFields`/`typeValues`/`typeFiles` from the existing `typeValuesProps($type, 'section')`. `TypeValuesPanels` after this serves only «SEO»: the parameter `panel` is narrowed to `'seo'`, and the shared parts (the state of files, `NodeFieldFrame`) are moved out if needed, so as not to duplicate the code. `ContentTypeNav`: `active` gets `'display'` instead of `'section'`, the item «Відображення» is shown for all kinds. The block of values of the section inside the tab keeps the heading «Розділ»/«Section» and the existing `data-testid="section-panel"`; for the new block the caption of the tab is the translation key `Display` («Відображення»). The shared `useForm` receives the errors as `values.{key}` and `settings.public.*`; the errors of `settings.public` are shown in the same way as in `ContentTypeForm` now.

**6. Moving the UI state.** `ContentTypeForm` removes `publicOptions`, `public` from the state and `transform`, the helpers `isPublicError`/`publicErrors`; `Edit.tsx` does not accept `publicOptions`. In the mode `create` there are no changes.

## Risks / Trade-offs

- [Broken tests and e2e that open `/section` or look for the public section in the form] → they are updated in the same groups of tasks as the code; the list is in `tasks.md`.
- [A user expects a public section in the settings form] → recorded in the specification, the settings form is shorter on purpose.
- [A partial saving on a failure in the middle of a request] → a transaction (decision 3).
- [`TypeValuesPanels` after narrowing remains with one consumer] → acceptable; shared parts are moved out only if they are needed in `TypeDisplayForm`.

## Migration Plan

No DB migrations; `settings.public` and the values `section_*` are stored as they are. The deployment is ordinary; the rollback is the return of the code. The bookmarks to `/admin/content-types/{key}/section` stop working (404).

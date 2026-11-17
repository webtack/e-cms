# Tasks

## 1. Domain: saving one language, translated languages, labels

- [x] 1.1 Add the `TranslationSaver` service (node and type owners) with the rules of the `content-values` delta. Verify with `docker compose exec app php artisan test --compact --filter=TranslationSaver` covering every scenario (equal-to-base, emptied field, required not enforced, non-translatable key, invalid language, atomicity, multiple list as a whole, composed values); English docblocks with `@example`.
- [x] 1.2 Add the `TranslatedLanguages` service (one `UNION ALL` query per page, `NOT EXISTS` filter, type variant). Verify with `--filter=TranslatedLanguages` including a query-count assertion for a page of nodes.
- [x] 1.3 Add `settings.labels` support: the model accessor and writer for a label of one language, `labels` accepted for every type in `StoreContentFieldRequest` and carried over by `ContentFieldController`, `ContentTypeCloner` copying it, and a language usage checker for labels (registered in the usage registry from `content-languages-admin`) tested with `pt-BR`. Verify with `--filter=ContentField` and `--filter=ContentTypeClone`, including a `term` field with values.
- [x] 1.4 Update `docs/content-values.md` (saving one language, translated definition) and `docs/content-model.md` (translated labels). Verify that the documented examples run.

## 2. Backend: nodes

- [x] 2.1 Node controllers (`content`, `page`, `block` sections): `edit` accepts `?lang=` through the shared concern and returns prefilled translatable values and the `translation` prop; add `PUT .../nodes/{node}/translations/{lang}` and `DELETE` under the existing node access checks; touch `updated_at` on save. Verify with `--filter=ContentNodeTranslation` (all scenarios of the `admin-content-nodes` delta, including the access level of another user's node).
- [x] 2.2 List payload: the Translations column and the `untranslated` filter parameter, present only when languages are active. Verify with `--filter=ContentNodeIndex` (column, filter, unchanged payload on a single-language site, constant query count).
- [x] 2.3 Update `docs/admin-content-nodes.md` and `docs/admin-translation-mode.md` (nodes as a consumer). Verify that routes and prop names match the code.

## 3. Backend: type values and field labels

- [x] 3.1 Type values: `?lang=` on the Display and SEO tabs and `PUT/DELETE .../values/translations/{lang}` under the type configuration right; none for block types. Verify with `--filter=ContentTypeValuesTranslation`.
- [x] 3.2 Field labels: `?lang=` on the field edit page and `PUT/DELETE .../fields/{field}/translations/{lang}`. Verify with `--filter=ContentFieldTranslation` (all scenarios of the `admin-content-fields` delta).
- [x] 3.3 Update `docs/admin-content-types.md` and `docs/admin-content-fields.md`. Verify the documented routes against `php artisan route:list`.

## 4. Frontend

- [x] 4.1 `ContentNodeForm` in the translation mode with the same layout as the base form (non-translatable fields and the details panel shown inert, widgets unchanged, dirty tracking for richtext, lists, files, composed values); node list column and filter. Verify with `npm run build` and the new translation tests of `e2e/admin-content-nodes.spec.js` run with `-g "translation"`; JSDoc with `@example` for new components.
- [x] 4.2 Type tabs (Display, SEO) and the field form in the translation mode. Verify with `e2e/admin-content-types.spec.js`, `e2e/admin-content-fields.spec.js` and `e2e/admin-page-types.spec.js` (translation tests added, existing tests of changed pages re-run with `-g`).
- [x] 4.3 Update `docs/e2e-testing.md` (new tests, shared-code table rows for the new consumers of the shell). Verify the tables against the specs.

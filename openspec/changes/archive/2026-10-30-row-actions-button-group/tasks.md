# Tasks

## 1. The ButtonGroup primitive

- [x] 1.1 Add `resources/js/Components/ui/button-group.tsx` in the style of the existing shadcn components, with an English JSDoc and `@example`. Check: `npm run build` on the host without type errors.

## 2. Content types

- [x] 2.1 In `ContentTypeRowActions.tsx` replace the single trigger with a `ButtonGroup`: the button «Налаштування» (a `Link` to `/admin/content-types/{key}/edit`) + the trigger «⋮» with the unchanged menu; update the JSDoc. Check: in the browser on `http://localhost:8080/admin/content-types` the group is visible, the menu contains «Редагувати», «Поля», «Видалити».
- [x] 2.2 Update `e2e/admin-content-types.spec.js`: a scenario of the transition through the main button «Налаштування»; the existing scenarios with «Actions» must remain green. Check: `npm run test:e2e -- admin-content-types`.
- [x] 2.3 Update `docs/admin-content-types.md` (the description of the row actions and of the component, in Ukrainian). Check: the text matches the new behaviour, the links in `docs/README.md` are valid.

## 3. Content fields

- [x] 3.1 In `ContentFieldRowActions.tsx` do the same: the button «Редагувати» (a `Link` to `{base}/edit`) + the trigger «⋮» with the unchanged menu; the button is active also for a system field; update the JSDoc. Check: on the page of fields of a type the group is visible, for `title` «Видалити» in the menu is inactive, and the button «Редагувати» is active.
- [x] 3.2 Update `e2e/admin-content-fields.spec.js`: the transition through the main button, including for `title`. Check: `npm run test:e2e -- admin-content-fields`.
- [x] 3.3 Update `docs/admin-content-fields.md` (the section «Меню рядка») and the mention of the row menu in `docs/admin-data-table.md`. Check: the texts match the behaviour, the links are valid.

## 4. Integration

- [x] 4.1 A full run of `npm run test:e2e` and `docker compose exec app vendor/bin/pint --dirty --format agent`. Check: everything is green; if the e2e fail because of the locale `uk`, act according to the rule from the memory.

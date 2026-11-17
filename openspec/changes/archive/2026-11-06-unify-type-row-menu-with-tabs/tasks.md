# Tasks

## 1. Shared item list and components

- [x] 1.1 Add `resources/js/Components/Admin/ContentTypes/typeSectionItems.ts` exporting `typeSectionItems(kind)` (ordered `settings`, `display`, `seo`, `fields`, `nodes`; `seo` dropped for `block`; `menuLabel: 'Edit'` for `settings`; lucide icons). Add JSDoc in English with an `@example`. Verify: `npx tsc --noEmit` (on the host) reports no errors for the module.
- [x] 1.2 Make `ContentTypeNav.tsx` build its tabs from `typeSectionItems(section.kind)`, keeping the `types` permission filter, `aria-current` and styling unchanged. Update its JSDoc. Verify: `npm run build` succeeds and `npm run test:e2e -- e2e/admin-page-types.spec.js` still passes the tab scenarios.
- [x] 1.3 Make `ContentTypeRowActions.tsx` render the menu from `typeSectionItems(section.kind)` (label `menuLabel ?? label`, link, icon), then the separator and "Delete" with its dialog unchanged. Update its JSDoc. Verify: `npm run build` succeeds; opening the menu on `/admin/content-types`, `/admin/page-types`, `/admin/block-types` shows the items of the specs (no "SEO" for blocks).

## 2. e2e tests

- [x] 2.1 Update the exact menu assertions: `e2e/admin-content-types.spec.js` (Ukrainian: `Редагувати`, `Відображення`, `SEO`, `Поля`, `Записи`, `Видалити`), `e2e/admin-page-types.spec.js` (`Edit`, `Display`, `SEO`, `Fields`, `Nodes`, `Delete`), `e2e/admin-block-types.spec.js` (`Edit`, `Display`, `Fields`, `Nodes`, `Delete`). Verify: the three specs pass (`npm run test:e2e -- e2e/<spec>.spec.js`, one per spec, containers running).
- [x] 2.2 Add a scenario to `e2e/admin-content-types.spec.js` and `e2e/admin-page-types.spec.js` that selects "Display" and "SEO" in the row menu and checks the URLs `…/display` and `…/seo` and the active tab; in `e2e/admin-block-types.spec.js` check "Display" and the absence of "SEO". Verify: the three specs pass. Also run `admin-content-fields` and `admin-content-nodes`, because `ContentTypeNav` is rendered on their pages and the "Forms and row menus of types and fields" row of `docs/e2e-testing.md` lists them; no other specs and no PHP tests are needed (no PHP changes).

## 3. Documentation

- [x] 3.1 Update `docs/admin-content-types.md`: the "Row actions and the "Create" button" section (menu items and order, the shared list) and the "Type section menu" section (tabs come from `typeSectionItems`). Verify: the text matches the delta specs and all relative links resolve.
- [x] 3.2 Update `docs/admin-page-types.md` (the Frontend note about the row menu: "Display" and "SEO" items, no "SEO" for blocks) and check `docs/admin-block-types.md`/`docs/admin-data-table.md` mentions of the row menu for stale item lists. Verify: `grep -rn '"Edit", "Fields"' docs` returns no stale menu description.
- [x] 3.3 Update `docs/e2e-testing.md` only if a new e2e dependency appears (the spec files stay the same, so expected: no change). Verify: the "area → spec" and "shared code → specs" rows still match the specs run in group 2.

# Tasks

## 1. Menu component

- [x] 1.1 Create `ContentTypeNav` (the items "Settings"/"Fields", `aria-current` on the active one, a link back to the list of types) with JSDoc in English and `@example`; check: `npm run build` without type errors
- [x] 1.2 Add the row "Settings" to `lang/uk.json` and `lang/en.json` (if missing); check: switching the language shows the translation of the menu

## 2. Connection to the pages

- [x] 2.1 Use `ContentTypeNav` in `ContentTypes/Edit.tsx` (active=settings) and `ContentFields/Index.tsx` (active=fields, remove its own link "Content types"); check: the pages open in the browser with the menu
- [x] 2.2 Use `ContentTypeNav` in `ContentFields/Create.tsx` and `Edit.tsx` (active=fields); check: the menu is visible, "Fields" is highlighted; `ContentTypes/Create.tsx` unchanged
- [x] 2.3 Add e2e to `e2e/admin-content-types.spec.js` and `e2e/admin-content-fields.spec.js` (the menu on all four pages, `aria-current`, switching, no menu on the creation of a type); check: `npm run test:e2e` passes
- [x] 2.4 Update `docs/admin-content-types.md` and `docs/admin-content-fields.md` with a description of the menu and the component; check: the links in `docs/README.md` are valid

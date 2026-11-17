# Tasks

## 1. Reproduce and fix

- [x] 1.1 Add a desktop test (1280 px, narrowed content area or a column with a long value, sidebar expanded and collapsed) to `e2e/admin-data-table.spec.js`; run it first and confirm it fails (page width exceeds the window or the table container does not overflow), which verifies the cause described in design.md. Verify: `npm run test:e2e -- e2e/admin-data-table.spec.js -g "desktop"` fails before the fix.
- [x] 1.2 Add `min-w-0` to the shell content area (`SidebarInset` via `AdminLayout.tsx`, adding it to the inner `<main>` only if needed), with JSDoc/comment in English explaining why. Verify: the test from 1.1 passes, `document.documentElement.scrollWidth <= clientWidth`, and the `table-container` has `scrollWidth > clientWidth`.
- [x] 1.3 Verify a table that fits shows no scrollbar and the existing 375 px test still passes. Verify: `npm run test:e2e -- e2e/admin-data-table.spec.js`.

## 2. Docs and regression

- [x] 2.1 Update `docs/admin-data-table.md` ("Narrow screens" → any width), the "responsiveness" bullet in `docs/e2e-testing.md` (add the desktop check), and `docs/admin-shell.md` if it describes the content area. Verify: the docs match the new behavior and links remain valid.
- [x] 2.2 Run the full `npm run test:e2e` (the change touches the shell layout) and report the result; fix any regression on wide non-table content. Verify: the full run passes.

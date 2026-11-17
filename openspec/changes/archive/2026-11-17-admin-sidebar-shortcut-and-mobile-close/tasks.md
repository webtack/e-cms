## 1. Sidebar behavior

- [x] 1.1 In `resources/js/Components/ui/sidebar.tsx` remove the Ctrl/Cmd+B `keydown` listener and the `SIDEBAR_KEYBOARD_SHORTCUT` constant; keep `toggleSidebar` for the trigger. Verification: JSDoc/comments in English; Ctrl+B no longer changes the sidebar.
- [x] 1.2 Close the mobile drawer on every Inertia navigation (`router.on('navigate')` or an effect on `usePage().url` calling `setOpenMobile(false)`). Verification: JSDoc/comment in English.
- [x] 1.3 Show the close button in the mobile drawer (remove `[&>button]:hidden`) and make sure it does not overlap the branding block in `AppSidebar.tsx` at 375 px.

## 2. Tests and docs

- [x] 2.1 Add e2e scenarios to `e2e/admin-shell.spec.js`: Ctrl+B does not change the sidebar state (desktop); on a 375 px viewport the drawer closes with its close button and after clicking a sidebar link. Run `npm run test:e2e -- e2e/admin-shell.spec.js` (the sidebar changed; the project rule requires the full `npm run test:e2e` for shell/sidebar changes, so run it once here).
- [x] 2.2 Optionally add a check that Ctrl+B inside the rich text editor applies bold, in `e2e/admin-content-nodes.spec.js` with `-g` for the editor tests only.
- [x] 2.3 Update `docs/admin-shell.md` (sidebar section: no shortcut, mobile drawer closes on navigation and has a close button) and the admin-shell description in `docs/e2e-testing.md`; keep `docs/README.md` links valid.

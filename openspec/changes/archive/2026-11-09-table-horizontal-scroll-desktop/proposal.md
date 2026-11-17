# Proposal

## Why

The admin data table scrolls horizontally inside its container only on narrow (mobile) screens. On a desktop window where the table does not fit, the table is not scrolled: the content area stretches to the table's width instead, so the table is cut off or the whole page scrolls sideways. The table must scroll inside its own container at any window width whenever it does not fit.

## What Changes

- The admin content area (the shell's inset next to the sidebar) is allowed to shrink below the width of its content, so a wide table overflows its own scroll container instead of widening the page. This applies at every window width, with the sidebar expanded or collapsed.
- The "Accessibility and adaptivity" requirement of the data table is generalized from "on narrow screens" to "whenever the table does not fit its container, at any screen width".
- A desktop-width e2e check is added next to the existing 375 px check.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-data-table`: the requirement "Accessibility and adaptivity" — the horizontal scroll inside the container applies at any window width (not only narrow screens), and the page never gets a horizontal scroll because of the table.

## Impact

- Code: `resources/js/Layouts/AdminLayout.tsx` (and/or `resources/js/Components/ui/sidebar.tsx`, `SidebarInset`) — the content area gets `min-w-0`. The change touches the admin shell, so every page with a table is affected visually (no behavior change).
- E2E: `e2e/admin-data-table.spec.js` gets a desktop-width test; because the shell/sidebar layout changes, the full `npm run test:e2e` run applies (see `docs/e2e-testing.md`, "When to run e2e").
- Docs: `docs/admin-data-table.md` ("Narrow screens" item), `docs/e2e-testing.md` (the "responsiveness" bullet of the data table scenario), and the shell description in `docs/admin-shell.md` if it describes the content area layout.

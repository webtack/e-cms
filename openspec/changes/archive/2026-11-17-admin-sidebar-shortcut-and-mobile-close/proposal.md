# Proposal

## Why

The admin sidebar listens for Ctrl/Cmd+B on `window` (a leftover of the shadcn template) and calls `preventDefault()`, so pressing it inside the rich text editor toggles the sidebar instead of applying bold. On mobile the drawer stays open after the user follows a link, and it has no visible close button, because the built-in one is hidden.

## What Changes

- Remove the Ctrl/Cmd+B keyboard shortcut of the sidebar; the sidebar is toggled only by the header button.
- Close the mobile drawer automatically after every client-side navigation.
- Show a close button inside the mobile drawer.
- The desktop expand/collapse behavior and the header toggle button are unchanged.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `admin-shell`: a new requirement that the sidebar has no keyboard shortcut; the "Mobile navigation" requirement gains auto-close on navigation and a close button.

## Impact

- Code: `resources/js/Components/ui/sidebar.tsx` (shortcut listener, mobile `SheetContent` close button, close on navigation), possibly `resources/js/Components/Admin/AppSidebar.tsx`.
- Tests: `e2e/admin-shell.spec.js` (new scenarios). No PHP changes.
- Docs: `docs/admin-shell.md` (sidebar section), `docs/e2e-testing.md` (admin-shell description).

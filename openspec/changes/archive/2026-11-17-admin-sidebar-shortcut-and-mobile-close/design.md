## Context

`resources/js/Components/ui/sidebar.tsx` is the shadcn `Sidebar` primitive copied into the project. `SidebarProvider` registers a `window` `keydown` listener for Ctrl/Cmd+B that calls `preventDefault()` and `toggleSidebar()`. On mobile `Sidebar` renders a `Sheet` driven by `openMobile`; its `SheetContent` hides the built-in close button with `[&>button]:hidden`. `AdminLayout` keeps `SidebarProvider` mounted across Inertia visits, so `openMobile` survives navigation. Components are described in `docs/admin-shell.md`.

## Goals / Non-Goals

**Goals:** no sidebar keyboard shortcut; mobile drawer closes after navigation and has a close button.
**Non-Goals:** changes to the desktop collapse behavior, the header trigger, the sidebar cookie, or the item set.

## Decisions

1. **Remove the shortcut entirely** (the `SIDEBAR_KEYBOARD_SHORTCUT` constant and the `useEffect`), rather than rebinding it: the requirement is that no hotkey toggles the sidebar.
2. **Close on navigation via Inertia's `router.on('navigate', ...)`** inside `SidebarProvider` (or an effect on `usePage().url`), calling `setOpenMobile(false)`. One place covers every link, the browser back button and programmatic visits; per-link `onClick` handlers would have to be repeated across `AppSidebar` link kinds. The effect only changes state when the drawer is open, so desktop is unaffected.
3. **Close button: reuse the built-in `SheetContent` button** by removing `[&>button]:hidden` from the mobile `SheetContent`. It is already translated (`Close`). Its absolute `top-4 right-4` position must not overlap the logo and site name block of `AppSidebar`; adjust the header padding if it does.

## Risks / Trade-offs

- The close button may overlap the sidebar branding on 18rem width → check visually at 375 px and add right padding to the header block if needed.
- `ui/sidebar.tsx` is a vendored file; the edits are small but diverge from upstream shadcn → keep them commented in English.

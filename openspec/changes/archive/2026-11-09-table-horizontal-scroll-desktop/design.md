# Design

## Context

See proposal.md - Why. Current state, read from the code:

- `components/ui/table.tsx` already wraps `<table>` in `data-slot="table-container"` with `overflow-x-auto`, and `DataTable` renders it inside `rounded-md border bg-card`.
- The shell (`Layouts/AdminLayout.tsx`) is `SidebarProvider` → `AppSidebar` + `SidebarInset` (a `<main class="relative flex w-full flex-1 flex-col ...">`) → `<main class="flex-1 p-4 md:p-6">`.
- `SidebarInset` is a flex item next to the sidebar spacer on desktop and has no `min-w-0`. A flex item's default `min-width: auto` is its content's min-content width, and the table cells are `whitespace-nowrap`, so a wide table makes the inset wider than the window; the table container never overflows because its parent has grown to fit it. On mobile the sidebar is an off-canvas sheet, so the inset is not constrained by a sibling and the existing behavior works.

This cause is derived from reading the code and is not yet reproduced in a browser; the first implementation task confirms it.

Documents describing the components: `docs/admin-data-table.md` (adaptivity), `docs/admin-shell.md` (shell layout), `docs/e2e-testing.md` (data table scenario).

## Goals / Non-Goals

**Goals:**
- A table that does not fit scrolls inside its container at any width, with the sidebar expanded or collapsed.
- No horizontal page scroll caused by tables.

**Non-Goals:**
- No sticky columns, no changes to column widths or `max-width` handling.
- No change to the table markup or to other wide content (forms, rich text editor).

## Decisions

- **Add `min-w-0` to the shell's content area** (`SidebarInset`, passed from `AdminLayout` or set in the `ui/sidebar.tsx` class list). This lets the flex item shrink to the free space so `overflow-x-auto` of the table container takes effect. Alternative considered: `overflow-x-hidden` on the inset — rejected, it would clip content (and sticky/fixed behavior) instead of fixing the sizing. Alternative: `max-w-full` on the table wrapper — rejected, the problem is the parent growing, not the wrapper.
- **Keep the scroll on the existing `table-container`** instead of adding a second wrapper in `DataTable`, so there is a single scroll area and the existing 375 px test keeps working.
- If the browser check shows `min-w-0` on the inset is not sufficient (e.g. the inner `<main class="flex-1 ...">` also needs it), add it there too; only the minimum needed is applied.

## Risks / Trade-offs

- Other wide non-table content in the shell would now overflow or wrap differently instead of widening the page → check the pages with forms and the node editor in the full e2e run.
- The change affects the shell for every admin page → run the full e2e suite as required by `docs/e2e-testing.md` for shell/sidebar changes.

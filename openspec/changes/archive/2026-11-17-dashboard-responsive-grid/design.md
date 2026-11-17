# Design

## Context

`Pages/Admin/Dashboard.tsx` renders the widgets in `<div className="grid gap-4 lg:grid-cols-2">`. Before `lg` the grid has one implicit auto-sized column. A grid track sized `auto` gets at least the `min-content` of its widest item, and a widget card (`DashboardWidget` > `FormSection`) is a grid item with `min-width: auto`, so wide content (a long node title, the counters table) can widen the track beyond the content area. The shell already protects the page from this with `min-w-0` on `SidebarInset` (`Layouts/AdminLayout.tsx`), but the grid inside does not. The `lg` breakpoint is a window breakpoint, so the sidebar state is ignored. Tailwind v4 is used (`@tailwindcss/vite`), so container query variants (`@container`, `@3xl:`) are available without a plugin. See proposal.md - Why. Components are described in `docs/admin-dashboard.md` (section "Frontend").

## Goals / Non-Goals

**Goals:**
- Widgets always fit the content area; wide content is shortened inside the card.
- Columns (1 or 2) follow the width of the content area.
- Widgets keep their order; widgets in a row have equal height.

**Non-Goals:**
- More than two columns, `col-span` for wide widgets, masonry layout.
- Changes to widget data, access rules, `COUNTERS_VISIBLE`, or the number of rows of the other widgets (only "Recent changes" and media latest change to 7).
- Responsive behavior of the header row ("Dashboard" and the Create menu); it is not part of the reported overflow and stays as is unless the reproduction shows it overflows too.

## Decisions

1. **Keep CSS grid.** Rows are read left to right and the widget order is meaningful. Alternatives: flex-wrap (columns do not align between rows), CSS `columns` (order reads by columns, a card can be split), masonry (no stable support, a dependency for at most eight cards).
2. **Columns by container width.** The grid sits inside a wrapper with `@container`; the grid uses `grid-cols-[minmax(0,1fr)] @3xl:grid-cols-2` (the container is 48rem or wider for two columns). Alternative: window breakpoints (`md:`/`lg:`), which ignore the sidebar; `auto-fill`/`minmax`, whose column count is not explicit and can leave gaps at in-between widths. The threshold `@3xl` is a starting value to confirm visually with the sidebar expanded and collapsed.
3. **`minmax(0,1fr)` columns and `min-w-0` on the card.** `minmax(0,1fr)` stops the track from growing to the `min-content` of its items; `min-w-0` on the card lets it shrink inside the track so that `truncate` rows and the `overflow-x-auto` table container work. Both are needed because each covers a different level (track and item). The two-column variant needs no explicit form: Tailwind's `grid-cols-2` is already `repeat(2, minmax(0, 1fr))`.
4. **Equal heights in a row.** The grid keeps the default `align-items: stretch`, so cards in a row get the height of the tallest (owner's decision after the first version used `items-start`). "Recent changes" and the media latest list grow from 5 to 7 rows (`DashboardData::WIDE_LIMIT`) to fill the space next to taller widgets.
5. **Reproduce first.** The cause (the auto track widened by a card) is a hypothesis from reading the code; the first task reproduces the overflow at about 400 px in the browser and confirms it before the fix, and checks whether the header row also overflows.

## Risks / Trade-offs

- [The reproduction shows another cause (for example, the sidebar or the header row)] → the first task confirms the cause; the grid change is harmless and the spec requirement (no horizontal overflow) stays the acceptance criterion.
- [A short card in a row gets empty space at the bottom] → accepted: equal heights were requested.
- [Container query threshold is wrong for some widths] → one constant to adjust; checked at both sidebar states.

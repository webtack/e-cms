# Proposal

## Why

On a narrow screen the dashboard widgets overflow the visible area: the widgets are wider than the header and the page title row, and the page scrolls horizontally. The grid is also tied to the browser window width (`lg:grid-cols-2`), not to the width of the content area, so the sidebar state (expanded or collapsed) is ignored when choosing the number of columns.

## What Changes

- Make the dashboard widget grid fit the available width at any screen width: the widgets never exceed the content area, and a long title is truncated instead of widening its card.
- Choose the number of columns by the width of the dashboard container (container queries), not by the window width: one column on a narrow container, two columns on a wider one. Two columns is the maximum.
- Keep the widgets in the current order (read by rows); widgets in one row have equal height.
- Show 7 rows (was 5) in "Recent changes" and in the latest uploads of "Media Library".
- No changes to widget props or access rules.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-dashboard`: adds a requirement for the responsive widget grid (fits the content width, one or two columns by container width, no horizontal overflow).

## Impact

- Code: `app/Dashboard/DashboardData.php` (`WIDE_LIMIT`); `resources/js/Pages/Admin/Dashboard.tsx` (grid wrapper and classes); possibly `resources/js/Components/Admin/Dashboard/DashboardWidget.tsx` (`min-w-0` on the card).
- Tests: one new case in `e2e/admin-dashboard.spec.js` (narrow and wide viewport); PHP: `DashboardDataTest` limits use `WIDE_LIMIT`; the "area → spec" row for the dashboard in `docs/e2e-testing.md` stays valid.
- Docs: `docs/admin-dashboard.md` (section "Frontend": the grid description "one column on narrow screens, two from `lg`" is replaced by the container-based rule).
- Dependencies: none (Tailwind v4 already supports container queries).

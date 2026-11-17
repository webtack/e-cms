# Tasks

## 1. Reproduce

- [x] 1.1 Open `/admin/dashboard` in a window about 400 px wide (Playwright or browser devtools) with a node with a very long title among the recent changes; confirm that the widgets are wider than the content area and the page scrolls horizontally, and note whether the header row ("Dashboard" and Create) overflows too. Verification: the cause is confirmed or the design's Decision 5 is revised before the fix.

## 2. Grid

- [x] 2.1 In `resources/js/Pages/Admin/Dashboard.tsx` wrap the grid in an `@container` element and set the grid classes to one `minmax(0,1fr)` column and two columns from the `@3xl` container width (default stretch, so widgets in a row have equal height); add `min-w-0` to the card in `resources/js/Components/Admin/Dashboard/DashboardWidget.tsx` if the reproduction needs it. Update the JSDoc of `Dashboard` (the grid description) in English. Verification: at about 400 px there is no horizontal scroll and a long title is shortened; with the sidebar expanded and collapsed the columns change by the container width, never more than two.

## 3. Equal height and longer lists

- [x] 3.0 Follow-up (owner's request): equal height in a row (no `items-start`); `DashboardData::WIDE_LIMIT` = 7 for "Recent changes" and the media latest list; PHP tests in `DashboardDataTest` use the new limit; `docs/admin-dashboard.md` updated.

## 4. Tests and docs

- [x] 3.1 Add a case to `e2e/admin-dashboard.spec.js`: at a 400 px viewport the document has no horizontal overflow (`scrollWidth <= clientWidth`) and two widgets are one under another; at a wide viewport two widgets share a row. Run `npm run test:e2e -- e2e/admin-dashboard.spec.js` and add the equal-height check.
- [x] 3.2 Update `docs/admin-dashboard.md`, section "Frontend": replace "one column on narrow screens, two from `lg`" with the container-based rule (one column on a narrow content area, two on a wide one, never more; no horizontal overflow). The row for the dashboard in `docs/e2e-testing.md` already points to `e2e/admin-dashboard.spec.js`; add the new check to the checks description of that spec if the document lists them.

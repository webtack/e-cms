## Why

`/admin/dashboard` is the page every user lands on after login, but it only shows a heading and the text "Welcome to the admin area." (`DashboardController` renders `Admin/Dashboard` with no data). Editors have to open the sidebar and a type list just to find their drafts, recent changes or scheduled publications. The data for a useful start page already exists (`content_nodes`, `media`, `user_favorite_types`), so no schema work is needed.

## What Changes

- The dashboard becomes a set of widgets, one layout for every role. Roles differ only through `AccessResolver`: a widget or a row the user has no right to is not computed and not shown.
- Group 1 (work widgets):
  - **Recent changes** — the last updated nodes (title, type, author, updated time, link to the edit form).
  - **My drafts** — the user's own nodes with `is_active = false`.
  - **Quick create** — a "+ Create" menu listing the types the user may create nodes of.
  - **Favorite types** — tiles for the user's favorite types with the node count.
- Group 2 (overview widgets):
  - **Counters by type** — total, published and draft nodes per type.
  - **Top by views** — the most viewed nodes (`content_nodes.views`).
  - **Scheduled and expired** — nodes with `published_at` in the future, and nodes whose `unpublished_at` has passed.
  - **Media** — file count, total size and the latest uploads (by the media level).
- The statistics are computed by a dedicated service, not in the controller.
- The placeholder text "Welcome to the admin area." is removed.
- Out of scope: charts or history (`views` is a plain counter), system and administrator widgets (users, languages, settings), new tables or migrations, a configurable or draggable layout.

## Capabilities

### New Capabilities

- `admin-dashboard`: the content and access rules of the `/admin/dashboard` page — which widgets exist, what each one shows, and how every widget is filtered by the user's rights.

### Modified Capabilities

None. The existing `admin-shell` requirement "Admin start page" (the page opens inside the shell for an authenticated user) stays valid as written; the new content rules live in `admin-dashboard`.

## Impact

- Code: `app/Http/Controllers/Admin/DashboardController.php`, a new service under `app/Dashboard/`, `resources/js/Pages/Admin/Dashboard.tsx` and new widget components under `resources/js/Components/Admin/Dashboard/`, translations in `lang/en.json` and `lang/uk.json`.
- Tests: new PHP Feature tests for the service and the endpoint (rights per level, counters), a new e2e spec `e2e/admin-dashboard.spec.js`.
- Docs: new `docs/admin-dashboard.md`; `docs/README.md` (index link), `docs/admin-shell.md` (the dashboard no longer a placeholder, link to the new page), `docs/e2e-testing.md` ("Area → spec" row for the new spec, and the "Shared code → specs" row for the shell/sidebar if the new components use shared ones).
- No database migrations, no new dependencies, no changes to routes.

# Design

## Context

- `DashboardController` is an invokable controller that renders `Admin/Dashboard` without props; the page is a heading plus placeholder text (`resources/js/Pages/Admin/Dashboard.tsx`). The route `admin.dashboard` is open to every active user (docs/access-control.md, "Helper endpoints").
- Access is decided by `App\Access\AccessResolver`: `nodeLevel($user, $type)` and `mediaLevel($user)` return `none` / `own` / `any`; the system role has `any` everywhere. `ContentNodePolicy::create` and `viewAny` both reduce to "level is not `none`"; `canEditNode()` is the shared edit rule used by the admin menu (docs/access-control.md).
- Reusable parts that already exist: `ContentNode` (`is_active`, `published_at`, `unpublished_at`, `views`, `user_id`, `scopeVisible()`, `status()`, `author()`), `App\Navigation\NodeTitles` (title in the current locale, then the base language, then the slug, for many nodes in one query), `SidebarNavigation::forUser()` (favorites already filtered by level), `File` (`size`, `original_name`, `user_id`, `uuid`; table `media`), and the per-kind section map used by the routes (`content-types`, `page-types`, `block-types`).
- `inertiajs/inertia-laravel` is `^3.5` and `@inertiajs/react` is `^3.9.1`; deferred props are therefore available, but no controller in `app/` uses them yet.
- See proposal.md for motivation and scope; the observable rules are in `specs/admin-dashboard/spec.md`.

## Goals / Non-Goals

**Goals:**
- One service that returns every widget's data for a user, with rights applied in one place, so the controller stays a thin wrapper and the rules can be tested without HTTP.
- No schema changes; use only indexed or bounded queries.

**Non-Goals:**
- Charts, history of views, caching layers, a configurable layout, system and administrator widgets.
- Changing `AccessResolver`, the policies or the sidebar.

## Decisions

**1. A service `App\Dashboard\DashboardData`, not controller code.** `DashboardController` calls `DashboardData::forUser($request->user())` and passes the array to Inertia. The service is in its own namespace next to `App\Access` and `App\Navigation`. Alternative — one class per widget: rejected for now; the widgets share the same access scoping, so one class with small private methods is simpler. If it grows, the scoping moves into a shared private helper, not into more public classes.

**2. One access-scoping helper for every node query.** The service computes once the list of type keys per level (`any` keys and `own` keys, from `ContentType::query()->get(['key','name','kind'])` and `nodeLevel`). A private method `scoped()` returns a `ContentNode` query constrained to `content_type IN (any keys) OR (content_type IN (own keys) AND user_id = :user)`. With no accessible keys it returns a query that matches nothing, and the node widgets are not queried at all. Every node widget (recent, top by views, scheduled, expired, counters, favorite counts) starts from `scoped()`, so a rule change happens in one place. Alternative — filter in PHP after the query: rejected, it breaks the `limit`.

**3. Definitions that match existing code.**
- Published = `scopeVisible()` (active, `published_at <= now`, not unpublished). Drafts = `is_active = false`. Total = all accessible nodes. So `total` may exceed `published + drafts`, because scheduled and expired nodes are active but neither; they are shown in the scheduled/expired widget.
- Scheduled = `is_active` and `published_at > now`, ordered by `published_at` ascending. Expired = `is_active` and `unpublished_at <= now`, ordered by `unpublished_at` descending.
- My drafts = `is_active = false` and `user_id = current user`, restricted to types still accessible (a draft of a type that became `none` is hidden).
- Counters come from one grouped query (`content_type`, conditional sums) over `scoped()`, then merged with the type list so empty types show zeros.

**4. Row shape and links.** Each node row carries `uuid`, `title`, `type` (`key`, `name`, `kind`), `author` (name), `updated_at` and an `edit_url`. `edit_url` is set only when `AccessResolver::canEditNode()` allows (so an `own` user never gets a link to someone else's node, matching the admin-menu rule); otherwise it is `null` and the row renders without a link. URLs are built on the server from the kind-to-section map, so the client holds no routing logic. Titles come from `NodeTitles::for($uuids)` for all widgets in one query. Authors are loaded with `with('author:id,name')` (or `avatar` if the existing author column component needs it — check `UserAvatar` usage).

**5. Quick create and favorites.** Quick-create types are the accessible types (`own` or `any`) with their `create` URL, grouped by kind. Favorite tiles reuse the `favorites` produced by `SidebarNavigation::forUser()` (already level-filtered, ordered by addition) and add a node count taken from the same grouped counters query (no extra query per tile).

**6. Media.** For `mediaLevel` `none` the key is `null` and the widget is omitted. Otherwise query `File` with `user_id = user` for `own`; compute `count`, `sum(size)` and the latest five (`original_name`, `size`, `created_at`, link to `admin.media.show` by `uuid`). The size is sent as bytes and formatted on the client; check whether a byte-formatting helper already exists in `resources/js` (the media list shows sizes) and reuse it.

**7. Plain props, not deferred props.** Every query is bounded (a fixed `limit` per list, one grouped query for counters, one aggregate for media) and uses existing indexes (`views`, `content_type + published_at`), so the page can render with ordinary props in one request. Deferred props would add a loading state, a second request and a new pattern nobody uses in the project, for no measured gain. Revisit only if a profile on real data (the legacy import) shows a slow query; the service API does not change in that case, only the controller wraps a method in `Inertia::defer`.

**8. Frontend structure.** `Pages/Admin/Dashboard.tsx` receives the props and lays out widgets; each widget is a small component in `Components/Admin/Dashboard/` (counters table, node list used by recent/drafts/top/scheduled/expired with a configurable right-hand column, favorites tiles, media card, quick-create dropdown). Layout is a responsive grid (one column on narrow screens), using the existing card/`FormSection`-style surfaces and visual tokens (docs/admin-visual-style.md). The "Create" menu uses the existing dropdown component. All labels go through `useTranslation` with entries added to `lang/en.json` and `lang/uk.json`; dates use the existing date formatting by the user's locale. Empty-state messages replace hidden widgets only when nothing at all is available (spec: "Widget without any access is hidden").

**9. Documentation.** The new page `docs/admin-dashboard.md` describes the service, the access scoping, the definitions of Section 3 and the widget props; `docs/admin-shell.md` stops calling the dashboard a placeholder and links to it; `docs/README.md` gets the index line; `docs/e2e-testing.md` gets the new spec row.

## Risks / Trade-offs

- [`total` is not `published + drafts`, which may look like a bug] → the column headings are explicit ("Total", "Published", "Drafts"), and docs/admin-dashboard.md states the definitions; scheduled and expired nodes are visible in their own widget.
- [Counting on a large `content_nodes` table (legacy import)] → one grouped query with indexed `content_type`; a profile on the imported data is a task, and deferring the heavy widget stays possible (Decision 7).
- [Rights drift: a widget query that forgets `scoped()` leaks nodes of a `none` type] → every node query is built from `scoped()`, and the Feature tests cover `none`, `own` and `any` for each widget.
- [Titles: a node without a `title` value] → `NodeTitles` already falls back to the slug.
- [Time zone and "now" in scheduled/expired tests] → the service takes an optional `$now` argument (default `now()`), so tests can fix the time, like `ContentNode::status()`.
- [Dashboard is the landing page, so a failing query breaks login flow] → the service is read-only; the controller test and the shell e2e (`admin-shell`, which opens the dashboard after login) cover it.

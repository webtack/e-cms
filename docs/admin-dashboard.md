# Dashboard in the admin panel

The start page `/admin/dashboard` (route `admin.dashboard`, open to every active user, see [access control](access-control.md)). It is one set of widgets for every role: roles differ only by the data inside the widgets, because every query is limited by the user's rights. A widget with nothing to show is hidden; when nothing at all is available, an empty-state message is shown.

## Service

`Webtack\Ecms\Dashboard\DashboardData::forUser(User $user, ?CarbonInterface $now = null)` returns the data of all widgets. `DashboardController` is a thin wrapper that passes the array to `Inertia::render('Admin/Dashboard', ...)` as props. `$now` is injectable so that tests can fix "published / scheduled / expired" (like `ContentNode::status()`).

### Access scoping

The service computes the accessible types once (`AccessResolver::nodeLevel()` is not `none`) and builds every node query from one private constraint:

- `any` type: all nodes of the type;
- `own` type: only the nodes whose `user_id` is the user;
- `none` type: never queried; with no accessible type, no node query is run at all.

Media follows `AccessResolver::mediaLevel()`: `none` → the `media` prop is `null`, `own` → only the user's uploads, `any` → all files. Row links follow the admin-menu rule: a row gets an `edit_url` only when `AccessResolver::canEditNode()` allows it, otherwise `edit_url` is `null` and the row has no link. See [access control](access-control.md).

### Definitions

| Term | Meaning |
| --- | --- |
| Published | `is_active`, `published_at <= now` and (`unpublished_at` is null or `> now`); the rule of `ContentNode::scopeVisible()`, evaluated at `$now` inside the grouped counters query |
| Drafts | `is_active = false` |
| Total | every accessible node of the type |
| Scheduled | `is_active` and `published_at > now`, nearest publication first |
| Expired | `is_active` and `unpublished_at <= now`, most recent expiry first |

`total` can exceed `published + drafts`: scheduled and expired nodes are active but neither, and are listed in their own widgets. Disabled nodes are in neither the scheduled nor the expired list.

## Widgets and props

| Widget | Prop | Content |
| --- | --- | --- |
| Recent changes | `recent` | up to 7 nodes (`DashboardData::WIDE_LIMIT`), `updated_at` descending |
| My drafts | `drafts` | up to 5 disabled nodes of the current user, `updated_at` descending |
| Quick create | `create`, `create_flat` | `create`: the types to list (`key`, `name`, `kind`, `url` of the creation form); `create_flat`: `false` by default, `true` when the menu is configured (see [Configured Create menu](#configured-create-menu)) |
| Favorite types | `favorites` | favorites of the sidebar (already limited to accessible types, in the order of adding) with `count` |
| Counters by type | `counters` | one row per accessible type: `total`, `published`, `drafts`, `href` to the node list; zeros for empty types |
| Top by views | `top` | up to 5 nodes with `views > 0`, descending |
| Scheduled / expired | `scheduled`, `expired` | up to 5 nodes each |
| Media | `media` | `null` or `{count, size, latest[]}`; `size` is in bytes, `latest` has up to 7 files (`WIDE_LIMIT`) (`uuid`, `name`, `size`, `created_at`, `url`) |

A node row has the same shape in every list: `uuid`, `title`, `type` (`key`, `name`, `kind`), `author` (name), `views`, `updated_at`, `published_at`, `unpublished_at` (ISO 8601 strings) and `edit_url`. Titles come from `Webtack\Ecms\Navigation\NodeTitles` (current locale, then base language, then the slug) in one query for all lists. URLs are built on the server from `TypeSections::segment()`, so the client holds no routing logic.

## Why plain props

Every query is bounded: a fixed limit per list (`DashboardData::LIMIT`, 5; `WIDE_LIMIT`, 7, for recent changes and media), one grouped query for the counters, one aggregate for media; they use existing indexes. The page renders in one request, so deferred props (a loading state, a second request, a pattern unused elsewhere in the project) bring no gain. If a profile on real data (the legacy import) shows a slow query, only the controller changes: it wraps that part of the array in `Inertia::defer`.

## Frontend

`Pages/Admin/Dashboard.tsx` receives the props above (type `DashboardProps` in `Components/Admin/Dashboard/types.ts`), lays the widgets out in a grid (one column when the content area is narrow, two from the `@3xl` container width, never more; the width of the content area decides, not the window, so the sidebar state counts; the columns are `minmax(0,1fr)` and the cards `min-w-0`, so a long title is shortened inside its card and the page has no horizontal overflow; widgets in one row have the same height) and renders a widget only when its data is not empty. When every list is empty and `media` is `null`, it shows the empty-state message (`data-testid="dashboard-empty"`) instead of the grid. The "Create" menu sits in the page header and is rendered only when `create` is not empty.

| Component (`Components/Admin/Dashboard/`) | Widget |
| --- | --- |
| `DashboardWidget` | The card (`FormSection` with a heading) every widget sits in; `data-testid="dashboard-{id}"` |
| `NodeListWidget` | `recent`, `drafts`, `top`, `scheduled`, `expired`; the `column` prop picks the right-hand value (`updated_at`, `published_at`, `unpublished_at`, `views`); a title is a link only when `edit_url` is set |
| `CountersWidget` | `counters` (table: type, total, published, drafts); shows the first 7 rows (`COUNTERS_VISIBLE`) and, when there are more types, a "Show all (N)" / "Show fewer" toggle (`data-testid="dashboard-counters-toggle"`) |
| `FavoritesWidget` | `favorites` (tiles with the count) |
| `MediaWidget` | `media` (count, total size, latest uploads) |
| `QuickCreateMenu` | `create`, `create_flat` (dropdown grouped by kind, or a flat list when `create_flat` is true) |

Sizes and dates are formatted by `lib/format.ts` (`formatSize`, `formatDateTime` with the interface locale from `useTranslation()`).

### Configured Create menu

By default (`dashboard.create_types` not set in [app settings](app-settings.md)) `create` holds every type on which the user's level is `own` or `any`, ordered by kind (content, page, block) then name, `create_flat` is `false` and `QuickCreateMenu` groups the items by kind.

When the administrator configures the menu on the [menu settings page](admin-navigation-menu.md#create-menu), `create` holds only the configured types in the stored order and `create_flat` is `true`; `QuickCreateMenu` then renders one flat list without kind headings. A configured type the user has no access to (level `none`) or that no longer exists is skipped. When no configured type remains, `create` is empty and the "Create" menu is hidden, even though the user may create nodes of other types. Clearing the list in the settings restores the default behavior.

### Translation keys

All strings go through `t()` with English keys in `lang/en.json` and `lang/uk.json`: `Dashboard`, `Recent changes`, `My drafts`, `Favorites`, `Nodes by type`, `Show all (:count)`, `Show fewer`, `Total`, `Published`, `Drafts`, `Most viewed`, `Scheduled`, `Expired`, `Media Library`, `files`, `No files yet.`, `by :name`, `Create`, `Content`, `Page`, `Block`, `Type` and the empty-state message `You have no access to any content yet. Ask an administrator to grant it.`

## Tests

PHP: `tests/Feature/Dashboard/DashboardDataTest.php` (rights per level, ordering, numbers with a fixed `$now`, media levels) and `tests/Feature/Admin/DashboardTest.php` (the endpoint and its props). e2e: `e2e/admin-dashboard.spec.js`.

## Related

- [Favorites](admin-favorites.md) — the source of the favorite tiles.
- [Access control by roles](access-control.md) — levels `none` / `own` / `any` and `canEditNode()`.
- [Admin shell](admin-shell.md) — the layout the page is rendered in.

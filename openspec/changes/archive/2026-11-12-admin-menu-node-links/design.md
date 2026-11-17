# Design

## Context

`admin_menu_entries` (model `AdminMenuEntry`) stores a type link (`type_key`, unique FK to `content_types.key`) or a group (`type_key` null, `title` json); `isGroup()` is `type_key === null`. `AdminMenu` keeps the write rules, `SidebarNavigation::menu()` builds the sidebar tree and filters by `AccessResolver::nodeLevel`. The sidebar computes the active item by URL prefix, and a type link already "owns" the nodes URLs of its type. Node edit access is `ContentNodePolicy::update` (`any`, or `own` and `node.user_id`). `ReferenceLookupController::nodes` already searches nodes by default-language title within a type. Documented in `docs/admin-navigation-menu.md`, `docs/access-control.md`, `docs/admin-shell.md`.

## Goals / Non-Goals

**Goals:**
- Add node links with the least change to the current table, rules and UI.
- No N+1 queries on the sidebar, which is built on every Inertia response.

**Non-Goals:**
- Nodes in "Favorites"; per-language custom labels; links to anything other than types and nodes.

## Decisions

1. **New nullable column `node_uuid`** on `admin_menu_entries`, unique, FK to `content_nodes.uuid` with `cascadeOnDelete`. `content_nodes.uuid` is already unique. The node is addressed by `uuid`, which is immutable and is what the routes use. Alternatives: a `kind` discriminator column (extra column and backfill for no gain); a polymorphic `target_type/target_id` (loses FK cascade); a separate table (breaks the single ordered tree and the `order` save).
2. **Kind detection** in the model: `isNodeLink()` (`node_uuid !== null`), `isTypeLink()` (`type_key !== null`), and `isGroup()` becomes "both null". A check constraint is not added (the validation in `AdminMenu` keeps one of the three).
3. **`AdminMenu::addNodeLink(string $uuid, ?AdminMenuEntry $group)`** mirrors `addLink`: the node must exist (error on `node_uuid`), not be in the menu yet, the parent must be a root group. `save()` already accepts any non-group entry as a child; it only needs `isGroup()` to stay correct. `POST …/links` accepts either `type_key` or `node_uuid` (exactly one).
4. **Label is not stored.** `SidebarNavigation` loads, in one query for all node links, the node (`uuid`, `content_type`, `user_id`) and the title from `content_values_string` (`title`, current locale then `default`). Alternative: store a snapshot title (goes stale on rename).
5. **Access is computed in batch**: for each node, `any` on its type is allowed, `own` is allowed when `user_id` equals the user id, otherwise hidden. This mirrors `ContentNodePolicy::owns` without calling the policy per row; the shared rule stays in one place by moving the level-to-allowed decision into a small method on `AccessResolver` or the policy that both use (decided during implementation, covered by tests that compare with the policy).
6. **Href** is `/admin/{section}/{type}/nodes/{uuid}/edit`, with the section from `TypeSections::segment(kind)`. The sidebar item carries `{ type: 'link', node: true, … }`; the icon is a node icon distinct from type icons.
7. **Active item**: the node link uses an exact path match; `AppSidebar` treats a type link as active only when no node link of the menu matches the URL. The existing "owned by a link" rule for the section item then covers node links as well.
8. **Settings page UI**: next to the type picker, a second control "Add node": a type select, then `RefAutocomplete` over `/admin/lookup/nodes`. `NavigationMenuController::index` returns node rows with the node title and type name; a node row in the tree is drawn like a link row with the node icon. Titles in the tree are read in one query, same as in the sidebar.
9. **Not-visible nodes** (inactive, scheduled, expired) are still shown to users who may edit them; the menu is an admin tool.

## Risks / Trade-offs

- Sidebar cost grows with the number of node links → one batched query for nodes and titles, no per-row policy calls.
- Node author changes silently show or hide a link → intended, same filter-at-read model as type access.
- A node with no title value in the current or base language → fall back to the node `slug`, so the link is never empty.
- The lookup endpoint is open to all verified admins and returns titles of nodes the settings editor may not edit → acceptable: the page already needs the `settings` right and the endpoint exists for reference fields.

## Migration Plan

One additive migration (nullable column, unique index, FK). Existing rows are unaffected. Rollback drops the FK and the column; node links are lost, type links and groups stay.

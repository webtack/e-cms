# Access control by roles

Each user has exactly one role (`users.role_id`); the role determines which admin sections the user sees and which records the user may change. The server is always the arbiter: the client only hides unavailable elements. Management pages: [users](admin-users.md), [roles](admin-roles.md).

## Schema

| Table / column | Purpose |
|---|---|
| `roles` | `id`, `name` (unique), `is_system`. |
| `role_permissions` | `role_id` (FK, `cascadeOnDelete`), `scope`, `level`, unique pair (`role_id`, `scope`). A missing row = `none`. |
| `users.role_id` | FK `restrictOnDelete`, nullable: a user without a role has no rights in the CMS. |
| `users.is_active` | Defaults to `true`; a deactivated user cannot log in ([authentication](admin-auth.md#deactivated-user)). |

The migration `2026_11_03_100000_create_roles_and_permissions` creates the role `admin` (`is_system = true`) and leaves `role_id` nullable. It never assigns a role to existing users: accounts of the host application that existed before the CMS was installed stay roleless until the installer ([package installer](package-installer.md)) or an administrator gives them a role. The rollback removes the columns and tables together with the role data.

## Scopes (`scope`) and levels (`level`)

| `scope` | Levels | What it grants |
|---|---|---|
| `users`, `roles`, `menus`, `settings`, `taxonomy`, `types` | `allow` | Full access to the section (view, create, change, delete). `types` — creating and configuring types and fields of all three kinds. `settings` opens the [site settings](admin-site-settings.md) page `/admin/settings` (view and save; without the right — 403). |
| `node:{type_key}` | `own`, `any` | Nodes of a type (all kinds `content`, `page`, `block`). `own` — creating, viewing the whole list, changing and deleting only own nodes (by `content_nodes.user_id`); `any` — any nodes. |
| `media` | `own`, `any` | Media library; the file owner is the user who uploaded it (`media.user_id`). A file without an owner is accessible only with `any`. |

There is no separate "publish" right: `is_active`, `published_at`, and `unpublished_at` are changed with the same right as the node.

## Users without a role

A user whose `role_id` is `null` has no rights. `AccessResolver` returns the empty permission set for such a user without querying the database: every system permission is denied, every scope (`node:*`, `media`) is `none`, `canEditAnyContent()` is false and `forClient()` works as for any role. Nobody gets a role automatically. As the *target* of an action, a roleless account counts as non-system for `UserPolicy` (there are no privileges to protect), so an administrator, or a holder of `users`, can open, edit, delete it and assign it a role. The users list shows it with the "No role" label.

## System role `admin`

It has no rows in `role_permissions`: `AccessResolver` treats `is_system` as "everything allowed, level `any` everywhere", so new types are available immediately. The role cannot be changed or deleted. New types for the other roles have `none`.

## Host user model

The package never names a user class: it takes `auth.providers.users.model`, which must implement `Webtack\Ecms\Contracts\EcmsUser` (normally by using `HasEcmsAccess`, [package-structure.md](package-structure.md#the-user-model-contract)). `AccessResolver`, the policies, `LastAdminGuard` and the `Gate` closures type their parameter as `EcmsUser`. The provider binds `UserPolicy` with `Gate::policy(UserModel::class(), UserPolicy::class)` and the route parameter `{user}` with `Route::model('user', UserModel::class())`, because the controllers and `UpdateUserRequest` type `{user}` as the interface, which implicit binding cannot resolve. A host user model that does not implement the contract fails with a type error on the first admin request.

## Service and checks

- `Webtack\Ecms\Access\AccessResolver` (singleton): `allows($user, 'menus')`, `level($user, $scope)`, `nodeLevel($user, $typeOrKey)`, `mediaLevel($user)`, `canEditAnyContent($user)` (has `own`/`any` on an existing type or on media), `forClient($user)` (rights for the client), `flush()`. The rights of a role are loaded once and cached in memory; `flush()` is called by changes to role rights. Rows `node:{key}` without a corresponding type are ignored.
- A `Gate` for each system right (`can:menus` in routes, `Gate::allows('users')`), defined by the package provider.
- `Webtack\Ecms\Policies\ContentNodePolicy`: `viewAny(User, ContentType)`, `create(User, ContentType)`, `update`/`delete(User, ContentNode)`.
- `Webtack\Ecms\Policies\MediaPolicy` (for `File`): `viewAny`, `update`, `delete`.
- `Webtack\Ecms\Policies\UserPolicy` (for `User`): `manage(User, User)` (open, edit, deactivate, delete) and `assignRole(User, Role)`; both refuse a non-administrator for the system role `admin`. They use `AccessResolver::isAdministrator($user)` (the cached `system` flag of the user's role). Details — [users](admin-users.md#administrator-accounts).
- Denial — 403.
- **Binding order (known compromise).** Route model binding (`{contentType}`, `{field}`, `{node}`, `{vocabulary}`, `{term}`, `{menu}`, `{item}`, `{entry}`, `{language}`, `{user}`, `{role}`, `{file}`) runs before the `can:*` middleware. A user without the right therefore gets 404 for a missing record and 403 for an existing one, so such a user can probe whether a type key, vocabulary key, menu key, language code, user id or role id exists. The response for a user who has the right is unchanged, and the data of the record is never returned. It is accepted instead of changing the global middleware order.

## Media and files in nodes

Editing a node does not grant control over arbitrary library files. A file that is new in a node's values (any `image`/`file` field, YouTube screenshot, or rich-text image, in any language) must be one the user uploaded or one their `media` level lets them change (`MediaPolicy::update`); otherwise the save is refused and nothing is stored. Files already in the node stay valid for every user who may edit it, and that right includes changing their name and description. An administrator is unrestricted. The same rule of thumb holds for the media details: a place of use the viewer may not open (a node of a type with level `none`, an owner behind a system right they lack) shows only as "Used elsewhere" ([media-library.md](media-library.md#owner-registry)). Details: [admin-content-nodes.md](admin-content-nodes.md#image-and-file-fields).

## Last administrator protection

`Webtack\Ecms\Access\LastAdminGuard` does not allow the system to be left without an active user of the role `admin` (deactivation, deletion, role change; the check and the change are in one transaction with `lockForUpdate`). Details — [users](admin-users.md#last-administrator-protection).

## Access to nodes without the `types` right

The page listing types (`index` of each of the three sections) is an exception to `can:types`: a user with `own`/`any` on a type of this kind sees only such types and the "Nodes" button (`ContentTypeController::index`, prop `canManage`); the remaining type and field routes stay under `types`. Without available types — 403.

## Helper endpoints

`dashboard`, `profile` (the page and its three saves: data, avatar, password; they act only on the signed-in user, see [profile](admin-profile.md)), and `lookup/*` are open to every active user. `files.store` and `youtube/lookup` require `canEditAnyContent()`.

**Accepted: `lookup/*` shows titles of unpublished nodes.** `lookup/nodes` returns the `uuid` and title of every node of the requested type, drafts and unpublished included, and `lookup/content-types` returns the name and key of every type of a kind, to every active user (`ReferenceLookupController`; both are in the `RouteCoverageTest` allow-list as open to everyone). This is accepted: reference fields need the lookups across types, and the staff are trusted. Revisit when a role for contributors who must not see unpublished titles is introduced.

**Rate limits** (per signed-in user, answered with 429 over the limit, the work is not done; values in `config/limits.php`, `.env` keys in `.env.example`): `files.store` `throttle:upload` (`LIMIT_UPLOAD_PER_MINUTE`, default 120), `lookup/content-types` and `lookup/nodes` share `throttle:lookup` (`LIMIT_LOOKUP_PER_MINUTE`, default 120), `youtube/lookup` `throttle:youtube` (`LIMIT_YOUTUBE_PER_MINUTE`, default 20; lower because a call may spend the external API quota). The limit of one user does not affect another; within the limit the access checks answer as before. Tests: `tests/Feature/Access/HelperEndpointLimitsTest.php`.

## Favorites and admin menu

`SidebarNavigation` (`Webtack\Ecms\Navigation`) shows a type in [Favorites](admin-favorites.md) and in the [admin menu](admin-navigation-menu.md), only when `nodeLevel` is not `none`; a group without visible links is hidden. A node link of the admin menu follows the edit right of that node: it is shown only when `AccessResolver::canEditNode()` allows (`any` on the type, or `own` and the user is the author); this is the same method `ContentNodePolicy::update` and `delete` call, so the sidebar and the edit form agree. The filter works at read time and deletes nothing: when access returns, the item returns. Adding to favorites without access — 403. The menu settings page requires the system right `settings`, but the menu itself only arranges links: access to the list of nodes is determined by the level per type.

## Cleanup on type deletion

`ContentType::deleting` removes the `node:{key}` rows from `role_permissions`; `ContentTypeController::destroy` wraps the deletion in a transaction. A new type with the same key starts with `none`.

## Risk

A user with the `roles` right is effectively a superadmin: they can grant themselves any right. Grant it deliberately; the `admin` role is untouchable.

The `users` right is not a path to administrator access: only an administrator assigns the role `admin` or manages a user who has it ([users](admin-users.md#administrator-accounts)). A holder of `users` can still reset the password of, deactivate, or delete everyone who is not an administrator, including a holder of `roles`, because no hierarchy between other roles applies. Roles that held `users` before this rule lose the abilities they could use on administrators; there is no data migration.

## Tests

`tests/Feature/Access` (`RouteCoverageTest`: every `admin.*` route outside the allow-list answers 403 to a role without rights, with a real record behind each route parameter; a parameter without a record fails the test, except the ones the controller checks itself (`lang`, `typeKey`); `AccessResolverTest`, `ContentNodePolicyTest`, `MediaPolicyTest`, `UserPolicyTest`, `RolesMigrationTest`). Factories: `UserFactory` by default creates an administrator (`withRole($role)`, `inactive()`), `RoleFactory::withPermissions([...])`.

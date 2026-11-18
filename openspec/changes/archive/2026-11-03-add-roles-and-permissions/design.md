# Design

## Context

The motivation and the list of changes are in `proposal.md`, the requirements are in `specs/`. The current state:

- The user (`User`) has no role; all routes `/admin/*` are behind `auth` + `verified`; there are no policies and gates.
- A content record has an immutable author `content_nodes.user_id` (NOT NULL, `restrictOnDelete`), so the rule «own records» needs no new columns. A file has `media.user_id` (nullable, `nullOnDelete`).
- The routes of types and records are shared across the three kinds and receive `kind` from the `defaults` of the route; `Route::bind` already cuts off a foreign kind.
- There is no page «Налаштування сайту» ("Site settings") yet (the item Settings in the sidebar is a layout); there is only the storage `AppSettings`.
- The state of the components is described in `docs/admin-auth.md`, `docs/admin-shell.md`, `docs/admin-content-nodes.md`, `docs/media-library.md`; the new components are documented in `docs/access-control.md`, `docs/admin-users.md`, `docs/admin-roles.md`.

## Goals / Non-Goals

**Goals:**
- One mechanism of checking the rights for all sections, which is read from the code and tested.
- The matrix of the rights is built dynamically from the existing types.
- The server is always the arbiter; the client only hides the elements.

**Non-Goals:**
- Several roles per user, rights at the level of a single record or field, a separate right «publish».
- Password reset by email, self-registration, invitations (the admin sets the password himself).
- Logging of the users' actions (audit).
- The page of site settings: the right `settings.manage` is defined and stored, but the points of application will appear together with the page.

## Decisions

**1. The data schema.**
`roles(id, name unique, is_system bool)`, `role_permissions(role_id FK cascade, scope string, level string, unique(role_id, scope))`, `users.role_id` (FK `restrictOnDelete`, NOT NULL after the backfill), `users.is_active` (default true).
`scope`: the system keys `users`, `roles`, `menus`, `settings`, `taxonomy`, `types`; `node:{type_key}`; `media`. `level`: `allow` for the system ones, `own` | `any` for `node:*` and `media`. A missing row = `none`.
*Alternative:* a JSON column `roles.permissions` — simpler, but it is impossible to query «which roles have access to a type» and to remove the types on deletion. The rows are better. *Alternative:* `spatie/laravel-permission` — the levels `own/any` and the dynamic `node:{type}` would still have to be done by hand in it, so the dependency is not justified.
The `scope` for a type is not an FK (the key of a type is immutable); when a type is deleted, the rows `node:{key}` are deleted in the same transaction.

**2. The role `admin`.**
It is created by a migration (`is_system = true`) and has no rows in `role_permissions`: the checking service treats `is_system` as «everything is allowed, the level `any` everywhere». So the new types are automatically available, and the matrix of `admin` is only for viewing. The migration assigns it to all existing users, then makes `role_id` NOT NULL.

**3. The checking service.**
The service `AccessResolver` (on `User` — thin methods) loads the rights of the role in one request per request and caches them in the memory of the instance. The public operations: a check like `can('users')` for a system right, `level('node:article')`, `levelForMedia()`, `canEditAnyContent()` (there is `own/any` on at least one type or media).
The integration with Laravel: `Gate::define` for the system rights and the policies `ContentNodePolicy` and `MediaPolicy` (`viewAny`, `create`, `update`, `delete`; `update/delete` compare `user_id` with the level `own`). The routes of the system sections are protected by the middleware `can:…`, and the records and media — by `$this->authorize(...)` in the controllers (where a model or a type is needed).
A file without an owner (`media.user_id = null`) is available only at the level `any`.

**4. Types, kinds and records.**
For records the check takes the type from the route-binding (`contentType`) and the level `node:{key}`. The kinds `page`/`block` use the same levels. The management of the types themselves and their fields (all three kinds) — the system right `types`. The creation/change/deletion of records requires `own` or `any`; viewing the list also (the level `none` = 403).

**5. The activity of the user.**
The middleware `EnsureUserIsActive` in the group `web` after the authentication: it logs out the inactive one, invalidates the session and redirects to `/login`. `LoginRequest` rejects the login of an inactive user with the same error message. A separate storage of sessions is not needed — the middleware works on every request.

**6. Protection of the last administrator.**
The check is in one place (a method on `User`/the service), which is called from the controller of the users and from the change of a role: the number of active users of the role `admin` is counted in a transaction with the lock `lockForUpdate`, so that parallel requests do not leave zero admins.

**7. The client.**
`HandleInertiaRequests` adds to `auth.user` an object `can`: the system booleans, the map of levels for the types and `media`. `navigation.ts`/`AppSidebar` filter the items by it; the buttons of change/deletion in the list of a record take `own` into account (the author of the record is taken from the already passed data of the record). For this the list of records passes the sign «can be changed» from the server, not computing it on the client, so that the rule is in one place.

**8. Auxiliary endpoints.**
`lookup/*` remain without a check of rights (the decision of the owner of the product: «softly»). `files.store` and `youtube/lookup` — through `canEditAnyContent()`. `profile` and `dashboard` are open to any active user.

**9. Users.**
An administrator creates: the name, the email, the password, the role; `email_verified_at = now()` (there are no verification letters). Deletion — only without records-author; the check is made in advance (an `exists` request), and the FK `restrictOnDelete` remains a safety net. A user cannot change his own role or deactivate himself.

## Risks / Trade-offs

- [Forgotten routes without a check] → a Feature test goes through all routes `admin.*` and requires that each (except the explicit allow-list: dashboard, profile, lookup) returns 403 for a role without rights.
- [A user with the right `roles` is in fact a superadmin, because he can give himself any right] → accepted by the model; the risk is reduced by the fact that the role `admin` is untouchable, and the right `roles` is granted deliberately. Document in `docs/access-control.md`.
- [The levels in `role_permissions` for deleted types] → cleaning on deletion of a type and a check in the test; a `scope` without a corresponding type is ignored by the service.
- [Additional requests for the rights] → one request per request, a cache in the instance.
- [The existing e2e specs log in as the administrator] → the behaviour does not change, because `admin` has all the rights; a full e2e run is not needed, but the specs of sidebar/auth are checked separately.

## Migration Plan

1. The migration creates the tables, adds `users.role_id` (nullable) and `users.is_active`, creates the role `admin`, assigns it to all existing users, then makes `role_id` NOT NULL.
2. The seeder creates the administrator already with the role `admin`.
3. Rollback: remove the columns and the tables (the data of the roles is lost).

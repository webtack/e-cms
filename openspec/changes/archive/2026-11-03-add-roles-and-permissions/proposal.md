# Proposal

## Why

The admin is open to every user with a confirmed email: there are no roles and no rights, and the only account is the administrator from the seeder (`routes/web.php` says directly «open to every verified admin until roles exist»). To pass part of the work to editors without giving them the settings of the system, a model «access by role» is needed: the role defines which sections the user sees and which records he can change.

## What Changes

- A new entity **role**; each user has exactly one role (`users.role_id`). The system role `admin` has all the rights; it cannot be changed or deleted.
- The rights of a role are stored as a set of records «area → level»:
  - system rights (yes/no): users, roles, menus, site settings, taxonomy, types (creation/settings);
  - a **level** (`none` / `own` / `any`) separately for each content type (all kinds `content`, `page`, `block`) and for the media library. `own` — create and change/delete only own records (by the author), `any` — any. Foreign records at the level `own` remain visible in the list.
- New admin pages **«Користувачі»** ("Users") and **«Ролі»** ("Roles"): CRUD, the matrix of rights on the screen of the role. A user gets `is_active`: a deactivated one cannot log in, his open sessions are blocked; a user can be deleted only if he has no records.
- All routes `/admin/*` (except the dashboard, the profile and the auxiliary endpoints) check the rights on the server; the sidebar hides the unavailable items; an unavailable URL responds with 403.
- Auxiliary endpoints: `lookup/*` remain open for every active user; `files.store` and `youtube/lookup` require the level `own`/`any` on at least one type or on the media.
- Protections: a role with users cannot be deleted; the last active administrator cannot be deactivated, deleted or deprived of the role `admin`; a user cannot deactivate himself.
- New content types by default have `none` for all roles except `admin`.
- The migration creates the role `admin` and assigns it to all existing users.
- There is no separate right «publish»: `is_active`, `published_at`, `unpublished_at` of records are changed by the same right as the record itself.
- **BREAKING**: after the migration a user without a role cannot be created; any new user needs a role.

## Capabilities

### New Capabilities
- `access-control`: the model of roles and rights (system rights, the levels `none/own/any` for types and media), the rules of checking on the server, the behaviour on refusal, the auxiliary endpoints, the initial role `admin`.
- `admin-users`: the pages of managing users, the role and the activity of a user, the rules of deletion/deactivation.
- `admin-roles`: the pages of managing roles and the matrix of rights, the protection of the system role and of the roles with users.

### Modified Capabilities
- `admin-auth`: a deactivated user cannot log in, and an already open session is terminated.
- `admin-shell`: the sidebar shows only the items available to the role of the user, and contains the new items «Користувачі» and «Ролі».

## Impact

- Code: migrations (`roles`, `role_permissions`, `users.role_id`, `users.is_active`), the models and the service of checking the rights, policies/gates, the middleware of activity, the controllers and requests `Admin/User*`, `Admin/Role*`, the authorization in the existing controllers (`ContentNode`, `ContentType`/`Field`, `Menu`, `Vocabulary`/`Term`, `Media`, `File`), `HandleInertiaRequests` (passing the rights to the client), the seeder, the factories, the React pages `Admin/Users`, `Admin/Roles`, `navigation.ts`/sidebar.
- Tests: PHP Feature tests of the rights by areas, an e2e spec for users/roles.
- Documentation (new): `docs/access-control.md`, `docs/admin-users.md`, `docs/admin-roles.md`.
- Documentation (updates): `docs/README.md`, `docs/admin-auth.md`, `docs/admin-shell.md`, `docs/admin-content-nodes.md`, `docs/media-library.md`, `docs/e2e-testing.md`, and the mentions «open to everyone» in `docs/admin-menus.md`, `docs/admin-taxonomy.md`, `docs/admin-content-types.md`.

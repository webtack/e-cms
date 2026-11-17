## Context

See proposal.md - Why. Current state, read from the code:

- `routes/web.php` guards all `/admin/users/**` routes with `can:users`; nothing else limits what a holder of `users` may do.
- `StoreUserRequest` and `UpdateUserRequest` validate `role_id` only with `exists:roles,id`; their `authorize()` is `can('users')`. `UserController::edit` and `destroy` have no request class and no object-level check.
- `UserController::update` already protects the last administrator (`LastAdminGuard`), and `UpdateUserRequest::after()` blocks changing one's own role or deactivating oneself. These stay as they are.
- The system role is the `roles` row with `is_system = true`. `AccessResolver` knows whether a user has it (`role()['system']`) but exposes it only through `allows()`/`level()`; there is no public "is this user an administrator" method.
- The project already expresses object-level rules as policies registered in `AppServiceProvider` (`ContentNodePolicy`, `MediaPolicy`). `User` has no policy.
- The users list builds rows in `UserController::index` (`is_self` per row) and the row menu is `UserRowActions.tsx`; the role select is `UserForm.tsx` fed by the `roles` prop built in `UserController::roles()`.

Documentation describing these components: `docs/admin-users.md`, `docs/access-control.md`.

## Goals / Non-Goals

**Goals:**
- One server-side rule, in one place, used by every action: only an administrator assigns `admin` or touches an `admin` user.
- The UI matches the rule (no dead actions, no `admin` option for other actors) without being relied upon.

**Non-Goals:**
- No role hierarchy and no "a role cannot manage roles with more rights" logic; only the system role is special.
- No change to `roles` rights (a holder of `roles` can still grant themselves anything; this stays documented).
- No change to `LastAdminGuard`, self-protection, session handling after a password reset, or the list of rights.

## Decisions

**1. A `UserPolicy` with two abilities, registered like the existing policies.**
`manage(User $actor, User $target)`: true unless `$target` has the system role and `$actor` does not. `assignRole(User $actor, Role $role)`: true unless `$role->is_system` and `$actor` is not an administrator. Controller actions and form requests call these through `Gate`/`can()`, so the 403 comes from the standard authorization path.
Alternatives: inline `abort_if` checks in each action (rejected: five places to keep in sync, easy to forget a new action); a route middleware (rejected: it cannot see the submitted `role_id`, and the policy pattern is already the project's convention for object-level rules).

**2. `AccessResolver::isAdministrator(User $user): bool`.**
Public method returning the cached `system` flag of the user's role. The policy uses it for the actor, so the "is administrator" meaning lives next to `allows()`/`level()` and reuses their per-user cache. The target is checked through its own role (`$target->role->is_system`), loaded fresh with the route binding.

**3. Where each check runs.**
- `StoreUserRequest::authorize()`: `can('users')` and `can('assignRole', Role)` for the submitted `role_id` (an unknown or non-integer id is left to validation, so the 403 never masks the existing "role does not exist" error).
- `UpdateUserRequest::authorize()`: `can('users')`, `can('manage', $target)`, and `can('assignRole', Role)` for the submitted `role_id`.
- `UserController::edit` and `destroy`: `Gate::authorize('manage', $user)`.
All of them run before the controller body, so a refused request never reaches `LastAdminGuard` or writes anything.

**4. The users list keeps administrators visible; rows carry `can_manage`.**
`index()` adds `can_manage` per row (the `manage` ability) and loads the role's `is_system` with it. `UserRowActions` renders no actions when `can_manage` is false. The own row keeps working as before (`is_self`): an administrator is always allowed to manage, and a non-administrator never has the role `admin`, so the flag never hides the own row.
Alternative: hide administrators from the list (rejected by the owner: the list stays informational).

**5. The role select is filtered on the server.**
`UserController::roles()` takes the actor and drops the system role unless the actor is an administrator. The form needs no new prop and no client logic. A non-administrator editing a non-admin user keeps their selected role in the list because it is not `admin`.

**6. 403, not a validation error.**
The owner asked for 403 on assigning `admin`. A client that respects the filtered select never sends it, so a refusal can only come from a crafted request; there is no form message to design.

## Risks / Trade-offs

- [A race: the target is promoted to `admin` between the binding and the write, so a non-administrator edits an administrator] → The window is the length of one request and needs a concurrent administrator action; accepted and noted in the docs. `LastAdminGuard` is not affected.
- [A role with `users` but without `admin` can still reset passwords and deactivate everyone who is not an administrator, including holders of `roles`] → This is the owner's decision (no role hierarchy); `docs/access-control.md` keeps `roles` flagged as a path to full rights.
- [Other sessions of a user whose password was reset stay open] → Out of scope (audit finding F7, separate decision).
- [Existing roles with `users` lose abilities they could silently use] → Intended; no data migration. Mention in the docs.

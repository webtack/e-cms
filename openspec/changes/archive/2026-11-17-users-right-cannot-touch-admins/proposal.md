## Why

The `users` system right is documented as a plain "manage users" right, but it is in fact a path to full administrator access. An auth audit (finding F1, confirmed by a throwaway probe) showed that a user holding only `users` can (a) create a user with the system role `admin` and log in as them, and (b) reset the password of an existing administrator. `docs/access-control.md` warns only about `roles` ("effectively a superadmin"), so an owner may hand out `users` to a staff member (for example, one who creates accounts for new editors) without knowing it is equal to `admin`.

## What Changes

- A signed-in user who does not have the system role `admin` (an actor without `is_system`) is refused with 403 when they:
  - create a user with the `admin` role;
  - change any user's role to `admin`;
  - open the edit form of, edit, deactivate, or delete a user who currently has the `admin` role.
- The users list `/admin/users` still shows administrators to such actors, but their rows carry `can_manage = false` and have no Edit/Delete actions.
- The role select of the user create/edit forms omits `admin` for such actors.
- Only the system role `admin` is special: other roles that hold `users` are not restricted against each other (no role hierarchy is introduced).
- Actors with the role `admin` keep today's behavior. `LastAdminGuard` and the self-protection rules (no own role change, no self-deactivation) stay unchanged.
- Documentation states the new rule and that `users` is no longer a path to administrator access, while `roles` still is.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `admin-users`: adds the requirement that accounts of the system role `admin`, and assigning that role, are managed only by administrators; the list marks such rows as not manageable and the role select hides `admin` for other actors.

## Impact

- Code: `app/Http/Requests/Admin/StoreUserRequest.php`, `UpdateUserRequest.php`, `app/Http/Controllers/Admin/UserController.php` (`index`, `create`, `edit`, `update`, `destroy`); frontend `resources/js/Components/Admin/Users/UserRowActions.tsx` and `Pages/Admin/Users/Index.tsx` (row flag), `UserForm.tsx` and the `roles` prop of `Create`/`Edit` pages (role list).
- Documentation (English): `docs/admin-users.md` (the rule, the list flag, the role select, tests) and `docs/access-control.md` (the "Risk" section: `users` is no longer an administrator path; `roles` still is).
- Tests: `tests/Feature/Admin/UserAdminTest.php` and `tests/Feature/Access` (negative 403 per action with a real administrator as the target; positive for the administrator actor and for a non-admin `users` actor managing non-admin users); e2e `e2e/admin-users.spec.js` only for the changed list/select behavior.
- No migrations, no new dependencies, no change for administrators.

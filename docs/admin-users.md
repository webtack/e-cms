# Users in the admin panel

The `/admin/users` section manages admin panel users: creation, role, activity, deletion. The access model is described in [access-control.md](access-control.md), roles — in [admin-roles.md](admin-roles.md). Access — `auth` + `verified` and the system right `users` (`can:users` on the route group; without it 403). A guest is redirected to `/login`.

Users maintain their own name, email and password on the [profile page](admin-profile.md); this section is for administrators managing other accounts.

## Routes

`{user}` — the numeric `id`; an unknown `id` gives 404.

| Method | Address | Name | Action |
| --- | --- | --- | --- |
| GET | `/admin/users` | `admin.users.index` | list |
| GET | `/admin/users/create` | `admin.users.create` | create form |
| POST | `/admin/users` | `admin.users.store` | create |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | edit form |
| PUT | `/admin/users/{user}` | `admin.users.update` | update |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | delete |

The controller is `Webtack\Ecms\Http\Controllers\Admin\UserController`, the requests are `StoreUserRequest`, `UpdateUserRequest`, and the last-administrator protection is `Webtack\Ecms\Access\LastAdminGuard`. The pages are `Pages/Admin/Users/{Index,Create,Edit}.tsx`, the components are `Components/Admin/Users/{UserForm,UserRowActions}.tsx`.

## List

A server-side [data table](admin-data-table.md) without filters: "Name", "Email", "Role", "Active"; search by name, email, and role; sorting by all columns, the default order — by name. The name cell starts with a round avatar (`Components/Admin/UserAvatar.tsx`: the user's [picture](admin-profile.md#avatar) from the row's `avatar` URL, or the initials when there is none — soft indigo for an active user, grey for an inactive one; decorative and `aria-hidden`, so the cell's accessible text is still the name); the "Active" column uses `booleanTone: 'warning'` (see [visual style](admin-visual-style.md#booleanbadge-and-status-tones)). A row carries `is_self` (the own row without "Delete") and `can_manage` (the `manage` ability of [`UserPolicy`](#administrator-accounts)): an administrator's row is listed for everyone, but for a non-administrator it has no "Edit" and no "⋮" menu (`UserRowActions` renders nothing). Above the table — "Create"; in the row — "Edit" and the "⋮" menu with "Edit" and "Delete".

## Creation

Fields: `name`, `email` (unique, email format), `password` (required, at least 8 characters), `role_id` (required, an existing role), `is_active` (default `true`). The administrator sets the password: there are no invitation emails. The created user has a verified email (`email_verified_at = now()`, no verification emails) and can log in. Without a role or with a non-existent role — an error next to the `role_id` field; a duplicate email — next to `email`.

## Editing

`name`, `email`, `role_id`, and `is_active` can be changed; an empty `password` keeps the current password, a non-empty one replaces it and ends the user's sessions ([sessions after a password change](admin-auth.md#sessions-after-a-password-change)); an administrator's own session is not affected. New rights take effect from the user's next request. After a successful save, the administrator stays on the user form (`back()`) with a `success` message. A user cannot change their own role or deactivate themselves (errors next to `role_id` / `is_active`; the form blocks these fields for the own record).

## Deactivation

A deactivated user cannot log in, and their open session is ended by the `EnsureUserIsActive` middleware ([authentication](admin-auth.md#deactivated-user)); their nodes keep the authorship (`content_nodes.user_id` does not change). Reactivation restores access.

## Deletion

Only a user who is not the author of any content node (`content_nodes.user_id`): `exists` is checked in advance, and the FK `restrictOnDelete` remains as a safeguard (a `QueryException` also gives a message). Deleting a user also releases their avatar in the [media library](media-library.md) (`FileRelations::forgetOwner('user', id)` in the same transaction); the file itself stays in the storage. An author can only be deactivated: the message "Cannot delete a user who authored content. Deactivate the user instead."

## Administrator accounts

Only a user of the system role `admin` assigns that role or manages a user who has it. `Webtack\Ecms\Policies\UserPolicy` holds the rule: `manage(actor, target)` is false when the target has the role `admin` and the actor does not; `assignRole(actor, role)` is false for the system role and a non-administrator. It is enforced on the server before the controller body runs:

- `StoreUserRequest::authorize()` and `UpdateUserRequest::authorize()` check `assignRole` for the submitted `role_id` (an unknown or non-integer id is left to validation, so the 403 never masks "role does not exist"); `UpdateUserRequest` also checks `manage` on the route user;
- `UserController::edit` and `destroy` call `Gate::authorize('manage', $user)`.

A non-administrator with the `users` right therefore gets **403** (and nothing changes) when they create a user with `admin`, change a role to `admin`, open the edit form of, edit (including a new password or role), deactivate, or delete an administrator. Users of other roles are managed as before, and no hierarchy between other roles applies (a role with `users` may edit another role with `users`). An administrator keeps full management, subject to the last-administrator and self-protection rules.

The role select (`UserController::roles()`) omits `admin` for a non-administrator; the server stays the arbiter. A known race: if an administrator promotes the target between the route binding and the write, a non-administrator's request in that single-request window still passes; this is accepted. 

## Last administrator protection

At least one **active** user of the system role `admin` always remains: it cannot be deactivated, deleted, or moved to another role (an error next to `is_active` / `role_id`, or a message on deletion). Only an active administrator can manage administrators, so through HTTP another active administrator always exists; the guard remains as the protection against concurrent writes. `LastAdminGuard::run()` performs the check and the change in one transaction and locks the rows of active administrators (`lockForUpdate`), so two parallel requests cannot leave zero administrators. A deactivated administrator is not counted.

## Tests

```bash
docker compose exec app php artisan test --compact --filter=User
npm run test:e2e -- e2e/admin-users.spec.js
```

Feature tests — `tests/Feature/Admin/UserAdminTest.php` (creation, password, role, self-protection, deletion, last administrator through `LastAdminGuard`, 403 for a non-administrator on every action against an administrator, the `can_manage` flag and the filtered `roles` props) and `tests/Feature/Access/UserPolicyTest.php` (the policy matrix); e2e — `e2e/admin-users.spec.js` (creation with a role and login, deactivation and login of a deactivated user, deleting an author is rejected, the own row, a user with only `users` sees an administrator without actions and no `admin` in the role select, avatar in the list and release on deletion).

The parallel last-administrator scenario is not reproduced in PHPUnit (a single process): the sequential logic is checked, and the locking is verified by the `lockForUpdate` property in the transaction (SQLite in tests has no locking; MySQL applies it).

# Roles in the admin panel

The `/admin/roles` section manages roles and their permissions matrix. The access model, data schema, and the check service are described in [access-control.md](access-control.md); here — routes, page responses, and form behavior. Access — `auth` + `verified` and the system right `roles` (`can:roles` on the route group; without it 403). A guest is redirected to `/login`.

## Routes

`{role}` — the numeric `id` (a role has no key or uuid); an unknown `id` gives 404.

| Method | Address | Name | Action |
| --- | --- | --- | --- |
| GET | `/admin/roles` | `admin.roles.index` | list |
| GET | `/admin/roles/create` | `admin.roles.create` | create form |
| POST | `/admin/roles` | `admin.roles.store` | create |
| GET | `/admin/roles/{role}/edit` | `admin.roles.edit` | edit form (for `admin` — view only) |
| PUT | `/admin/roles/{role}` | `admin.roles.update` | update |
| DELETE | `/admin/roles/{role}` | `admin.roles.destroy` | delete |

The controller is `Webtack\Ecms\Http\Controllers\Admin\RoleController`, the requests are `StoreRoleRequest`, `UpdateRoleRequest`, and the matrix is read and written by `Webtack\Ecms\Access\PermissionMatrix`. The pages are `Pages/Admin/Roles/{Index,Create,Edit}.tsx`, the components are `Components/Admin/Roles/{RoleForm,PermissionMatrix,RoleRowActions}.tsx`.

## List

A server-side [data table](admin-data-table.md) without filters: columns "Name" (with a soft indigo "System" badge for `admin`), "Users" (number of users), "System"; search by name, sorting by all columns, the default order — by name. Above the table — "Create"; in the row — the "Edit" button ("View" for `admin`) and the "⋮" menu with "Edit" and "Delete" (the system role has no menu).

## Form and matrix

Name (unique, up to 255 characters; an error next to the field) and the matrix (`PermissionMatrix.tsx`):

- **Sections** — toggles of the system rights: `users`, `roles`, `menus`, `taxonomy`, `types`, `settings` (`settings` is saved, but takes effect with the settings page).
- **Content, page, and block types** — for each type a choice of "No access" (`none`), "Own nodes only" (`own`), "Any nodes" (`any`). The list is built from the types that exist when the screen is opened: a type created after the role is shown with `none`.
- **Media library** — the same choice for files (`own` — files the user uploaded).

Request body: `name` and `permissions` = `{ system: {users: bool, …}, nodes: {type_key: level}, media: level }`. Levels are validated (`none|own|any`); unknown system keys and keys of non-existent types are ignored; `none` creates no row. After saving, the user stays on the role form (`back()`) with a `success` message. Saving replaces all rights of the role in a transaction and resets the `AccessResolver` cache, so the change applies to the users of the role from their next request. A new role without `permissions` is created without any rights.

## Protections

- The `admin` role (`is_system`): the edit page opens for viewing (everything is enabled, elements are blocked, there is no "Save" button); `PUT` and `DELETE` give 403.
- Deleting a role that has users is rejected with the message "Cannot delete a role that has users." (the dialog in the list explains this and does not offer confirmation). An empty role is deleted together with its rights (`role_permissions` cascade).

## Tests

```bash
docker compose exec app php artisan test --compact --filter=Role
npm run test:e2e -- e2e/admin-roles.spec.js
```

Feature tests — `tests/Feature/Admin/RoleAdminTest.php`; e2e — `e2e/admin-roles.spec.js` (creating a role, changing the matrix, the system role, deleting a role with users and an empty one).

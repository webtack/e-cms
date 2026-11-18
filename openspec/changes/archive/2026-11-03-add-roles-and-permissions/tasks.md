# Tasks

## 1. Schema, models and the service of rights

- [x] 1.1 Migration: the tables `roles`, `role_permissions`, the columns `users.role_id` and `users.is_active`, the creation of the role `admin` and its assignment to the existing users, then `role_id` NOT NULL. Check: `docker compose exec app php artisan test --compact --filter=RolesMigration` (a test on a DB with users before the migration and without them).
- [x] 1.2 Models `Role`, `RolePermission`, the relation `User::role()`, the field `is_active` in `User`, factories; the seeder creates the administrator with the role `admin`. Check: the Feature test `tests/Feature/Auth` (the seeder) passes; the code has JSDoc/PHPDoc in English with `@example` where it is not obvious.
- [x] 1.3 The service `AccessResolver` (system rights, `level('node:{key}')`, media, `canEditAnyContent()`, `admin` = everything is allowed), `Gate::define` for the system rights. Check: the unit/Feature tests `tests/Feature/Access/AccessResolverTest` for each level and for `admin`.
- [x] 1.4 Policies `ContentNodePolicy` and `MediaPolicy` (`viewAny`, `create`, `update`, `delete`, the rule `own` by the author, a file without an owner only `any`). Check: `--filter=ContentNodePolicy` and `--filter=MediaPolicy`.
- [x] 1.5 Cleaning of the rows `node:{key}` on the deletion of a type in the same transaction. Check: a test of the deletion of a type.
- [x] 1.6 Documentation: create `docs/access-control.md` (the model, the schema, the levels, the risk of the role `roles`), add it to `docs/README.md`; update `docs/content-model.md` (the link of the author with the rights). Check: the references in `docs/README.md` are valid.

## 2. Activity of the user and login

- [x] 2.1 Middleware `EnsureUserIsActive` in the group `web`, refusal of the login of a deactivated user in `LoginRequest`. Check: `--filter=Auth` (the login of a deactivated user, the logout of an open session).
- [x] 2.2 Documentation: update `docs/admin-auth.md`. Check: the section about deactivation is present.

## 3. Protection of the routes and the rights on the client

- [x] 3.1 Protect the routes of the menus, the taxonomy, the types/fields (all three kinds) by the system rights through the `can:` middleware. Check: Feature tests of each section (`--filter=Menu`, `--filter=Taxonomy`, `--filter=ContentType`) with roles without rights and with rights.
- [x] 3.2 Authorization of records (`ContentNodeController`: index, create, store, edit, update, destroy) by the level of the type and the rule `own`; the list passes the sign «can be changed» for each record. Check: `--filter=ContentNode` (the levels none/own/any, a foreign record).
- [x] 3.3 Authorization of the media library (`MediaController`) and `files.store`/`youtube/lookup` through `canEditAnyContent()`; `lookup/*` unchanged. Check: `--filter=Media`, `--filter=File`, `--filter=Lookup`.
- [x] 3.4 A test-review of all routes `admin.*`: each one, except the allow-list (dashboard, profile, lookup), gives 403 to a role without rights. Check: `--filter=RouteCoverage`.
- [x] 3.5 `HandleInertiaRequests` passes `auth.user.can`; `navigation.ts`/`AppSidebar` filter the items; the buttons of the list of records use the sign from the server. Check: a Feature test of the shared props + `npm run test:e2e -- e2e/admin-shell.spec.js`.
- [x] 3.6 Documentation: update `docs/admin-shell.md`, `docs/admin-content-nodes.md`, `docs/media-library.md`, `docs/admin-menus.md`, `docs/admin-taxonomy.md`, `docs/admin-content-types.md` (remove «відкрито всім» — "open to everyone"). Check: the search `grep -n "відкрит" docs` does not find outdated statements about access.

## 4. Management of roles

- [x] 4.1 Controller and requests of roles (`index`, `create`, `store`, `edit`, `update`, `destroy`), the matrix of rights on the server (dynamically from `content_types`, grouped by kind), protection of `admin` and of the roles with users. Check: `--filter=Role`.
- [x] 4.2 React pages `Admin/Roles` (the list on DataTable, the form with the matrix), the items of the sidebar and the translations `uk`/`en`. Check: the page renders, `npm run build` passes.
- [x] 4.3 e2e spec `e2e/admin-roles.spec.js` (creation of a role, change of the matrix, an attempt to delete a role with users). Check: `npm run test:e2e -- e2e/admin-roles.spec.js`; add a row to the table «Область → спек» in `docs/e2e-testing.md`.
- [x] 4.4 Documentation: create `docs/admin-roles.md`, update `docs/README.md` and the table «Спільний код → спеки», if the spec depends on DataTable. Check: the references are valid.

## 5. Management of users

- [x] 5.1 Controller and requests of users (CRUD, the password is optional at change, `is_active`, `email_verified_at` at creation), deletion only without records, a prohibition of self-deactivation and of change of one's own role, protection of the last administrator with the locking in a transaction. Check: `--filter=User` (including the parallel scenario of the last admin).
- [x] 5.2 React pages `Admin/Users` (the list on DataTable, the form with the choice of the role and the switch of activity), translations. Check: `npm run build` passes.
- [x] 5.3 e2e spec `e2e/admin-users.spec.js` (creation of a user with a role, deactivation, login of a deactivated user, the deletion of an author is rejected). Check: `npm run test:e2e -- e2e/admin-users.spec.js`; add a row to `docs/e2e-testing.md`.
- [x] 5.4 Documentation: create `docs/admin-users.md`, update `docs/README.md`. Check: the references are valid.

## 6. Integration check

- [x] 6.1 The full `docker compose exec app php artisan test` (the changed migrations, the DB schema, the global middleware and the authentication) and `openspec validate add-roles-and-permissions`. Check: both commands without errors; `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks.
- [x] 6.2 The full `npm run test:e2e` (the authentication and the sidebar changed). Check: all specs are green.

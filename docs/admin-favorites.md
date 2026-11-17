# Favorites in the admin panel

The personal "Favorites" menu: the user marks with a star the types they work with most often, and sees them in the sidebar as a separate block above the [admin menu](admin-navigation-menu.md). The list belongs only to this user.

## Schema

The table `user_favorite_types` (model `Webtack\Ecms\Models\UserFavoriteType`, relation `User::favoriteTypes()`, ordered by `id`):

| Column | Description |
| --- | --- |
| `id` | The order of addition |
| `user_id` | FK to `users`, `cascadeOnDelete` |
| `type_key` | FK to `content_types.key`, `cascadeOnDelete` |
| `created_at` | The time of addition |

The pair (`user_id`, `type_key`) is unique. Deleting a user or a type removes the rows by the database cascade (there are no model events and none are needed).

## Routes

They are open to every active user, but with a level check per type (`FavoriteTypeController`):

| Method and address | Name | Action |
| --- | --- | --- |
| `POST /admin/favorites/{typeKey}` | `admin.favorites.store` | Adds the type (a repeated call changes nothing) |
| `DELETE /admin/favorites/{typeKey}` | `admin.favorites.destroy` | Removes the type |

The level `AccessResolver::nodeLevel` for the type is `none` — the response is 403 (both methods); a missing type — 404. The response is `back()`, so the Inertia page keeps the state of the table (`preserveState`, `preserveScroll` on the client).

## Star and sidebar

- `ContentNodeController::index` adds the prop `isFavorite`; `Pages/Admin/ContentNodes/Index.tsx` passes the star as `children` of `ContentTypeNav`, which renders it next to the type-name heading (`data-testid="favorite-toggle"`, `aria-pressed`).
- The star is also in the row of the types list (`Pages/Admin/ContentTypes/Index.tsx`, the three kinds of types, also without the `types` right): `ContentTypeController::index` provides the prop `favoriteKeys` (the keys of the user's favorite types) and the service row field `can_favorite` (the level for the type is not `none`); for a type without access there is no star.
- The block "Favorites" in `AppSidebar` reads the prop `navigation.favorites` (see [admin-shell.md](admin-shell.md#sidebar)): `{ key, name, kind, href }`, the order — by addition. The icon depends on the kind of type: `content` — `Table2`, `page` — `FileStack`, `block` — `Blocks`. Without items the block is not shown.

## Filtering by access

`Webtack\Ecms\Navigation\SidebarNavigation::forUser()` reads the user's rows and keeps only the types where the level is not `none`. Nothing is deleted: when access returns, the item appears again. The prop `navigation` in `HandleInertiaRequests` is lazy, so it is updated with every Inertia response. More about levels — [access-control.md](access-control.md#favorites-and-admin-menu).

## Tests

`tests/Feature/Navigation/FavoriteTypeTest.php`, `SidebarNavigationTest.php`.

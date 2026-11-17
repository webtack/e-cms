# Design

## Context

- The list columns are `settings.list.visible` of the type. Service columns are declared in three places that must stay in step: `ContentType::LIST_SERVICE_KEYS` (what `assertValidListSettings` and the type form's `visible` validation accept), `NodeListSettings::SERVICE_COLUMNS` (header and table type; `isServiceKey`) and, for sorting, `ContentType::LIST_SORT_SERVICE_KEYS`. `NodeListSettings::DEFAULT_SERVICE_COLUMNS` is the composition of a type with no custom list; `formOptions()` offers `LIST_SERVICE_KEYS` in the type form.
- `ContentNodeController::index` builds each row with `row()` and each column with `column()`; the service values come last in the row so that a legacy field with a service key never shadows them. `ContentNodeQuery::paginate` is shared with the public site and knows nothing of authors.
- `ContentNode::author()` is `belongsTo(User::class, 'user_id')`. The form's "Author" row (`NodeServicePanel`, `data-testid="node-author"`) already shows the name.
- `docs/content-model.md` states the rule for a new service column: its key is added to `ContentField::RESERVED_KEYS`; existing fields with such a key stay valid and are not offered as columns. A read-only check of the development database found no field with the key `author` (other databases were not checked).
- The personal hiding of columns ("Columns" menu of the table, `localStorage`) already works on top of `visible`.

## Goals / Non-Goals

**Goals:**
- One more service column wired through the three declaration points, default-on, with no N+1.

**Non-Goals:**
- Sorting or searching by author; filtering the list by author.
- The avatar image (change `user-avatar`); the node form (already shows the author).

## Decisions

1. **Key `author`**, value in the row `author: {name: string} | null` (an object, so `user-avatar` can add `avatar` without changing the column contract). `column()` gives it type `text`, `service: true`, `sortable: false`: it is added to `LIST_SERVICE_KEYS` and `SERVICE_COLUMNS` but **not** to `LIST_SORT_SERVICE_KEYS`. Sorting by author name would need a join with `users` inside `ContentNodeQuery`, shared with the public site, for little value. *Alternative:* sortable by name via a join. Rejected for now.
2. **Default-on.** `DEFAULT_SERVICE_COLUMNS` becomes `['slug', 'status', 'published_at', 'author']` (the author last, so the existing columns keep their positions). This affects only a type whose `settings.list.visible` has nothing besides `title`, because `NodeListSettings::clean()` applies the default only then; types with their own list are untouched, for types without a list. The type form's `current.visible` reflects the new default for such types automatically (it reads the cleaned settings). Types with their own `visible` get `author` appended by the data migration `2026_11_16_100000_append_author_to_list_visible` (same pattern as `append_service_columns_to_list_visible`): it skips lists that hold only `title`, lists that already have `author`, and types without `visible`; it is idempotent and its rollback is a no-op. *Alternative:* leave such types unchanged (opt-in via the type form). Rejected by the owner: the column must show everywhere by default.
3. **Reserved key.** `author` is added to `ContentField::RESERVED_KEYS` per the project rule. Effects, all desirable: new fields named `author` are rejected, `PublicDisplaySettings` already excludes reserved keys from selectable fields, `NodeListSettings::formOptions()` hides an old field with this key from the column choices (the service column wins). The existing tests that loop over `RESERVED_KEYS` keep passing as they iterate the constant.
4. **No N+1.** After pagination, the controller loads the authors of the page with one query only when `author` is visible (`$page->getCollection()->load('author:id,name')`), instead of changing the shared `ContentNodeQuery`.
5. **Rendering.** The service-column branch of `Pages/Admin/ContentNodes/Index.tsx` renders `author` as `UserAvatar` (initials, `aria-hidden`) plus the name, nothing for `null`. `UserAvatar` is the existing component; its `size-6` default fits a table row.
6. **Form.** No change: the form already shows the author's name read-only, and the spec for it stays as is.

## Risks / Trade-offs

- [Default change alters the look of every list without custom columns] → Intended by the owner; users hide it per person in "Columns"; tests that assert the default column set are updated in the same task group.
- [A type built from legacy import may have a field `author`] → Not present in the development database; existing fields stay valid and the service column takes precedence (documented in `docs/content-model.md`); a field of that name would stop being offered as a column.
- [`LIST_SERVICE_KEYS`, `SERVICE_COLUMNS` and the docs lists can drift] → One task updates all of them together and the docs lists are rewritten in the same group.
- [Author deleted] → `user_id` is a non-null FK in practice (users with content cannot be deleted), but the code tolerates a missing relation by rendering an empty cell.

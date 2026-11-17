# Proposal

## Why

The node list shows slug, state and publication dates but not who created a node, although `content_nodes.user_id` has always stored the author and the node form already shows it read-only. Editors cannot see authorship at a glance, which matters most for roles with the `own` access level, where the list shows everyone's nodes but only the author's can be edited.

## What Changes

- New service column `author` of the node list: the author's name next to a round avatar (initials), an empty cell for an unknown author. It is not sortable.
- The column joins the choosable service columns of a type's "Node list" settings (next to "Views" and the others), so a type can place or drop it like any other column.
- The column is **in the default composition**: a type without its own column list shows `title`, slug, state, publication date, then the author. A user can hide it for themselves in the table's "Columns" menu (existing personal hiding, nothing to build). A data migration appends `author` to the end of every type's own non-empty `visible` list (a list holding only `title` already means the default and is skipped; a list that has `author` is not touched), so the column shows everywhere; a type can drop it in the type form.
- `author` becomes a reserved field key (as every service column key is). Existing fields that already use this key keep working; the service column wins over them.
- **Not changed:** the node form already shows "Author" (name, read-only) in the "Details" panel, so there is nothing to add there in this change. Showing the avatar there and in this column is part of `user-avatar`.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `admin-content-nodes`: the node table has the `author` column and shows it by default.
- `admin-content-types`: the "Node list" section offers the "Author" column, accepts the key `author` in `visible`, and the default composition includes it.

## Impact

- **PHP:** `ContentType::LIST_SERVICE_KEYS` (and the `assertValidListSettings` consumers), `ContentField::RESERVED_KEYS`, `NodeListSettings::SERVICE_COLUMNS` and `DEFAULT_SERVICE_COLUMNS`, `ContentNodeController` (column description, row value `author`, eager load of authors for the page).
- **Frontend:** the cell renderer for the service column `author` in `Pages/Admin/ContentNodes/Index.tsx`, using the existing `UserAvatar`; the `Node list` section of the type form picks the new column up from `listOptions` without its own change (to be confirmed by the e2e of the type form).
- **Tests:** `tests/Feature/Admin/ContentNodeIndexTest.php` and the tests of list settings and reserved keys; e2e `admin-content-nodes` (the default column set changes) and `admin-content-types` (the new option in the "Node list" section).
- **Docs:** `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/content-querying.md` (default list settings and service keys), `docs/content-model.md` (reserved keys, service keys of `settings.list`), `docs/admin-content-fields.md` (reserved keys).
- **Data migration** `append_author_to_list_visible` (no schema change); no new dependencies.

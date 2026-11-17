# Media library

The media library knows **where each storage file is used**. The files themselves, their profiles, and uploads are described by the [file storage](file-storage.md); here — the table of relations `media_relations`, the `FileRelations` service, and the owner registry. The page `/admin/media` is added in the same change (its section is below, after its implementation).

## Table `media_relations`

The model `Webtack\Ecms\Models\MediaRelation`, migration `database/migrations/2026_10_30_110300_create_media_relations_table.php`. One row — "the file is used by the owner in the role".

| Column | Type | Purpose |
| --- | --- | --- |
| `id` | bigint | — |
| `file_uuid` | char(32) | FK to `media.uuid`, `restrictOnDelete` (there is a unique index on `uuid`) |
| `owner_type` | string(64) | The type key of the owner (`content_node`, `content_type`, `taxonomy_term`) |
| `owner_id` | unsigned bigint | The owner's identifier |
| `role` | string(64) | Why the owner uses the file; for content — the field key |
| `lang` | string(16), binary comparison | `default` or a language code, as in `content_values_*` |
| `created_at`, `updated_at` | timestamp | — |

- **Uniqueness**: (`file_uuid`, `owner_type`, `owner_id`, `role`, `lang`). The same file in two records — two rows.
- **Protection**: the FK `RESTRICT` does not allow deleting a file that has a relation (`FileStorage::delete` throws a `QueryException`, the disk is not touched).
- The owner is polymorphic, so there is no foreign key to it: integrity is kept by the owner's hooks (cleanup below).
- The relation with a file is by `uuid`, not by the numeric `id`: `File::relations()` — `hasMany` by (`file_uuid`, `uuid`), `MediaRelation::file()` — the reverse.

## FileRelations

`Webtack\Ecms\Files\FileRelations` — the only one that writes to `media_relations`. It does not open its own transaction: it is called inside the transaction that writes the value, so the relations and the value are committed or rolled back together.

```php
$relations = app(FileRelations::class);

$relations->sync('content_node', $node->id, 'cover', 'default', [$uuid]); // bind, replace, or (empty array) clear
$relations->forgetOwner('content_node', $node->id);                       // the owner is deleted
$relations->forgetRole('content_node', 'cover', $nodeIdsOrSubquery);      // the field is deleted
$relations->usageCounts([$uuid]);                                         // [$uuid => 1]; an unused file — 0
$relations->usages($uuid);                                                // Collection<MediaRelation>
```

- `sync(ownerType, ownerId, role, lang, fileUuids)` makes the owner's set of files in this role and language exactly the one passed: it deletes the extra relations, adds the missing ones, and does not touch the existing ones. Duplicates are ignored; a repeated call changes nothing. Other roles, languages, and owners are not touched.
- `forgetOwner` removes all relations of the owner; `forgetRole` — the relations of one role. A field key repeats in different content types, so the caller limits `forgetRole` to the owners of its type (an array of `id`s or a subquery); without a limit, the relations of this role are removed for all owners of the type.
- Files are **never deleted** in this process: releasing a file only removes the relation.

## Owner registry

The media library page shows where a file is used, not just how many times. For the label and the link, it asks the registry `config('files.owners')`: `owner_type => class` that implements `Webtack\Ecms\Files\Owners\MediaOwner`.

```php
interface MediaOwner
{
    /** @return array<int, array{label: string, url: string|null}> keyed by owner id; a missing owner is not returned */
    public function describe(array $ids): array;
}
```

`describe` receives **all** owner ids of one type from the page and returns them in one pass (without a query per owner).

For `content_node` it is `Webtack\Ecms\Files\Owners\ContentNodeOwner`: the label is the `title` of the node in `default` (if absent — the `slug`), the link — to editing the node in the section of its kind: `/admin/content-types/{type}/nodes/{uuid}/edit`, `/admin/page-types/…` or `/admin/block-types/…` (the section segment is taken by `TypeSections::segment($kind)`). An unknown owner type (it is not in the registry) is shown as `owner_type #id` without a link.

For `content_type` it is `Webtack\Ecms\Files\Owners\ContentTypeOwner`: a file in the value of the type itself (`meta_image`, `section_cover`; see [content-values.md](content-values.md#type-values)). The label is the type's name, the link — its settings page in the section of its kind: `/admin/content-types/{key}/edit`, `/admin/page-types/…` or `/admin/block-types/…`; `owner_id` — `content_types.id`, the role — the field key, the language — the language of the value. All types of a page are described by one query.

For `taxonomy_term` it is `Webtack\Ecms\Files\Owners\TermOwner`: the file is the image of a term ([taxonomy.md](taxonomy.md#images)). The label is the term's name in `default` (if absent — the `slug`), the link leads to the term form `/admin/taxonomy/{key}/terms/{uuid}/edit` ([admin-taxonomy.md](admin-taxonomy.md)), which `TermOwner` itself builds from the term's `vocabulary_key` (without a query per owner); `owner_id` — `taxonomy_terms.id`, the role — `image`. The terms of a page are described by two queries.

For `menu_item` it is `Webtack\Ecms\Files\Owners\MenuItemOwner`: the file is the image of a menu item ([menus.md](menus.md#file-owner)). The label is the item's title in `default` (if absent — the item's `uuid`), the link leads to the item form `/admin/menus/{key}/items/{uuid}/edit`, which `MenuItemOwner` itself builds from the item's `menu_key`; `owner_id` — `menu_items.id`, the role — `image`. The items of a page are described by two queries.

For `site_setting` it is `Webtack\Ecms\Files\Owners\SiteSettingOwner`: the file is the large or small logo of the site ([admin-site-settings.md](admin-site-settings.md#logos)). The site settings are a single entity without a row in the DB, so `owner_id` is always `0` (real `id`s start from 1), the role is `logo_large` or `logo_small`, the language is `default`. The label is fixed — "Site settings", the link — `/admin/settings`; any other `owner_id` is unknown and is not described.

For `user` it is `Webtack\Ecms\Files\Owners\UserOwner`: the file is the avatar of a user ([admin-profile.md](admin-profile.md#avatar)). The label is the user name, the link leads to the user edit form `/admin/users/{id}/edit` (guarded by the `users` right on its own); `owner_id` — `users.id`, the role — `avatar`, the language — `default`. The users of a page are described by one query. Deleting a user releases the relation (`UserController::destroy`).

**What a viewer may see.** The registry describes owners; it does not authorize. `MediaController::show` first asks `Webtack\Ecms\Files\UsageAccess` which usages the viewer may open and calls `describe()` only for those, so a hidden title is never read. A usage is visible when: a `content_node` — the viewer's level on the node's type is not `none` (the link only when they may edit that node: `any`, or `own` for their own node; otherwise the title shows without a link; a node whose row is gone is hidden); `user` — the `users` right; `site_setting` — `settings`; `taxonomy_term` — `taxonomy`; `menu_item` — `menus`; `content_type` — `types`. An owner of an unknown type is never visible, even to an administrator. An administrator sees every known owner. A hidden usage keeps its position and is counted: it comes back as a neutral entry `{restricted: true, label: "Used elsewhere", url: null, owner_type: "", owner_id: null, role: "", lang: ""}`, and the panel draws only the label (`data-testid="media-usage-restricted"`). The usage count in the table and the refusal to delete a file that is in use do not depend on the viewer. See [access-control.md](access-control.md#media-and-files-in-nodes).

**A new owner type** — a `MediaOwner` class and one line in `config/files.php`.

### Files of type values

`ContentValueWriter` writes the type's values and syncs the relations in one transaction (`FileRelations::sync('content_type', $type->id, $fieldKey, $lang, …)`), as for a node. The `deleting` hook of the `ContentType` model calls `FileRelations::forgetOwner('content_type', $id)` before the deletion: the type's cascade is executed by the DB, and the events of fields and nodes do not fire in this case. The files remain in the media library and can be deleted by the administrator when they become unused.

## Rules for the consumer

1. Store the value — the `uuid` of the file or `{uuid}.{extension}` — and call `FileRelations::sync` for your set of files **in the same transaction**. For the `image` and `file` fields this is done by `ContentValueWriter` through the type's hook.
2. Remove the relation together with the owner. The owner (a node, a type, a field) should be deleted **through the model**: the hooks on the `deleted` events do not fire for raw SQL and `Model::withoutEvents`, and then the file would remain "used".
3. Do not delete the file yourself: releasing it is only changing or clearing your own value.
4. Deleting unused files is the administrator's job in the media library. There is no automatic cleanup.

**Consequence of a discrepancy.** If a value was written bypassing `FileRelations` or the owner was deleted by raw SQL, the counter becomes false: it is safe when a file looks used (it simply cannot be deleted), and dangerous when a used file looks free (a manual deletion removes a file that is still in use). There is no reconciliation command yet.

## Media library page

`/admin/media` (`admin.media.index`, `Webtack\Ecms\Http\Controllers\Admin\MediaController`, the page `Pages/Admin/Media/Index.tsx`) — a server-side [data table](admin-data-table.md). The routes `admin.media.show` (`GET /admin/media/{file:uuid}`, JSON) and `admin.media.destroy` (`DELETE`) are also by `uuid`. Access by the role level on `media` ([access control by roles](access-control.md)): `none` — 403 on all three routes; `own` — the list and details of all files, deletion only of the files the user uploaded; `any` — deletion of any unused file (a file without an owner — only `any`). The table row carries `can_delete` (the decision of `MediaPolicy`), by which the client hides "Delete".

**Columns.** The preview (`preview`: for images the address of the `thumb` [style](image-styles.md), not the full original; for a document — `null`; the row's `url` field is the address of the original, to which the thumbnail leads: `<a href={url} target="_blank" rel="noopener noreferrer">` with `cursor-pointer`; a document stays an icon without a link; the `url` field in the details of `admin.media.show` is also the address of the original), the name (`name`, the original file name), the type (`mime`; the cell shows the short label `type` — `docx`, `pdf`, `jpg`, see [file-storage.md](file-storage.md#short-type-labels), and the full MIME remains in `mime`), the size (`size`), who uploaded it (`uploader`; empty if the user was deleted; `uploader_avatar` is the uploader's [avatar](admin-profile.md#avatar) URL or `null`, and the cell shows a `UserAvatar` — the picture or the initials — next to the name, nothing for an unknown uploader), the upload date (`created_at`), and the number of usages (`uses`). Sortable are `name`, `mime`, `size`, `created_at`, `uses`; `preview` and `uploader` are not sortable. Without an explicit sort, the newest files come first. Search — by the original name (`LIKE` without the special characters `%` and `_`). An empty table shows "No files yet." (`emptyMessage`).

**Type filter.** Next to the search field there is a dropdown "Type" with the options "All types" and the labels from `FileTypes::options()` (the prop `typeOptions`). It is a [quick filter](admin-data-table.md#quick-filter-quickfilter) of the column `mime` (`type: 'select'`, `quickFilter: true`); the "Filters" button in the media library is disabled. The choice gives an `eq` filter in the address (`?filters[0][c]=mime&filters[0][o]=eq&filters[0][v]=pdf`), a chip under the panel, and "Reset" removes it. The server converts the labels to MIME (`FileTypes::mimesFor`) and applies `whereIn('mime', …)` to the count and to the page, so pagination, search, and sorting work together with the filter; a label that is not in `typeOptions` is dropped by the address parser (the table shows all files, a strip "Some parameters were ignored"). Sorting by the column remains by the full `mime`. A file whose MIME no longer belongs to any profile shows a fallback label, but cannot be filtered.

**Details.** The "Details" button in the row menu opens a panel (a Sheet) that loads `GET /admin/media/{uuid}`: the name, the type, the size, the image dimensions, who and when uploaded it, the description (if any; without a description there is no row), the URL, and the list of usages (the places the viewer may not open are shown as "Used elsewhere", see [what a viewer may see](#owner-registry)). Under the base description the panel lists the translations of the file (`translations`: `[{lang, language, name, description}]` ordered by code, `language` is the language name or the code when the language is no longer listed; `data-testid` `media-translations`, `media-translation-{lang}`, `media-translated-name-{lang}` and `media-description-{lang}`; see [file-storage.md](file-storage.md#translated-names-and-descriptions-media_translations)): the translated display name (the original name stays in the heading) and the description; nothing is drawn for a file without translations. They are read-only here and are edited in the translation mode of [nodes](admin-content-nodes.md#translation-mode) and [type values](admin-content-types.md). Deleting an unused file deletes its translations, and a language with a translated name or description cannot be deleted. 

## Public reading of file names and descriptions

`FileDescriptions::resolve($uuids, $lang)` returns the name and the description of files for the public site with the fallback: the translated value when it is not blank, else the base one (`original_name`, `description`), each independently; the base language reads the base values. `PresentContext::file()` uses it, so an image presented on an English page carries the English description, and a Polish page without a Polish one carries the base description. The stored translations and the original name are never changed.

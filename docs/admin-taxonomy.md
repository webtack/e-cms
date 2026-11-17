# Taxonomy in the admin panel

The `/admin/taxonomy` section manages vocabularies and the tree of terms. The domain rules (tables, identification, depth, translations, deletion) are described in [taxonomy.md](taxonomy.md); here — routes, page responses, and form behavior. Access — `auth` + `verified` and the role right `taxonomy` (without it 403 on all routes of the section); a guest is redirected to `/login`. See [access control by roles](access-control.md).

## Routes

A vocabulary is addressed by its `key` (`Route::bind('vocabulary')` in `routes/service.php`; a vocabulary has no uuid); an unknown key gives 404. Route names start with `admin.taxonomy.`.

| Method and address | Name | Action |
| --- | --- | --- |
| `GET /admin/taxonomy` | `index` | list of vocabularies |
| `GET /admin/taxonomy/create` | `create` | create form |
| `POST /admin/taxonomy` | `store` | create |
| `GET /admin/taxonomy/{vocabulary}/edit` | `edit` | edit form |
| `PUT /admin/taxonomy/{vocabulary}` | `update` | update |
| `DELETE /admin/taxonomy/{vocabulary}` | `destroy` | delete |

The controller is `Webtack\Ecms\Http\Controllers\Admin\VocabularyController`, the requests are `StoreVocabularyRequest` and `UpdateVocabularyRequest`.

## List of vocabularies

`GET /admin/taxonomy` shows the [data table](admin-data-table.md) in **server mode** (`TablePage::fromCollection()`): search, sorting, and pagination are on the server, the state is in the address, and there are no filters. The page is `Pages/Admin/Taxonomy/Index.tsx`.

| Key | Type | Sorting | Notes |
| --- | --- | --- | --- |
| `name` | text | yes | always visible; under the name — the description; both lines are cut |
| `key` | text | yes | always visible |
| `terms_count` | number | yes | the number of terms (`withCount('terms')`, no N+1) |
| `max_depth` | number | yes | empty if the depth is not limited; does not take part in search |

`description` is a server-side column without a client counterpart: the search `q` looks in the name, the key, and the description. Without an explicit `sort`, the rows go by name (case-insensitive).

Above the table — the "Create" button. The row menu (`Components/Admin/Taxonomy/VocabularyRowActions.tsx`): "Terms", "Edit", "Delete".

## Creating and editing a vocabulary

The form is `Components/Admin/Taxonomy/VocabularyForm.tsx` (modes `create`/`edit`), the pages `Create.tsx` and `Edit.tsx`. Fields: `key`, `name`, `description`, `max_depth`.

- `key` — `^[a-z][a-z0-9_]*$`, up to 64 characters, unique; in the edit form it is read-only, and `UpdateVocabularyRequest` has no rule for it, so it does not get into `validated()`.
- `name` — required, up to 255 characters.
- `max_depth` — an integer from 1; an empty value means "no limit" and is stored as the absence of `settings.max_depth`. Other `settings` keys remain.
- The depth cannot be reduced below the actual height of the tree: `UpdateVocabularyRequest` compares it with `TermTree::vocabularyHeight()` and adds an error to `max_depth` (the `Vocabulary` core remains the last barrier).

After creation the user lands on the term page of the new vocabulary; after an update the user stays on the edit form (`back()`); both cases with a `success` message.

## Deleting a vocabulary

The "Delete" action opens a confirmation dialog. A vocabulary with terms is not deleted: the dialog explains the reason and does not offer confirmation; the server checks this again and returns `back()` with an `error` message. A vocabulary referenced by a field of type "term" (`settings.vocabulary`) also remains, with a message that a field uses it. An empty vocabulary without references is deleted, and the user returns to the list with a `success` message. If the model still throws a `ValidationException`, the controller shows its first message.

## Terms of a vocabulary

Terms are addressed by `uuid` within their vocabulary (`Route::bind('term')`): the uuid of a term of another vocabulary and an unknown uuid give 404. The controller is `TermController`, the requests are `StoreTermRequest`, `UpdateTermRequest`, `ReorderTermsRequest`.

| Method and address | Name | Action |
| --- | --- | --- |
| `GET /admin/taxonomy/{vocabulary}/terms` | `terms.index` | tree of terms |
| `GET /admin/taxonomy/{vocabulary}/terms/create` | `terms.create` | create form |
| `POST /admin/taxonomy/{vocabulary}/terms` | `terms.store` | create |
| `PUT /admin/taxonomy/{vocabulary}/terms/order` | `terms.reorder` | change the order |
| `GET /admin/taxonomy/{vocabulary}/terms/{term}/edit` | `terms.edit` | edit form |
| `PUT /admin/taxonomy/{vocabulary}/terms/{term}` | `terms.update` | update |
| `DELETE /admin/taxonomy/{vocabulary}/terms/{term}` | `terms.destroy` | delete |

`terms/order` is declared before `{term}`, so `order` never becomes a term's uuid.

### Terms page

`terms.index` provides **all** terms of the vocabulary in tree traversal order (roots by weight, under each of them its children) in the [data table](admin-data-table.md) in **client mode** (`Pages/Admin/Taxonomy/Terms/Index.tsx`). A row: `uuid`, `name` (the base name), `slug`, `weight`, `depth` (a root has 1), `children_count`, and the service `usages`. Columns: the name (indented by `depth`, the attribute `data-depth`), `slug`, weight, the number of children. Above the table — a heading with the vocabulary name, a link "Edit vocabulary", and a "Create" button; the row menu: "Edit", "Delete". An empty vocabulary shows a no-data message.

The tree is collected by `TermTree::tree()`, the controller flattens the rows. `usages` — the places where the term is used by `term` fields (`owner`, `content_type`, `field_key`, `count`); they are returned by `TermUsage::forVocabulary()` in one grouped query to `content_values_string` for the whole vocabulary, with no query per term.

### Term form

`Components/Admin/Taxonomy/TermForm.tsx` (modes `create`/`edit`), the pages `Terms/Create.tsx` and `Terms/Edit.tsx`. The base form has the fields below; translations are edited in the [translation mode](#term-translations) of the edit page. Fields:

- **Name and description** in the base language (`translations.default.*`; the edit page receives them as `term.name` and `term.description`): the name is required, up to 255 characters.
- **Slug** — optional; an empty one during creation is generated from the name; during editing the current one remains.
- **Parent term** — a selection with indentation by depth, or "No parent". The list `parentOptions: {uuid, name, depth}` is computed by the server: all terms of the vocabulary except the term itself and its descendants (when editing), and only those for which `depth(parent) + subtree height ≤ max_depth`. The client does not compute the depth; the depth and cycles are additionally guarded by the model (an error next to `parent_uuid`). An empty parent during editing makes the term a root.
- **Weight** — an integer from 0; during creation the default is the largest weight among the siblings + 1 (the server passes `defaultWeight`; an empty weight is also replaced by this value), during editing an empty weight keeps the current one.
- **Image** — one file of the `image` profile; the `FileUpload` widget with `profile="image"`, the value `{uuid}.{ext}`. Saving, replacing, and clearing create or remove the relation in `media_relations` through `TermWriter`; a file of another profile gives an error next to the field. During editing the server passes `imageFile` (name, preview).
Saving the base form calls `TermWriter` (the term, its base name and description, and the image in one transaction) and **never changes translations of other languages**; the form has no translation fields and no switcher on the create page; after creation the user lands on the terms page with a `success` message, and after an update the user stays on the term form (`back()`) with the same message. The administrator's interface language and the content languages are independent.

### Term translations

The edit page follows the shared contract of [admin-translation-mode.md](admin-translation-mode.md). With multilingual mode on and at least one active additional language, the `LanguageSwitcher` appears above the form (the base language first, a marker on the languages that have a translation). `GET .../terms/{uuid}/edit?lang=en` opens the translation mode: the same form, where only the name and the description are editable, prefilled from the translation or else from the base (the slug, parent, weight and image keep their place, shown inert and dimmed with their base values); a language that is unknown, inactive or used while multilingual mode is off answers 404. The buttons are "Cancel", "Clear translation" (only when a translation exists, after confirmation) and "Save" (disabled until a field changes).

- `PUT /admin/taxonomy/{vocabulary}/terms/{term}/translations/{lang}` (`name`, `description`, both optional) calls `TermWriter::saveTranslation()`: a value equal to the base value is not stored, an empty one removes its field, other languages are untouched; the user stays on `?lang={lang}` with "Translation saved."
- `DELETE` of the same address calls `TermWriter::clearTranslation()` and returns with "Translation cleared."
- Both routes sit under `can:taxonomy`, like the base edit page.

### Deleting a term

The "Delete" action opens a dialog (`TermRowActions.tsx`). A term with children is not deleted: the dialog explains that the child terms must be moved or deleted first, and does not offer confirmation. A term that is in use is handled the same way: the dialog lists the places (`field (content type): count`). The server checks both rules again and returns `back()` with an `error` message (for the usage, with the same list of places from `TermUsage::forTerm()`). A free leaf is deleted together with its translations and the image relation.

### Drag-and-drop ordering

Rows are dragged by the handle (with the mouse and the keyboard) on condition that all rows are shown without search, filters, and sorting. The client sends `order[]` — the `uuid`s of all terms in the new row order; `ReorderTermsRequest` requires **exactly the whole set** of the vocabulary's uuids (without omissions, repeats, or foreign ones), and the controller in a transaction rewrites `weight = row position` with a direct `UPDATE`. Parents do not change.

**Limitation.** Dragging changes only the order. Since siblings are sorted by weight, their relative order matches the order of their rows, and a row dropped into a "foreign" branch returns under its own parent after the page is refreshed and only changes the order among its siblings. The parent of a term is changed by the form. A save error restores the previous order and shows a message (toast).

### Media library

A term among the usages of a file is shown with a label — its base name — and a link to `/admin/taxonomy/{key}/terms/{uuid}/edit` ([media-library.md](media-library.md)); the link is built by `Webtack\Ecms\Files\Owners\TermOwner`.

## Tests

```bash
vendor/bin/phpunit --filter=VocabularyAdmin
vendor/bin/phpunit --filter=TermAdmin
vendor/bin/phpunit --filter=TermOwnerTest
```


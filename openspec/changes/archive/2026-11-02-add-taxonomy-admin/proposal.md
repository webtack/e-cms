# Proposal

## Why

After `add-taxonomy-core` the taxonomy works only from the code: vocabularies, terms and the field `term` exist, but the administrator cannot create a vocabulary, arrange the tree of categories, or assign a category to a record. This change adds the admin interface without which the categorization is not usable for editors.

## What Changes

- A new section of the admin «Таксономія» (`/admin/taxonomy`) with an item in the sidebar and in the breadcrumbs: the list of vocabularies, creation, editing (the key read-only) and deletion of a vocabulary with an explanation of the reason for refusal.
- The page of terms of a vocabulary (`/admin/taxonomy/{vocabulary}/terms`): the tree in a table with indents by depth, creation, editing and deletion of a term, changing the order by drag-and-drop.
- The form of a term: the name and the description in the base language, `slug` (optional, generated), the parent term (without the term itself, its descendants, and taking `max_depth` into account), the weight, one image (a file of the profile `image` from the media library), translations for the active content languages, when multilingual support is enabled. A term is addressed by `uuid`, a vocabulary — by `key`.
- The media library shows a term among the usages of a file with a link to its form (the link is added by `TermOwner`).
- The dialog of deleting a term shows where it is used (fields, records), when the deletion is forbidden.
- The field `term` in the form of a field of a type: the type appears in the choice only for the types `content` (`admin_hidden_field_types` is removed; for the page/block types it is absent), the choice of a vocabulary is mandatory, the vocabulary is blocked if the field has values.
- The field `term` in the form of a record: the choice of one term (a non-multiple field) or several (a multiple one) with the tree of names; in the list of records the column shows the names of the terms.
- Ukrainian translations of the new labels; the e2e spec of the section.

Not included in this change: the field `term` for the page/block types, autocomplete and creation of a term «on the fly» in the form of a record, dragging a term under another parent (the parent is changed by the form), public category pages, import-export, roles/rights, editing of the translations of the values of the fields `term` in the form of a record (as with the other values, the form edits only the base language).

## Capabilities

### New Capabilities
- `admin-taxonomy`: the section «Таксономія»: vocabularies, the tree of terms, forms, deletion with an explanation, the order by drag-and-drop.

### Modified Capabilities
- `admin-shell`: the item «Таксономія» in the side navigation.
- `admin-content-fields`: the type `term` in the form of a field, the choice of a vocabulary.
- `admin-content-nodes`: the input of the value `term` in the form of a record and its display in the list of records.

## Impact

- Code: `routes/web.php`; the controllers `VocabularyController`, `TermController` and the requests in `app/Http/Controllers/Admin`, `app/Http/Requests/Admin`; changes in `ContentFieldController`, `StoreContentFieldRequest`/`UpdateContentFieldRequest`, `ContentNodeController`, `PresentsFormFields`, `App\Files\Owners\TermOwner` (the link to the form of a term); React: `Pages/Admin/Taxonomy/*`, `Components/Admin/Taxonomy/*`, `NodeFieldInput`, `ContentFieldForm`, `navigation.ts`; `lang/uk.json`, `lang/en.json`; `config/content.php` (remove `admin_hidden_field_types`).
- Depends on `add-taxonomy-core` (the models, `TermTree`, `TermWriter`, `TermUsage`, the type `term`).
- Documentation (`docs/`): a new `docs/admin-taxonomy.md`; updates of `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/admin-shell.md` (navigation), `docs/taxonomy.md`, `docs/e2e-testing.md` (new rows in «Область → спек» and «Спільний код → спеки»), `docs/README.md`.
- Tests: PHP Feature for the controllers and the requests; a new e2e `e2e/admin-taxonomy.spec.js`; the dependent specs of the changed shared components (`admin-content-fields`, `admin-content-nodes`, `admin-shell`).
